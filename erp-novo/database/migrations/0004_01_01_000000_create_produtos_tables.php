<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N3 — Produto (classificação fiscal/GLP) + origens de combustível.
 *
 * Escopo por EMPRESA. Schema LIMPO: preços/pesos/alíquotas decimal nativo, flags
 * boolean. Listas TIPI/LST e enquadramentos virão como tabela própria conforme o
 * uso (sair do hardcoded do legado); aqui ficam como código (int/string) tipado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtoclasses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        Schema::create('unidadesmedida', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->string('sigla', 6)->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['grupo_id', 'descricao']);
        });

        Schema::create('produtos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();

            // Dados
            $t->string('descricao', 255);
            $t->foreignId('produtoclasse_id')->nullable()->constrained('produtoclasses')->nullOnDelete();
            $t->foreignId('unidademedida_id')->nullable()->constrained('unidadesmedida')->nullOnDelete();
            $t->boolean('vasilhame_retornavel')->default(false);
            $t->foreignId('produto_retornavel_id')->nullable()->constrained('produtos')->nullOnDelete();
            $t->boolean('ativo')->default(true);
            $t->boolean('envia_app_nf')->default(false);
            $t->integer('dias_giro')->nullable();
            $t->text('observacao')->nullable();

            // Preços / pesos (decimal nativo)
            $t->decimal('preco_venda', 12, 2)->nullable();
            $t->decimal('preco_venda_minimo', 12, 2)->nullable();
            $t->decimal('custo_medio', 12, 4)->nullable();
            $t->decimal('custo_frete', 12, 4)->nullable();
            $t->decimal('preco_gasdopovo', 12, 2)->nullable();
            $t->decimal('peso_liquido', 12, 3)->nullable();
            $t->decimal('peso_bruto', 12, 3)->nullable();

            // Fiscal / NF-e
            $t->boolean('nfe_permite')->default(false);
            $t->boolean('sped')->default(false);
            $t->unsignedSmallInteger('nfe_tipo_item')->nullable();
            $t->string('ncm', 10)->nullable();
            $t->string('nfe_cest', 10)->nullable();
            $t->string('ean', 20)->nullable();
            $t->string('ean_trib', 20)->nullable();
            $t->string('especie', 60)->nullable();
            $t->string('marca', 60)->nullable();
            $t->unsignedInteger('nfe_cod_gen')->nullable();
            $t->unsignedInteger('nfe_cod_lst')->nullable();
            $t->decimal('nfe_aliq_ipi', 7, 4)->nullable();
            $t->decimal('nfe_bc_ipi', 12, 2)->nullable();
            $t->unsignedInteger('nfe_cod_enquadramento_ipi')->nullable();
            $t->string('nfe_ex_tipi', 10)->nullable();
            $t->string('nfe_descricao_fiscal', 255)->nullable();
            $t->string('nfe_nat_rec', 10)->nullable();

            // GLP / combustível
            $t->unsignedSmallInteger('tipo_glp')->nullable();
            $t->string('nfe_cprod_anp', 20)->nullable();
            $t->string('nfe_desc_anp', 255)->nullable();
            $t->decimal('nfe_qbc_prod', 12, 4)->nullable();
            $t->decimal('nfe_valiq_prod', 12, 4)->nullable();
            $t->decimal('nfe_vcide', 12, 4)->nullable();
            $t->decimal('pgni', 7, 4)->nullable();
            $t->decimal('pgnn', 7, 4)->nullable();
            $t->decimal('pglp', 7, 4)->nullable();

            $t->timestamps();
            $t->index(['empresa_id', 'descricao']);
        });

        // Origens de combustível (sub-recurso 1:N por UF).
        Schema::create('produtoorigens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $t->char('uf', 2)->nullable();
            $t->unsignedSmallInteger('ind_import')->default(0);   // 0=nacional,1=importado
            $t->unsignedInteger('cuf_orig')->nullable();          // UF de origem (cód IBGE)
            $t->decimal('p_orig', 7, 4)->nullable();              // % origem
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtoorigens');
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('unidadesmedida');
        Schema::dropIfExists('produtoclasses');
    }
};
