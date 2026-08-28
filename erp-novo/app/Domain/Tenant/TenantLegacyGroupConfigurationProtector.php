<?php

namespace App\Domain\Tenant;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Ativa a policy canonica somente apos o mapeamento documental estar completo. */
final class TenantLegacyGroupConfigurationProtector
{
    /** @var list<string> */
    private const TABLES = [
        'cargos',
        'centros_custo',
        'checklists',
        'clientecontatosituacoes',
        'clientecontatotipos',
        'condicaopagamentos',
        'contamovimentotipos',
        'motivos_nao_venda',
        'monitora_veiculo_tipos',
        'pedido_motivos_atraso',
        'pedidooperacoes',
        'pedidosituacoes',
        'planos_conta',
        'promocoes',
        'sorteios',
        'veiculo_tipos',
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

        foreach ([
            'checklist_perguntas' => ['checklists', 'checklist_id'],
            'condicaopagamento_parcelas' => ['condicaopagamentos', 'condicaopagamento_id'],
            'sorteio_numeros' => ['sorteios', 'sorteio_id'],
        ] as $child => [$parent, $foreignKey]) {
            $rows += $this->protectChildTable($child, $parent, $foreignKey);
        }
        foreach (['centros_custo', 'planos_conta'] as $table) {
            $this->assertHierarchyTenant($table);
        }

        return ['tables' => count(self::TABLES), 'rows' => $rows];
    }

    private function protectChildTable(string $child, string $parent, string $foreignKey): int
    {
        DB::statement("UPDATE {$child} child_row SET tenant_account_id = parent_row.tenant_account_id FROM {$parent} parent_row WHERE parent_row.id = child_row.{$foreignKey} AND child_row.tenant_account_id IS NULL");
        $invalid = (int) DB::scalar("SELECT count(*) FROM {$child} child_row LEFT JOIN {$parent} parent_row ON parent_row.id = child_row.{$foreignKey} WHERE parent_row.id IS NULL OR parent_row.tenant_account_id IS NULL OR child_row.tenant_account_id IS NULL OR child_row.tenant_account_id <> parent_row.tenant_account_id");
        if ($invalid > 0) {
            throw new LogicException("F1 recusada: {$child} possui {$invalid} filho(s) com tenant divergente do pai.");
        }
        DB::statement("ALTER TABLE {$child} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$child} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$child}");
        DB::statement("CREATE POLICY tenant_isolation ON {$child} USING (EXISTS (SELECT 1 FROM {$parent} parent_row WHERE parent_row.id = {$child}.{$foreignKey} AND app_tenant_can_read_group_config(parent_row.tenant_account_id, parent_row.grupo_id))) WITH CHECK (EXISTS (SELECT 1 FROM {$parent} parent_row WHERE parent_row.id = {$child}.{$foreignKey} AND parent_row.tenant_account_id = {$child}.tenant_account_id AND app_tenant_can_operate_group_config(parent_row.tenant_account_id, parent_row.grupo_id)))");

        return (int) DB::table($child)->count();
    }

    private function assertHierarchyTenant(string $table): void
    {
        $invalid = (int) DB::scalar("SELECT count(*) FROM {$table} child_row JOIN {$table} parent_row ON parent_row.id = child_row.pai_id WHERE child_row.pai_id IS NOT NULL AND child_row.tenant_account_id <> parent_row.tenant_account_id");
        if ($invalid > 0) {
            throw new LogicException("F1 recusada: {$table} possui {$invalid} vinculo(s) hierarquico(s) entre tenants distintos.");
        }
    }
}
