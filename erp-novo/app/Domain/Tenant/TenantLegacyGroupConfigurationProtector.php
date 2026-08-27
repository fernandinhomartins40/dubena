<?php

namespace App\Domain\Tenant;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Ativa a policy canonica somente apos o mapeamento documental estar completo. */
final class TenantLegacyGroupConfigurationProtector
{
    /** @var list<string> */
    private const TABLES = [
        'clientecontatosituacoes',
        'clientecontatotipos',
        'motivos_nao_venda',
        'pedido_motivos_atraso',
        'pedidooperacoes',
        'pedidosituacoes',
    ];

    /** @return array{tables:int,rows:int} */
    public function protect(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('A protecao de configuracao por grupo exige PostgreSQL efetivo.');
        }

        $rows = 0;
        foreach (self::TABLES as $table) {
            $withoutScope = (int) DB::scalar(<<<SQL
                SELECT count(*)
                FROM {$table} configuration_row
                LEFT JOIN tenant_legacy_group_scopes scope
                  ON scope.grupo_id = configuration_row.grupo_id
                 AND scope.status = 'APPROVED'
                WHERE scope.id IS NULL
            SQL);
            if ($withoutScope > 0) {
                throw new LogicException("F1 recusada: {$table} possui {$withoutScope} configuracao(oes) sem ponte documental aprovada.");
            }

            $drift = (int) DB::scalar(<<<SQL
                SELECT count(*)
                FROM {$table} configuration_row
                JOIN tenant_legacy_group_scopes scope
                  ON scope.grupo_id = configuration_row.grupo_id
                 AND scope.status = 'APPROVED'
                WHERE configuration_row.tenant_account_id <> scope.tenant_account_id
            SQL);
            if ($drift > 0) {
                throw new LogicException("F1 recusada: {$table} possui {$drift} chave(s) tenant divergente(s) da ponte documental de grupo.");
            }

            DB::statement(<<<SQL
                UPDATE {$table} configuration_row
                   SET tenant_account_id = scope.tenant_account_id
                  FROM tenant_legacy_group_scopes scope
                 WHERE scope.grupo_id = configuration_row.grupo_id
                   AND scope.status = 'APPROVED'
                   AND configuration_row.tenant_account_id IS NULL
            SQL);

            $withoutTenant = (int) DB::scalar("SELECT count(*) FROM {$table} WHERE tenant_account_id IS NULL");
            if ($withoutTenant > 0) {
                throw new LogicException("F1 recusada: {$table} possui {$withoutTenant} configuracao(oes) sem chave tenant apos o backfill documental.");
            }

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                USING (app_tenant_can_read_group_config(tenant_account_id, grupo_id))
                WITH CHECK (app_tenant_can_operate_group_config(tenant_account_id, grupo_id))
            SQL);
            $rows += (int) DB::table($table)->count();
        }

        return ['tables' => count(self::TABLES), 'rows' => $rows];
    }
}
