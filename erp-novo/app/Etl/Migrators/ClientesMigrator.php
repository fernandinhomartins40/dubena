<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use App\Etl\Support\RegistraFalhaDeLeitura;

/**
 * N2 — migra clientes + telefones do legado.
 *
 * Conversões-chave (do plano): flags '0'/'1' (Oracle) → boolean; lat/long string →
 * decimal; arrays posicionais de telefone → linhas normalizadas (clientetelefones).
 * Invariantes: contagem origem×destino + integridade (zero órfão/obrigatório nulo).
 */
final class ClientesMigrator implements Migrator
{
    use PreservaIdsDoLegado;
    use RegistraFalhaDeLeitura;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'clientes';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'geografico', 'cadastros-apoio'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;
        $this->limparAvisosDeLeitura();

        $clientes = $this->lerClientes($ctx);
        $telefones = $this->lerTelefones($ctx);

        // FKs geográficas: o dump referencia cidades/bairros/ruas que podem não
        // ter sobrevivido à carga do geográfico (descartados por falta de
        // cidade). Como são nullable aqui, a referência inválida vira null em
        // vez de derrubar a carga.
        $clientes = $this->anularFksInvalidas($clientes, [
            'cidade_id' => 'cidades',
            'bairro_id' => 'bairros',
            'rua_id' => 'ruas',
            // Cadastros de apoio podem não ter vindo no dump (o legado guarda
            // esses domínios em tabelas que nem sempre acompanham o export).
            'tipopessoa_id' => 'tipopessoas',
            'segmento_id' => 'segmentos',
        ]);

        // Idem para o cliente-convênio (auto-relação): o titular pode não estar
        // na fatia migrada.
        $idsClientes = array_flip(array_map(fn ($c) => (int) $c['id'], $clientes));
        $convenioInvalido = 0;
        foreach ($clientes as &$c) {
            $ref = (int) ($c['convenio_id'] ?? 0);
            if ($ref !== 0 && ! isset($idsClientes[$ref])) {
                $c['convenio_id'] = null;
                $convenioInvalido++;
            }
        }
        unset($c);

        // Telefone órfão não tem onde se prender (cliente_id é NOT NULL).
        $antes = count($telefones);
        $telefones = array_values(array_filter(
            $telefones,
            fn ($t) => isset($idsClientes[(int) $t['cliente_id']])
        ));
        $telefonesOrfaos = $antes - count($telefones);

        // `empresa_id` é NOT NULL nas filhas (isolamento multi-tenant), e o
        // legado não o traz no telefone: herda-se do cliente dono.
        $empresaDoCliente = [];
        foreach ($clientes as $c) {
            $empresaDoCliente[(int) $c['id']] = (int) $c['empresa_id'];
        }
        foreach ($telefones as &$t) {
            $t['empresa_id'] = $empresaDoCliente[(int) $t['cliente_id']] ?? null;
        }
        unset($t);

        $telefones = $this->anularFksInvalidas($telefones, [
            'telefonetipo_id' => 'telefonetipos',
        ]);

        $gravados = 0;
        if (! $ctx->dryRun) {
            // `convenio_id` é auto-referência: o titular pode estar num lote
            // posterior, então a FK falharia no meio da carga. Grava-se sem ela
            // e o vínculo é aplicado depois, com todas as linhas já presentes.
            $vinculos = [];
            foreach ($clientes as &$c) {
                if (! empty($c['convenio_id'])) {
                    $vinculos[(int) $c['id']] = (int) $c['convenio_id'];
                    $c['convenio_id'] = null;
                }
            }
            unset($c);

            // ids preservados: pedidos e telefones referenciam o cliente por id.
            $gravados += $this->gravarPreservandoId('clientes', $clientes);
            $gravados += $this->gravarPreservandoId('clientetelefones', $telefones);

            foreach (array_chunk($vinculos, 500, true) as $lote) {
                foreach ($lote as $id => $titular) {
                    $this->destino()->table('clientes')->where('id', $id)
                        ->update(['convenio_id' => $titular]);
                }
            }
        }

