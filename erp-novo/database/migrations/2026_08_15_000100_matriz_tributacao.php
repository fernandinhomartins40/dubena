<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MATRIZ DE TRIBUTAÇÃO — o destino que faltava (pendência consciente #1 da
 * AUDITORIA_MIGRACAO_DADOS_LEGADOS.md).
 *
 * O motor novo (FiscalService) usava CST/alíquota FIXOS ('00'/18%/CFOP 5102) porque
 * não havia onde guardar a regra tributária real do legado. As tabelas abaixo são o
 * porte fiel de `NFIMPOSTOS` + `NFIMPOSTOESTADOS` + `NFOPERACAOPRODUTOS`:
 *
 *  - `nf_impostos`: a regra por (operação fiscal × grupo fiscal). É a tributação
 *    DENTRO do estado. Cada linha carrega DOIS conjuntos completos de tributos —
 *    o de PJ (colunas `*`) e o de consumidor final/PF (colunas `pf_*`) — porque o
 *    legado escolhe um ou outro conforme `isConsumidorFinal` (ver ImpostoDB::init).
 *
 *  - `nf_imposto_estados`: o desdobramento por par origem_uf → destino_uf, usado
 *    quando a operação é INTERESTADUAL (idDest != 1). Mesma dualidade PJ/PF, mais
 *    a alíquota interna do destino (`pf_aliq_icms_dest`) que alimenta o DIFAL.
 *
 *  - `produto_operacao_fiscal`: quais produtos participam de cada operação fiscal
 *    (o `NFOPERACAOPRODUTOS`), usado para validar/filtrar a operação na emissão.
 *
 * Modelagem: os CST/CEST/classificações do legado são FKs para tabelas próprias
 * (`nficms`, `nfpis`, `nfcofins`, ...) que aqui já vivem consolidadas em
 * `malha_fiscal` (tipo+codigo). Para não acoplar a matriz a ids de catálogo que
 * mudam de instalação, gravamos o CÓDIGO do CST direto (ex. '00', '60') — é ele
 * que o XML e o CalculoImpostoService consomem. A rastreabilidade ao legado fica
 * em `legado_*_id`.
 *
 * Multi-tenant: `empresa_id` NOT NULL + policy de RLS igual às demais.
 */
