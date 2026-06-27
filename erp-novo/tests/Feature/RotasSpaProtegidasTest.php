<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * GATE de "níveis de acesso em toda a aplicação".
 *
 * Lê o routes.tsx da SPA e garante que TODA rota de página protegida passa pelo
 * helper `p(elemento, 'permissao')` COM uma permissão declarada — exceto as
 * rotas explicitamente abertas a qualquer usuário logado (allowlist). Assim,
 * adicionar uma página nova sem declarar a permissão QUEBRA o CI (anti-regressão
 * do controle de acesso no front).
 */
class RotasSpaProtegidasTest extends TestCase
{
    /**
     * Rotas que podem ser apenas autenticadas (sem permissão específica):
     * - '/'          Dashboard é a home de qualquer usuário logado.
     * - '/satelites' hub de satélites (qualquer logado).
     * - '/seguranca' conta do próprio usuário (2FA/sessões).
     *
     * @var list<string>
     */
    private const SO_AUTENTICADAS = ['/', '/satelites', '/seguranca'];

    public function test_toda_rota_de_pagina_declara_permissao(): void
    {
        $arquivo = base_path('frontend/src/routes.tsx');
        if (! is_file($arquivo)) {
            $this->markTestSkipped('routes.tsx não encontrado (frontend ausente).');
        }

        $src = (string) file_get_contents($arquivo);

        // Captura cada <Route path="..." element={p(<Page />[, 'perm'])} />.
        // Só nos interessam as que usam o helper `p(` (rotas de página protegidas);
        // <Navigate> e a rota /login pública ficam de fora naturalmente.
        preg_match_all(
            '/path="([^"]+)"\s+element=\{p\((.*?)\)\}/s',
            $src,
            $m,
            PREG_SET_ORDER,
        );

        $this->assertNotEmpty($m, 'Nenhuma rota com o helper p() encontrada — o padrão mudou?');

        $semPermissao = [];
        foreach ($m as $rota) {
            $path = $rota[1];
            $args = $rota[2];

            // Tem 2º argumento string? (a permissão). Ex.: p(<X />, 'cliente.view')
            $temPermissao = (bool) preg_match("/,\s*'[a-z]+(\.[a-z_]+)+'/", $args);

            if (! $temPermissao && ! in_array($path, self::SO_AUTENTICADAS, true)) {
                $semPermissao[] = $path;
            }
        }

        $this->assertSame(
            [],
            $semPermissao,
            "Rotas de página SEM permissão declarada (e fora da allowlist de só-auth):\n - ".
                implode("\n - ", $semPermissao).
                "\n\nDeclare a permissão no routes.tsx: p(<Pagina />, 'modulo.view').",
        );
    }
}
