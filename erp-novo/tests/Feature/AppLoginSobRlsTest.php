<?php

namespace Tests\Feature;

use App\Domain\Tenant\ConexaoDeOwner;
use App\Domain\Tenant\IdentidadeDeAutomacao;
use App\Models\Saas\TenantAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Os dois logins do app sob RLS de verdade, pela role do runtime.
 *
 * São rotas PÚBLICAS: não passam pelo middleware que abre o envelope, e em
 * 13/10/2026 a homologação recusava todos os logins — o cliente com "empresa
 * inválida" (o `exists:empresas` lia zero linhas) e o entregador com "exclusivo
 * para colaboradores" com a senha certa (a policy escondia o vínculo). A suíte
 * em sqlite passava verde nos dois, porque sqlite não tem RLS.
 *
 * Sem `RefreshDatabase`: o cenário é gravado pela conexão de owner, que commita
 * por conta própria — mesmo arranjo de `AutomacaoSobRlsTest`.
 */
class AppLoginSobRlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }

        config(['saas_transformation.enforcement.tenant_envelope' => true]);
    }

    /** @return array{tenant: int, empresa: int, grupo: int, company: int} */
    private function revenda(string $rotulo, bool $comIdentidade = true): array
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();
        $marca = $rotulo.' '.fake()->uuid();

        $grupo = $owner->table('grupos')->insertGetId([
            'descricao' => "App {$marca}", 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresa = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupo, 'razao_social' => "Empresa {$marca}", 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $tenant = $owner->table('tenant_accounts')->insertGetId([
            'legal_name' => "Tenant {$marca}", 'status' => 'ACTIVE', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $company = $owner->table('tenant_companies')->insertGetId([
            'tenant_account_id' => $tenant, 'empresa_id' => $empresa, 'status' => 'APPROVED',
            'approved_at' => $agora, 'ownership_evidence_ref' => 'app-login-rls',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        if ($comIdentidade) {
            ConexaoDeOwner::executar(fn () => app(IdentidadeDeAutomacao::class)->provisionar(TenantAccount::findOrFail($tenant)));
        }

        return ['tenant' => $tenant, 'empresa' => $empresa, 'grupo' => $grupo, 'company' => $company];
    }

    /** Colaborador com login, gravado como owner. Sem `$comFronteira`, sem membership. */
    private function colaborador(array $r, string $vinculo, string $senha, bool $comFronteira = true): string
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();
        $email = $vinculo.'.'.fake()->uuid().'@teste.invalid';

        $user = $owner->table('users')->insertGetId([
            'name' => "Campo {$vinculo}", 'email' => $email, 'password' => Hash::make($senha),
            'empresa_id' => $r['empresa'], 'grupo_id' => $r['grupo'], 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $owner->table('colaboradores')->insert([
            'empresa_id' => $r['empresa'], 'grupo_id' => $r['grupo'], 'tenant_account_id' => $r['tenant'],
            'user_id' => $user, 'nome' => "Campo {$vinculo}", 'vinculo' => $vinculo,
            'entregador' => true, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        if ($comFronteira) {
            $membership = $owner->table('tenant_memberships')->insertGetId([
                'tenant_account_id' => $r['tenant'], 'user_id' => $user, 'status' => 'ACTIVE',
                'membership_role' => 'MEMBER', 'approved_at' => $agora, 'approval_evidence_ref' => 'app-login-rls',
                'created_at' => $agora, 'updated_at' => $agora,
            ]);
            $owner->table('tenant_company_grants')->insert([
                'tenant_membership_id' => $membership, 'empresa_id' => $r['empresa'],
                'tenant_account_id' => $r['tenant'], 'tenant_company_id' => $r['company'],
                'can_read' => true, 'can_operate' => true, 'approved_at' => $agora,
                'grant_evidence_ref' => 'app-login-rls', 'created_at' => $agora, 'updated_at' => $agora,
            ]);
        }

        return $email;
    }

    public function test_cliente_se_cadastra_e_depois_entra_pelo_telefone(): void
    {
        $r = $this->revenda('cliente');
        $telefone = '+55429'.random_int(10000000, 99999999);

        $cadastro = $this->postJson('/api/app/v1/cliente/cadastro', [
            'firebase_id_token' => 'fake:'.$telefone,
            'empresa_id' => $r['empresa'],
            'nome' => 'Cliente do App',
            'device_id' => 'aparelho-'.fake()->uuid(),
        ]);
        $cadastro->assertCreated()->assertJsonStructure(['token', 'user' => ['id']]);
        $userId = $cadastro->json('user.id');

        // O mesmo telefone agora ENTRA — antes era 422 para sempre, porque a
        // busca pelo telefone não enxergava cliente nenhum.
        $this->postJson('/api/app/v1/cliente/login', [
            'firebase_id_token' => 'fake:'.$telefone,
            'empresa_id' => $r['empresa'],
        ])->assertOk()->assertJsonPath('user.id', $userId);

        $owner = DB::connection('pgsql_owner');
        // Gravado com a chave do tenant: sem ela a linha existiria e ninguém a veria.
        $cliente = $owner->table('clientes')->where('user_id', $userId)->first(['empresa_id', 'tenant_account_id']);
        $this->assertSame($r['empresa'], (int) $cliente->empresa_id);
        $this->assertSame($r['tenant'], (int) $cliente->tenant_account_id);

        // E a fronteira do próprio cliente, sem a qual o token tomaria 403 na
        // requisição seguinte.
        $grant = $owner->table('tenant_company_grants as g')
            ->join('tenant_memberships as m', 'm.id', '=', 'g.tenant_membership_id')
            ->where('m.user_id', $userId)->where('m.status', 'ACTIVE')
            ->where('g.empresa_id', $r['empresa'])->whereNotNull('g.approved_at')
            ->first(['g.can_read', 'g.can_operate']);
        $this->assertNotNull($grant);
        $this->assertTrue((bool) $grant->can_operate);
    }

    public function test_cliente_em_empresa_sem_identidade_de_automacao_e_negado(): void
    {
        $r = $this->revenda('sem-identidade', comIdentidade: false);

        $this->postJson('/api/app/v1/cliente/login', [
            'firebase_id_token' => 'fake:+5542900000000',
            'empresa_id' => $r['empresa'],
        ])->assertForbidden();
    }

    public function test_entregador_entra_com_a_senha_certa(): void
    {
        $r = $this->revenda('campo');

        foreach (['funcionario' => 'entregador', 'franqueado' => 'franqueado', 'industrial' => 'industrial'] as $vinculo => $papel) {
            $email = $this->colaborador($r, $vinculo, 'senha-de-teste');

            $this->postJson('/api/app/v1/login', [
                'email' => $email,
                'password' => 'senha-de-teste',
                'device_id' => 'aparelho-'.fake()->uuid(),
            ])->assertOk()
                ->assertJsonPath('user.vinculo', $vinculo)
                ->assertJsonPath('user.papel', $papel);
        }
    }

    public function test_colaborador_sem_fronteira_saas_e_negado_no_login(): void
    {
        $r = $this->revenda('sem-fronteira');
        $email = $this->colaborador($r, 'funcionario', 'senha-de-teste', comFronteira: false);

        // Negado no login, e não com um token que tomaria 403 logo depois.
        // A mensagem importa: sem envelope o login também dava 403, mas pelo
        // "exclusivo para colaboradores" — o vínculo escondido pela RLS — e o
        // teste passaria pela verificação errada. Esta é a do resolver: sem
        // grant, nem a `tenant_companies` da empresa fica visível ao usuário.
        $this->postJson('/api/app/v1/login', ['email' => $email, 'password' => 'senha-de-teste'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Empresa sem vinculo de titularidade SaaS aprovado.')
            ->assertJsonMissingPath('token');
    }
}
