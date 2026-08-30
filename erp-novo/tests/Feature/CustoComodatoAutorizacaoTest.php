<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustoComodatoAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_acrescimo_nao_serializa_custos_do_produto_relacionado(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'custo_medio' => 9876.5432,
            'custo_frete' => 8765.4321,
        ]);
        $comodato = Comodato::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'produto_id' => $produto->id,
            'sentido' => Comodato::CONCEDIDO,
            'quantidade' => 2,
            'quantidade_devolvida' => 0,
            'situacao' => 'ATIVO',
            'data_emprestimo' => now()->toDateString(),
        ]);

        $resposta = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/comodatos/{$comodato->id}/acrescentar", ['quantidade' => 1])
            ->assertOk();

        $json = json_encode($resposta->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('custo_medio', $json);
        $this->assertStringNotContainsString('custo_frete', $json);
        $this->assertStringNotContainsString('9876.5432', $json);
        $this->assertStringNotContainsString('8765.4321', $json);
        $this->assertArrayNotHasKey('custo_medio', $produto->toArray());
        $this->assertArrayNotHasKey('custo_frete', $produto->toArray());
    }
}
