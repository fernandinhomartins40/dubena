<?php

namespace Tests\Domain;

use App\Domain\Logistica\JornadaService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Monitora\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * L0 — JornadaService: abre/fecha turno, 1 ativa por entregador, veículo validado
 * por empresa e hodômetro não-retroativo ao encerrar.
 */
class JornadaServiceTest extends TestCase
{
    use RefreshDatabase;

    private JornadaService $svc;

    private Empresa $empresa;

    private User $entregador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(JornadaService::class);
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->entregador = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
    }

    private function veiculo(array $attr = []): Veiculo
    {
        return Veiculo::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'placa' => 'ABC'.fake()->unique()->numberBetween(1000, 9999),
            'km_atual' => 1000,
            'ativo' => true,
        ], $attr));
    }

    public function test_iniciar_cria_jornada_ativa_com_veiculo(): void
    {
        $v = $this->veiculo();

        $j = $this->svc->iniciar($this->entregador, $v->id, ['pneus' => 'ok'], 1200);

        $this->assertSame('ativa', $j->status);
        $this->assertSame($v->id, $j->veiculo_id);
        $this->assertSame(1200, $j->km_inicial);
        $this->assertSame(['pneus' => 'ok'], $j->checklist);
        $this->assertNotNull($this->svc->jornadaAtiva($this->entregador->id));
    }

    public function test_km_inicial_cai_para_o_hodometro_do_veiculo_quando_omitido(): void
    {
        $v = $this->veiculo(['km_atual' => 5000]);

        $j = $this->svc->iniciar($this->entregador, $v->id);

        $this->assertSame(5000, $j->km_inicial);
    }

    public function test_nao_permite_duas_jornadas_ativas(): void
    {
        $this->svc->iniciar($this->entregador, $this->veiculo()->id);

        $this->expectException(ValidationException::class);
        $this->svc->iniciar($this->entregador, $this->veiculo()->id);
    }

    public function test_veiculo_de_outra_empresa_e_rejeitado(): void
    {
        $outra = Empresa::factory()->create();
        $veiculoAlheio = Veiculo::create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'placa' => 'XXX0000', 'km_atual' => 1, 'ativo' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->svc->iniciar($this->entregador, $veiculoAlheio->id);
    }

    public function test_veiculo_inativo_e_rejeitado(): void
    {
        $v = $this->veiculo(['ativo' => false]);

        $this->expectException(ValidationException::class);
        $this->svc->iniciar($this->entregador, $v->id);
    }

    public function test_encerrar_fecha_e_atualiza_hodometro_sem_regredir(): void
    {
        $v = $this->veiculo(['km_atual' => 1000]);
        $j = $this->svc->iniciar($this->entregador, $v->id);

        $encerrada = $this->svc->encerrar($j, 1350);

        $this->assertSame('encerrada', $encerrada->status);
        $this->assertNotNull($encerrada->encerrada_em);
        $this->assertSame(1350, (int) $v->refresh()->km_atual);

        // Após encerrar, pode iniciar outra.
        $this->assertNull($this->svc->jornadaAtiva($this->entregador->id));
    }

    public function test_encerrar_nao_regride_hodometro(): void
    {
        $v = $this->veiculo(['km_atual' => 9000]);
        $j = $this->svc->iniciar($this->entregador, $v->id);

        $this->svc->encerrar($j, 100); // km menor que o atual → não regride

        $this->assertSame(9000, (int) $v->refresh()->km_atual);
    }

    public function test_exigir_jornada_ativa_lanca_sem_jornada(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->exigirJornadaAtiva($this->entregador->id);
    }
}
