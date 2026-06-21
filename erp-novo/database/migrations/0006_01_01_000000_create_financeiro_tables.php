<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N5 — Financeiro (a pagar / a receber).
 *
 * O FinanceiroService é o ÚNICO gerador (Pedido/NF/convênio/vale-gás/OFX chamam-no).
 * Schema LIMPO: valores decimal nativo; agrupamento_status como inteiro (enum no
 * domínio); saldo do título derivável das parcelas (Σ parcelas = financeiro.valor).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Plano de contas (árvore: pai opcional + nível).
        Schema::create('planos_conta', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('pai_id')->nullable()->constrained('planos_conta')->nullOnDelete();
            $t->string('codigo', 30)->nullable();
            $t->string('descricao');
            $t->char('pagarreceber', 1)->default('R'); // P=pagar, R=receber
            $t->unsignedSmallInteger('nivel')->default(1);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['grupo_id', 'pagarreceber']);
        });

        // Centro de custo (árvore).
        Schema::create('centros_custo', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('pai_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $t->string('codigo', 30)->nullable();
            $t->string('descricao');
            $t->unsignedSmallInteger('nivel')->default(1);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        // Título financeiro (o "documento" a pagar/receber).
        Schema::create('financeiros', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->foreignId('planoconta_id')->nullable()->constrained('planos_conta')->nullOnDelete();
            $t->foreignId('centrocusto_id')->nullable()->constrained('centros_custo')->nullOnDelete();

            $t->char('pagarreceber', 1); // P / R
            $t->string('documento', 60)->nullable();
            $t->string('descricao', 255)->nullable();
            $t->decimal('valor', 12, 2);          // valor total do título
            $t->date('data_emissao')->nullable();

            // Agrupamento/reparcelamento: 0=normal,1=agrupador,2=agrupado,3=reparcelado.
            $t->unsignedSmallInteger('agrupamento_status')->default(0);
            $t->foreignId('agrupador_id')->nullable()->constrained('financeiros')->nullOnDelete();

            // Origem (quem gerou): pedido, nf, convenio, valegas, ofx, manual.
            $t->string('origem', 40)->nullable();
            $t->unsignedBigInteger('origem_id')->nullable();

            $t->boolean('cancelado')->default(false);
            $t->timestamps();
            $t->index(['empresa_id', 'pagarreceber']);
            $t->index(['origem', 'origem_id']);
        });

        // Parcelas do título (Σ parcelas.valor = financeiro.valor).
        Schema::create('financeiroparcelas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('financeiro_id')->constrained('financeiros')->cascadeOnDelete();
            $t->unsignedSmallInteger('numero');
            $t->date('vencimento');
            $t->decimal('valor', 12, 2);
            $t->decimal('desconto', 12, 2)->default(0);
            $t->decimal('valor_efetivado', 12, 2)->default(0); // quanto já foi baixado (N6)
            $t->boolean('baixado')->default(false);
            $t->dateTime('datahora_baixa')->nullable();
            $t->timestamps();
            $t->unique(['financeiro_id', 'numero']);
            $t->index(['vencimento', 'baixado']);
        });

        // Rateio do título por plano/centro (Σ rateios = financeiro.valor, quando usado).
        Schema::create('financeirorateios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('financeiro_id')->constrained('financeiros')->cascadeOnDelete();
            $t->foreignId('planoconta_id')->nullable()->constrained('planos_conta')->nullOnDelete();
            $t->foreignId('centrocusto_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $t->decimal('valor', 12, 2);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financeirorateios');
        Schema::dropIfExists('financeiroparcelas');
        Schema::dropIfExists('financeiros');
        Schema::dropIfExists('centros_custo');
        Schema::dropIfExists('planos_conta');
    }
};
