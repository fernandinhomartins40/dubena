<?php

namespace Tests\Domain;

use App\Domain\Fiscal\IbptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FASE C7a — IBPT (Lei 12.741, "De olho no imposto"). Cálculo puro. */
class IbptTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_carga_aproximada_com_padrao(): void
    {
        $r = app(IbptService::class)->calcular('27111910', 100.0, 'nacional');

        // Sem tabela carregada → padrão nacional 12% + estadual 18% = 30% → 30,00.
        $this->assertSame('27111910', $r['ncm']);
        $this->assertEqualsWithDelta(30.0, $r['valor_tributos'], 0.01);
        $this->assertSame('padrão', $r['fonte']);
    }

    public function test_importado_usa_aliquota_maior(): void
    {
        $nac = app(IbptService::class)->calcular('27111910', 100.0, 'nacional');
        $imp = app(IbptService::class)->calcular('27111910', 100.0, 'importado');

        $this->assertGreaterThan($nac['valor_tributos'], $imp['valor_tributos']);
    }
}
