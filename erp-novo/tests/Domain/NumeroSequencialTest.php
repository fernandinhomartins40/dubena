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
        $svc = new NumeroSequencialService;

        $this->assertSame(1, $svc->proximo('nfe:empresa:1:serie:1'));
        $this->assertSame(2, $svc->proximo('nfe:empresa:1:serie:1'));
        $this->assertSame(3, $svc->proximo('nfe:empresa:1:serie:1'));
    }

    public function test_sequencias_sao_independentes_por_chave(): void
    {
        $svc = new NumeroSequencialService;

        $this->assertSame(1, $svc->proximo('a'));
        $this->assertSame(1, $svc->proximo('b'));
        $this->assertSame(2, $svc->proximo('a'));
    }

    /**
     * A empresa sempre esteve dentro da string da chave. A coluna `empresa_id`
     * torna a convencao explicita para a RLS — sem ela `sequencias` ficou sem
     * policy nenhuma e um tenant podia sobrescrever a numeracao fiscal de outro.
     * Esta extracao precisa casar com a da migration 2026_08_29_001600.
     */
    public function test_extrai_empresa_dos_dois_formatos_reais_de_chave(): void
    {
        // ModeloDocumento::chaveSequencia() — "nf:{empresa}:{modelo}:{serie}"
        $this->assertSame(12, NumeroSequencialService::empresaDaChave('nf:12:55:1'));
        // CnabDriverBase — "boleto:empresa:{empresa}:banco:{cod}:carteira:{c}"
        $this->assertSame(7, NumeroSequencialService::empresaDaChave('boleto:empresa:7:banco:001:carteira:17'));
    }

    public function test_chave_fora_dos_formatos_conhecidos_nao_recebe_dono_inventado(): void
    {
        // Sem dono derivavel a linha fica com empresa_id nulo e a policy a nega,
        // que e o desfecho correto: melhor invisivel do que atribuida a esmo.
        $this->assertNull(NumeroSequencialService::empresaDaChave('a'));
        $this->assertNull(NumeroSequencialService::empresaDaChave('nf:abc:55:1'));
        $this->assertNull(NumeroSequencialService::empresaDaChave('outra:coisa:9'));
    }

    public function test_definir_valor_inicial(): void
    {
        $svc = new NumeroSequencialService;
        $svc->definir('nfe:empresa:9:serie:1', 4063); // ETL importa numeração do legado

        $this->assertSame(4064, $svc->proximo('nfe:empresa:9:serie:1'));
    }
}
