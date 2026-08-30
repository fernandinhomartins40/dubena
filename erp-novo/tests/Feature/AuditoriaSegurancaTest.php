<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\LoginLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da FASE A6 — auditoria de segurança + histórico de papéis.
 *
 * Garante que ações sensíveis geram security_events, que toda alteração de papel
 * gera role_versions, e que a API de auditoria é gated por auditoria.view e
 * escopada por tenant.
 */
class AuditoriaSegurancaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $chaves
     * @return array{0:User,1:Empresa}
     */
    private function adminCom(array $chaves): array
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Admin']);
        $ids = collect($chaves)->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id)->all();
        $role->permissions()->sync($ids);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return [$user->fresh(), $empresa];
    }

    public function test_criar_papel_gera_evento_e_versao(): void
    {
        [$user] = $this->adminCom(['papel.create', 'cliente.view']);

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/papeis', [
            'nome' => 'Vendas', 'permissoes' => ['cliente.view'],
        ])->assertCreated();
        $papelId = $resp->json('data.id');

        $this->assertDatabaseHas('security_events', ['tipo' => 'papel.criado', 'alvo' => "role:{$papelId}"]);
        $this->assertDatabaseHas('role_versions', ['role_id' => $papelId]);
    }

    public function test_editar_papel_gera_nova_versao(): void
    {
        [$user, $empresa] = $this->adminCom(['papel.create', 'papel.edit', 'cliente.view', 'cliente.edit']);
        $papel = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Base']);
        $papel->permissions()->sync([Permission::where('chave', 'cliente.view')->value('id')]);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/papeis/{$papel->id}", [
            'nome' => 'Base', 'permissoes' => ['cliente.view', 'cliente.edit'],
        ])->assertOk();

        $this->assertDatabaseHas('security_events', ['tipo' => 'papel.editado', 'alvo' => "role:{$papel->id}"]);

        $hist = $this->actingAs($this->adminComAuditoria($empresa), 'sanctum')
            ->getJson("/api/admin/papeis/{$papel->id}/historico")->assertOk()->json('data');
        $this->assertNotEmpty($hist);
        $this->assertContains('cliente.edit', $hist[0]['snapshot']['permissoes']);
    }

    public function test_403_gera_evento_de_autorizacao_negada(): void
    {
        [$user] = $this->adminCom([]); // sem permissão alguma

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/produtos')->assertStatus(403);

        $this->assertDatabaseHas('security_events', ['tipo' => 'autorizacao.negada', 'alvo' => 'produto.view']);
    }

    public function test_api_auditoria_exige_permissao(): void
    {
        [$user] = $this->adminCom([]); // sem auditoria.view

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/auditoria/eventos')->assertStatus(403);
    }

    public function test_api_auditoria_lista_eventos(): void
    {
        [$user] = $this->adminCom(['auditoria.view', 'papel.create', 'cliente.view']);

        // Gera um evento (cria papel).
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/papeis', [
            'nome' => 'X', 'permissoes' => ['cliente.view'],
        ])->assertCreated();

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/admin/auditoria/eventos')
            ->assertOk()->json('data');

        $this->assertNotEmpty($data);
        $this->assertContains('papel.criado', array_column($data, 'tipo'));
    }

    public function test_historico_exibe_somente_logins_atribuidos_a_empresa(): void
    {
        [$user, $empresa] = $this->adminCom(['auditoria.view']);
        LoginLog::query()->create([
            'empresa_id' => $empresa->id, 'email' => 'da-empresa@example.test',
            'ip' => '127.0.0.1', 'sucesso' => false, 'motivo' => 'credenciais',
        ]);
        LoginLog::query()->create([
            'empresa_id' => null, 'email' => 'pre-tenant@example.test',
            'ip' => '198.51.100.1', 'sucesso' => false, 'motivo' => 'credenciais',
        ]);
        $outra = Empresa::factory()->create();
        LoginLog::query()->create([
            'empresa_id' => $outra->id, 'email' => 'outra-empresa@example.test',
            'ip' => '203.0.113.1', 'sucesso' => false, 'motivo' => 'credenciais',
        ]);

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/admin/auditoria/logins?apenas_falhas=1')
            ->assertOk()->json('data');

        $emails = array_column($data, 'email');
        $this->assertContains('da-empresa@example.test', $emails);
        $this->assertNotContains('pre-tenant@example.test', $emails);
        $this->assertNotContains('outra-empresa@example.test', $emails);
    }

    /** Helper: usuário com auditoria.view na MESMA empresa (para ler histórico). */
    private function adminComAuditoria(Empresa $empresa): User
    {
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Auditor']);
        $role->permissions()->sync([Permission::firstOrCreate(['chave' => 'auditoria.view'])->id]);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return $user->fresh();
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }
}