        $avisos = [];
        if ($convenioInvalido) {
            $avisos[] = "{$convenioInvalido} cliente(s) apontavam para um titular "
                .'de convênio fora do dump — convenio_id nulo';
        }
        if ($telefonesOrfaos) {
            $avisos[] = "{$telefonesOrfaos} telefone(s) sem cliente — descartados";
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($clientes) + count($telefones) + $telefonesOrfaos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $telefonesOrfaos,
            avisos: array_merge($avisos, $this->avisosDeLeitura()),
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        return [
            // Acréscimo legítimo de SEGUNDA ORIGEM (T2.4): o app "Gás em Casa"
            // (MySQL `sgcm_api`) tem cadastros que nunca existiram no ERP —
            // clientes que se registraram pelo app e ainda não compraram pelo
            // balcão. O `AppGasEmCasaMigrator` os cria, e são reconhecíveis pela
            // coluna-ponte `api_id` (T2.1).
            //
            // Contar `api_id IS NOT NULL` no destino é o que torna esta
            // invariante honesta: antes, um migrator que cria linhas de outra
            // origem era estruturalmente incapaz de passar, e a falha por
            // desenho se misturava às falhas reais no mesmo placar vermelho.
            new CountInvariant(
                $ctx, 'clientes', 'clientes',
                acrescimosEsperados: fn () => (int) $this->destino()->table('clientes')
                    ->whereNotNull('api_id')->count(),
            ),

            // Mesma lógica: os telefones desses clientes. Só contam os que
            // pertencem a cliente vindo do app.
            new CountInvariant(
                $ctx, 'clientetelefones', 'clientetelefones',
                acrescimosEsperados: fn () => (int) $this->destino()->table('clientetelefones')
                    ->join('clientes', 'clientes.id', '=', 'clientetelefones.cliente_id')
                    ->whereNotNull('clientes.api_id')
                    ->count(),
            ),
            // Integridade de FK no banco NOVO: zero cliente com empresa inexistente;
            // zero telefone órfão (sem cliente).
            new IntegrityInvariant($ctx, 'clientes', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'clientetelefones', 'cliente_id', 'clientes'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lerClientes(MigrationContext $ctx): array
    {
        $rows = $this->lerOuAvisar(
            'clientes (legado)',
            fn () => $ctx->legado()->table('clientes')->get(),
            collect(),
        );

        if ($rows->isEmpty()) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'empresa_id' => (int) $r->empresa_id,
            'grupo_id' => (int) $r->grupo_id,
            'nome' => trim((string) $r->nome),
            'fantasia' => $r->fantasia ?? null,
            'tipopessoa_id' => $r->tipopessoa_id ?? null,
            'segmento_id' => $r->segmento_id ?? null,
            'sexo' => $r->sexo ?? null,
            'datanascimento' => $r->datanascimento ?? null,
            'observacoes' => $r->observacoes ?? null,
            // O legado guarda documento com máscara; o schema novo quer dígitos.
            'cpf' => $this->soDigitos($r->cpf ?? null, 20),
            'rg' => $r->rg ?? null,
            'cnpj' => $this->soDigitos($r->cnpj ?? null, 20),
            'inscricao_estadual' => $r->inscricao_estadual ?? null,
            'indicador_ie' => isset($r->indicador_ie) ? (int) $r->indicador_ie : null,
            'cliente' => (bool) ($r->cliente ?? true),
            'fornecedor' => (bool) ($r->fornecedor ?? false),
            'transportador' => (bool) ($r->transportador ?? false),
            'simples' => (bool) ($r->simples ?? false),
            'nfemite' => (bool) ($r->nfemite ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
            'endereco' => $r->endereco ?? null,
            'numero' => $r->numero ?? null,
            'complemento' => $r->complemento ?? null,
            'ponto_referencia' => $r->ponto_referencia ?? null,
            'cep' => $this->soDigitos($r->cep ?? null, 10),
            'uf' => $r->uf ?? null,
            'cidade_id' => $r->cidade_id ?? null,
            'bairro_id' => $r->bairro_id ?? null,
            'rua_id' => $r->rua_id ?? null,
            'ponto_referencia' => $r->ponto_referencia ?? null,
            'suframa' => $r->suframa ?? null,
            'gasdopovo' => (bool) ($r->gasdopovo ?? false),
            'convenio_id' => $r->convenio_id ?? null,
            'location_type' => $r->locationtype ?? null,
            'data_ultima_compra' => $r->dataultimacompra ?? null,
            'email' => $r->email ?? null,
            'latitude' => isset($r->latitude) ? (float) $r->latitude : null,
            'longitude' => isset($r->longitude) ? (float) $r->longitude : null,
            'convenio' => (bool) ($r->convenio ?? false),
            'convenio_ativo' => (bool) ($r->convenioativo ?? false),
            'convenio_limite' => isset($r->conveniolimite) ? (float) $r->conveniolimite : null,
            'credito_limite' => isset($r->creditolimite) ? (float) $r->creditolimite : null,
            'credito_saldo' => isset($r->creditosaldo) ? (float) $r->creditosaldo : null,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerTelefones(MigrationContext $ctx): array
    {
        $rows = $this->lerOuAvisar(
            'clientetelefones (legado)',
            fn () => $ctx->legado()->table('clientetelefones')->get(),
            collect(),
        );

        if ($rows->isEmpty()) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'cliente_id' => (int) $r->cliente_id,
            'telefonetipo_id' => $r->telefonetipo_id ?? null,
            'telefone' => trim((string) $r->telefone),
            'whatsapp' => (bool) ($r->whatsapp ?? false),
        ])->all();
    }

    /** Remove máscara de documento/CEP e limita ao tamanho da coluna nova. */
    private function soDigitos(mixed $v, int $max): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($v ?? ''));

        return $d === '' ? null : substr($d, 0, $max);
    }

    private function legadoDisponivel(MigrationContext $ctx): bool
    {
        try {
            $ctx->legado()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
