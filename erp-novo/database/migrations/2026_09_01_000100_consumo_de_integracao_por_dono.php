<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F6-01 — quota, custo e health por conta de integração.
 *
 * ## O que já existe
 *
 * Proprietário e credencial: `IntegracaoTenant` resolve por empresa → grupo →
 * plataforma, com os segredos cifrados. Circuit breaker: por credencial desde
 * o commit `63e81b68` — a quota estourada de uma revenda não derruba as outras.
 *
 * ## O que falta, e por que dói
 *
 * **Ninguém conta.** Três APIs do Google são cobradas por chamada — geocoding,
 * routes e roads —, e o sistema não sabe quantas fez, por quem, nem quanto
 * custou.
 *
 * Num SaaS isso tem três consequências concretas:
 *
 *  - **a fatura chega sem dono.** O Google cobra a conta da plataforma ou da
 *    revenda; se for da plataforma, não há como repassar nem saber quem gastou;
 *  - **a quota estoura sem aviso.** O circuit breaker reage *depois* do 403, e
 *    então o traçado já degradou para reta. Com contagem, dá para avisar antes;
 *  - **o fallback silencioso vira dívida.** `googleMapsKey` cai para a chave da
 *    plataforma quando não há grupo resolvido — está logado, mas log não soma.
 *
 * ## Uma linha por dia, não por chamada
 *
 * Geocodificação em lote faz milhares de chamadas; uma linha cada produziria
 * uma tabela que ninguém consulta e que cresce mais rápido que o dado do
 * negócio.
 *
 * O agregado por (dono, serviço, dia) responde as perguntas que importam —
 * *quanto esta revenda gastou este mês*, *quem estourou a quota ontem* — e cabe
 * num índice. Quem precisar do detalhe de uma chamada tem o log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integracao_consumos', function (Blueprint $t) {
            $t->id();

            // O DONO da credencial usada. Os dois nulos significam "chave da
            // plataforma" — o caso que hoje some no log, e que é justamente o
            // que se quer enxergar.
            $t->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
            $t->unsignedBigInteger('grupo_id')->nullable();

            // Toda tabela COMPANY carrega `tenant_account_id` — e o guardiao de
            // F1 (`TenantBoundarySchemaTest`) reprova quem nao carrega. Ele me
            // pegou nesta migration, e estava certo: sem a coluna, a policy
            // canonica nao tem o primeiro argumento para decidir.
            //
            // Nulo quando a chave e da PLATAFORMA, que e o mesmo caso de
            // `empresa_id` nulo — consumo que nao e de revenda nenhuma.
            $t->unsignedBigInteger('tenant_account_id')->nullable();

            // `geocoding` | `routes` | `roads` | `erede` | ...
            //
            // O serviço, não o driver: duas classes podem consumir a mesma
            // quota (geocoding e roads usam a mesma chave do Maps), e é a quota
            // que se quer medir.
            $t->string('servico', 40);

            // A FINALIDADE da chamada — o que a tarefa pede junto de quota e
            // custo. `geocodificar_cliente` e `tracar_rota` consomem a mesma
            // chave, e quando a fatura sobe é isto que diz onde olhar.
            $t->string('finalidade', 60)->nullable();

            $t->date('dia');

            $t->unsignedInteger('chamadas')->default(0);
            $t->unsignedInteger('erros')->default(0);

            // HEALTH: quando a última chamada falhou, e por quê. É o que
            // responde "esta integração está viva?" sem precisar chamá-la.
            $t->timestamp('ultimo_erro_em')->nullable();
            $t->string('ultimo_erro', 255)->nullable();

            // CUSTO estimado, em centavos. Estimado de propósito: o preço real
            // vem da fatura, e cravar valor no código seria a mesma armadilha
            // de sempre — número de negócio virando constante. Aqui é ordem de
            // grandeza para a revenda decidir se investiga.
            $t->unsignedBigInteger('custo_centavos')->default(0);

            $t->timestamps();

            // Uma linha por (dono, serviço, finalidade, dia). O upsert depende
            // deste índice — verificação em PHP não sobrevive a dois workers
            // geocodificando em paralelo, que é o caso normal.
            //
            // `finalidade` entra na chave porque duas finalidades da mesma
            // chave precisam somar separado — é isso que torna o número
            // acionável.
            $t->unique(['empresa_id', 'grupo_id', 'servico', 'finalidade', 'dia'], 'integracao_consumo_unico');
            $t->index(['servico', 'dia']);
        });

        $this->aplicarRls();
    }

    public function down(): void
    {
        Schema::dropIfExists('integracao_consumos');
    }

    /**
     * RLS: a revenda vê o próprio consumo.
     *
     * As linhas da PLATAFORMA (empresa e grupo nulos) ficam fora do alcance
     * dela, e é o certo — consumo da chave da plataforma não é dado de nenhuma
     * revenda em particular.
     */
    private function aplicarRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE integracao_consumos ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE integracao_consumos FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON integracao_consumos');

        // As funções canônicas, com as duas chaves — como em toda tabela
        // COMPANY. `empresa_id IS NOT NULL` mantém as linhas da plataforma fora
        // do alcance da revenda: consumo da chave da plataforma não é dado de
        // nenhuma revenda em particular.
        DB::statement(
            'CREATE POLICY tenant_isolation ON integracao_consumos
             USING (empresa_id IS NOT NULL AND app_tenant_can_read(tenant_account_id, empresa_id))
             WITH CHECK (empresa_id IS NOT NULL AND app_tenant_can_operate(tenant_account_id, empresa_id))'
        );
        DB::statement('GRANT SELECT, INSERT, UPDATE ON integracao_consumos TO erp_app');
        DB::statement('GRANT USAGE, SELECT ON SEQUENCE integracao_consumos_id_seq TO erp_app');
    }
};
