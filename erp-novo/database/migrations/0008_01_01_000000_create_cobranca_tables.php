<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N7 — Cobrança registrada: boleto (CNAB) + PIX. GATES bancários.
 *
 * A integração com o banco (geração de linha digitável/CNAB, transmissão, webhook)
 * é isolada atrás de drivers; aqui ficam os registros RASTREÁVEIS (boleto, ocorrência,
 * remessa, cobrança PIX) ligados à parcela financeira (N5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('financeiroparcela_id')->nullable()->constrained('financeiroparcelas')->nullOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->unsignedInteger('banco_codigo');         // 104 Caixa, 341 Itaú...
            $t->string('carteira', 10)->nullable();
            $t->string('nosso_numero', 30)->nullable();
            $t->string('linha_digitavel', 60)->nullable();
            $t->string('codigo_barras', 60)->nullable();
            $t->decimal('valor', 12, 2);
            $t->date('vencimento');
            // REGISTRADO, LIQUIDADO, BAIXADO, PROTESTADO, REJEITADO, CANCELADO.
            $t->string('situacao', 20)->default('PENDENTE');
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
            $t->index(['banco_codigo', 'nosso_numero']);
        });

        Schema::create('boleto_ocorrencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('boleto_id')->constrained('boletos')->cascadeOnDelete();
            $t->string('codigo', 10)->nullable();    // código de ocorrência CNAB
            $t->string('descricao', 255)->nullable();
            $t->decimal('valor', 12, 2)->nullable();
            $t->date('data_ocorrencia')->nullable();
            $t->timestamps();
        });

        Schema::create('remessas_cnab', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->unsignedInteger('banco_codigo');
            $t->unsignedInteger('numero_remessa');
            $t->string('arquivo', 255)->nullable();   // caminho do .rem gerado
            $t->unsignedInteger('total_boletos')->default(0);
            $t->decimal('valor_total', 14, 2)->default(0);
            $t->string('situacao', 20)->default('GERADA'); // GERADA, ENVIADA, PROCESSADA
            $t->timestamps();
        });

        Schema::create('pix_cobrancas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('financeiroparcela_id')->nullable()->constrained('financeiroparcelas')->nullOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->string('txid', 64)->unique();          // identificador da cobrança (EMV)
            $t->string('e2eid', 64)->nullable();        // id da transação (no pagamento)
            $t->decimal('valor', 12, 2);
            $t->text('copia_e_cola')->nullable();       // payload EMV (BR Code)
            $t->string('qrcode', 500)->nullable();
            $t->dateTime('expira_em')->nullable();
            // ATIVA, CONCLUIDA, REMOVIDA_PSP, EXPIRADA.
            $t->string('situacao', 20)->default('ATIVA');
            $t->dateTime('pago_em')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_cobrancas');
        Schema::dropIfExists('remessas_cnab');
        Schema::dropIfExists('boleto_ocorrencias');
        Schema::dropIfExists('boletos');
    }
};
