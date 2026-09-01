<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use Illuminate\Support\Facades\DB;

/**
 * MATRIZ DE TRIBUTAÇÃO — `NFIMPOSTOS` + `NFIMPOSTOESTADOS` + `NFOPERACAOPRODUTOS`.
 *
 * Fecha a pendência consciente #1 da AUDITORIA_MIGRACAO_DADOS_LEGADOS.md: as
 * matrizes existiam íntegras no espelho, mas não tinham destino no schema novo — o
 * motor fiscal usava CST/alíquota fixos. Agora há destino (migration
 * `matriz_tributacao`) e a ResolucaoTributariaService lê daqui.
 *
 * Duas traduções de modelagem acontecem aqui:
 *
 *  1. CST por FK → CST por CÓDIGO. O legado aponta para catálogos próprios
 *     (`nficms`.id=25 → '00'); gravamos o código, que é o que o XML e o cálculo
 *     consomem, sem acoplar a matriz a ids de catálogo.
 *
 *  2. Operação fiscal DEDUPLICADA. O destino tem UNIQUE (grupo, descricao) e o
 *     CadastrosContabeisMigrator mantém a primeira ocorrência (66→57). Nove regras
 *     apontam para ids absorvidos nesse dedup (ex.: 656-659 → 48); em vez de
 *     descartá-las — o erro que a auditoria flagrou —, redirecionamos pela mesma
 *     chave (grupo, descrição) e, se ainda colidirem no UNIQUE do destino, a
 *     primeira vence e a repetição é contada em `pulados`.
 */
