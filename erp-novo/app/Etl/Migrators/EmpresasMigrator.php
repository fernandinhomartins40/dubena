<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\CountInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\DB;

/**
 * N1 — migra grupos e empresas (a entidade-tenant) do legado.
 *
 * Grupos primeiro (FK de empresas). Latitude/longitude viram decimal nativo;
 * flags matriz/ativo viram boolean. Config detalhada e certificado A1 ficam para
 * o EmpresaConfigMigrator/fase fiscal. Sem dump: 0 lidos/gravados.
 */
final class EmpresasMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'empresas';
    }

    public function dependeDe(): array
    {
        return [];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;

        $grupos = $this->lerGrupos($ctx);
        $empresas = $this->lerEmpresas($ctx);

        $gravados = 0;
        if (! $ctx->dryRun) {
            // ids preservados: TODO o resto do dump (clientes, pedidos, ...)
            // referencia a empresa pelo id do legado — que não é sequencial
            // (2, 114..117, 134, 135). Deixar o auto-increment renumerar
            // quebraria o vínculo de tenant de toda a carga.
            $gravados += $this->gravarPreservandoId('grupos', $grupos);
            $gravados += $this->gravarPreservandoId('empresas', $empresas);
            $this->configFiscalECadastral($ctx);
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: count($grupos) + count($empresas),
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: 0,
            avisos: $ctx->dryRun ? [] : [
                'config fiscal/SPED/contador gravada em `empresa_configs.dados` '
                .'(o formulário de empresa já enviava estes campos; o backend os descartava)',
            ],
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->legadoDisponivel($ctx)) {
            return []; // sem dump não há o que comparar (ambiente dev/CI)
        }

        return [
            new CountInvariant($ctx, 'empresasgrupos', 'grupos'),

            // Acréscimo legítimo de SEGUNDA ORIGEM (T2.4/T2.5): o destino tem
            // 3 empresas a mais que o Oracle — CENTRAL GÁS, DUBENA PARTICULAR e
            // QTI — que vêm do dump MySQL do `monitora`, não do dump Oracle
            // contra o qual esta invariante compara. Verificado: as três estão
            // sem clientes e sem pedidos (são cadastros do módulo de GPS).
            //
            //   SELECT id, razao_social FROM public.empresas
            //    WHERE id::text NOT IN (SELECT id::text FROM legado.empresas);
            //
            // Closure contando a origem real do acréscimo, em vez do literal 3:
            // se o dump do monitora mudar, a invariante acompanha.
            new CountInvariant(
                $ctx, 'empresas', 'empresas',
                acrescimosEsperados: fn () => $this->empresasSoDoMonitora($ctx),
            ),
        ];
    }

    /**
     * Empresas que existem no destino mas NÃO no dump Oracle — as que vieram do
     * dump MySQL do `monitora` (segunda origem deste pipeline).
     *
     * Contado sobre os dados a cada execução (T2.5): número fixo aqui
     * esconderia uma divergência futura em vez de detectá-la.
     */
    private function empresasSoDoMonitora(MigrationContext $ctx): int
    {
        try {
            $doOracle = $ctx->legado()->table('empresas')->pluck('id')
                ->map(fn ($v) => (string) $v)->all();
        } catch (\Throwable) {
            return 0;
        }

        if ($doOracle === []) {
            return 0;
        }

        return (int) $this->destino()->table('empresas')
            ->whereNotIn(DB::raw('id::text'), $doOracle)
            ->count();
    }

    /** @return list<array<string, mixed>> */
    private function lerGrupos(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('empresasgrupos')->get(['id', 'descricao', 'ativo']);
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'descricao' => trim((string) $r->descricao),
            'ativo' => (bool) $r->ativo,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function lerEmpresas(MigrationContext $ctx): array
    {
        try {
            $rows = $ctx->legado()->table('empresas')->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'grupo_id' => (int) ($r->grupo_id ?? $r->empresasgrupo_id ?? 0),
            'razao_social' => trim((string) ($r->razaosocial ?? $r->razao_social ?? '')),
            'nome_fantasia' => $r->nomefantasia ?? $r->nome_fantasia ?? null,
            'nome_informal' => $r->nomeinformal ?? $r->nome_informal ?? null,
            // O legado guarda com máscara ("04.190.715/0001-05", 18 chars);
            // o schema novo é varchar(14) só com dígitos.
            'cnpj' => $this->soDigitos($r->cnpj ?? null, 14),
            'inscricao_estadual' => $r->inscricaoestadual ?? null,
            'inscricao_municipal' => $r->inscricaomunicipal ?? null,
            'cep' => $this->soDigitos($r->cep ?? null, 8),
            'uf' => $r->uf ?? null,
            // O endereço por FK NÃO é gravado aqui: este migrator roda em 2º e
            // `cidades`/`bairros`/`ruas`/`regioes` só existem depois do
            // `geografico` (7º), que depende deste — gravar agora viola a FK.
            // É o `GeograficoMigrator::enderecoDasEmpresas()` que preenche, e de
            // lá deriva o texto que a DANFE imprime.
            //
            // O legado NÃO tem cidade/bairro em texto, só as FKs: ler
            // `$r->cidade` devolvia null, e foi por isso que as 7 empresas
            // migraram sem endereço nenhum.
            'numero' => $r->numero ?? null,
            'complemento' => $r->complemento ?? null,
            'telefone1' => $r->telefone1 ?? $r->telefone ?? null,
            'telefone2' => $r->telefone2 ?? null,
            'latitude' => isset($r->latitude) ? (float) $r->latitude : null,
            'longitude' => isset($r->longitude) ? (float) $r->longitude : null,
            'matriz' => (bool) ($r->matriz ?? false),
            'ativo' => (bool) ($r->ativo ?? true),
        ])->all();
    }

    /**
     * Remove máscara de documento/CEP e limita ao tamanho da coluna nova.
     * Devolve null quando não sobra dígito (campo vazio no legado).
     */
    private function soDigitos(mixed $v, int $max): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($v ?? ''));

        return $d === '' ? null : substr($d, 0, $max);
    }

    /**
     * Config fiscal, SPED, contador e dados cadastrais → `empresa_configs.dados`.
     *
     * O formulário de empresa já enviava estes campos (`nfeserie`, `nfecrt`,
     * `spedperfil`, `contnome`, `cnae`, `registro_anp`…) e o backend os
     * descartava por não existirem no `$fillable`. Todos existem no legado e
     * estão preenchidos: CRT e tipo de ambiente em 7/7 empresas, CNAE em 6/7,
     * dados do contador em 1/7.
     *
     * Ficam em `dados` (JSON) e não em colunas próprias porque é assim que a
     * config da empresa é modelada aqui — colunas são promovidas quando uma fase
     * precisa consultá-las por índice. A numeração NÃO entra neste JSON: ela é
     * estado transacional e vive em `sequencias`, semeada pelo FiscalMigrator.
     */
    private function configFiscalECadastral(MigrationContext $ctx): void
    {
        // Sem legado (dev/CI) não há config a herdar. O guard tem de estar aqui
        // e não só no chamador: `lerEmpresas()` devolve vazio silenciosamente
        // nesse caso, então chegar até aqui com a conexão morta é o normal.
        if (! $this->legadoDisponivel($ctx)) {
            return;
        }

        try {
            $doLegado = $ctx->legado()->table('empresas')->get();
        } catch (\Throwable) {
            return; // dump sem a tabela: nada a herdar
        }

        $idsEmpresa = $this->destino()->table('empresas')->pluck('id')->flip();

        foreach ($doLegado as $r) {
            $id = (int) $r->id;
            if (! isset($idsEmpresa[$id])) {
                continue;
            }

            $fiscal = array_filter([
                'nfe_modelo' => $this->inteiroOuNull($r->nfemodelo ?? null),
                'nfe_serie' => $this->inteiroOuNull($r->nfeserie ?? null),
                'nfe_crt' => $this->inteiroOuNull($r->nfecrt ?? null),
                'nfe_tipo_ambiente' => $this->inteiroOuNull($r->nfetipoambiente ?? null),
                'nfe_tipo_emissao' => $this->inteiroOuNull($r->nfetipoemissao ?? null),
                'nfe_emite' => $this->boolOuNull($r->nfeemite ?? null),
                'nfce_modelo' => $this->inteiroOuNull($r->nfcemodelo ?? null),
                'nfce_serie' => $this->inteiroOuNull($r->nfceserie ?? null),
                'nfce_crt' => $this->inteiroOuNull($r->nfcecrt ?? null),
                'nfce_tipo_ambiente' => $this->inteiroOuNull($r->nfcetipoambiente ?? null),
                'nfce_emite' => $this->boolOuNull($r->nfceemite ?? null),
            ], fn ($v) => $v !== null);

            $sped = array_filter([
                'emite' => $this->boolOuNull($r->spedemite ?? null),
                'perfil' => $this->texto($r->spedperfil ?? null),
                'atividade' => $this->inteiroOuNull($r->spedatividade ?? null),
                'incidencia_tributaria' => $this->inteiroOuNull($r->spedincidenciatributaria ?? null),
                'apropriacao_credito' => $this->inteiroOuNull($r->spedapropriacaocredito ?? null),
                'tipo_contribuicao' => $this->inteiroOuNull($r->spedtipocontribuicao ?? null),
                'regime_cumulativo' => $this->inteiroOuNull($r->spedregimecumulativo ?? null),
            ], fn ($v) => $v !== null);

            $contador = array_filter([
                'nome' => $this->texto($r->contnome ?? null),
                'cpf' => $this->soDigitos($r->contcpf ?? null, 11),
                'cnpj' => $this->soDigitos($r->contcnpj ?? null, 14),
                'crc' => $this->texto($r->contcrc ?? null),
                'telefone' => $this->texto($r->conttelefone ?? null),
                'email' => $this->texto($r->contemail ?? null),
            ], fn ($v) => $v !== null);

            $cadastro = array_filter([
                'cnae' => $this->texto($r->cnae ?? null),
                'email' => $this->texto($r->email ?? null),
                'registro_anp' => $this->texto($r->registro_anp ?? null),
                'distribuidora' => $this->texto($r->distribuidora ?? null),
                'suframa' => $this->texto($r->suframa ?? null),
                'inscricao_estadual_st' => $this->texto($r->inscricao_estadual_st ?? null),
                'capacidade_armazenamento' => $this->inteiroOuNull($r->capacidadearmazenamento ?? null),
            ], fn ($v) => $v !== null);

            $blocos = array_filter([
                'fiscal' => $fiscal,
                'sped' => $sped,
                'contador' => $contador,
                'cadastro' => $cadastro,
            ], fn ($b) => $b !== []);

            if ($blocos === []) {
                continue;
            }

            // `dados` é compartilhado (integrações, etc.): mescla em vez de
            // sobrescrever, senão a carga apagaria o que outro migrator gravou.
            $atual = json_decode(
                (string) $this->destino()->table('empresa_configs')->where('empresa_id', $id)->value('dados'),
                true,
            ) ?: [];

            $this->destino()->table('empresa_configs')->updateOrInsert(
                ['empresa_id' => $id],
                [
                    'dados' => json_encode(array_merge($atual, $blocos), JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function inteiroOuNull(mixed $v): ?int
    {
        return ($v === null || $v === '') ? null : (int) $v;
    }

    /** O legado guarda flag como texto '0'/'1'. */
    private function boolOuNull(mixed $v): ?bool
    {
        return ($v === null || $v === '') ? null : ! in_array((string) $v, ['0', 'N', 'n'], true);
    }

    private function texto(mixed $v): ?string
    {
        $t = trim((string) ($v ?? ''));

        return $t === '' ? null : $t;
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
