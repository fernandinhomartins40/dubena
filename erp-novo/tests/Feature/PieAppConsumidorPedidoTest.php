<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteEndereco;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIE rodada 2 — P2-B-01 e P2-B-02: o pedido do app do consumidor.
 *
 * Os testes antigos mandavam `cliente_id` ou `lat/lng`, e por isso nenhum
 * reproduzia o app: o carrinho manda só pagamento, cupom, gás do povo e itens
 * (`app-gas-em-casa/src/app/(auth)/carrinho.tsx`). Aqui o payload é o LITERAL
 * do carrinho — se alguém voltar a exigir campo que o app não manda, quebra.
 */
class PieAppConsumidorPedidoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Produto, 2: Cliente, 3: Empresa} */
    private function cenario(bool $comCoordNoCadastro, bool $comArea): array
    {
        $empresa = Empresa::factory()->create([
            'latitude' => -25.3862, 'longitude' => -51.4868,
            'raio_entrega_km' => $comArea ? 5 : null,
        ]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'user_id' => $user->id,
            'latitude' => $comCoordNoCadastro ? -25.3870 : null,
            'longitude' => $comCoordNoCadastro ? -51.4880 : null,
        ]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $empresa->grupo_id]);
        Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100, 'ativo' => true,
        ]);

        return [$user, $produto, $cliente, $empresa];
    }

    /** O que o carrinho manda hoje: sem cliente_id, sem coordenada. */
    private function payloadDoCarrinho(int $produtoId, array $extra = []): array
    {
        return [
            'condicaopagamento_id' => null,
            'codigo_cupom' => null,
            'gasdopovo' => false,
            'itens' => [['produto_id' => $produtoId, 'quantidade' => 1]],
        ] + $extra;
    }

    private function endereco(Empresa $empresa, Cliente $cliente, float $lat, float $lng): ClienteEndereco
    {
        return ClienteEndereco::create([
            'empresa_id' => $empresa->id, 'cliente_id' => $cliente->id, 'endereco' => 'Rua Teste',
            'latitude' => $lat, 'longitude' => $lng, 'favorito' => true,
        ]);
    }

    public function test_p2_b_01_payload_literal_do_carrinho_cria_o_pedido(): void
    {
        [$user, $produto, $cliente] = $this->cenario(true, false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/app/v1/pedidos', $this->payloadDoCarrinho($produto->id))
            ->assertCreated();

        $this->assertDatabaseHas('pedidos', ['cliente_id' => $cliente->id]);
    }

    public function test_p2_b_01_sem_cliente_vinculado_a_coordenada_continua_obrigatoria(): void
    {
        [, $produto, , $empresa] = $this->cenario(true, false);
        $semVinculo = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($semVinculo, 'sanctum')
            ->postJson('/api/app/v1/pedidos', $this->payloadDoCarrinho($produto->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_p2_b_02_endereco_escolhido_fora_da_area_e_recusado(): void
    {
        // Cliente novo do app: sem coordenada no cadastro — antes a cerca era pulada.
        [$user, $produto, $cliente, $empresa] = $this->cenario(false, true);
        $longe = $this->endereco($empresa, $cliente, -25.4284, -49.2733); // ~250 km

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/app/v1/pedidos', $this->payloadDoCarrinho($produto->id, ['endereco_id' => $longe->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endereco']);
    }

    public function test_p2_b_02_sem_endereco_id_vale_o_favorito_do_cliente(): void
    {
        [$user, $produto, $cliente, $empresa] = $this->cenario(false, true);
        $this->endereco($empresa, $cliente, -25.4284, -49.2733); // favorito, ~250 km

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/app/v1/pedidos', $this->payloadDoCarrinho($produto->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endereco']);
    }

    public function test_p2_b_02_endereco_escolhido_dentro_da_area_e_aceito(): void
    {
        [$user, $produto, $cliente, $empresa] = $this->cenario(false, true);
        $perto = $this->endereco($empresa, $cliente, -25.3870, -51.4880);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/app/v1/pedidos', $this->payloadDoCarrinho($produto->id, ['endereco_id' => $perto->id]))
            ->assertCreated();
    }

    public function test_p2_b_02_endereco_de_outro_cliente_e_recusado(): void
    {
        [$user, $produto, , $empresa] = $this->cenario(true, false);
        $outro = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $alheio = $this->endereco($empresa, $outro, -25.3870, -51.4880);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/app/v1/pedidos', $this->payloadDoCarrinho($produto->id, ['endereco_id' => $alheio->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endereco_id']);
    }
}
