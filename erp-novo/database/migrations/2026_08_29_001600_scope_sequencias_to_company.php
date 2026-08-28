<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sequencias` guarda a numeracao fiscal (NF-e/NFC-e) e o nosso-numero do CNAB.
 * Ela nasceu com `chave` unica e mais nada: a empresa vivia DENTRO da string
 * (`nf:{empresa}:{modelo}:{serie}` e `boleto:empresa:{id}:banco:...`).
 *
 * Como a tabela nao tinha `empresa_id`, a conversao COMPANY de F1 a ignorou em
 * silencio e ela ficou sem RLS alguma — provado com a role `erp_app` sem
 * contexto nenhum: leu e sobrescreveu o contador de outra empresa. Num SaaS
 * isso permite a um tenant forcar numero fiscal repetido nos demais, que e o
 * pior desfecho possivel (a SEFAZ rejeita e o erro so aparece ao faturar).
 *
 * Esta migration torna a convencao explicita: extrai a empresa da chave para
 * uma coluna real. Nao inventa dono para chave que nao siga os dois formatos
 * conhecidos — essas ficam com `empresa_id` nulo e sao recusadas pela policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sequencias', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->after('chave');
            $table->index('empresa_id');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Os dois unicos produtores de chave: ModeloDocumento::chaveSequencia()
        // e CnabDriverBase. A empresa esta em posicao fixa em ambos.
        DB::statement(<<<'SQL'
            UPDATE sequencias
               SET empresa_id = NULLIF(split_part(chave, ':', 2), '')::bigint
             WHERE chave LIKE 'nf:%'
               AND split_part(chave, ':', 2) ~ '^[0-9]+$'
        SQL);
        DB::statement(<<<'SQL'
            UPDATE sequencias
               SET empresa_id = NULLIF(split_part(chave, ':', 3), '')::bigint
             WHERE chave LIKE 'boleto:empresa:%'
               AND split_part(chave, ':', 3) ~ '^[0-9]+$'
        SQL);

        // A chave tenant vem do vinculo documental aprovado, nunca do grupo.
        DB::statement(<<<'SQL'
            UPDATE sequencias sequencia
               SET tenant_account_id = company_link.tenant_account_id
              FROM tenant_companies company_link
             WHERE company_link.empresa_id = sequencia.empresa_id
               AND company_link.status = 'APPROVED'
               AND sequencia.tenant_account_id IS NULL
        SQL);

        // A policy so entra depois do backfill: instalada antes, ela esconderia
        // as proprias linhas que o UPDATE precisa enxergar.
        DB::statement('ALTER TABLE sequencias ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE sequencias FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON sequencias');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON sequencias
            USING (app_tenant_can_read(tenant_account_id, empresa_id))
            WITH CHECK (app_tenant_can_operate(tenant_account_id, empresa_id))
        SQL);
    }

    public function down(): void
    {
        // A policy referencia `empresa_id`; derrubar a coluna antes dela deixaria
        // a tabela com policy invalida.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON sequencias');
            DB::statement('ALTER TABLE sequencias NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE sequencias DISABLE ROW LEVEL SECURITY');
        }

        Schema::table('sequencias', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
