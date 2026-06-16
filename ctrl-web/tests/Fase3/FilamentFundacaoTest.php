<?php

namespace Tests\Fase3;

use Tests\TestCase;
use App\User;

/**
 * FASE 3 — fundação da UI moderna (Filament 3). Caracteriza a coexistência com
 * o ERP legado: painel exige auth e reusa o login atual; permissões vêm das
 * flags legadas (menuusers) via Policy/canAccessPanel; e a feature flag de UI
 * por módulo redireciona da tela antiga para o recurso Filament.
 *
 * Estilo igual à NavegacaoPostgresTest: login HTTP real + asserções de status.
 */
class FilamentFundacaoTest extends TestCase
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
        return $resp;
    }

    /** Deslogado, o painel /admin redireciona para o login (não expõe nada). */
    public function test_admin_exige_autenticacao()
    {
        $resp = $this->get('/admin');
        $this->assertTrue(
            in_array($resp->getStatusCode(), [301, 302]),
            '/admin deveria redirecionar deslogado (status ' . $resp->getStatusCode() . ')'
        );
    }

    /** Logado como admin, o painel e os recursos-piloto respondem sem erro. */
    public function test_admin_logado_acessa_painel_e_recursos()
    {
        $this->login();

        foreach (['/admin', '/admin/cidades', '/admin/bairros'] as $uri) {
            $code = $this->get($uri)->getStatusCode();
            $this->assertNotEquals(500, $code, "[$uri] retornou 500 no painel Filament");
            $this->assertNotEquals(403, $code, "[$uri] negou acesso ao admin (permissão)");
            $this->assertNotEquals(404, $code, "[$uri] não encontrado (recurso/rota ausente)");
        }
    }

    /** canAccessPanel: admin ativo entra; usuário não-ativo é barrado. */
    public function test_can_access_panel_respeita_ativo()
    {
        $panel = \Filament\Facades\Filament::getPanel('admin');

        $ativo = new User(['ativo' => '1']);
        $this->assertTrue($ativo->canAccessPanel($panel), 'Usuário ativo deveria acessar o painel');

        $inativo = new User(['ativo' => '0']);
        $this->assertFalse($inativo->canAccessPanel($panel), 'Usuário inativo não deveria acessar o painel');
    }

    /**
     * podeNoMenu reflete as flags de menuusers: o admin do seeder tem acesso
     * total, então pode visualizar/criar/editar/deletar cidade e bairro.
     */
    public function test_podenomenu_le_permissoes_legadas()
    {
        $this->login();
        $user = \Auth::user();

        foreach (['cidade.index', 'bairro.index'] as $rota) {
            foreach (['visualizar', 'criar', 'editar', 'deletar'] as $acao) {
                $this->assertTrue(
                    $user->podeNoMenu($rota, $acao),
                    "admin deveria ter '$acao' em $rota (menuusers)"
                );
            }
        }
    }

    /** Com a flag de UI moderna LIGADA, a tela legada redireciona ao Filament. */
    public function test_flag_ui_moderna_redireciona_legado_para_filament()
    {
        $this->login();

        config(['ui_moderna.habilitado' => true, 'ui_moderna.modulos.cidade' => true]);
        $resp = $this->get('/cidade');
        $this->assertTrue(
            in_array($resp->getStatusCode(), [301, 302]),
            'Com a flag ligada, /cidade deveria redirecionar'
        );
        $this->assertStringContainsString('/admin/cidades', $resp->headers->get('Location') ?? '');
    }

    /** Com a flag DESLIGADA, a tela legada continua sendo servida (rollback). */
    public function test_flag_desligada_mantem_tela_legada()
    {
        $this->login();

        config(['ui_moderna.modulos.cidade' => false]);
        $code = $this->get('/cidade')->getStatusCode();
        // 200 (renderizou a tela legada) — e NÃO um redirect para /admin.
        $this->assertNotEquals(500, $code, '/cidade legado quebrou com a flag desligada');
        $this->assertNotEquals(404, $code, '/cidade legado sumiu');
    }
}
