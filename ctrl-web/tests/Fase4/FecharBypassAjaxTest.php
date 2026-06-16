<?php

namespace Tests\Fase4;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Http\Middleware\AuthorizeCustom;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FASE 4 Bloco A (D11) — fechamento do bypass de autorização por AJAX.
 *
 * Caracteriza e valida o AuthorizeCustom em nível de UNIDADE (sem subir HTTP):
 * exercita authorization() para cada combinação rota × header AJAX × flag, via
 * um Request fabricado com uma rota nomeada. Isso isola a regra de autorização
 * do resto do stack (sessão de permissões é mockada com Session::put).
 *
 * Regras esperadas:
 *  - Rota `ajax.*`  → sempre liberada (auxiliar de leitura), independente da flag.
 *  - Rota "cheia" sensível (ex.: cliente.store) via AJAX:
 *      flag OFF → liberada (legado/bypass);
 *      flag ON  → cai na checagem de permissão (barra quem não tem).
 *  - Rota não-AJAX segue sempre na checagem de permissão.
 */
class FecharBypassAjaxTest extends TestCase
{
    /** Invoca AuthorizeCustom->authorization() para uma rota nomeada. */
    private function autoriza(string $routeName, bool $ajax): bool
    {
        $request = Request::create('/x', 'POST');
        if ($ajax) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }
        // Anexa uma rota nomeada (o middleware lê $request->route()->getName()).
        $route = new \Illuminate\Routing\Route(['POST'], '/x', []);
        $route->name($routeName);
        $request->setRouteResolver(fn () => $route);

        $mw = new AuthorizeCustom();
        $ref = new \ReflectionMethod($mw, 'authorization');
        $ref->setAccessible(true);

        return (bool) $ref->invoke($mw, $request);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Sessão SEM nenhuma permissão para a rota cheia testada → a checagem
        // de permissão deve NEGAR quando a flag estiver ligada.
        \Session::put('permissoes', collect([]));
    }

    /** Rota ajax.* permanece liberada com a flag ligada (não quebra auxiliares). */
    public function test_rota_ajax_continua_liberada_com_flag_ligada()
    {
        config(['seguranca.fechar_bypass_ajax' => true]);
        $this->assertTrue(
            $this->autoriza('ajax.buscatelefone', true),
            'Rota ajax.* (leitura auxiliar) deveria seguir liberada'
        );
    }

    /** LEGADO: com a flag desligada, POST via AJAX em rota cheia passa (bypass). */
    public function test_flag_desligada_mantem_bypass_legado()
    {
        config(['seguranca.fechar_bypass_ajax' => false]);
        $this->assertTrue(
            $this->autoriza('cliente.store', true),
            'Com a flag OFF, o comportamento legado (bypass AJAX) deve ser mantido'
        );
    }

    /** FECHADO: com a flag ligada, POST via AJAX sem permissão é NEGADO. */
    public function test_flag_ligada_fecha_bypass_em_rota_sensivel()
    {
        config(['seguranca.fechar_bypass_ajax' => true]);
        $this->assertFalse(
            $this->autoriza('cliente.store', true),
            'Com a flag ON, AJAX em rota cheia sem permissão deveria ser negado'
        );
    }

    /** Sanidade: rota cheia NÃO-AJAX sempre cai na checagem (independe da flag). */
    public function test_rota_cheia_nao_ajax_sempre_checa_permissao()
    {
        config(['seguranca.fechar_bypass_ajax' => false]);
        $this->assertFalse(
            $this->autoriza('cliente.store', false),
            'Rota cheia não-AJAX sem permissão deveria ser negada mesmo com a flag OFF'
        );
    }
}
