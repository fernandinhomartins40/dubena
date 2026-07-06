<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 do PLANO_SEGURANCA_MULTITENANT_APPS — cobertura revalidada SERVER-SIDE.
 *
 * A escolha da loja no app marketplace é UX; o pedido só nasce se a empresa
 * ATENDE o ponto de entrega (cerca/raio — mesma regra da descoberta pública).
 * Builds white-label (empresa fora do marketplace) mantêm o comportamento atual.
 */
class AppPedidoCoberturaTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(bool $marketplace): array
    {
        // Matriz em Guarapuava, raio de 5 km.
        $empresa = Empresa::factory()->create([
            'app_marketplace_ativo' => $marketplace,
            'latitude' => -25.3862, 'longitude' => -51.4868, 'raio_entrega_km' => 5,
        ]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'user_id' => $user->id,
        ]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $empresa->grupo_id]);
        Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100, 'ativo' => true,
        ]);

        return [$user, $produto];
    }

    public function test_marketplace_rejeita_pedido_fora_da_area(): void
    {
        [$user, $produto] = $this->cenario(marketplace: true);

        // Curitiba — ~250 km da matriz, fora do raio de 5 km.
        $this->actingAs($user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'lat' => -25.4284, 'lng' => -49.2733,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['endereco']);
    }

    public function test_marketplace_aceita_pedido_dentro_da_area(): void
    {
        [$user, $produto] = $this->cenario(marketplace: true);

        // ~200 m da matriz.
        $this->actingAs($user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'lat' => -25.3870, 'lng' => -51.4880,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertCreated();
    }

    public function test_white_label_sem_marketplace_nao_e_restringido(): void
    {
        [$user, $produto] = $this->cenario(marketplace: false);

        // Mesmo ponto longe: empresa fora do marketplace mantém o fluxo atual.
        $this->actingAs($user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'lat' => -25.4284, 'lng' => -49.2733,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertCreated();
    }
}
