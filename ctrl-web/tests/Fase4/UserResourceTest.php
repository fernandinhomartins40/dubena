<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\User;
use App\Filament\Resources\UserResource;

/**
 * FASE 4 Bloco A — gestão de usuários em Filament.
 * Valida: a tela exige auth; o admin logado acessa; e a query do recurso é
 * ESCOPADA por empresa (corrige o IDOR do User::all() legado).
 */
class UserResourceTest extends TestCase
{
    private function login()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $resp = $this->post('/handleLogin', [
            'email'    => env('ADMIN_SEED_EMAIL', 'admin'),
            'password' => env('ADMIN_SEED_PASSWORD', 'admin1234'),
            'ativo'    => 1,
        ]);
        $this->assertTrue(in_array($resp->getStatusCode(), [301, 302]), 'Login falhou');
        return \Auth::user();
    }

    /** Deslogado, a tela de usuários redireciona para o login. */
    public function test_users_exige_autenticacao()
    {
        $resp = $this->get('/admin/users');
        $this->assertTrue(in_array($resp->getStatusCode(), [301, 302]), '/admin/users deveria exigir auth');
    }

    /** Admin logado acessa a tela sem erro. */
    public function test_admin_acessa_users()
    {
        $this->login();
        $code = $this->get('/admin/users')->getStatusCode();
        $this->assertNotEquals(500, $code);
        $this->assertNotEquals(403, $code);
        $this->assertNotEquals(404, $code);
    }

    /**
     * A query do recurso NÃO é um User::all() global: para um usuário não-suporte
     * ela restringe por empresa (whereIn empresa_id). Verificamos que o SQL
     * gerado contém o filtro de empresa — a correção do IDOR.
     */
    public function test_query_escopada_por_empresa_para_nao_suporte()
    {
        $admin = $this->login();

        // Garante caminho não-suporte para inspecionar o escopo.
        $admin->support = 0;
        \Auth::setUser($admin);

        $sql = UserResource::getEloquentQuery()->toSql();
        $this->assertStringContainsString('empresa_id', $sql,
            'A query de usuários deveria filtrar por empresa (correção do IDOR User::all)');
    }

    /** Usuário de suporte vê todos (sem filtro de empresa), como no legado. */
    public function test_suporte_ve_todos()
    {
        $admin = $this->login();
        $admin->support = 1;
        \Auth::setUser($admin);

        $sql = UserResource::getEloquentQuery()->toSql();
        $this->assertStringNotContainsString('"empresa_id" in', strtolower($sql),
            'Suporte não deveria ter filtro whereIn de empresa');
    }
}
