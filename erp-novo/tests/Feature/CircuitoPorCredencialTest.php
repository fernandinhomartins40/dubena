<?php

namespace Tests\Feature;

use App\Domain\Logistica\Drivers\GoogleRoutesDriver;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * F6-01 — o circuit breaker pertence à credencial, não à plataforma.
 *
 * ## O defeito
 *
 * `GoogleRoutesDriver` tinha a chave do circuito numa constante:
 *
 * ```php
 * private const CIRCUITO_KEY = 'groutes:circuito-aberto';
 * ```
 *
 * O circuito em si é bom e existe por um motivo real — com a API desabilitada
 * ou a quota estourada, cada requisição de rota disparava dezenas de 403 e o
 * endpoint levava 20 s.
 *
 * Só que a chave é **global**. Com N revendas, cada uma com o seu credenciamento
 * Google, a quota estourada de **uma** abre o circuito de **todas**: as demais
 * param de traçar rota sem ter feito nada, e o traçado reto assume em silêncio.
 *
 * O inverso também dói: a revenda com problema real fica invisível, porque o
 * circuito abre e fecha para o conjunto.
 *
 * É a assinatura desta transformação inteira — **correto para uma revenda,
 * errado para N**, e sem nenhum sintoma que aponte para a causa.
 *
 * ## Por que a credencial, e não a empresa
 *
 * É a chave que tem quota. Duas empresas que compartilham a mesma chave
 * compartilham o limite de verdade, e escopar por empresa faria a segunda
 * continuar batendo numa API que já recusou a primeira.
 *
 * Só o hash entra no nome da entrada: cache não é lugar de segredo, nem em nome
 * de chave — quem inspeciona o Redis não pode sair de lá com a API key.
 */
class CircuitoPorCredencialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * O caso que motivou a tarefa: a quota de uma revenda não pode derrubar a
     * outra.
     */
    public function test_circuito_de_uma_credencial_nao_afeta_a_outra(): void
    {
        // A chave da revenda A responde 403 (quota estourada); a de B, 200.
        Http::fake(function ($request) {
            $chave = $request->header('X-Goog-Api-Key')[0] ?? '';

            return $chave === 'chave-da-revenda-A'
                ? Http::response(['error' => 'quota'], 403)
                : Http::response(['routes' => [[
                    'duration' => '600s',
                    'distanceMeters' => 5000,
                    'polyline' => ['encodedPolyline' => 'abc'],
                ]]], 200);
        });

        $a = new GoogleRoutesDriver('chave-da-revenda-A');
        $b = new GoogleRoutesDriver('chave-da-revenda-B');

        // A falha e abre o próprio circuito.
        $this->assertNull($a->tracar(-25.39, -51.45, -25.43, -51.49));

        // B, com credencial própria e saudável, continua traçando.
        $rota = $b->tracar(-25.50, -51.60, -25.55, -51.65);

        $this->assertNotNull($rota, 'a quota da revenda A não pode derrubar a revenda B');
        $this->assertSame('abc', $rota['polyline']);
    }

    /** O circuito da própria credencial continua funcionando — ele existe por um motivo. */
    public function test_o_circuito_da_propria_credencial_corta_a_chamada_seguinte(): void
    {
        Http::fake(fn () => Http::response(['error' => 'quota'], 403));

        $driver = new GoogleRoutesDriver('chave-unica');

        $this->assertNull($driver->tracar(-25.39, -51.45, -25.43, -51.49));

        // Par DIFERENTE (não é o cache de par): só o circuito pode barrar.
        $this->assertNull($driver->tracar(-26.00, -52.00, -26.10, -52.10));

        // Uma chamada só: a segunda foi curto-circuitada antes de sair.
        Http::assertSentCount(1);
    }

    /** A chave da API não aparece no nome da entrada de cache. */
    public function test_a_credencial_nao_vaza_no_nome_da_chave_de_cache(): void
    {
        Http::fake(fn () => Http::response(['error' => 'quota'], 403));

        $segredo = 'AIzaSyD-segredo-que-nao-pode-vazar';
        (new GoogleRoutesDriver($segredo))->tracar(-25.39, -51.45, -25.43, -51.49);

        // O circuito abriu com ALGUMA chave; nenhuma delas pode conter o segredo.
        // Como o store de teste é array, dá para inspecionar o que foi gravado.
        $store = Cache::getStore();
        $this->assertInstanceOf(ArrayStore::class, $store);

        foreach (array_keys((fn () => $this->storage)->call($store)) as $nome) {
            $this->assertStringNotContainsString(
                $segredo,
                (string) $nome,
                'cache não é lugar de segredo, nem em nome de chave',
            );
        }
    }

    /**
     * Duas empresas que COMPARTILHAM a credencial compartilham o circuito.
     *
     * É o comportamento certo, e o contraponto do primeiro teste: quem usa a
     * mesma chave divide a mesma quota de verdade, e insistir depois do 403 só
     * gastaria os 20 s de timeout que o circuito existe para evitar.
     */
    public function test_credencial_compartilhada_compartilha_o_circuito(): void
    {
        Http::fake(fn () => Http::response(['error' => 'quota'], 403));

        $primeira = new GoogleRoutesDriver('chave-compartilhada');
        $segunda = new GoogleRoutesDriver('chave-compartilhada');

        $this->assertNull($primeira->tracar(-25.39, -51.45, -25.43, -51.49));
        $this->assertNull($segunda->tracar(-26.00, -52.00, -26.10, -52.10));

        Http::assertSentCount(1);
    }

    /**
     * Guardião: nenhuma chave de circuito volta a ser constante.
     *
     * O sinal é `circuito` (ou `breaker`) numa `const` — que é como o defeito
     * original estava escrito, e a forma óbvia de escrever de novo.
     */
    public function test_nenhuma_chave_de_circuito_e_constante(): void
    {
        $achados = [];
        $varridos = 0;

        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            $varridos++;
            $conteudo = (string) file_get_contents($arquivo->getPathname());

            foreach (explode("\n", $conteudo) as $n => $linha) {
                // `const ALGO = 'texto'` onde o texto nomeia um circuito. TTL é
                // constante legítima — o que não pode ser fixo é o NOME da
                // entrada, porque é ele que define o escopo.
                if (preg_match('/const\s+\w*(CIRCUITO|BREAKER)\w*\s*=\s*[\'"]/i', $linha)) {
                    $achados[] = basename($arquivo->getPathname()).':'.($n + 1);
                }
            }
        }

        $this->assertGreaterThan(200, $varridos, 'a varredura precisa ter varrido algo');
        $this->assertSame([], $achados, 'a chave do circuito precisa carregar a credencial que falhou');
    }
}