final class MatrizTributariaMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    /** Quantas regras foram redirecionadas por dedup da operação fiscal. */
    private int $redirecionadas = 0;

    public function nome(): string
    {
        return 'matriz-tributaria';
    }

    public function dependeDe(): array
    {
        // Precisa das operações fiscais (cadastros contábeis), dos grupos fiscais
        // (malha fiscal) e dos produtos.
        return ['cadastros-contabeis', 'fiscal-config', 'produtos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;
        $this->redirecionadas = 0;

        if (! $this->tabelaExiste($ctx, 'nfimpostos')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['tabela `nfimpostos` ausente no espelho — matriz de tributação NÃO migrada '
                    .'(a emissão de NF-e cairia em CST/alíquota padrão)']);
        }

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $avisos = [];

        $cst = $this->catalogosDeCst($ctx);
        $operacoes = $this->mapaOperacoes($ctx);
        $gruposFiscais = $this->mapaGruposFiscais($ctx);
        $empresas = $this->empresasValidas();

        // ── 1) Regras por operação × grupo fiscal ──
        $mapaImposto = [];   // legado_id => id novo
        $semOperacao = [];

        foreach ($ctx->legado()->table('nfimpostos')->orderBy('id')->get() as $r) {
            $lidos++;
            $linha = (array) $r;

            $operacao = $operacoes[(int) ($linha['nfoperacao_id'] ?? 0)] ?? null;
            $empresa = (int) ($linha['empresa_id'] ?? 0);
            if ($operacao === null || ! isset($empresas[$empresa])) {
                $pulados++;
                $semOperacao[] = (int) $linha['id'];

                continue;
            }

            if ($ctx->dryRun) {
                continue;
            }

            $dados = $this->regra($linha, $cst, $operacao, $empresa, $empresas[$empresa], $gruposFiscais);

            // UNIQUE (empresa, operacao, grupo_fiscal): o dedup da operação pode
            // fazer duas regras legadas caírem na mesma chave — a primeira vence.
            $existente = DB::table('nf_impostos')
                ->where('empresa_id', $dados['empresa_id'])
                ->where('operacao_fiscal_id', $dados['operacao_fiscal_id'])
                ->where(fn ($q) => $dados['grupo_fiscal_id'] === null
                    ? $q->whereNull('grupo_fiscal_id')
                    : $q->where('grupo_fiscal_id', $dados['grupo_fiscal_id']))
                ->value('id');

            if ($existente) {
                $mapaImposto[(int) $linha['id']] = (int) $existente;
                $pulados++;

                continue;
            }

            $novoId = (int) DB::table('nf_impostos')->insertGetId($dados);
            $mapaImposto[(int) $linha['id']] = $novoId;
            $gravados++;
        }

        // ── 2) Desdobramento por UF ──
        if ($this->tabelaExiste($ctx, 'nfimpostoestados')) {
            foreach ($ctx->legado()->table('nfimpostoestados')->orderBy('id')->get() as $r) {
                $lidos++;
                $linha = (array) $r;

                $impostoId = $mapaImposto[(int) ($linha['nfimposto_id'] ?? 0)] ?? null;
                $origem = strtoupper(trim((string) ($linha['origem_uf'] ?? '')));
                $destino = strtoupper(trim((string) ($linha['destino_uf'] ?? '')));

                if ($impostoId === null || $origem === '' || $destino === '') {
                    $pulados++;

                    continue;
                }
                if ($ctx->dryRun) {
                    continue;
                }

                $pai = DB::table('nf_impostos')->where('id', $impostoId)
                    ->first(['empresa_id', 'grupo_id']);

                $gravou = DB::table('nf_imposto_estados')->upsert(
                    [$this->regraUf($linha, $cst, $impostoId, (int) $pai->empresa_id, (int) $pai->grupo_id, $origem, $destino)],
                    ['nf_imposto_id', 'origem_uf', 'destino_uf'],
                    ['aliq_icms', 'pf_aliq_icms', 'updated_at'],
                );
                $gravados += (int) $gravou;
            }
        } else {
            $avisos[] = 'tabela `nfimpostoestados` ausente no espelho — operações '
                .'interestaduais ficarão sem regra (a emissão erra em vez de tributar errado)';
        }

        // ── 3) Produtos habilitados por operação fiscal ──
        if ($this->tabelaExiste($ctx, 'nfoperacaoprodutos')) {
            $produtos = [];
            foreach (DB::table('produtos')->pluck('id') as $id) {
                $produtos[(int) $id] = true;
            }

            $vinculos = [];
            foreach ($ctx->legado()->table('nfoperacaoprodutos')->orderBy('id')->get() as $r) {
                $lidos++;
                $operacao = $operacoes[(int) ($r->nfoperacao_id ?? 0)] ?? null;
                $produto = (int) ($r->produto_id ?? 0);

                if ($operacao === null || ! isset($produtos[$produto])) {
                    $pulados++;

                    continue;
                }

                $chave = $operacao['id'].':'.$produto;
                if (isset($vinculos[$chave])) {
                    $pulados++;

                    continue;
                }
                $vinculos[$chave] = [
                    'operacao_fiscal_id' => $operacao['id'],
                    'produto_id' => $produto,
                    'created_at' => $r->created_at ?? now(),
                    'updated_at' => now(),
                ];
            }

            if (! $ctx->dryRun && $vinculos !== []) {
                DB::table('produto_operacao_fiscal')->upsert(
                    array_values($vinculos),
                    ['operacao_fiscal_id', 'produto_id'],
                    ['updated_at'],
                );
                $gravados += count($vinculos);
            }
        }

        // ── 4) Grupo fiscal do produto (o elo que casa produto ↔ matriz) ──
        // Fica aqui, e não no ProdutosMigrator, porque a tradução do grupo fiscal
        // legado → `malha_fiscal` depende do mapa construído neste migrator (a
        // malha não preserva ids do legado).
        if (! $ctx->dryRun && $this->tabelaExiste($ctx, 'produtos') && $gruposFiscais !== []) {
            $semGrupo = 0;
            foreach (
                $ctx->legado()->table('produtos')
                    ->whereNotNull('nfgrupofiscal_id')
                    ->get(['id', 'nfgrupofiscal_id']) as $r
            ) {
                $destino = $gruposFiscais[(int) $r->nfgrupofiscal_id] ?? null;
                if ($destino === null) {
                    $semGrupo++;

                    continue;
                }
                $gravados += DB::table('produtos')->where('id', (int) $r->id)
                    ->update(['grupo_fiscal_id' => $destino]);
            }
            if ($semGrupo > 0) {
                $avisos[] = $semGrupo.' produto(s) com grupo fiscal que não existe na '
                    .'malha fiscal — caem na regra coringa da operação';
            }
        }

        if ($semOperacao !== []) {
            $avisos[] = count($semOperacao).' regra(s) de imposto sem operação fiscal '
                .'correspondente no destino: '.implode(', ', array_slice($semOperacao, 0, 10));
        }
        if ($this->redirecionadas > 0) {
            $avisos[] = $this->redirecionadas.' regra(s) redirecionadas para a operação '
                .'fiscal sobrevivente do dedup (grupo+descrição) — nenhuma foi descartada';
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $lidos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->tabelaExiste($ctx, 'nfimpostos')) {
            return [];
        }

        return [
            // O legado repete regras que o UNIQUE do destino funde; a contagem
            // exata é validada pelo teste, aqui garantimos a integridade das FKs.
            new IntegrityInvariant($ctx, 'nf_impostos', 'operacao_fiscal_id', 'operacoes_fiscais'),
            new IntegrityInvariant($ctx, 'nf_imposto_estados', 'nf_imposto_id', 'nf_impostos'),
            new IntegrityInvariant($ctx, 'produto_operacao_fiscal', 'produto_id', 'produtos'),
        ];
    }

    /**
     * Monta a linha de `nf_impostos` a partir da linha legada.
     *
     * @param  array<string,mixed>  $l
     * @param  array<string,array<int,string>>  $cst
     * @param  array{id:int,grupo_id:int}  $operacao
     * @param  array<int,int>  $gruposFiscais
     * @return array<string,mixed>
     */
    private function regra(array $l, array $cst, array $operacao, int $empresa, int $grupo, array $gruposFiscais): array
    {
        return [
            'empresa_id' => $empresa,
            'grupo_id' => $grupo,
            'operacao_fiscal_id' => $operacao['id'],
            'grupo_fiscal_id' => $gruposFiscais[(int) ($l['nfgrupofiscal_id'] ?? 0)] ?? null,

            // ICMS (PJ)
            'cst_icms' => $cst['icms'][(int) ($l['nficms_id'] ?? 0)] ?? null,
            'aliq_icms' => $this->dec($l['nficmsaliq'] ?? 0),
            'perc_bc_icms' => $this->dec($l['nficmsbase'] ?? 100, 100),
            'origem_icms' => $this->int($l['origemicms'] ?? null),
            'modalidade_bc_icms' => $this->int($l['modalidadebcicms'] ?? null),
            'aliq_icms_mono' => $this->dec($l['nficmsalimono'] ?? 0),

            // ICMS-ST (PJ)
            'aliq_icms_st' => $this->dec($l['aliqicmsst'] ?? 0),
            'perc_bc_icms_st' => $this->dec($l['nficmsbasest'] ?? 100, 100),
            'modalidade_bc_icms_st' => $this->int($l['modalidadebcicmsst'] ?? null),
            'mva' => $this->dec($l['mva'] ?? 0),
            'mva_reduzido' => $this->dec($l['mvareduzido'] ?? 0),

            // Outros ICMS (PJ)
            'aliq_diferimento' => $this->dec($l['nfaliqdiferimento'] ?? 0),
            'taxa_fecop' => $this->dec($l['taxafecop'] ?? 0),
            'mot_deson_icms' => $this->texto($l['nfmotdesonicms'] ?? null, 4),
            'cod_beneficio' => null, // beneficiario_id é FK a catálogo fora do espelho

            // PIS/COFINS (PJ)
            'cst_pis' => $cst['pis'][(int) ($l['nfpis_id'] ?? 0)] ?? null,
            'aliq_pis' => $this->dec($l['nfpisaliq'] ?? 0),
            'perc_bc_pis' => $this->dec($l['nfpisbase'] ?? 100, 100),
            'aliq_pis_credito' => $this->dec($l['nfpisaliqcred'] ?? 0),
            'cst_cofins' => $cst['cofins'][(int) ($l['nfcofins_id'] ?? 0)] ?? null,
            'aliq_cofins' => $this->dec($l['nfcofinsaliq'] ?? 0),
            'perc_bc_cofins' => $this->dec($l['nfcofinsbase'] ?? 100, 100),
            'aliq_cofins_credito' => $this->dec($l['nfcofinsaliqcred'] ?? 0),

            // PF / consumidor final
            'pf_cst_icms' => $cst['icms'][(int) ($l['pfnficms_id'] ?? 0)] ?? null,
            'pf_aliq_icms' => $this->dec($l['pfnficmsaliq'] ?? 0),
            'pf_perc_bc_icms' => $this->dec($l['pfnficmsbase'] ?? 100, 100),
            'pf_origem_icms' => $this->int($l['pforigemicms'] ?? null),
            'pf_modalidade_bc_icms' => $this->int($l['pfmodalidadebcicms'] ?? null),
            'pf_aliq_icms_mono' => $this->dec($l['pfnficmsalimono'] ?? 0),
            'pf_modalidade_bc_icms_st' => $this->int($l['pfmodalidadebcicmsst'] ?? null),
            'pf_mva' => $this->dec($l['pfmva'] ?? 0),
            'pf_taxa_fecop' => $this->dec($l['pftaxafecop'] ?? 0),
            'pf_mot_deson_icms' => $this->texto($l['pfnfmotdesonicms'] ?? null, 4),
            'pf_cod_beneficio' => null,
            'pf_cst_pis' => $cst['pis'][(int) ($l['pfnfpis_id'] ?? 0)] ?? null,
            'pf_aliq_pis' => $this->dec($l['pfnfpisaliq'] ?? 0),
            'pf_perc_bc_pis' => $this->dec($l['pfnfpisbase'] ?? 100, 100),
            'pf_aliq_pis_credito' => $this->dec($l['pfnfpisaliqcred'] ?? 0),
            'pf_cst_cofins' => $cst['cofins'][(int) ($l['pfnfcofins_id'] ?? 0)] ?? null,
            'pf_aliq_cofins' => $this->dec($l['pfnfcofinsaliq'] ?? 0),
            'pf_perc_bc_cofins' => $this->dec($l['pfnfcofinsbase'] ?? 100, 100),

            // Complementos
            'informacoes_adicionais' => $this->texto($l['informacoesadicional'] ?? null, 65000),
            'pf_informacoes_adicionais' => $this->texto($l['pfinformacoesadicional'] ?? null, 65000),
            'piscofins_tipo_credito' => $this->texto($l['piscofinstipocredito'] ?? null, 10),
            'piscofins_nat_receita' => $this->texto($l['piscofinsnatreceita'] ?? null, 10),
            'piscofins_tipo_bc_credito' => $this->texto($l['piscofinstipobccredito'] ?? null, 10),
            'piscofins_gera_credito' => $this->texto($l['piscofinsgeracredito'] ?? null, 10),

            'legado_id' => (int) $l['id'],
            'created_at' => $l['created_at'] ?? now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Monta a linha de `nf_imposto_estados`.
     *
     * @param  array<string,mixed>  $l
     * @param  array<string,array<int,string>>  $cst
     * @return array<string,mixed>
     */
    private function regraUf(array $l, array $cst, int $impostoId, int $empresa, int $grupo, string $origem, string $destino): array
    {
        return [
            'empresa_id' => $empresa,
            'grupo_id' => $grupo,
            'nf_imposto_id' => $impostoId,
            'origem_uf' => $origem,
            'destino_uf' => $destino,

            // PJ
            'cst_icms' => $cst['icms'][(int) ($l['nficms_id'] ?? 0)] ?? null,
            'aliq_icms' => $this->dec($l['nficmsaliq'] ?? 0),
            'perc_bc_icms' => $this->dec($l['nficmsbase'] ?? 100, 100),
            'origem_icms' => $this->int($l['nficmsorigem'] ?? null),
            'modalidade_bc_icms' => $this->int($l['nficmsmodalidadebc'] ?? null),
            'aliq_icms_st' => $this->dec($l['nficmsstaliq'] ?? 0),
            'perc_bc_icms_st' => $this->dec($l['nficmsbasest'] ?? 100, 100),
            'modalidade_bc_icms_st' => $this->int($l['nficmsstmodalidadebc'] ?? null),
            'mva' => $this->dec($l['mva'] ?? 0),
            'mva_reduzido' => $this->dec($l['mvareduzido'] ?? 0),
            'aliq_diferimento' => $this->dec($l['nfaliqdiferimento'] ?? 0),
            'taxa_fecop' => $this->dec($l['taxafecop'] ?? 0),
            'mot_deson_icms' => $this->texto($l['nfmotdesonicms'] ?? null, 4),
            'cod_beneficio' => null,

            // PF / consumidor final
            'pf_cst_icms' => $cst['icms'][(int) ($l['pfnficms_id'] ?? 0)] ?? null,
            'pf_aliq_icms' => $this->dec($l['pfnficmsaliq'] ?? 0),
            'pf_perc_bc_icms' => $this->dec($l['pfnficmsbase'] ?? 100, 100),
            'pf_origem_icms' => $this->int($l['pfnficmsorigem'] ?? null),
            'pf_modalidade_bc_icms' => $this->int($l['pfnficmsmodalidadebc'] ?? null),
            'pf_aliq_icms_st' => $this->dec($l['pfnficmsstaliq'] ?? 0),
            'pf_modalidade_bc_icms_st' => $this->int($l['pfnficmsstmodalidadebc'] ?? null),
            'pf_mva' => $this->dec($l['pfmva'] ?? 0),
            'pf_taxa_fecop' => $this->dec($l['pftaxafecop'] ?? 0),
            'pf_mot_deson_icms' => $this->texto($l['pfnfmotdesonicms'] ?? null, 4),
            'pf_cod_beneficio' => null,
            'pf_aliq_icms_dest' => $this->dec($l['pfaliqicmsdest'] ?? 0),

            'legado_id' => (int) $l['id'],
            'created_at' => $l['created_at'] ?? now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Catálogos de CST do legado: id → código ('00', '60', ...).
     *
     * @return array<string,array<int,string>>
     */
    private function catalogosDeCst(MigrationContext $ctx): array
    {
        $mapa = ['icms' => [], 'pis' => [], 'cofins' => []];

        foreach (['icms' => 'nficms', 'pis' => 'nfpis', 'cofins' => 'nfcofins'] as $chave => $tabela) {
            if (! $this->tabelaExiste($ctx, $tabela)) {
                continue;
            }
            foreach ($ctx->legado()->table($tabela)->get(['id', 'codigo']) as $r) {
                $codigo = trim((string) $r->codigo);
                if ($codigo !== '') {
                    $mapa[$chave][(int) $r->id] = mb_substr($codigo, 0, 4);
                }
            }
        }

        return $mapa;
    }

    /**
     * Operação fiscal do legado → operação no destino, atravessando o dedup por
     * (grupo, descrição) do CadastrosContabeisMigrator.
     *
     * @return array<int,array{id:int,grupo_id:int}>
     */
    private function mapaOperacoes(MigrationContext $ctx): array
    {
        $porChave = [];
        foreach (DB::table('operacoes_fiscais')->get(['id', 'grupo_id', 'descricao']) as $o) {
            $porChave[$o->grupo_id.'|'.mb_strtolower(trim((string) $o->descricao))] = [
                'id' => (int) $o->id,
                'grupo_id' => (int) $o->grupo_id,
            ];
        }

        $mapa = [];
        if (! $this->tabelaExiste($ctx, 'nfoperacaos')) {
            return $mapa;
        }

        foreach ($ctx->legado()->table('nfoperacaos')->get(['id', 'grupo_id', 'descricao']) as $r) {
            $chave = ((int) $r->grupo_id).'|'.mb_strtolower(trim((string) $r->descricao));
            if (! isset($porChave[$chave])) {
                continue;
            }
            $mapa[(int) $r->id] = $porChave[$chave];
            if ($porChave[$chave]['id'] !== (int) $r->id) {
                $this->redirecionadas++;
            }
        }

        return $mapa;
    }

    /**
     * Grupo fiscal do legado → linha em `malha_fiscal` (tipo='grupos-fiscais'),
     * casando pela descrição (o FiscalConfigMigrator não preserva ids).
     *
     * @return array<int,int>
     */
    private function mapaGruposFiscais(MigrationContext $ctx): array
    {
        if (! $this->tabelaExiste($ctx, 'nfgrupofiscals')) {
            return [];
        }

        $porDescricao = [];
        foreach (
            DB::table('malha_fiscal')->where('tipo', 'grupos-fiscais')
                ->get(['id', 'grupo_id', 'descricao']) as $m
        ) {
            $porDescricao[$m->grupo_id.'|'.mb_strtolower(trim((string) $m->descricao))] = (int) $m->id;
        }

        $mapa = [];
        foreach ($ctx->legado()->table('nfgrupofiscals')->get(['id', 'grupo_id', 'descricao']) as $r) {
            $chave = ((int) $r->grupo_id).'|'.mb_strtolower(trim((string) $r->descricao));
            if (isset($porDescricao[$chave])) {
                $mapa[(int) $r->id] = $porDescricao[$chave];
            }
        }

        return $mapa;
    }

    /** @return array<int,int> empresa_id => grupo_id */
    private function empresasValidas(): array
    {
        $mapa = [];
        foreach (DB::table('empresas')->get(['id', 'grupo_id']) as $e) {
            $mapa[(int) $e->id] = (int) $e->grupo_id;
        }

        return $mapa;
    }

    private function dec(mixed $v, float $padraoSeNulo = 0.0): float
    {
        if ($v === null || $v === '') {
            return $padraoSeNulo;
        }

        return (float) str_replace(',', '.', (string) $v);
    }

    private function int(mixed $v): ?int
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }

        return (int) $v;
    }

    private function texto(mixed $v, int $max): ?string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
