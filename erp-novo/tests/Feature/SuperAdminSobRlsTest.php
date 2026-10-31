<?php

namespace Tests\Feature;

use App\Models\Saas\PlatformAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIE P2-E-01 — o superadmin sob RLS de verdade, pela role do runtime.
 *
 * `SuperAdminTest` roda em sqlite, que não tem RLS, e por isso passava verde
 * enquanto, no Postgres, a plataforma via ZERO empresas: lista vazia, dashboard
 * zerado e 404 em suspender. Sem `RefreshDatabase`: o cenário é gravado pela
 * conexão de owner, que commita por conta própria (mesmo arranjo de
 * `AppLoginSobRlsTest`).
 */
class SuperAdminSobRlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }
    }

    public function test_superadmin_lista_e_suspende_empresa_sob_rls(): void
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();
        $marca = fake()->uuid();

        $grupo = $owner->table('grupos')->insertGetId([
            'descricao' => "SA {$marca}", 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresa = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupo, 'razao_social' => "Empresa SA {$marca}", 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $adminId = $owner->table('platform_admins')->insertGetId([
            'nome' => 'SA RLS', 'email' => "sa.{$marca}@teste.invalid", 'password' => Hash::make('x'),
            'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        // Pré-condição que dá sentido ao teste: o runtime, sozinho, não enxerga.
        $this->assertSame(0, DB::table('empresas')->where('id', $empresa)->count());

        $token = PlatformAdmin::query()->findOrFail($adminId)->createToken('rls')->plainTextToken;

        $ids = collect($this->withToken($token)->getJson('/api/superadmin/empresas?q='.urlencode($marca))
            ->assertOk()->json('data'))->pluck('id');
        $this->assertContains($empresa, $ids->all());

        $this->withToken($token)->postJson("/api/superadmin/empresas/{$empresa}/suspender")->assertOk();
        $this->assertFalse((bool) $owner->table('empresas')->where('id', $empresa)->value('ativo'));

        $this->withToken($token)->postJson("/api/superadmin/empresas/{$empresa}/reativar")->assertOk();
        $this->assertTrue((bool) $owner->table('empresas')->where('id', $empresa)->value('ativo'));
    }
}