return new class extends Migration
{
    private const COM_TENANT = ['nf_impostos', 'nf_imposto_estados'];

    public function up(): void
    {
        // ── Regra tributária por operação × grupo fiscal (dentro do estado) ──
        Schema::create('nf_impostos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('operacao_fiscal_id')
                ->constrained('operacoes_fiscais')->cascadeOnDelete();
            // Grupo fiscal do produto: vive em malha_fiscal (tipo='grupos-fiscais').
            $t->foreignId('grupo_fiscal_id')->nullable()
                ->constrained('malha_fiscal')->nullOnDelete();

            // ── ICMS (PJ) ──
            $t->string('cst_icms', 4)->nullable();
            $t->decimal('aliq_icms', 9, 4)->default(0);
            $t->decimal('perc_bc_icms', 9, 4)->default(100);
            $t->unsignedTinyInteger('origem_icms')->nullable();
            $t->unsignedTinyInteger('modalidade_bc_icms')->nullable();
            $t->decimal('aliq_icms_mono', 9, 4)->default(0);

            // ── ICMS-ST (PJ) ──
            $t->decimal('aliq_icms_st', 9, 4)->default(0);
            $t->decimal('perc_bc_icms_st', 9, 4)->default(100);
            $t->unsignedTinyInteger('modalidade_bc_icms_st')->nullable();
            $t->decimal('mva', 9, 4)->default(0);
            $t->decimal('mva_reduzido', 9, 4)->default(0);

            // ── Outros ICMS (PJ) ──
            $t->decimal('aliq_diferimento', 9, 4)->default(0);
            $t->decimal('taxa_fecop', 9, 4)->default(0);
            $t->string('mot_deson_icms', 4)->nullable();
            $t->string('cod_beneficio', 20)->nullable();

            // ── PIS / COFINS (PJ) ──
            $t->string('cst_pis', 4)->nullable();
            $t->decimal('aliq_pis', 9, 4)->default(0);
            $t->decimal('perc_bc_pis', 9, 4)->default(100);
            $t->decimal('aliq_pis_credito', 9, 4)->default(0);
            $t->string('cst_cofins', 4)->nullable();
            $t->decimal('aliq_cofins', 9, 4)->default(0);
            $t->decimal('perc_bc_cofins', 9, 4)->default(100);
            $t->decimal('aliq_cofins_credito', 9, 4)->default(0);

            // ── Conjunto PF / consumidor final ──
            $t->string('pf_cst_icms', 4)->nullable();
            $t->decimal('pf_aliq_icms', 9, 4)->default(0);
            $t->decimal('pf_perc_bc_icms', 9, 4)->default(100);
            $t->unsignedTinyInteger('pf_origem_icms')->nullable();
            $t->unsignedTinyInteger('pf_modalidade_bc_icms')->nullable();
            $t->decimal('pf_aliq_icms_mono', 9, 4)->default(0);
            $t->unsignedTinyInteger('pf_modalidade_bc_icms_st')->nullable();
            $t->decimal('pf_mva', 9, 4)->default(0);
            $t->decimal('pf_taxa_fecop', 9, 4)->default(0);
            $t->string('pf_mot_deson_icms', 4)->nullable();
            $t->string('pf_cod_beneficio', 20)->nullable();
            $t->string('pf_cst_pis', 4)->nullable();
            $t->decimal('pf_aliq_pis', 9, 4)->default(0);
            $t->decimal('pf_perc_bc_pis', 9, 4)->default(100);
            $t->decimal('pf_aliq_pis_credito', 9, 4)->default(0);
            $t->string('pf_cst_cofins', 4)->nullable();
            $t->decimal('pf_aliq_cofins', 9, 4)->default(0);
            $t->decimal('pf_perc_bc_cofins', 9, 4)->default(100);

            // ── Informações adicionais e PIS/COFINS (SPED) ──
            $t->text('informacoes_adicionais')->nullable();
            $t->text('pf_informacoes_adicionais')->nullable();
            $t->string('piscofins_tipo_credito', 10)->nullable();
            $t->string('piscofins_nat_receita', 10)->nullable();
            $t->string('piscofins_tipo_bc_credito', 10)->nullable();
            $t->string('piscofins_gera_credito', 10)->nullable();

            $t->unsignedBigInteger('legado_id')->nullable()->index();
            $t->timestamps();

            $t->unique(['empresa_id', 'operacao_fiscal_id', 'grupo_fiscal_id'], 'nf_imposto_unico');
            $t->index(['empresa_id', 'operacao_fiscal_id']);
        });

        // ── Desdobramento por UF (operação interestadual) ──
        Schema::create('nf_imposto_estados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('nf_imposto_id')->constrained('nf_impostos')->cascadeOnDelete();
            $t->char('origem_uf', 2);
            $t->char('destino_uf', 2);

            // ── PJ ──
            $t->string('cst_icms', 4)->nullable();
            $t->decimal('aliq_icms', 9, 4)->default(0);
            $t->decimal('perc_bc_icms', 9, 4)->default(100);
            $t->unsignedTinyInteger('origem_icms')->nullable();
            $t->unsignedTinyInteger('modalidade_bc_icms')->nullable();
            $t->decimal('aliq_icms_st', 9, 4)->default(0);
            $t->decimal('perc_bc_icms_st', 9, 4)->default(100);
            $t->unsignedTinyInteger('modalidade_bc_icms_st')->nullable();
            $t->decimal('mva', 9, 4)->default(0);
            $t->decimal('mva_reduzido', 9, 4)->default(0);
            $t->decimal('aliq_diferimento', 9, 4)->default(0);
            $t->decimal('taxa_fecop', 9, 4)->default(0);
            $t->string('mot_deson_icms', 4)->nullable();
            $t->string('cod_beneficio', 20)->nullable();

            // ── PF / consumidor final ──
            $t->string('pf_cst_icms', 4)->nullable();
            $t->decimal('pf_aliq_icms', 9, 4)->default(0);
            $t->decimal('pf_perc_bc_icms', 9, 4)->default(100);
            $t->unsignedTinyInteger('pf_origem_icms')->nullable();
            $t->unsignedTinyInteger('pf_modalidade_bc_icms')->nullable();
            $t->decimal('pf_aliq_icms_st', 9, 4)->default(0);
            $t->unsignedTinyInteger('pf_modalidade_bc_icms_st')->nullable();
            $t->decimal('pf_mva', 9, 4)->default(0);
            $t->decimal('pf_taxa_fecop', 9, 4)->default(0);
            $t->string('pf_mot_deson_icms', 4)->nullable();
            $t->string('pf_cod_beneficio', 20)->nullable();
            // Alíquota interna do estado de DESTINO — base do DIFAL.
            $t->decimal('pf_aliq_icms_dest', 9, 4)->default(0);

            $t->unsignedBigInteger('legado_id')->nullable()->index();
            $t->timestamps();

            $t->unique(['nf_imposto_id', 'origem_uf', 'destino_uf'], 'nf_imposto_uf_unico');
            $t->index(['empresa_id', 'origem_uf', 'destino_uf']);
        });

        // ── Produtos habilitados em cada operação fiscal ──
        Schema::create('produto_operacao_fiscal', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operacao_fiscal_id')
                ->constrained('operacoes_fiscais')->cascadeOnDelete();
            $t->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['operacao_fiscal_id', 'produto_id'], 'produto_operacao_unico');
        });

        $this->aplicarRls();
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_operacao_fiscal');
        Schema::dropIfExists('nf_imposto_estados');
        Schema::dropIfExists('nf_impostos');
    }

    private function aplicarRls(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COM_TENANT as $tabela) {
            DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$tabela}
                 USING (
                     nullif(current_setting('app.empresa_id', true), '') IS NULL
                     OR empresa_id = current_setting('app.empresa_id', true)::int
                 )
                 WITH CHECK (
                     nullif(current_setting('app.empresa_id', true), '') IS NULL
                     OR empresa_id = current_setting('app.empresa_id', true)::int
                 )"
            );
        }
    }
};
