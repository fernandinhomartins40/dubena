<?php

namespace Tests\Feature;

use App\Domain\Shared\Geo;
use Tests\TestCase;

/**
 * F6-04 — os algoritmos geométricos são **únicos** e testados.
 *
 * ## O que a medição encontrou
 *
 * Quatro implementações de Haversine, com **três raios da Terra diferentes**:
 *
 * | Onde | Raio |
 * |---|---|
 * | `Geo` (o canônico) | 6 371 000 m |
 * | `DistanciaEntrega` | 6371.0 km |
 * | `ViagensService` | 6371.0088 km |
 * | `MonitoraLegadoMigrator` | 6371000.0 m |
 *
 * O detalhe que dói: o `Geo` **foi criado para acabar com isso** — o próprio
 * docblock dele diz "a mesma fórmula estava reimplementada em 5+ services, cada
 * uma com seu raio hard-coded" (Q-4 da auditoria). As cópias voltaram depois.
 *
 * ## Precisão não era o problema
 *
 * Medi: entre 6371.0 e 6371.0088 a diferença é de **29 centímetros numa rota de
 * 200 km**. Irrelevante para taxa de entrega ou km rodado.
 *
 * O problema é manutenção. Quatro cópias significam que uma correção — no
 * tratamento de antimeridiano, num arredondamento, numa mudança de datum —
 * alcança uma só, e as outras três continuam com o comportamento antigo. Que é
 * exatamente como este sistema chegou a ter três raios.
 *
 * Por isso o guardião: unificar sem ele só recomeça o ciclo.
 */
class GeometriaPontoUnicoTest extends TestCase
{
    /**
     * Distância conhecida: dois pontos a ~1 grau de latitude.
     *
     * Um grau de latitude é ~111,19 km em qualquer lugar do planeta — é o valor
     * que se pode conferir sem rodar o código, e por isso serve de âncora.
     */
    public function test_um_grau_de_latitude_da_cerca_de_111_km(): void
    {
        $km = Geo::km(0.0, 0.0, 1.0, 0.0);

        $this->assertGreaterThan(111.0, $km);
        $this->assertLessThan(111.5, $km);
    }

    /** Pontos idênticos: zero, sem NaN por erro de ponto flutuante. */
    public function test_pontos_identicos_dao_zero(): void
    {
        $m = Geo::metros(-25.3935, -51.4562, -25.3935, -51.4562);

        $this->assertSame(0.0, $m);
        $this->assertFalse(is_nan($m), 'o cálculo não pode produzir NaN no caso trivial');
    }

    /**
     * Antipodais: metade da circunferência, sem estourar.
     *
     * É o caso onde `1 - a` fica em zero e implementações com `asin` sem clamp
     * produzem NaN. `atan2` atravessa sem proteção extra.
     */
    public function test_pontos_antipodais_nao_produzem_nan(): void
    {
        $m = Geo::metros(0.0, 0.0, 0.0, 180.0);

        $this->assertFalse(is_nan($m));
        $this->assertGreaterThan(20_000_000, $m, 'meia volta ao mundo');
        $this->assertLessThan(20_100_000, $m);
    }

    /**
     * O antimeridiano: 179°E e 179°W são vizinhos, não opostos.
     *
     * Uma implementação que subtraia longitudes sem cuidado daria 358 graus de
     * distância em vez de 2. O Haversine trata isso naturalmente porque o seno
     * da meia-diferença é periódico — e este teste é o que garante que continue
     * assim.
     */
    public function test_atravessar_o_antimeridiano_e_uma_distancia_curta(): void
    {
        $km = Geo::km(0.0, 179.0, 0.0, -179.0);

        $this->assertLessThan(250, $km, '2 graus no equador são ~222 km, não 358 graus');
        $this->assertGreaterThan(200, $km);
    }

