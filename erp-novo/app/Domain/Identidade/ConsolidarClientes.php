<?php

namespace App\Domain\Identidade;

use App\Domain\Auditoria\RegistroAcao;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteVinculo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Funde dois cadastros que são a mesma pessoa.
 *
 * NÃO apaga o perdedor. Remapeia todas as FKs para o vencedor, completa os
 * campos que faltavam, desativa o absorvido e registra o vínculo — assim um id
 * antigo (num relatório impresso, num link, num pedido do legado) ainda resolve
 * para o cadastro certo.
 *
 * A descoberta de FKs vem do `DedupClientesApp`, e com ela a lição que custou
 * caro em produção: usar `pg_constraint`, nunca `information_schema` — que só
 * mostra objetos que a role POSSUI, e a aplicação roda como `erp_app`, que não
 * é dona das tabelas. Um dry-run chegou a reportar "0 tabelas referenciam
 * clientes.id" com 40 mil linhas filhas apontando para elas.
 */
class ConsolidarClientes
{
    /** @var list<array{tabela: string, coluna: string}>|null */
    private ?array $referencias = null;

    public function __construct(private RegistroAcao $auditoria) {}

    /**
     * Funde `$absorvido` em `$principal`.
     *
     * @param  string  $decididoPor  automatico | humano
     * @param  list<string>  $motivos  quais traços justificaram
     */
    public function executar(
        Cliente $principal,
        Cliente $absorvido,
        int $escore,
        string $decididoPor = 'humano',
        array $motivos = [],
    ): Cliente {
        $this->validar($principal, $absorvido);

        DB::transaction(function () use ($principal, $absorvido, $escore, $decididoPor, $motivos) {
            $this->completarCampos($principal, $absorvido);
            $this->transferirTelefones($principal, $absorvido);
            $remapeadas = $this->remapearReferencias($principal, $absorvido);

            // O absorvido sai da lista, mas continua existindo e apontando
            // para o vencedor.
            $absorvido->forceFill([
                'ativo' => false,
                'desativado_em' => now(),
                'desativado_por' => Auth::id(),
                'motivo_desativacao' => 'Cadastro consolidado no cliente #'.$principal->id,
            ])->save();

            ClienteVinculo::query()->create([
                'empresa_id' => $principal->empresa_id,
                'cliente_id' => $absorvido->id,
                'principal_id' => $principal->id,
                'escore' => $escore,
                'tracos' => $motivos,
                'decidido_por' => $decididoPor,
                'user_id' => Auth::id(),
            ]);

            // Os traços do absorvido são DESCARTADOS e o vencedor é reindexado
            // do zero (logo abaixo, fora da transação).
            //
            // Remapear com UPDATE colidia no unique(cliente_id,tipo,valor): os
            // dois cadastros compartilham justamente os traços que provaram
            // serem a mesma pessoa — o telefone igual viraria duas linhas
            // idênticas para o vencedor. Recalcular é mais simples e correto:
            // o vencedor já absorveu telefones e campos acima, então os traços
            // dele passam a cobrir os do absorvido.
            DB::table('cliente_identidades')->where('cliente_id', $absorvido->id)->delete();

            // As revisões que envolviam o absorvido perdem o sentido.
            DB::table('cliente_revisoes')
                ->where(fn ($q) => $q->where('cliente_id', $absorvido->id)->orWhere('candidato_id', $absorvido->id))
                ->where('situacao', 'pendente')
                ->update([
                    'situacao' => 'consolidado',
                    'decidido_em' => now(),
                    'decidido_por_user_id' => Auth::id(),
                ]);

            $this->auditoria->registrar($principal, 'consolidou', null, [
                'absorveu_cliente_id' => $absorvido->id,
                'absorveu_nome' => $absorvido->nome,
                'escore' => $escore,
                'decidido_por' => $decididoPor,
                'referencias_remapeadas' => $remapeadas,
            ]);
        });

        // Recalcula os traços com o cadastro já enriquecido.
        app(IdentidadeCliente::class)->sincronizar($principal->refresh(), 'consolidacao');

        return $principal->refresh();
    }

    private function validar(Cliente $principal, Cliente $absorvido): void
    {
        if ($principal->id === $absorvido->id) {
            throw ValidationException::withMessages([
                'cliente' => 'Não é possível consolidar um cadastro nele mesmo.',
            ]);
        }

        if ($principal->empresa_id !== $absorvido->empresa_id) {
            throw ValidationException::withMessages([
                'cliente' => 'Os cadastros são de empresas diferentes.',
            ]);
        }

        // Consolidação encadeada esconderia o cadastro real: se o absorvido já
        // foi absorvido antes, a fusão certa é com o vencedor dele.
        if (ClienteVinculo::query()->where('cliente_id', $absorvido->id)->exists()) {
            throw ValidationException::withMessages([
                'cliente' => 'Este cadastro já foi consolidado em outro.',
            ]);
        }
    }

