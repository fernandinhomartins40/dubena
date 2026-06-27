<?php

namespace Tests\Domain;

use App\Domain\Monitora\RelatorioMonitoraService;
use App\Models\Empresa;
use App\Models\Monitora\Veiculo;
use App\Models\Monitora\VeiculoTipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 — Relatório do Monitora: apuração de paradas e excessos de velocidade.
 */
class RelatorioMonitoraTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
    }

    private function veiculo(?int $velMax = null): Veiculo
    {
        $tipoId = null;
        if ($velMax !== null) {
            $tipoId = VeiculoTipo::create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Tipo', 'velocidade_maxima' => $velMax])->id;
        }

        return Veiculo::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'placa' => 'ABC1D23', 'tipo_id' => $tipoId, 'ativo' => true,
        ]);
    }

    public function test_apura_parada_acima_do_limiar(): void
    {
        $veiculo = $this->veiculo();
        // Parado por 10 min (>5min) → 1 parada; depois anda.
        $veiculo->posicoes()->createMany([
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 0, 'registrado_em' => '2026-06-01 08:00:00'],
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 0, 'registrado_em' => '2026-06-01 08:10:00'],
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 40, 'registrado_em' => '2026-06-01 08:12:00'],
        ]);

        $ev = app(RelatorioMonitoraService::class)->eventosVeiculo($veiculo, '2026-06-01', '2026-06-01');

        $this->assertCount(1, $ev['paradas']);
        $this->assertEqualsWithDelta(10.0, $ev['paradas'][0]['duracao_min'], 0.1);
    }

    public function test_parada_curta_nao_conta(): void
    {
        $veiculo = $this->veiculo();
        // Parado só 2 min (<5min) → nenhuma parada.
        $veiculo->posicoes()->createMany([
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 0, 'registrado_em' => '2026-06-01 08:00:00'],
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 0, 'registrado_em' => '2026-06-01 08:02:00'],
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 30, 'registrado_em' => '2026-06-01 08:05:00'],
        ]);

        $ev = app(RelatorioMonitoraService::class)->eventosVeiculo($veiculo, '2026-06-01', '2026-06-01');
        $this->assertCount(0, $ev['paradas']);
    }

    public function test_excesso_de_velocidade_usa_o_tipo(): void
    {
        $veiculo = $this->veiculo(velMax: 60);
        $veiculo->posicoes()->createMany([
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 50, 'registrado_em' => '2026-06-01 09:00:00'],
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 80, 'registrado_em' => '2026-06-01 09:01:00'],
            ['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 70, 'registrado_em' => '2026-06-01 09:02:00'],
        ]);

        $ev = app(RelatorioMonitoraService::class)->eventosVeiculo($veiculo, '2026-06-01', '2026-06-01');
        $this->assertCount(2, $ev['excessos']); // 80 e 70 km/h
        $this->assertSame(80.0, $ev['excessos'][0]['velocidade']);
    }

    public function test_sem_tipo_nao_apura_excesso(): void
    {
        $veiculo = $this->veiculo(); // sem tipo → sem velocidade-máxima
        $veiculo->posicoes()->create(['latitude' => -25.0, 'longitude' => -51.0, 'velocidade' => 120, 'registrado_em' => '2026-06-01 09:00:00']);

        $ev = app(RelatorioMonitoraService::class)->eventosVeiculo($veiculo, '2026-06-01', '2026-06-01');
        $this->assertCount(0, $ev['excessos']);
    }
}
