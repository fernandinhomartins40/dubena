<?php

namespace Tests;

use App\Domain\Contrato\ColetorDeSchema;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * F2-01 — liga a captura de schema quando `api:schema` roda a suíte.
     *
     * Aqui e não num teste isolado porque o contrato precisa do que TODA a suíte
     * exercita: cada teste que bate numa rota contribui com a forma dela.
     *
     * Fora dessa execução a variável não existe e nada é observado.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('API_SCHEMA_CAPTURA') === '1' && ! ColetorDeSchema::ligado()) {
            ColetorDeSchema::ligar(zerar: false);
        }
    }

    /**
     * Grava o que foi capturado até aqui.
     *
     * A cada teste, e não no fim da suíte: o PHPUnit não oferece um gancho
     * confiável de "fim de tudo" que sobreviva a uma execução interrompida, e
     * reescrever o arquivo acumulado é barato perto de rodar a suíte de novo.
     */
    protected function tearDown(): void
    {
        if (getenv('API_SCHEMA_CAPTURA') === '1') {
            $coletado = ColetorDeSchema::coletado();

            if ($coletado !== []) {
                $destino = base_path(ColetorDeSchema::CAMINHO_CAPTURA);
                @mkdir(dirname($destino), 0775, true);
                file_put_contents(
                    $destino,
                    json_encode($coletado, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                );
            }
        }

        parent::tearDown();
    }
}
