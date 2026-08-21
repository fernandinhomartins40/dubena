<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\User;
use App\Models\Venda\AlcadaDesconto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 — cadastro das alçadas.
 *
 * Sem CRUD a verificação fail-closed deixa todo mundo com teto zero: o sistema
 * fica MAIS travado do que antes da fase. A tabela sem por onde cadastrar é uma
 * funcionalidade que não existe.
 */
class AlcadaCrudTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->gestor = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'support' => true,   // bypass de RBAC: aqui o foco é o CRUD
        ]);
        $this->actingAs($this->gestor, 'sanctum');
    }

    public function test_cadastra_lista_e_remove(): void
    {
        $criada = $this->postJson('/api/admin/alcadas', [
            'percentual_max' => 5,
            'valor_max' => 30,
        ])->assertCreated();

        $id = $criada->json('data.id');

        $this->getJson('/api/admin/alcadas')
            ->assertOk()
            ->assertJsonPath('data.0.percentual_max', 5)
            ->assertJsonPath('data.0.valor_max', 30);

        $this->deleteJson("/api/admin/alcadas/{$id}")->assertOk();
        $this->assertDatabaseCount('alcada_descontos', 0);
    }

    public function test_defaults_sao_os_seguros(): void
    {
        $this->postJson('/api/admin/alcadas', ['percentual_max' => 3])->assertCreated();

        $regra = AlcadaDesconto::first();

        // 'tabela': o percentual corre sobre o preço de LISTA. Se corresse sobre o
        // praticado, um cliente com preço especial acumularia desconto em cascata.
        $this->assertSame('tabela', $regra->base_calculo);
        $this->assertTrue((bool) $regra->permite_solicitar);
        $this->assertTrue((bool) $regra->ativo);
    }

    public function test_edicao_altera_o_teto(): void
    {
        $id = $this->postJson('/api/admin/alcadas', ['percentual_max' => 5])->json('data.id');

        $this->putJson("/api/admin/alcadas/{$id}", ['percentual_max' => 12])->assertOk();

        $this->assertSame('12.0000', AlcadaDesconto::find($id)->percentual_max);
    }

    public function test_percentual_acima_de_cem_e_recusado(): void
    {
        // Desconto de 150% não é política comercial, é erro de digitação.
        $this->postJson('/api/admin/alcadas', ['percentual_max' => 150])->assertStatus(422);
    }

    public function test_regra_de_outra_empresa_nao_e_alcancavel(): void
    {
        $outra = Empresa::factory()->create();
        $alheia = AlcadaDesconto::create([
            'empresa_id' => $outra->id, 'percentual_max' => 50, 'ativo' => true,
        ]);

        $this->putJson("/api/admin/alcadas/{$alheia->id}", ['percentual_max' => 1])
            ->assertStatus(404);
    }

    public function test_especificidade_orienta_qual_regra_vence(): void
    {
        $produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);

        $this->postJson('/api/admin/alcadas', ['percentual_max' => 2])->assertCreated();
        $this->postJson('/api/admin/alcadas', [
            'percentual_max' => 10, 'produto_id' => $produto->id,
        ])->assertCreated();

        $lista = collect($this->getJson('/api/admin/alcadas')->json('data'));

        // A regra do produto é mais específica que a geral — o gestor precisa ver
        // isso na lista para entender por que uma vence a outra.
        $geral = $lista->firstWhere('produto_id', null);
        $doProduto = $lista->firstWhere('produto_id', $produto->id);

        $this->assertGreaterThan($geral['especificidade'], $doProduto['especificidade']);
    }
}
