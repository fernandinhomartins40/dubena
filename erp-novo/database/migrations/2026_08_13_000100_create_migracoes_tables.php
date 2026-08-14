<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ferramenta de migração no painel — controle das execuções.
 *
 * Uma `migracao` é uma tentativa de trazer os dados de um sistema antigo para o
 * ERP. Ela guarda a conexão de origem, o mapeamento empresa-legada → tenant
 * decidido pelo usuário, e o resultado por etapa. Existe porque uma migração de
 * verdade é longa (16M de linhas no caso Dubena), roda em fila e precisa ser
 * auditável depois: quem migrou, o que entrou, o que ficou de fora e por quê.
 *
 * Tabelas globais (não têm empresa_id): a migração ACONTECE ANTES de existir
 * tenant, e pode criar empresas. Só o SuperAdmin acessa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migracoes', function (Blueprint $t) {
            $t->id();
            $t->string('descricao');

            // ORIGEM. As credenciais ficam em `config` (cast encrypted no model):
            // são segredo de banco de terceiro e não podem ficar em claro.
            $t->string('origem_tipo', 20);              // erp_oracle | erp_pg | app_mysql | monitora_mysql
            $t->text('config')->nullable();

            // ETAPAS: pendente → diagnosticando → aguardando_mapeamento →
            // migrando → concluida | falhou. O mapeamento é o único passo que
            // exige decisão humana (ver MigracaoService::diagnosticar).
            $t->string('status', 30)->default('pendente');
            $t->json('diagnostico')->nullable();        // contagens + problemas achados
            $t->json('mapa_empresas')->nullable();      // empresa legada => tenant (ou "criar")
            $t->json('resultado')->nullable();          // por migrador: lidos/gravados/pulados
            $t->text('erro')->nullable();

            $t->unsignedInteger('progresso')->default(0);   // 0..100
            $t->string('etapa_atual')->nullable();

            $t->foreignId('platform_admin_id')->nullable();
            $t->timestamp('iniciada_em')->nullable();
            $t->timestamp('concluida_em')->nullable();
            $t->timestamps();

            $t->index('status');
        });

        // Toda linha que a migração NÃO conseguiu trazer fica registrada aqui,
        // com o dado original. É o que sustenta a promessa de "não descartar":
        // nada some silenciosamente, e dá para reprocessar ou exportar em CSV.
        Schema::create('migracao_descartes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('migracao_id')->constrained('migracoes')->cascadeOnDelete();
            $t->string('migrador', 50);
            $t->string('entidade', 50);        // clientes, pedidos, posicoes...
            $t->string('motivo', 255);
            $t->string('chave_origem', 100)->nullable();
            $t->json('dados')->nullable();     // a linha original, para reprocessar
            $t->timestamps();

            $t->index(['migracao_id', 'entidade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migracao_descartes');
        Schema::dropIfExists('migracoes');
    }
};
