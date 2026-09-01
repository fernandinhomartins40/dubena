<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F9-07 — medir o uso das pontes legadas.
 *
 * ## Por que medir é a primeira parte da tarefa
 *
 * A tarefa pede "medir uso, substituir por contratos canônicos e remover
 * gradualmente". As três estão em ordem de dependência, e a primeira é a que
 * está faltando: hoje ninguém sabe **quais** dos 29 endpoints de ponte ainda são
 * chamados, por qual revenda, nem por qual versão de APK.
 *
 * Sem isso, "remover gradualmente" só tem dois desfechos, os dois ruins:
 *
 *  - remove-se por leitura de código, e um vendedor em campo descobre no meio
 *    de uma venda que o endpoint sumiu — o MovelApp está em `targetSdk 28` e
 *    **não publica na Play Store**, então não há correção rápida do outro lado;
 *  - não se remove nada, e a ponte com "data para morrer" vira permanente.
 *
 * A medida é o que transforma a remoção numa decisão verificável: *este endpoint
 * teve zero chamadas em 90 dias, de nenhuma revenda* é um fato; *acho que
 * ninguém usa* é um palpite.
 *
 * ## Uma linha por (rota, empresa, dia), como o consumo de integração
 *
 * Mesmo desenho de `integracao_consumos` (F6-01), e pela mesma razão: o app em
 * campo bate `getPedidosPendentes` em polling. Uma linha por chamada geraria uma
 * tabela que cresce mais rápido que o pedido que ela acompanha, e que ninguém
 * consultaria.
 *
 * O agregado responde as perguntas que decidem a remoção — *quem ainda chama*,
 * *quando foi a última vez*, *qual versão do app* — e cabe num índice.
 *
 * ## Por que `ultima_versao_app`, e não um histórico de versões
 *
 * O que decide a remoção é a versão **mais nova** que ainda usa o endpoint: se
 * já existe APK novo que não chama mais, a ponte pode sair para quem atualizou.
 * Guardar todas as versões vistas seria dado para um relatório que ninguém pediu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ponte_usos', function (Blueprint $t) {
            $t->id();

            // Qual ponte: `movelapp` ou `nfweb`. Os dois apps morrem em momentos
            // diferentes (o NFWEB publica, o MovelApp não), então a contagem
            // precisa separá-los.
            $t->string('ponte', 20);

            // O endpoint no dialeto do legado (`getPedidosPendentes`), e não a
            // rota do Laravel: é esse nome que está compilado dentro do APK, e
            // é por ele que se decide o que pode sair.
            $t->string('endpoint', 80);

            // Nulo é possível: `login` e `init` acontecem ANTES de haver tenant
            // resolvido. Justamente as chamadas que mais interessam, porque
            // provam que um app ainda está vivo em campo.
            $t->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();

            // Toda tabela COMPANY carrega `tenant_account_id` — `TenantBoundarySchemaTest`
            // reprova quem não carrega, e a policy canônica precisa dele como
            // primeiro argumento.
            $t->unsignedBigInteger('tenant_account_id')->nullable();

            $t->date('dia');
            $t->unsignedBigInteger('chamadas')->default(0);

            // Quantas terminaram em recusa de REGRA (o `OPS` do legado). Um
            // endpoint muito chamado e sempre recusado não é uso — é app velho
            // insistindo, e a leitura de "está em uso" seria falsa.
            $t->unsignedBigInteger('recusas')->default(0);

            // A versão mais nova do app vista neste dia. Vem do header que o
            // APK manda; nulo quando o app não manda nenhum (os mais antigos).
            $t->string('ultima_versao_app', 40)->nullable();

            $t->timestamp('ultima_chamada_em')->nullable();
            $t->timestamps();

            $t->unique(['ponte', 'endpoint', 'empresa_id', 'dia'], 'ponte_uso_unico');
            $t->index(['ponte', 'dia']);
        });

        $this->protegerNoPostgres();
    }

    /**
     * RLS: a revenda vê o próprio uso; a plataforma vê tudo pelo owner.
     *
     * A tabela é COMPANY (tem `empresa_id`), então segue a policy canônica. As
     * linhas de `empresa_id IS NULL` — o `login` antes de resolver tenant —
     * ficam FORA da visão de qualquer revenda, o que é o certo: elas não são de
     * revenda nenhuma, e quem precisa delas é o relatório de plataforma, que
     * roda como owner.
     */
    private function protegerNoPostgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE ponte_usos ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ponte_usos FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON ponte_usos');

        // LEITURA por tenant, como toda tabela COMPANY: a revenda vê o próprio
        // uso, e as linhas sem empresa ficam fora do alcance dela (são de
        // revenda nenhuma; quem precisa delas é o relatório de plataforma, que
        // roda como owner).
        //
        // A ESCRITA, porém, precisa aceitar `empresa_id` NULO — e é aí que a
        // policy canônica não serve como está.
        //
        // `login` e `init` acontecem ANTES de haver tenant resolvido, e são
        // justamente as chamadas que provam que um app segue vivo em campo. Com
        // `WITH CHECK (empresa_id IS NOT NULL AND ...)` e `FORCE ROW LEVEL
        // SECURITY`, o Postgres **rejeitaria** esses inserts — e como
        // `UsoDaPonte::registrar()` engole toda exceção (medição não pode
        // derrubar uma venda em campo), a medição perderia em silêncio
        // exatamente o dado mais importante.
        //
        // Não é frouxidão: a linha de `empresa_id` nulo não pertence a tenant
        // nenhum, então não há sigilo a violar ao gravá-la. O que a policy
        // continua impedindo é uma revenda gravar linha de OUTRA.
        DB::statement(
            'CREATE POLICY tenant_isolation ON ponte_usos
             USING (empresa_id IS NOT NULL AND app_tenant_can_read(tenant_account_id, empresa_id))
             WITH CHECK (empresa_id IS NULL OR app_tenant_can_operate(tenant_account_id, empresa_id))'
        );

        // Escreve (é ele quem conta) e NÃO apaga: contagem apagada é endpoint
        // que parece morto sem estar. O risco aqui não é hipotético — é
        // exatamente o dado que autoriza remover uma rota de que um app depende.
        DB::statement('GRANT SELECT, INSERT, UPDATE ON ponte_usos TO erp_app');
        DB::statement('REVOKE DELETE ON ponte_usos FROM erp_app');
        DB::statement('GRANT USAGE, SELECT ON SEQUENCE ponte_usos_id_seq TO erp_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('ponte_usos');
    }
};
