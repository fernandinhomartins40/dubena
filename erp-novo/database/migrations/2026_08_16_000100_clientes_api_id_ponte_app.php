<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna-ponte `clientes.api_id` (T2.1 do PLANO_PRODUCAO).
 *
 * O AppGasEmCasaMigrator criava clientes do app com id sintético
 * (`max(id)+1`) e registrava a origem apenas num texto livre:
 * `observacoes LIKE 'Cadastro originado do app% (id de origem: N)'`.
 *
 * Como a dedup dependia de um mapa lido só da ponte do ERP legado
 * (`legado.clientes.api_id`), o migrator nunca reconhecia os clientes que ele
 * mesmo havia criado antes: cada reexecução escolhia uma faixa de ids nova e o
 * upsert por `id` INSERIA em vez de atualizar. Resultado medido: 11.104 origens
 * viraram 44.416 linhas (4× cada).
 *
 * Esta coluna torna a origem uma chave de verdade — consultável, indexada e
 * única por empresa — para que o migrator possa ser idempotente e para que a
 * dedup (T2.2) agrupe por ela em vez de por parsing de texto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $tabela) {
            $tabela->unsignedBigInteger('api_id')->nullable()->after('id')
                ->comment('Id de origem em sgcm_api.clienteimportacoes (ponte do app). Null = cliente do ERP legado.');
        });

        // Backfill a partir do texto livre: é a ÚNICA fonte da correlação para
        // as linhas já carregadas. Sem isto a T2.2 não teria como agrupar as
        // duplicatas, e o migrator corrigido recriaria tudo mais uma vez.
        $this->preencherDeObservacoes();

        Schema::table('clientes', function (Blueprint $tabela) {
            $tabela->index('api_id', 'clientes_api_id_index');
        });

        // O UNIQUE (empresa_id, api_id) NÃO entra aqui: num banco já carregado
        // existem 4 linhas por api_id, e a restrição falharia. Ele é criado na
        // migration seguinte (…000200), que roda depois da dedup da T2.2.
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $tabela) {
            $tabela->dropIndex('clientes_api_id_index');
            $tabela->dropColumn('api_id');
        });
    }

    /**
     * Extrai "(id de origem: N)" de `observacoes` para a coluna nova.
     *
     * Roda só no Postgres: em sqlite (testes/CI) a tabela nasce vazia e não há
     * o que preencher, e `substring(... from ...)` com regex é sintaxe PG.
     */
    private function preencherDeObservacoes(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE public.clientes
               SET api_id = NULLIF(substring(observacoes from 'id de origem: ([0-9]+)'), '')::bigint
             WHERE observacoes LIKE 'Cadastro originado do app%'
               AND substring(observacoes from 'id de origem: ([0-9]+)') IS NOT NULL
        SQL);
    }
};
