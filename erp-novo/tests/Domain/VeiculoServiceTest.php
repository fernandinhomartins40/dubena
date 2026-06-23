<?php

namespace Tests\Domain;

use App\Domain\Frota\VeiculoService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Frota\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * BASELINE C6 — consumo médio (km/l), alerta de óleo e regra de km não-retroativo.
 */
class VeiculoServiceTest extends TestCase
{
    use RefreshDatabase;

    private VeiculoService $svc;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(VeiculoService::class);
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
    }

    private function veiculo(array $attr = []): Veiculo
    {
        return Veiculo::factory()->create(array_merge([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'km_atual' => 1000,
        ], $attr));
    }

    public function test_consumo_medio_entre_tanques_cheios(): void
    {
        $v = $this->veiculo(['km_atual' => 1000]);

        // Partida em 1000 km. Depois roda e abastece de tanque cheio em 1500 (50L)
        // e em 2000 (50L). km rodado = 1000; litros após a partida = 100 → 10 km/l.
        $this->svc->abastecer($v, ['km' => 1000, 'litros' => 40, 'tanque_cheio' => true]);
        $this->svc->abastecer($v, ['km' => 1500, 'litros' => 50, 'tanque_cheio' => true]);
        $this->svc->abastecer($v, ['km' => 2000, 'litros' => 50, 'tanque_cheio' => true]);

        $this->assertSame(10.0, $this->svc->consumoMedio($v->refresh()));
    }

    public function test_consumo_medio_sem_dados_suficientes_e_null(): void
    {
        $v = $this->veiculo();
        $this->svc->abastecer($v, ['km' => 1000, 'litros' => 40, 'tanque_cheio' => true]);

        $this->assertNull($this->svc->consumoMedio($v->refresh()));
    }

    public function test_abastecimento_avanca_km_do_veiculo(): void
    {
        $v = $this->veiculo(['km_atual' => 1000]);
        $this->svc->abastecer($v, ['km' => 1200, 'litros' => 30]);

        $this->assertSame(1200, (int) $v->refresh()->km_atual);
    }

    public function test_abastecimento_com_km_retroativo_bloqueia(): void
    {
        $v = $this->veiculo(['km_atual' => 5000]);

        $this->expectException(ValidationException::class);
        $this->svc->abastecer($v, ['km' => 4000, 'litros' => 30]);
    }

    public function test_alerta_troca_oleo(): void
    {
        // Intervalo 10.000 km; última troca em 90.000; km atual 101.000 → rodou 11.000 → precisa.
        $v = $this->veiculo(['km_atual' => 101000, 'km_troca_oleo' => 10000, 'km_ultima_troca_oleo' => 90000]);

        $alerta = $this->svc->alertaTrocaOleo($v);
        $this->assertTrue($alerta['precisa_trocar']);
        $this->assertSame(11000, $alerta['km_rodado']);
        $this->assertSame(-1000, $alerta['km_restante']);
    }

    public function test_alerta_sem_intervalo_nao_exige_troca(): void
    {
        $v = $this->veiculo(['km_troca_oleo' => null]);
        $this->assertFalse($this->svc->alertaTrocaOleo($v)['precisa_trocar']);
    }
}