    /** Hemisfério sul e longitude negativa (o caso da operação real). */
    public function test_hemisferio_sul_com_longitude_negativa(): void
    {
        // Guarapuava → Curitiba, ~150 km em linha reta.
        $km = Geo::km(-25.3935, -51.4562, -25.4284, -49.2733);

        $this->assertGreaterThan(200, $km);
        $this->assertLessThan(230, $km);
    }

    /** A distância é simétrica: A→B é igual a B→A. */
    public function test_a_distancia_e_simetrica(): void
    {
        $ida = Geo::metros(-25.39, -51.45, -25.43, -51.49);
        $volta = Geo::metros(-25.43, -51.49, -25.39, -51.45);

        $this->assertEqualsWithDelta($ida, $volta, 0.000001);
    }

    /**
     * A bounding box CONTÉM o raio pedido.
     *
     * Ela é um pré-filtro para a query indexada: se for menor que o raio,
     * candidatos legítimos são descartados antes do cálculo fino — e ninguém
     * percebe, porque o resultado sai plausível, só menor.
     */
    public function test_a_bounding_box_contem_o_raio(): void
    {
        $lat = -25.3935;
        $raio = 5000.0; // 5 km

        $caixa = Geo::boundingBox($lat, $raio);

        // Um ponto exatamente no raio, ao norte, tem de caber na caixa.
        //
        // Tolerância de 1 mm porque a ida e a volta do cálculo (raio → grau →
        // distância) deixa erro na última casa do double — 4999,999999999913 em
        // vez de 5000. Um milímetro não descarta cliente nenhum; a versão
        // anterior errava por 5,6 METROS, que é o que este teste pega.
        $latNorte = $lat + $caixa['lat_delta'];
        $this->assertGreaterThanOrEqual(
            $raio - 0.001,
            Geo::metros($lat, -51.45, $latNorte, -51.45),
            'a caixa não pode ser menor que o raio que ela pré-filtra',
        );
    }

    /** Perto do polo a caixa alarga em longitude — senão recorta demais. */
    public function test_a_bounding_box_alarga_em_longitude_perto_do_polo(): void
    {
        $noEquador = Geo::boundingBox(0.0, 5000.0);
        $noPolo = Geo::boundingBox(80.0, 5000.0);

        $this->assertEqualsWithDelta($noEquador['lat_delta'], $noPolo['lat_delta'], 0.000001,
            'latitude não muda de escala');
        $this->assertGreaterThan($noEquador['lng_delta'], $noPolo['lng_delta'],
            'longitude encolhe com o cosseno, então o delta precisa crescer');
    }

    /**
     * Guardião: nenhuma reimplementação nova de Haversine.
     *
     * O `Geo` já existia e as cópias voltaram assim mesmo — unificar sem este
     * teste só recomeçaria o ciclo. O sinal é o raio da Terra escrito à mão:
     * ninguém implementa Haversine sem ele.
     */
    public function test_nenhuma_reimplementacao_de_haversine(): void
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

            $caminho = str_replace('\\', '/', $arquivo->getPathname());

            // O ponto único é quem pode ter o raio.
            if (str_ends_with($caminho, 'Domain/Shared/Geo.php')) {
                continue;
            }

            $varridos++;
            $conteudo = (string) file_get_contents($arquivo->getPathname());

            foreach (explode("\n", $conteudo) as $n => $linha) {
                $semEspaco = ltrim($linha);

                // Comentário citando o raio (para explicar a decisão) é o que se
                // quer preservar, não acusar.
                if (str_starts_with($semEspaco, '*') || str_starts_with($semEspaco, '//')) {
                    continue;
                }

                // 6371 em qualquer unidade: km, m, com ou sem separador.
                if (preg_match('/\b6[_ ]?371[_ ]?(000)?\b/', $linha)) {
                    $achados[] = basename($caminho).':'.($n + 1);
                }
            }
        }

        $this->assertGreaterThan(200, $varridos, 'a varredura precisa ter varrido algo');
        $this->assertSame([], $achados, 'use App\Domain\Shared\Geo: o raio da Terra mora num lugar só');
    }
}
