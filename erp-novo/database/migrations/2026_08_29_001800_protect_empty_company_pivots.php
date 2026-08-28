<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Achado da analise da homologacao: `produto_operacao_fiscal` e
 * `convenio_fechamento_pedidos` continuavam com `rls=false` mesmo depois do
 * deploy, e o portao passou a reprovar por causa delas.
 *
 * A causa e de desenho, nao de dados. Os dois pivots recebem a COLUNA numa
 * migration, mas a POLICY so pelo `saas:tenant:proteger-configuracao-grupo`,
 * que existe para converter legado sob evidencia documental. Como eles estao
 * VAZIOS (0 linhas na copia real), nao ha titularidade a decidir — e num tenant
 * novo, que nunca roda o comando de conversao, eles nasceriam permanentemente
 * sem RLS.
 *
 * Enquanto a tabela esta vazia a protecao e estrutural e nao depende de
 * ninguem: sem linha, nao ha o que classificar errado. Se ja houver dados, esta
 * migration NAO os toca e deixa a conversao documental decidir — nunca inferir
 * dono de linha existente e a regra que o resto de F1 segue.
 */
return new class extends Migration
{
    /** @var array<string, array{0:string,1:string}> */
    private const PIVOTS = [
        'produto_operacao_fiscal' => ['produtos', 'produto_id'],
        'convenio_fechamento_pedidos' => ['convenio_fechamentos', 'convenio_fechamento_id'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::PIVOTS as $pivot => [$pai, $fk]) {
            if ((int) DB::table($pivot)->count() > 0) {
                // Tem dado: a titularidade e decisao documental, nao desta migration.
                continue;
            }

            DB::statement("ALTER TABLE {$pivot} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$pivot} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$pivot}");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$pivot}
                USING (EXISTS (
                    SELECT 1 FROM {$pai} parent_row
                     WHERE parent_row.id = {$pivot}.{$fk}
                       AND app_tenant_can_read(parent_row.tenant_account_id, parent_row.empresa_id)
                ))
                WITH CHECK (EXISTS (
                    SELECT 1 FROM {$pai} parent_row
                     WHERE parent_row.id = {$pivot}.{$fk}
                       AND parent_row.tenant_account_id = {$pivot}.tenant_account_id
                       AND app_tenant_can_operate(parent_row.tenant_account_id, parent_row.empresa_id)
                ))
            SQL);
        }
    }

    public function down(): void
    {
        // Nao restaura estado fail-open: uma tabela que passou a ser isolada nao
        // volta a ficar legivel por qualquer tenant so porque houve rollback.
    }
};
