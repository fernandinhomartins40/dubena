<?php

namespace App\Domain\Identidade;

use App\Domain\Auditoria\RegistroAcao;
use App\Domain\Cliente\ClienteService;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteRevisao;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A PORTA ÚNICA de entrada de cliente no sistema.
 *
 * Antes desta classe havia cinco caminhos com cinco regras diferentes —
 * admin bloqueava por endereço, app e entregador bloqueavam por telefone, e
 * venda em campo e a ponte do legado não checavam nada. Onde havia regra, ela
 * travava a venda; onde não havia, sujava a base. Os dois sintomas ao mesmo
 * tempo, pela mesma causa: nenhum lugar concentrava a decisão.
 *
 * A decisão agora é uma só, por confiança:
 *
 *   escore >= 100  → é a mesma pessoa: devolve o cadastro existente
 *   escore 50..99  → CRIA o cadastro, conclui a venda, enfileira para revisão
 *   escore < 50    → cadastro novo, sem ruído
 *
 * A faixa do meio é o coração do desenho: a venda nunca trava. A trava antiga
 * ("Telefone informado já existe em outro cliente") abortava a venda em campo,
 * e o entregador contornava inventando outro telefone — sujando a base
 * justamente quando ela tentava se proteger.
 */
class IdentificarOuCriarCliente
{
    public function __construct(
        private IdentidadeCliente $identidade,
        private ClienteService $clientes,
        private RegistroAcao $auditoria,
    ) {}

    /**
     * Identifica um cliente existente ou cria um novo.
     *
     * @param  array<string, mixed>  $dados  nome + (telefone|endereço) no mínimo
     * @param  string  $origem  app | entregador | admin | legado | campo
     */
    public function executar(int $empresaId, int $grupoId, array $dados, string $origem = 'admin'): ResultadoCadastro
    {
        $this->garantirMinimo($dados);

        $candidatos = $this->identidade->candidatos($empresaId, $dados);
        $melhor = $candidatos->first();

        // ── Confiança alta: é a mesma pessoa ────────────────────────────────
        if ($melhor !== null && $melhor->consolidaAutomaticamente()) {
            $this->enriquecer($melhor->cliente, $dados, $origem);

            return new ResultadoCadastro(
                cliente: $melhor->cliente,
                criado: false,
                identificado: true,
                escore: $melhor->escore,
                motivos: $melhor->motivos,
            );
        }

        // ── Cria o cadastro. SEMPRE. ────────────────────────────────────────
        $cliente = $this->criar($empresaId, $grupoId, $dados, $origem);

        // ── Faixa da dúvida: enfileira o par, sem atrapalhar a venda ────────
        $paraRevisar = $candidatos->filter(fn (ResultadoIdentidade $r) => $r->mereceRevisao());
        foreach ($paraRevisar as $suspeito) {
            $this->enfileirar($cliente, $suspeito, $origem);
        }

        return new ResultadoCadastro(
            cliente: $cliente,
            criado: true,
            identificado: false,
            escore: $melhor?->escore ?? 0,
            motivos: $melhor?->motivos ?? [],
            emRevisao: $paraRevisar->isNotEmpty(),
        );
    }

    /**
     * Só consulta: quem PODE ser esta pessoa, sem criar nada.
     *
     * Serve à tela que pergunta "é este cliente?" antes de cadastrar.
     *
     * @param  array<string, mixed>  $dados
     * @return \Illuminate\Support\Collection<int, ResultadoIdentidade>
     */
    public function sugerir(int $empresaId, array $dados)
    {
        return $this->identidade->candidatos($empresaId, $dados);
    }

    /**
     * O mínimo para cadastrar: nome + (telefone OU endereço).
     *
     * CPF fora do mínimo DE PROPÓSITO. 90,5% da base não tem documento, e
     * exigi-lo é o que trava a venda quando o cliente se recusa a informar —
     * receio legítimo, dada a quantidade de golpes. O documento é pedido
     * depois, quando o negócio realmente exige (nota fiscal, convênio).
     *
     * @param  array<string, mixed>  $dados
     */
    private function garantirMinimo(array $dados): void
    {
        if (trim((string) ($dados['nome'] ?? '')) === '') {
            throw ValidationException::withMessages(['nome' => 'Informe o nome do cliente.']);
        }

        $temTelefone = NormalizadorTexto::telefone($dados['telefone'] ?? null) !== ''
            || collect((array) ($dados['telefones'] ?? []))
                ->contains(fn ($t) => NormalizadorTexto::telefone(is_array($t) ? ($t['telefone'] ?? null) : $t) !== '');

        $temEndereco = ! empty($dados['cidade_id']) && trim((string) ($dados['numero'] ?? '')) !== '';

        if (! $temTelefone && ! $temEndereco) {
            throw ValidationException::withMessages([
                'contato' => 'Informe ao menos um telefone ou o endereço (cidade e número) do cliente.',
            ]);
        }
    }

