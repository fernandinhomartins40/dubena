<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F7 — o plano de controle da conversão ganha memória.
 *
 * ## O que existe hoje
 *
 * O ETL é bom: 28 migradores com ordenação topológica por dependência,
 * invariantes por migrador, `--dry-run`, trava pós-cutover que detecta o cutover
 * **pela evidência no banco** (existe pedido criado aqui) em vez de por flag que
 * alguém precisa lembrar de ligar, e distinção entre "origem indisponível" e
 * "origem vazia".
 *
 * ## O que falta
 *
 * **Nada disso é persistido.** Tudo vive no terminal de quem rodou. Quando a
 * carga termina, não sobra registro de:
 *
 *  - **que execução foi essa** — quando, quem, com quais opções, quanto durou;
 *  - **de onde veio cada linha** — dado o cliente #4218 aqui, qual era o id dele
 *    no legado, e por qual migrador ele passou;
 *  - **o que foi descartado e por quê** — a linha que não entrou some sem
 *    rastro, e é justamente ela que alguém vai procurar.
 *
 * O terceiro é o que mais dói num cutover. A conferência acontece dias depois
 * ("faltam 40 clientes"), e sem quarentena a única resposta possível é rodar
 * tudo de novo e comparar — com o sistema já em produção, o que a trava
 * pós-cutover impede, e com razão.
 *
 * ## Três tabelas, não oito
 *
 * O plano nomeia oito entidades (`ConversionRun`, `SourceSnapshot`, `MappingSet`,
 * `StagingRecord`, `QuarantineRecord`, `InvariantResult`, `CutoverPlan`,
 * `EvidenceBundle`). Três resolvem o que a operação precisa **agora** — a
 * execução, a linhagem e o descarte —, e as outras cinco descrevem um pipeline
 * de staging que este ETL não usa: ele lê do dump e escreve no destino, sem
 * área intermediária.
 *
 * Criar as cinco vazias seria pior que não criar: tabela sem escritor parece
 * resolvida e não responde nada — foi exatamente o que aconteceu com
 * `tenant_account_id` em F1, criada e deixada nula.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── A execução ────────────────────────────────────────────────────
        Schema::create('conversao_execucoes', function (Blueprint $t) {
            $t->id();

            // Quem disparou. Nulo quando é o cron ou um comando de console sem
            // usuário — que é o caso normal do ETL, e por isso não é obrigatório.
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // EM_ANDAMENTO | CONCLUIDA | FALHOU | INTERROMPIDA
            //
            // `INTERROMPIDA` existe porque um ETL de 3 GB morre por OOM ou por
            // sessão fechada, e o registro tem de dizer isso em vez de ficar
            // "em andamento" para sempre.
            $t->string('situacao', 20)->default('EM_ANDAMENTO');

            // Qual migrador (ou vazio = todos) e com que opções.
            $t->string('alvo', 100)->nullable();
            $t->boolean('dry_run')->default(false);
            $t->boolean('com_invariantes')->default(false);

            $t->timestamp('iniciada_em');
            $t->timestamp('encerrada_em')->nullable();

            // Totais consolidados — o que responde "a carga deste dia trouxe o
            // quê" sem reprocessar nada.
            $t->unsignedBigInteger('linhas_lidas')->default(0);
            $t->unsignedBigInteger('linhas_gravadas')->default(0);
            $t->unsignedBigInteger('linhas_quarentena')->default(0);

            $t->text('resumo')->nullable();
            $t->timestamps();

            $t->index(['situacao', 'iniciada_em']);
        });

        // ── A linhagem ────────────────────────────────────────────────────
        Schema::create('conversao_linhagem', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversao_execucao_id')->constrained('conversao_execucoes')->cascadeOnDelete();

            // A chave que o plano define: sistema de origem + entidade + PK lá.
            $t->string('sistema_origem', 40);   // oracle | mysql_app | mysql_monitora
            $t->string('entidade', 60);         // clientes | pedidos | ...
            $t->string('pk_origem', 120);

            // Para onde foi. Nulo quando a linha caiu em quarentena.
            $t->string('tabela_destino', 60)->nullable();
            $t->unsignedBigInteger('id_destino')->nullable();

            // Versão do transformador: a mesma linha reprocessada por um
            // migrador corrigido produz resultado diferente, e sem isto não há
            // como saber qual versão gerou o que está lá.
            $t->string('versao_transformador', 20)->nullable();

            $t->timestamps();

            // Upsert idempotente por (origem, entidade, pk): reprocessar
            // atualiza a linha em vez de duplicá-la. Garantido pelo BANCO —
            // duas execuções simultâneas passariam por qualquer verificação
            // feita em PHP.
            $t->unique(['sistema_origem', 'entidade', 'pk_origem'], 'conversao_linhagem_origem_unica');
            $t->index(['tabela_destino', 'id_destino']);
        });

        // ── A quarentena ──────────────────────────────────────────────────
        Schema::create('conversao_quarentena', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversao_execucao_id')->constrained('conversao_execucoes')->cascadeOnDelete();

            $t->string('sistema_origem', 40);
            $t->string('entidade', 60);
            $t->string('pk_origem', 120)->nullable();

            // Por que não entrou. Categoria + texto: a categoria permite contar
            // e filtrar, o texto explica o caso.
            //
            // OWNER_AMBIGUO | IDENTIDADE | DINHEIRO | FISCAL | ITEM | GPS |
            // ESTRUTURA — as classes que o plano nomeia como bloqueantes.
            $t->string('motivo', 30);
            $t->text('detalhe')->nullable();

            // A linha bruta, como veio. Sem ela a quarentena diz que algo foi
            // descartado e não permite recuperar — que é metade do valor.
            $t->json('payload')->nullable();

            // PENDENTE | APROVADA | DESCARTADA — decisão humana sobre o caso.
            $t->string('decisao', 20)->default('PENDENTE');
            $t->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decidido_em')->nullable();

            $t->timestamps();

            $t->index(['entidade', 'motivo']);
            $t->index(['decisao', 'created_at']);
        });

        $this->aplicarGrants();
    }

    public function down(): void
    {
        Schema::dropIfExists('conversao_quarentena');
        Schema::dropIfExists('conversao_linhagem');
        Schema::dropIfExists('conversao_execucoes');
    }

    /**
     * Tabelas de PLATAFORMA, não de tenant.
     *
     * A conversão é operação da plataforma sobre os dados de todas as revendas —
     * é o processo que **cria** os tenants a partir do legado, então não pode
     * estar sujeito ao escopo deles. Uma RLS por tenant aqui esconderia
     * justamente a linha cujo owner ficou ambíguo, que é o caso mais importante
     * da quarentena.
     *
     * `erp_app` lê e não escreve: quem escreve é o comando de console, que roda
     * como owner.
     */
    private function aplicarGrants(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['conversao_execucoes', 'conversao_linhagem', 'conversao_quarentena'] as $tabela) {
            DB::statement("GRANT SELECT ON {$tabela} TO erp_app");
        }
    }
};
