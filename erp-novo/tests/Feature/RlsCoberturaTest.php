<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GUARDIÃO de RLS — garante que o isolamento multi-tenant cobre 100% das tabelas
 * com empresa_id/grupo_id, e impede regressão futura.
 *
 * Se alguém criar uma tabela nova com empresa_id (ou grupo_id) e esquecer de
 * incluí-la na RLS, ESTE TESTE FALHA — porque a migration de RLS é auto-descoberta
 * e este teste reconfere a verdade no banco. É a rede de segurança contra vazamento.
 *
 * Só roda em PostgreSQL (RLS é específico do pg). Em sqlite (CI padrão) é pulado —
 * a 1ª barreira (global scope) é exercida pelos demais testes cross-tenant.
 *
 * Para rodar localmente/na VPS contra o pg:
 *   DB_CONNECTION=pgsql php artisan test --filter=RlsCobertura
 */
class RlsCoberturaTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Tabelas que LEGITIMAMENTE não têm RLS apesar da coluna de tenant.
     * Deve espelhar a allowlist da migration 2026_06_26_000300_rls_tenant_completa.
     *
     * @var list<string>
     */
    private array $allowlist = [
        'grupos', 'users', 'role_user', 'permission_role',
        'empresa_user', 'empresa_configs', 'roles',
        // Auditoria: recebem empresa_id NULL por design (ver migration 000300).
        'audit_logs', 'login_logs', 'platform_audit_logs',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }
    }

    public function test_role_de_conexao_nao_ignora_rls(): void
    {
        // SUPERUSER e BYPASSRLS fazem o Postgres IGNORAR todas as policies — mesmo
        // com FORCE. Se a app conectar com uma role assim, a 2ª barreira não vale
        // NADA. Este teste garante que a role de conexão respeita RLS.
        $info = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');

        $this->assertFalse((bool) $info->rolsuper, 'A role de conexão da app é SUPERUSER — ela ignora RLS. Use uma role NOSUPERUSER (erp_app).');
        $this->assertFalse((bool) $info->rolbypassrls, 'A role de conexão da app tem BYPASSRLS — ela ignora RLS. Use uma role NOBYPASSRLS (erp_app).');
    }

    public function test_toda_tabela_com_empresa_id_tem_rls_forcado_e_policy(): void
    {
        $this->assertTabelasIsoladas('empresa_id');
    }

    public function test_toda_tabela_com_grupo_id_sem_empresa_id_tem_rls_forcado_e_policy(): void
    {
        $comEmpresa = $this->tabelasComColuna('empresa_id');
        $grupoOnly = array_diff($this->tabelasComColuna('grupo_id'), $comEmpresa);

        $this->assertTabelasIsoladas('grupo_id', $grupoOnly);
    }

    public function test_rls_nega_select_e_update_cross_tenant_no_banco(): void
    {
        [$empresaA, $empresaB, $clienteA, $clienteB, $contextoA] = $this->cenarioRls();
        $this->setTenantBanco($contextoA);

        $ids = DB::table('clientes')->orderBy('id')->pluck('id')->all();
        $this->assertSame([$clienteA->id], $ids);
        $this->assertSame(0, DB::table('clientes')->where('id', $clienteB->id)->update(['nome' => 'INVASAO']));

        $this->assertNotSame(
            'INVASAO',
            DB::connection('pgsql_owner')->table('clientes')->where('id', $clienteB->id)->value('nome'),
        );
    }

    public function test_rls_nega_insert_cross_tenant_no_banco(): void
    {
        [$empresaA, $empresaB, , , $contextoA] = $this->cenarioRls();
        $this->setTenantBanco($contextoA);
        $dados = Cliente::factory()->make([
            'empresa_id' => $empresaB->id,
            'grupo_id' => $empresaB->grupo_id,
        ])->getAttributes();

        DB::statement('SAVEPOINT teste_rls_insert');
        $negou = false;
        try {
            DB::table('clientes')->insert($dados);
        } catch (QueryException) {
            $negou = true;
            DB::statement('ROLLBACK TO SAVEPOINT teste_rls_insert');
        } finally {
            DB::statement('RELEASE SAVEPOINT teste_rls_insert');
            $this->setTenantBanco(null);
        }

        $this->assertTrue($negou, 'RLS deveria rejeitar INSERT com empresa_id de outro tenant.');
    }

    public function test_rls_nega_leitura_e_escrita_sem_contexto(): void
    {
        [, , $clienteA] = $this->cenarioRls();
        $this->setTenantBanco(null);

        $this->assertSame([], DB::table('clientes')->pluck('id')->all());

        $dados = Cliente::factory()->make([
            'empresa_id' => $clienteA->empresa_id,
            'grupo_id' => $clienteA->grupo_id,
        ])->getAttributes();

        $this->expectException(QueryException::class);
        DB::table('clientes')->insert($dados);
    }

    /**
     * Para cada tabela escopada (menos allowlist), exige RLS ENABLE + FORCE + policy.
     *
     * @param  list<string>|null  $tabelas  conjunto a verificar (default: todas com a coluna)
     */
    private function assertTabelasIsoladas(string $coluna, ?array $tabelas = null): void
    {
        $tabelas ??= $this->tabelasComColuna($coluna);
        $alvo = array_values(array_diff($tabelas, $this->allowlist));
        $this->assertNotEmpty($alvo, "Nenhuma tabela com {$coluna} encontrada — schema vazio?");

        $protegidas = DB::table('pg_class')
            ->where('relkind', 'r')
            ->where('relnamespace', DB::raw("'public'::regnamespace"))
            ->where('relrowsecurity', true)
            ->where('relforcerowsecurity', true)
            ->pluck('relname')
            ->all();

        $comPolicy = DB::table('pg_policies')
            ->where('policyname', 'tenant_isolation')
            ->pluck('tablename')
            ->all();

        foreach ($alvo as $tabela) {
            $this->assertContains(
                $tabela,
                $protegidas,
                "Tabela [{$tabela}] tem {$coluna} mas NÃO está com RLS ENABLE+FORCE — risco de vazamento entre tenants."
            );
            $this->assertContains(
                $tabela,
                $comPolicy,
                "Tabela [{$tabela}] tem {$coluna} mas NÃO tem a policy tenant_isolation — risco de vazamento entre tenants."
            );
        }
    }

    /** @return list<string> */
    private function tabelasComColuna(string $coluna): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', $coluna)
            ->pluck('table_name')
            ->all();
    }

    /** @return array{Empresa,Empresa,Cliente,Cliente,array{tenant_account_id:int,tenant_membership_id:int}} */
    private function cenarioRls(): array
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();

        $grupoA = $owner->table('grupos')->insertGetId([
            'descricao' => 'RLS A '.fake()->uuid(), 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $grupoB = $owner->table('grupos')->insertGetId([
            'descricao' => 'RLS B '.fake()->uuid(), 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresaAId = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupoA, 'razao_social' => 'Empresa RLS A',
            'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresaBId = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupoB, 'razao_social' => 'Empresa RLS B',
            'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $tenantAId = $owner->table('tenant_accounts')->insertGetId([
            'legal_name' => 'Tenant RLS A '.fake()->uuid(), 'status' => 'ACTIVE',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $tenantBId = $owner->table('tenant_accounts')->insertGetId([
            'legal_name' => 'Tenant RLS B '.fake()->uuid(), 'status' => 'ACTIVE',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $userAId = $owner->table('users')->insertGetId([
            'name' => 'Usuario RLS A '.fake()->uuid(), 'email' => fake()->unique()->safeEmail(),
            'password' => '$2y$12$rlsRuntimeOnlyNotARealPasswordHash000000000000000000000',
            'empresa_id' => $empresaAId, 'grupo_id' => $grupoA, 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $membershipAId = $owner->table('tenant_memberships')->insertGetId([
            'tenant_account_id' => $tenantAId, 'user_id' => $userAId, 'status' => 'ACTIVE',
            'approved_at' => $agora, 'approval_evidence_ref' => 'rls-a',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $companyAId = $owner->table('tenant_companies')->insertGetId([
            'tenant_account_id' => $tenantAId, 'empresa_id' => $empresaAId, 'status' => 'APPROVED',
            'approved_at' => $agora, 'ownership_evidence_ref' => 'rls-a',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $companyBId = $owner->table('tenant_companies')->insertGetId([
            'tenant_account_id' => $tenantBId, 'empresa_id' => $empresaBId, 'status' => 'APPROVED',
            'approved_at' => $agora, 'ownership_evidence_ref' => 'rls-b',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $owner->table('tenant_company_grants')->insert([
            'tenant_membership_id' => $membershipAId, 'tenant_account_id' => $tenantAId,
            'tenant_company_id' => $companyAId, 'empresa_id' => $empresaAId,
            'can_read' => true, 'can_operate' => true, 'approved_at' => $agora,
            'grant_evidence_ref' => 'rls-a', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $clienteAId = $owner->table('clientes')->insertGetId([
            'empresa_id' => $empresaAId, 'grupo_id' => $grupoA,
            'tenant_account_id' => $tenantAId,
            'nome' => 'Cliente RLS A', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $clienteBId = $owner->table('clientes')->insertGetId([
            'empresa_id' => $empresaBId, 'grupo_id' => $grupoB,
            'tenant_account_id' => $tenantBId,
            'nome' => 'Cliente RLS B', 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $empresaA = new Empresa(['grupo_id' => $grupoA]);
        $empresaA->id = $empresaAId;
        $empresaA->exists = true;
        $empresaB = new Empresa(['grupo_id' => $grupoB]);
        $empresaB->id = $empresaBId;
        $empresaB->exists = true;
        $clienteA = new Cliente(['empresa_id' => $empresaAId, 'grupo_id' => $grupoA]);
        $clienteA->id = $clienteAId;
        $clienteA->exists = true;
        $clienteB = new Cliente(['empresa_id' => $empresaBId, 'grupo_id' => $grupoB]);
        $clienteB->id = $clienteBId;
        $clienteB->exists = true;

        return [$empresaA, $empresaB, $clienteA, $clienteB, [
            'tenant_account_id' => $tenantAId,
            'tenant_membership_id' => $membershipAId,
        ]];
    }

    /** @param array{tenant_account_id:int,tenant_membership_id:int}|null $contexto */
    private function setTenantBanco(?array $contexto): void
    {
        DB::select('SELECT set_config(?, ?, false), set_config(?, ?, false)', [
            'app.tenant_account_id', $contexto ? (string) $contexto['tenant_account_id'] : '',
            'app.tenant_membership_id', $contexto ? (string) $contexto['tenant_membership_id'] : '',
        ]);
    }
}
