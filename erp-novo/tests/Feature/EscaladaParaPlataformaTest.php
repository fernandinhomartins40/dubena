<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F9-08 — escalada de tenant para SuperAdmin, em TODA a superfície.
 *
 * ## Por que varrer, e não testar uma rota
 *
 * Já havia `test_usuario_de_tenant_nao_acessa_superadmin`, e ele está certo —
 * mas cobre **uma** rota de 34. Um teste assim protege enquanto ninguém adiciona
 * a 35ª esquecendo o guard, que é exatamente quando a proteção seria necessária.
 *
 * O gate F9 exige *"todas as mutações críticas têm autorização negativa"*. A
 * palavra é **todas**, e a única forma de honrá-la é partir da lista de rotas do
 * router, não de uma lista escrita à mão que envelhece em silêncio.
 *
 * ## O que se está testando
 *
 * O SuperAdmin é a única superfície que cruza o sigilo entre empresas. Um token
 * de tenant que passasse ali leria o dado de **todas as revendas** — é a falha
 * mais cara que este sistema pode ter, e a mais fácil de introduzir sem notar
 * (basta um `Route::` fora do grupo com `auth:platform`).
 *
 * ## Não é duplicata do `SuperAdminTest`
 *
 * Aquele testa o comportamento do SuperAdmin (login, 2FA, auditoria). Este testa
 * a **fronteira**, e falha por um motivo diferente: rota nova sem guard.
 */
class EscaladaParaPlataformaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * As rotas de plataforma, lidas do router.
     *
     * @return list<array{metodo:string, uri:string}>
     */
    private function rotasDePlataforma(): array
    {
        $saida = [];

        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with($rota->uri(), 'api/superadmin')) {
                continue;
            }

            // `login` é público por necessidade — é onde o SuperAdmin se
            // autentica. Exigir token nele seria exigir token para pedir token.
            if (str_ends_with($rota->uri(), 'superadmin/login')) {
                continue;
            }

            foreach ($rota->methods() as $metodo) {
                if (in_array($metodo, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $saida[] = ['metodo' => $metodo, 'uri' => $rota->uri()];
            }
        }

        return $saida;
    }

    /**
     * Nenhuma rota de plataforma aceita token de tenant.
     *
     * O `assertGreaterThan` não é decoração: um teste que varre a lista de rotas
     * pode varrer **zero** e passar — já aconteceu nesta base mais de uma vez.
     * Se o filtro parar de casar, o teste reprova em vez de dar falso verde.
     */
    public function test_nenhuma_rota_de_plataforma_aceita_token_de_tenant(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $rotas = $this->rotasDePlataforma();

        $this->assertGreaterThan(
            20,
            count($rotas),
            'a varredura precisa enxergar a superfície real do SuperAdmin — '.
            'zero rotas varridas passaria sem proteger nada',
        );

        $furos = [];

        foreach ($rotas as $r) {
            // Placeholders viram um id que não existe: o que se mede é a
            // AUTENTICAÇÃO, e ela acontece antes de qualquer busca. Um 404 aqui
            // seria tão grave quanto um 200 — significaria que a rota resolveu
            // o parâmetro antes de exigir o guard.
            $uri = (string) preg_replace('/\{[^}]+\}/', '999999', $r['uri']);

            $resposta = $this->actingAs($user, 'sanctum')
                ->json($r['metodo'], '/'.$uri);

            if ($resposta->getStatusCode() !== 401) {
                $furos[] = "{$r['metodo']} /{$uri} devolveu {$resposta->getStatusCode()}";
            }
        }

        $this->assertSame(
            [],
            $furos,
            "Token de TENANT passou em rota de PLATAFORMA — é a falha mais cara deste sistema:\n".
            implode("\n", array_slice($furos, 0, 15)),
        );
    }

    /**
     * Nem sequer autenticado: sem token nenhum também é 401.
     *
     * Parece óbvio e não é — uma rota registrada fora do grupo com
     * `auth:platform` responde 200 para qualquer um, e o sintoma é o dado de
     * todas as revendas exposto sem autenticação.
     */
    public function test_nenhuma_rota_de_plataforma_responde_sem_token(): void
    {
        $rotas = $this->rotasDePlataforma();

        $this->assertGreaterThan(20, count($rotas), 'a varredura precisa enxergar a superfície real');

        $furos = [];

        foreach ($rotas as $r) {
            $uri = (string) preg_replace('/\{[^}]+\}/', '999999', $r['uri']);

            $resposta = $this->json($r['metodo'], '/'.$uri);

            if ($resposta->getStatusCode() !== 401) {
                $furos[] = "{$r['metodo']} /{$uri} devolveu {$resposta->getStatusCode()}";
            }
        }

        $this->assertSame(
            [],
            $furos,
            "Rota de PLATAFORMA respondeu sem autenticação:\n".implode("\n", array_slice($furos, 0, 15)),
        );
    }
}
