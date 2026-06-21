<?php

namespace Tests\Domain;

use App\Domain\Shared\NumeroSequencialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Numeração sequencial atômica (base da numeração fiscal anti-duplicidade, N9).
 */
class NumeroSequencialTest extends TestCase
{
    use RefreshDatabase;

    public function test_incrementa_sequencialmente(): void
    {
        $svc = new NumeroSequencialService();

        $this->assertSame(1, $svc->proximo('nfe:empresa:1:serie:1'));
        $this->assertSame(2, $svc->proximo('nfe:empresa:1:serie:1'));
        $this->assertSame(3, $svc->proximo('nfe:empresa:1:serie:1'));
    }

    public function test_sequencias_sao_independentes_por_chave(): void
    {
        $svc = new NumeroSequencialService();

        $this->assertSame(1, $svc->proximo('a'));
        $this->assertSame(1, $svc->proximo('b'));
        $this->assertSame(2, $svc->proximo('a'));
    }

    public function test_definir_valor_inicial(): void
    {
        $svc = new NumeroSequencialService();
        $svc->definir('nfe:empresa:9:serie:1', 4063); // ETL importa numeração do legado

        $this->assertSame(4064, $svc->proximo('nfe:empresa:9:serie:1'));
    }
}
