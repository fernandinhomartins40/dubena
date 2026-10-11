<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O CEP do cadastro de cliente não preenchia nada; a base já sabia a resposta.
 */
class EnderecoPorCepTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
    }

    /** @return array{Cidade, Bairro} */
    private function lugar(int $grupoId, string $cidade = 'Guarapuava'): array
    {
        // A UF tem FK para `estados`: a factory garante o estado.
        $c = Cidade::factory()->create(['grupo_id' => $grupoId, 'descricao' => $cidade]);
        $b = Bairro::factory()->create(['grupo_id' => $grupoId, 'cidade_id' => $c->id, 'descricao' => 'Centro']);

        return [$c, $b];
    }

    public function test_cep_da_rua_preenche_cidade_bairro_e_rua(): void
    {
        [$c, $b] = $this->lugar($this->empresa->grupo_id);
        $rua = Rua::query()->create(['grupo_id' => $this->empresa->grupo_id, 'cidade_id' => $c->id, 'bairro_id' => $b->id, 'descricao' => 'Rua XV', 'cep' => '85010-100', 'ativo' => true]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/geo/cep/85010100')->assertOk()
            ->assertJsonPath('data.cidade.id', $c->id)
            ->assertJsonPath('data.cidade.uf', 'SP')
            ->assertJsonPath('data.bairro.id', $b->id)
            ->assertJsonPath('data.rua.id', $rua->id);
    }

    /** Sem rua com o CEP, vale o endereço mais comum entre os clientes. */
    public function test_sem_rua_usa_o_endereco_mais_comum_dos_clientes(): void
    {
        [$c, $b] = $this->lugar($this->empresa->grupo_id);
        Cliente::factory()->count(3)->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'cep' => '85015000', 'cidade_id' => $c->id, 'bairro_id' => $b->id]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/geo/cep/85015-000')->assertOk()
            ->assertJsonPath('data.cidade.id', $c->id)
            ->assertJsonPath('data.bairro.id', $b->id)
            ->assertJsonPath('data.fonte', 'clientes');
    }

    public function test_cep_invalido_ou_desconhecido_devolve_vazio(): void
    {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/geo/cep/123')->assertOk()->assertJsonPath('data', null);
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/geo/cep/99999999')->assertOk()->assertJsonPath('data', null);
    }

    /** O endereço de outra rede nunca aparece. */
    public function test_nao_devolve_endereco_de_outra_rede(): void
    {
        $outra = Empresa::factory()->create();
        [$c, $b] = $this->lugar($outra->grupo_id, 'Cidade Alheia');
        Rua::query()->withoutGlobalScopes()->create(['grupo_id' => $outra->grupo_id, 'cidade_id' => $c->id, 'bairro_id' => $b->id, 'descricao' => 'Rua Alheia', 'cep' => '01001000', 'ativo' => true]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/geo/cep/01001000')->assertOk()->assertJsonPath('data', null);
    }
}
