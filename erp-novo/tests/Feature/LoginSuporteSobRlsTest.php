<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIE P2-D-01 — login de usuário `support` sob RLS, pela role do runtime.
 *
 * O evento `login.suporte` nasce antes do envelope de tenant; a policy canônica
 * de `security_events` o recusava e o login inteiro virava HTTP 500. Em sqlite
 * não há RLS, então só aqui o defeito aparece. Sem `RefreshDatabase`: cenário
 * gravado pelo owner, como em `AppLoginSobRlsTest`.
 */
class LoginSuporteSobRlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }
    }

    public function test_usuario_support_loga_e_o_evento_fica_gravado(): void
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();
        $marca = fake()->uuid();

        $grupo = $owner->table('grupos')->insertGetId([
            'descricao' => "Sup {$marca}", 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresa = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupo, 'razao_social' => "Empresa Sup {$marca}", 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $email = "suporte.{$marca}@teste.invalid";
        $user = $owner->table('users')->insertGetId([
            'name' => 'Suporte', 'email' => $email, 'password' => Hash::make('Senha-forte-2026!'),
            'empresa_id' => $empresa, 'grupo_id' => $grupo, 'ativo' => true, 'support' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $this->postJson('/api/login', ['email' => $email, 'password' => 'Senha-forte-2026!'])->assertOk();

        $this->assertSame(1, $owner->table('security_events')
            ->where('tipo', 'login.suporte')->where('user_id', $user)->count());
    }
}
