<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

    /**
     * Tabelas que LEGITIMAMENTE não têm RLS apesar da coluna de tenant.
     * Deve espelhar a allowlist da migration 2026_06_26_000300_rls_tenant_completa.
     *
     * @var list<string>
     */
    private array $allowlist = [
        'grupos', 'users', 'role_user', 'permission_role',
        'empresa_user', 'empresa_configs', 'roles',
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
}
