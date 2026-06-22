<?php

namespace Tests\Domain;

use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Domain\Monitora\Drivers\FakeSgcasaDriver;
use App\Domain\Monitora\MonitoraService;
use App\Domain\Monitora\MonitoraSyncService;
use App\Models\Empresa;
use App\Models\Monitora\Cerca;
use App\Models\Monitora\Posicao;
use App\Models\Monitora\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N11 — Monitora: ingestão atualiza última posição, geofencing, sync via gate.
 */
class MonitoraServiceTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
    }

    private function veiculo(?string $imei = null): Veiculo
    {
        return Veiculo::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'placa' => 'ABC'.random_int(1000, 9999), 'imei' => $imei, 'ativo' => true,
        ]);
    }

    public function test_ingestao_grava_historico_e_atualiza_ultima_posicao(): void
    {
        $svc = app(MonitoraService::class);
        $veiculo = $this->veiculo();

        $svc->registrarPosicao($veiculo, ['latitude' => -23.55, 'longitude' => -46.63, 'velocidade' => 40, 'ignicao' => true]);
        $svc->registrarPosicao($veiculo, ['latitude' => -23.56, 'longitude' => -46.64, 'velocidade' => 0, 'ignicao' => false]);

        // Histórico mantém as duas; última posição reflete a mais recente.
        $this->assertEquals(2, Posicao::query()->where('veiculo_id', $veiculo->id)->count());
        $ultima = $veiculo->ultimaPosicao()->first();
        $this->assertEqualsWithDelta(-23.56, (float) $ultima->latitude, 0.0001);
        $this->assertFalse($ultima->ignicao);
    }

    public function test_geofencing_dentro_e_fora_da_cerca(): void
    {
        $svc = app(MonitoraService::class);
        $cerca = Cerca::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'Garagem',
            'centro_lat' => -23.5505, 'centro_lng' => -46.6333, 'raio_metros' => 200, 'ativo' => true,
        ]);

        // ~30m de distância → dentro.
        $this->assertTrue($svc->dentroDaCerca($cerca, -23.5507, -46.6334));
        // outro estado → fora.
        $this->assertFalse($svc->dentroDaCerca($cerca, -22.9068, -43.1729));
    }

    public function test_cercas_no_ponto_lista_apenas_as_que_contem(): void
    {
        $svc = app(MonitoraService::class);
        Cerca::create(['empresa_id' => $this->empresa->id, 'descricao' => 'Perto', 'centro_lat' => -23.55, 'centro_lng' => -46.63, 'raio_metros' => 500, 'ativo' => true]);
        Cerca::create(['empresa_id' => $this->empresa->id, 'descricao' => 'Longe', 'centro_lat' => -10, 'centro_lng' => -50, 'raio_metros' => 500, 'ativo' => true]);

        $cercas = $svc->cercasNoPonto($this->empresa->id, -23.551, -46.631);
        $this->assertCount(1, $cercas);
        $this->assertEquals('Perto', $cercas->first()->descricao);
    }

    public function test_sync_sgcasa_ingere_posicoes_via_gate(): void
    {
        $veiculo = $this->veiculo('IMEI-123');

        // Stub do driver com posições fixas.
        $fake = new FakeSgcasaDriver();
        $fake->posicoes = [
            ['imei' => 'IMEI-123', 'latitude' => -23.55, 'longitude' => -46.63, 'velocidade' => 25, 'ignicao' => true, 'registrado_em' => now()->toDateTimeString()],
            ['imei' => 'OUTRO', 'latitude' => 0, 'longitude' => 0, 'velocidade' => 0, 'ignicao' => false, 'registrado_em' => now()->toDateTimeString()],
        ];
        $this->app->instance(SgcasaDriver::class, $fake);

        $n = app(MonitoraSyncService::class)->sincronizar($this->empresa->id);

        $this->assertEquals(1, $n); // só o veículo com IMEI conhecido
        $this->assertNotNull($veiculo->ultimaPosicao()->first());
    }
}
