<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FASE C10 — status agregado dos satélites (relatórios/monitoramento/integrações). */
class SateliteStatusTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true,
        ]);

        return [$user, $empresa];
    }

    public function test_relatorios_lista_catalogo(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/satelites/relatorios')
            ->assertOk()
            ->assertJsonStructure(['data' => [['chave', 'titulo', 'modulo']]]);
    }

    public function test_monitoramento_traz_contagens(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/satelites/monitoramento')
            ->assertOk()
            ->assertJsonStructure(['data' => ['veiculos', 'com_posicao', 'sem_posicao']]);
    }

    public function test_integracoes_traz_status_dos_gates(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/satelites/integracoes')
            ->assertOk()
            ->assertJsonStructure(['data' => ['fiscal' => ['driver', 'ativo'], 'convenios_ativos', 'vale_gas_ativos', 'comodatos_abertos']]);
    }
}