    /**
     * Preenche no vencedor os campos que só o absorvido tinha.
     *
     * Nunca sobrescreve: o dado do vencedor é o de referência. É assim que a
     * consolidação AGREGA informação em vez de perder — o cadastro do app que
     * tinha CPF completa o do entregador que tinha o endereço certo.
     */
    private function completarCampos(Cliente $principal, Cliente $absorvido): void
    {
        $campos = [
            'cpf', 'cnpj', 'rg', 'inscricao_estadual', 'email', 'datanascimento',
            'endereco', 'numero', 'complemento', 'ponto_referencia', 'cep',
            'cidade_id', 'bairro_id', 'rua_id', 'uf', 'latitude', 'longitude',
            'observacoes',
        ];

        $novos = [];
        foreach ($campos as $campo) {
            if (blank($principal->{$campo}) && filled($absorvido->{$campo})) {
                $novos[$campo] = $absorvido->{$campo};
            }
        }

        if ($novos !== []) {
            $principal->forceFill($novos)->save();
        }
    }

    /** Telefones do absorvido que o vencedor ainda não tem. */
    private function transferirTelefones(Cliente $principal, Cliente $absorvido): void
    {
        $existentes = $principal->telefones()->pluck('telefone')
            ->map(fn ($t) => NormalizadorTexto::telefone($t))->filter()->all();

        foreach ($absorvido->telefones()->get() as $telefone) {
            $normalizado = NormalizadorTexto::telefone($telefone->telefone);
            if ($normalizado === '' || in_array($normalizado, $existentes, true)) {
                continue;
            }
            $existentes[] = $normalizado;
            $principal->telefones()->create([
                'telefone' => $telefone->telefone,
                'whatsapp' => $telefone->whatsapp,
                'telefonetipo_id' => $telefone->telefonetipo_id,
            ]);
        }
    }

    /**
     * Aponta para o vencedor tudo que referenciava o absorvido.
     *
     * @return array<string, int> tabela.coluna => linhas afetadas
     */
    private function remapearReferencias(Cliente $principal, Cliente $absorvido): array
    {
        $afetadas = [];

        foreach ($this->referencias() as $ref) {
            // As tabelas da própria identidade são tratadas à parte (acima):
            // remapeá-las aqui violaria o unique(cliente_id) do vínculo.
            if (in_array($ref['tabela'], ['cliente_vinculos', 'cliente_revisoes', 'cliente_identidades'], true)) {
                continue;
            }

            $n = DB::table($ref['tabela'])
                ->where($ref['coluna'], $absorvido->id)
                ->update([$ref['coluna'] => $principal->id]);

            if ($n > 0) {
                $afetadas[$ref['tabela'].'.'.$ref['coluna']] = $n;
            }
        }

        return $afetadas;
    }

    /**
     * Tabelas e colunas que referenciam `clientes.id`, do catálogo do Postgres.
     *
     * Lê de `pg_constraint`, NÃO de `information_schema`: este último só expõe
     * constraints de objetos que a role POSSUI, e o runtime é `erp_app`, que
     * não é dona das tabelas. Em produção isso já fez um dry-run reportar "0
     * tabelas referenciam clientes.id" havendo 40 mil linhas filhas.
     *
     * @return list<array{tabela: string, coluna: string}>
     */
    private function referencias(): array
    {
        if ($this->referencias !== null) {
            return $this->referencias;
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            // sqlite (suíte): sem catálogo equivalente, usa a lista conhecida
            // das tabelas que a suíte exercita.
            return $this->referencias = [
                ['tabela' => 'pedidos', 'coluna' => 'cliente_id'],
                ['tabela' => 'financeiros', 'coluna' => 'cliente_id'],
                ['tabela' => 'clientetelefones', 'coluna' => 'cliente_id'],
            ];
        }

        $linhas = DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS tabela,
                   a.attname                  AS coluna
              FROM pg_constraint c
              JOIN pg_attribute a
                ON a.attrelid = c.conrelid
               AND a.attnum   = ANY (c.conkey)
             WHERE c.contype   = 'f'
               AND c.confrelid = 'public.clientes'::regclass
             ORDER BY 1, 2
        SQL);

        $refs = array_map(fn ($l) => [
            'tabela' => str_replace('public.', '', $l->tabela),
            'coluna' => $l->coluna,
        ], $linhas);

        // Lista vazia em Postgres significa catálogo ilegível, não ausência de
        // FK — seguir apagaria/remaparia às cegas. Aborta.
        if ($refs === []) {
            throw new \RuntimeException(
                'Nenhuma FK para clientes encontrada no catálogo: consolidação abortada por segurança.',
            );
        }

        return $this->referencias = $refs;
    }
}
