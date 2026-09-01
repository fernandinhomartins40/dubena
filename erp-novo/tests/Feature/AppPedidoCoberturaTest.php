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
 * A escolha da loja no app é UX; o pedido só nasce se a empresa ATENDE o ponto
 * de entrega (cerca/raio — mesma regra da descoberta pública).
 *
 * **F6-05 mudou o alcance da regra.** A F7 abria exceção para builds
 * white-label: empresa fora do marketplace "mantinha o comportamento atual", ou
 * seja, aceitava qualquer endereço. O plano SaaS revoga isso — a flag de canal
 * decide se a revenda aparece na descoberta pública, não até onde ela entrega.
 *
 * A fronteira passou a ser a configuração: quem declarou área respeita a área;
 * quem não declarou não é restringido.
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

    /**
     * F6-05 revogou a exceção do white-label: quem DECLAROU área respeita a
     * área, marketplace ou não.
     *
     * O teste antigo afirmava o contrário — que uma revenda fora do marketplace
     * "mantém o fluxo atual" e aceita qualquer endereço. Era a decisão da F7, e
     * o plano SaaS a supera com razão: a flag de canal decide se a revenda
     * **aparece na descoberta pública**, não até onde ela **entrega**.
     *
     * O efeito prático do comportamento antigo, com esta mesma empresa: raio de
     * 5 km declarado, e um pedido de Curitiba — 250 km — era aceito.
     */
    public function test_white_label_com_area_declarada_respeita_a_area(): void
    {
        [$user, $produto] = $this->cenario(marketplace: false);

        $this->actingAs($user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'lat' => -25.4284, 'lng' => -49.2733,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['endereco']);
    }

    /**
     * Quem NÃO declarou área continua sem restrição — é o que impede a correção
     * de derrubar a operação de quem ainda não configurou cerca nem raio.
     */
    public function test_sem_area_declarada_o_pedido_distante_passa(): void
    {
        [$user, $produto] = $this->cenario(marketplace: false);

        Empresa::query()->where('id', $user->empresa_id)->update([
            'raio_entrega_km' => null,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'lat' => -25.4284, 'lng' => -49.2733,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertCreated();
    }
}