    /** @param array<string, mixed> $dados */
    private function criar(int $empresaId, int $grupoId, array $dados, string $origem): Cliente
    {
        $cliente = $this->clientes->criar(array_merge($dados, [
            'empresa_id' => $empresaId,
            'grupo_id' => $grupoId,
            'cliente' => $dados['cliente'] ?? true,
            'ativo' => true,
            // As portas mandam telefone de dois jeitos: `telefone` (string,
            // como o app e o entregador enviam) e `telefones` (lista, como o
            // formulário do admin). O ClienteService só entende a lista — sem
            // normalizar aqui, o telefone do campo era silenciosamente perdido
            // e o cliente nascia sem o traço que mais identifica.
            'telefones' => $this->telefonesNormalizados($dados),
        ]));

        $this->identidade->sincronizar($cliente, $origem);

        return $cliente;
    }

    /**
     * Unifica `telefone` (string) e `telefones` (lista) no formato do
     * ClienteService, sem repetir número.
     *
     * @param  array<string, mixed>  $dados
     * @return list<array{telefone: string, whatsapp: bool}>|null
     */
    private function telefonesNormalizados(array $dados): ?array
    {
        $brutos = [];

        foreach ((array) ($dados['telefones'] ?? []) as $t) {
            $brutos[] = is_array($t) ? ($t['telefone'] ?? null) : $t;
        }
        $brutos[] = $dados['telefone'] ?? null;

        $saida = [];
        $vistos = [];

        foreach ($brutos as $bruto) {
            $chave = NormalizadorTexto::telefone($bruto);
            if ($chave === '' || isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $saida[] = ['telefone' => NormalizadorTexto::digitos($bruto), 'whatsapp' => true];
        }

        return $saida === [] ? null : $saida;
    }

    /**
     * Completa o cadastro existente com o que veio novo, sem sobrescrever.
     *
     * Quem já tem CPF cadastrado não perde o CPF porque uma venda em campo veio
     * sem ele; mas quem não tinha telefone ganha o telefone informado agora. O
     * cadastro melhora a cada contato, em vez de virar um cadastro novo.
     *
     * @param  array<string, mixed>  $dados
     */
    private function enriquecer(Cliente $cliente, array $dados, string $origem): void
    {
        $novos = [];

        // Só campos VAZIOS no cadastro atual são preenchidos: o dado já
        // conferido vale mais que o digitado às pressas no campo.
        foreach (['cpf', 'cnpj', 'email', 'endereco', 'numero', 'complemento', 'cep', 'cidade_id', 'bairro_id'] as $campo) {
            $valor = $dados[$campo] ?? null;
            if ($valor !== null && $valor !== '' && blank($cliente->{$campo})) {
                $novos[$campo] = $valor;
            }
        }

        // Telefone novo é ACRESCENTADO (nunca substitui): a pessoa pode ter
        // dois números, e trocar o antigo pelo novo perderia contato válido.
        $telefonesNovos = $this->telefonesInexistentes($cliente, $dados);

        if ($novos === [] && $telefonesNovos === []) {
            return;
        }

        DB::transaction(function () use ($cliente, $novos, $telefonesNovos, $origem) {
            if ($novos !== []) {
                $cliente->forceFill($novos)->save();
            }

            foreach ($telefonesNovos as $telefone) {
                $cliente->telefones()->create(['telefone' => $telefone, 'whatsapp' => false]);
            }

            $this->identidade->sincronizar($cliente->refresh(), $origem);

            $this->auditoria->registrar($cliente, 'identificado', null, [
                'origem' => $origem,
                'campos_completados' => array_keys($novos),
                'telefones_adicionados' => count($telefonesNovos),
            ]);
        });
    }

    /**
     * Telefones do payload que o cliente ainda não tem.
     *
     * @param  array<string, mixed>  $dados
     * @return list<string>
     */
    private function telefonesInexistentes(Cliente $cliente, array $dados): array
    {
        $atuais = $cliente->telefones()->pluck('telefone')
            ->map(fn ($t) => NormalizadorTexto::telefone($t))
            ->filter()->all();

        $candidatos = [];
        foreach ((array) ($dados['telefones'] ?? []) as $t) {
            $candidatos[] = is_array($t) ? ($t['telefone'] ?? null) : $t;
        }
        $candidatos[] = $dados['telefone'] ?? null;

        $novos = [];
        foreach ($candidatos as $bruto) {
            $normalizado = NormalizadorTexto::telefone($bruto);
            if ($normalizado === '' || in_array($normalizado, $atuais, true)) {
                continue;
            }
            $atuais[] = $normalizado;         // evita duplicar dentro do payload
            $novos[] = NormalizadorTexto::digitos($bruto);
        }

        return $novos;
    }

    /** Registra o par suspeito na fila de revisão (idempotente). */
    private function enfileirar(Cliente $novo, ResultadoIdentidade $suspeito, string $origem): void
    {
        ClienteRevisao::query()->updateOrCreate(
            ['cliente_id' => $novo->id, 'candidato_id' => $suspeito->cliente->id],
            [
                'empresa_id' => $novo->empresa_id,
                'escore' => $suspeito->escore,
                'tracos' => $suspeito->motivos,
                'origem' => $origem,
                'situacao' => 'pendente',
            ],
        );
    }
}
