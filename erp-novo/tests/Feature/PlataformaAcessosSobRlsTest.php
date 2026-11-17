<?php

namespace Tests\Feature;

use App\Models\Saas\PlatformAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIE R3-C (P2-E-06) — lockout por conta do superadmin e token de conta
 * desativada, pela role do runtime (`erp_app`) no Postgres.
 *
 * Os dois dependem de leituras que o sqlite não testa: o lockout conta as
 * falhas em `platform_audit_logs` pela conexão do LOGIN, que roda sem
 * `plataforma.owner`; o corte do token lê `ativo` do dono do token no guard,
 * antes de qualquer middleware. Se a RLS ou um grant esconder essas linhas, o
 * lockout nunca dispara e o token desativado segue valendo — em silêncio.
 *
 * Sem `RefreshDatabase`: o cenário é gravado pela conexão de owner (mesmo
 * arranjo de `SuperAdminSobRlsTest`).
 */
class PlataformaAcessosSobRlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }
    }

    private function admin(string $marca): int
    {
        return DB::connection('pgsql_owner')->table('platform_admins')->insertGetId([
            'nome' => 'SA R3', 'email' => "sa.r3.{$marca}@teste.invalid", 'password' => Hash::make('Senha-Certa-123'),
            'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_lockout_do_superadmin_dispara_sob_rls(): void
    {
        config(['superadmin.dois_fatores_obrigatorio' => false]);
        $marca = fake()->uuid();
        $id = $this->admin($marca);
        $email = "sa.r3.{$marca}@teste.invalid";

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.77.0.{$i}"])
                ->postJson('/api/superadmin/login', ['email' => $email, 'password' => 'errada'])->assertStatus(401);
        }

        // As falhas foram GRAVADAS (o runtime consegue inserir na trilha)...
        $this->assertSame(5, DB::connection('pgsql_owner')->table('platform_audit_logs')
            ->where('acao', 'login.falha')->where('entidade_id', $id)->count());

        // ...e LIDAS pelo runtime: a senha certa é barrada.
        $this->withServerVariables(['REMOTE_ADDR' => '10.77.1.1'])
            ->postJson('/api/superadmin/login', ['email' => $email, 'password' => 'Senha-Certa-123'])
            ->assertStatus(429)->assertJsonMissingPath('token');
    }

    public function test_token_do_superadmin_desativado_deixa_de_valer_sob_rls(): void
    {
        $id = $this->admin(fake()->uuid());
        $token = PlatformAdmin::query()->findOrFail($id)->createToken('r3', ['superadmin'])->plainTextToken;

        $this->withToken($token)->getJson('/api/superadmin/me')->assertOk();

        DB::connection('pgsql_owner')->table('platform_admins')->where('id', $id)->update(['ativo' => false]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/superadmin/me')->assertStatus(401);
    }
}
