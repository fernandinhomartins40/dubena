<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Satelite\ComodatoAvaliacao;
use App\Models\Satelite\Convenio;
use App\Models\Satelite\ConvenioFechamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R3-F-06 — subrecurso de um pai que o usuário não enxerga responde 404, como o
 * resto da API. Antes, a listagem filtrava os filhos pelo id do pai sem carregá-lo
 * e devolvia 200 com lista vazia: não vazava dado, mas confirmava a rota e
 * divergia do contrato.
 */
class SubrecursoPaiInvisivelTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresaA;

    private Empresa $empresaB;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresaA = Empresa::factory()->create();
        $this->empresaB = Empresa::factory()->create();
        $this->userB = User::factory()->create([
            'empresa_id' => $this->empresaB->id,
            'grupo_id' => $this->empresaB->grupo_id,
        ]);
    }

    private function clienteDeA(): Cliente
    {
        return Cliente::factory()->create([
            'empresa_id' => $this->empresaA->id,
            'grupo_id' => $this->empresaA->grupo_id,
        ]);
    }

    public function test_vigilancia_de_cliente_alheio_e_404(): void
    {
        $cliente = $this->clienteDeA();
        ComodatoAvaliacao::query()->create([
            'empresa_id' => $this->empresaA->id,
            'grupo_id' => $this->empresaA->grupo_id,
            'cliente_id' => $cliente->id,
            'referencia' => '2026-10-01',
            'em_posse' => 2,
            'dias_janela' => 180,
            'classificacao' => 'PARADO',
        ]);

        $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/admin/comodatos/vigilancia/{$cliente->id}")
            ->assertNotFound();
    }

    public function test_fechamentos_de_convenio_alheio_e_404(): void
    {
        $cliente = $this->clienteDeA();
        $convenio = Convenio::query()->create([
            'empresa_id' => $this->empresaA->id,
            'grupo_id' => $this->empresaA->grupo_id,
            'cliente_id' => $cliente->id,
            'descricao' => 'Convênio A',
        ]);
        ConvenioFechamento::query()->create([
            'empresa_id' => $this->empresaA->id,
            'convenio_id' => $convenio->id,
            'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-31',
        ]);

        $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/admin/convenios/{$convenio->id}/fechamentos")
            ->assertNotFound();
    }

    public function test_fechamentos_do_proprio_convenio_seguem_200(): void
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresaB->id,
            'grupo_id' => $this->empresaB->grupo_id,
        ]);
        $convenio = Convenio::query()->create([
            'empresa_id' => $this->empresaB->id,
            'grupo_id' => $this->empresaB->grupo_id,
            'cliente_id' => $cliente->id,
            'descricao' => 'Convênio B',
        ]);

        $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/admin/convenios/{$convenio->id}/fechamentos")
            ->assertOk();
    }

    public function test_cidades_de_empresa_da_rede_sem_concessao_e_404(): void
    {
        // Mesma rede, mas o usuário não tem a empresa 2 entre as suas (o "emp2" da matriz).
        $outraDaRede = Empresa::factory()->create(['grupo_id' => $this->empresaB->grupo_id]);

        $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/admin/empresas/{$outraDaRede->id}/cidades")
            ->assertNotFound();

        $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/admin/empresas/{$this->empresaB->id}/cidades")
            ->assertOk();
    }
}
