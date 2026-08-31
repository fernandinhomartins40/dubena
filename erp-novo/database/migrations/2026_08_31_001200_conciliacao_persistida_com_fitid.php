<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F5-04 — a conciliação bancária deixa de ser efêmera.
 *
 * ## O que existe hoje
 *
 * `ConciliacaoService` lê o OFX, casa por (valor, data) dentro de uma tolerância
 * e **devolve JSON**. Nada é gravado. O parser inclusive já extrai o `FITID` —
 * o identificador único que o banco dá a cada lançamento — e o campo morre na
 * resposta HTTP.
 *
 * ## As três consequências
 *
 * **Subir o mesmo extrato duas vezes reconcilia tudo de novo.** Sem FITID
 * guardado não há como saber que aquele lançamento já foi visto — e reprocessar
 * o OFX do mês é operação rotineira, não acidente.
 *
 * **Não há histórico de matching.** Ninguém sabe por que a transação casou com
 * aquele movimento, com que tolerância, nem em que rodada. Quando um valor
 * aparece conciliado errado, a única forma de investigar é rodar tudo de novo e
 * torcer para reproduzir.
 *
 * **A exceção manual não é auditada.** O operador que desfaz um casamento
 * automático, ou casa dois lançamentos à mão, não deixa rastro — e é justamente
 * essa a decisão que alguém vai querer revisar depois.
 *
 * ## Por que uma tabela e não uma coluna em `contamovimentos`
 *
 * Uma coluna `fitid` no movimento diria "este movimento foi conciliado", mas não
 * guarda o lado do banco: valor, data e descrição **como o banco informou**. É
 * essa comparação que dá sentido à conciliação — o movimento do ERP diz o que
 * nós achamos, e o extrato diz o que aconteceu na conta. Guardar só um lado
 * responde metade da pergunta.
 *
 * Além disso, uma transação do extrato pode ficar **sem par** (pendente), e uma
 * coluna no movimento não teria onde morar nesse caso — que é exatamente o caso
 * que o operador precisa ver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacao_lancamentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->unsignedBigInteger('grupo_id')->nullable();
            $t->unsignedBigInteger('tenant_account_id')->nullable();
            $t->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();

            // O identificador do BANCO. É a chave da idempotência: reprocessar o
            // mesmo OFX reencontra a linha em vez de criar outra.
            $t->string('fitid', 120);

            // O lado do extrato, como o banco informou — congelado. Se o
            // operador reprocessar com outro arquivo e os valores divergirem,
            // essa divergência é um fato, não um detalhe a sobrescrever.
            $t->date('data_banco')->nullable();
            $t->decimal('valor_banco', 14, 2);
            $t->string('descricao_banco', 255)->nullable();
            $t->string('tipo_banco', 30)->nullable();

            // O lado do ERP. Nulo enquanto pendente — o caso que o operador
            // precisa ver.
            $t->foreignId('conta_movimento_id')->nullable()
                ->constrained('contamovimentos')->nullOnDelete();

            // AUTOMATICO | MANUAL | PENDENTE | DESFEITO — como o par foi feito.
            // A distinção entre automático e manual é o que a tarefa chama de
            // "exceções manuais auditadas": sem ela, a decisão humana some
            // dentro do resultado do algoritmo.
            $t->string('origem_match', 20)->default('PENDENTE');

            // Com que tolerância de dias o casamento automático foi aceito.
            // Sem isso não dá para explicar por que casou: a mesma base, com
            // tolerância diferente, produz pares diferentes.
            $t->unsignedSmallInteger('tolerancia_dias')->nullable();

            // Quem decidiu, quando o par foi manual ou desfeito.
            $t->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decidido_em')->nullable();
            $t->string('motivo', 255)->nullable();

            $t->timestamps();

            // A idempotência real: o mesmo lançamento do banco, na mesma conta,
            // existe uma vez só. Garantido pelo BANCO, não pela aplicação —
            // duas requisições simultâneas passariam por qualquer verificação
            // feita em PHP.
            $t->unique(['conta_id', 'fitid']);
            $t->index(['empresa_id', 'conta_id', 'data_banco']);
            $t->index('conta_movimento_id');
        });

        $this->aplicarRls();
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacao_lancamentos');
    }

    /**
     * RLS para a tabela nova.
     *
     * A descoberta automática varreu o banco uma vez e não alcança o que nasce
     * depois — armadilha registrada no CLAUDE.md.
     */
    private function aplicarRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE conciliacao_lancamentos ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE conciliacao_lancamentos FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON conciliacao_lancamentos');
        DB::statement(
            'CREATE POLICY tenant_isolation ON conciliacao_lancamentos
             USING (app_tenant_can_read(tenant_account_id, empresa_id))
             WITH CHECK (app_tenant_can_operate(tenant_account_id, empresa_id))'
        );
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON conciliacao_lancamentos TO erp_app');
        DB::statement('GRANT USAGE, SELECT ON SEQUENCE conciliacao_lancamentos_id_seq TO erp_app');
    }
};
