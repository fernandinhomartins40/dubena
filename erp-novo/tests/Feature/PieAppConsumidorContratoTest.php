<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteEndereco;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoAvaliacao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * PIE rodada 3 — Agente E: contrato do app do consumidor × API.
 *
 * O payload e os campos lidos pelo app vêm de `app-gas-em-casa/contrato/app-v1.json`,
 * o MESMO arquivo que o vitest do app compara com o que o carrinho monta
 * (`src/helpers/pedidoApp.test.ts`). Se um lado mudar sem o outro, um dos dois
 * testes quebra — é o elo que faltou no P2-B-01 (422 em todo pedido desde junho).
 */
class PieAppConsumidorContratoTest extends TestCase
{
    use RefreshDatabase;

    private array $contrato;

    protected function setUp(): void
    {
        parent::setUp();
        $arquivo = base_path('../app-gas-em-casa/contrato/app-v1.json');
        $this->assertFileExists($arquivo, 'O contrato do app precisa existir: é ele que liga os dois lados.');
        $this->contrato = json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array{0: User, 1: Produto, 2: Cliente, 3: Empresa, 4: CondicaoPagamento, 5: ClienteEndereco} */
    private function cenario(bool $pix = true): array
    {
        $empresa = Empresa::factory()->create(['latitude' => -25.3862, 'longitude' => -51.4868]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'user_id' => $user->id,
        ]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $empresa->grupo_id]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $empresa->grupo_id]);
        Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100, 'preco_gasdopovo' => 50, 'ativo' => true,
        ]);
        $condicao = CondicaoPagamento::create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => $pix ? 'PIX pelo app' : 'Dinheiro',
            'num_parcelas' => 1, 'intervalo_dias' => 0, 'dias_primeira' => 0,
            'a_vista' => true, 'ativo' => true, 'pix_online' => $pix,
        ]);
        $endereco = ClienteEndereco::create([
            'empresa_id' => $empresa->id, 'cliente_id' => $cliente->id, 'endereco' => 'Rua Teste',
            'latitude' => -25.3870, 'longitude' => -51.4880, 'favorito' => true,
        ]);

        return [$user, $produto, $cliente, $empresa, $condicao, $endereco];
    }

    /** O corpo LITERAL do contrato, com os ids do cenário. */
    private function payloadDoContrato(int $enderecoId, int $condicaoId, int $produtoId, array $troca = []): array
    {
        $json = json_encode($this->contrato['requisicoes']['POST app/v1/pedidos']);
        $json = str_replace(['"<endereco_id>"', '"<condicao_id>"', '"<produto_id>"'], [$enderecoId, $condicaoId, $produtoId], $json);

        return array_replace(json_decode($json, true), $troca);
    }

    private function assertCampos(array $dados, array $campos): void
    {
        foreach ($campos as $campo) {
            $this->assertTrue(Arr::has($dados, $campo), "A API não devolve `{$campo}`, que o app lê.");
        }
    }

    public function test_payload_do_contrato_cria_pedido_e_respostas_tem_os_campos_que_o_app_le(): void
    {
        [$user, $produto, , , $condicao, $endereco] = $this->cenario();
        $this->actingAs($user, 'sanctum');

        $init = $this->getJson('/api/app/v1/init')->assertOk()->json('data');
        $this->assertCampos($init['condicoes'][0], $this->contrato['respostas']['GET app/v1/init']['condicoes']);
        $this->assertTrue($init['condicoes'][0]['pix_online']);

        $id = $this->postJson('/api/app/v1/pedidos', $this->payloadDoContrato($endereco->id, $condicao->id, $produto->id))
            ->assertCreated()->json('data.id');

        $hist = $this->getJson('/api/app/v1/pedidos')->assertOk()->json('data.0');
        $this->assertCampos($hist, $this->contrato['respostas']['GET app/v1/pedidos']);
        $this->assertFalse($hist['avaliado']);

        $pedido = $this->getJson("/api/app/v1/pedidos/{$id}")->assertOk()->json('data');
        $this->assertCampos($pedido, $this->contrato['respostas']['GET app/v1/pedidos/{id}']);
        $this->assertTrue($pedido['pagamento']['pix']);
        $this->assertFalse($pedido['pagamento']['pago']);
    }

    /** R2-13: a mesma chave reenviada devolve o MESMO pedido — não um 422 "já tem pedido". */
    public function test_r2_13_reenvio_com_a_mesma_idempotency_key_devolve_o_mesmo_pedido(): void
    {
        [$user, $produto, $cliente, , $condicao, $endereco] = $this->cenario();
        $payload = $this->payloadDoContrato($endereco->id, $condicao->id, $produto->id);
        $this->actingAs($user, 'sanctum');

        $a = $this->postJson('/api/app/v1/pedidos', $payload, ['Idempotency-Key' => 'app-teste-1'])->assertCreated()->json('data.id');
        $b = $this->postJson('/api/app/v1/pedidos', $payload, ['Idempotency-Key' => 'app-teste-1'])->assertCreated()->json('data.id');

        $this->assertSame($a, $b);
        $this->assertSame(1, Pedido::query()->where('cliente_id', $cliente->id)->count());
    }

    /** P2-B-04: o PIX abandonado não prende mais — o cliente cancela e pede de novo. */
    public function test_p2_b_04_cliente_cancela_pedido_com_pix_abandonado_e_pede_de_novo(): void
    {
        [$user, $produto, , , $condicao, $endereco] = $this->cenario();
        $payload = $this->payloadDoContrato($endereco->id, $condicao->id, $produto->id);
        $this->actingAs($user, 'sanctum');

        $id = $this->postJson('/api/app/v1/pedidos', $payload)->assertCreated()->json('data.id');
        $this->postJson("/api/app/v1/pedidos/{$id}/pix")->assertCreated();
        $this->postJson('/api/app/v1/pedidos', $payload)->assertStatus(422)->assertJsonValidationErrors(['pedido']);

        $this->postJson("/api/app/v1/pedidos/{$id}/cancelar")->assertOk();
        $this->assertSame('CANCELADO', $this->getJson("/api/app/v1/pedidos/{$id}")->json('data.efeito'));
        $this->postJson('/api/app/v1/pedidos', $payload)->assertCreated();
    }

    public function test_p2_b_15_historico_marca_o_pedido_avaliado(): void
    {
        [$user, $produto, , , $condicao, $endereco] = $this->cenario(false);
        $this->actingAs($user, 'sanctum');
        $id = $this->postJson('/api/app/v1/pedidos', $this->payloadDoContrato($endereco->id, $condicao->id, $produto->id))->json('data.id');
        PedidoAvaliacao::create(['empresa_id' => $user->empresa_id, 'pedido_id' => $id, 'rating' => 5, 'ignorado' => false]);

        $this->assertTrue($this->getJson('/api/app/v1/pedidos')->json('data.0.avaliado'));
    }

    public function test_situacao_papel_em_rota_vem_da_marca_declarada(): void
    {
        [$user, $produto, , $empresa, $condicao, $endereco] = $this->cenario(false);
        $this->actingAs($user, 'sanctum');
        $id = $this->postJson('/api/app/v1/pedidos', $this->payloadDoContrato($endereco->id, $condicao->id, $produto->id))->json('data.id');
        $rota = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Despachado', 'papel' => 'EM_ROTA',
        ]);
        Pedido::query()->whereKey($id)->update(['pedidosituacao_id' => $rota->id]);

        $this->assertSame('EM_ROTA', $this->getJson("/api/app/v1/pedidos/{$id}")->json('data.situacao_papel'));
    }

    /** Achado da frente A (PIE-35 pelo app): o cadastro do app gravava CPF sem validar. */
    public function test_cadastro_do_app_recusa_cpf_invalido_e_grava_so_digitos(): void
    {
        $empresa = Empresa::factory()->create();
        $base = ['empresa_id' => $empresa->id, 'nome' => 'Maria Exemplo'];

        foreach (['123', '111.111.111-11', '529.982.247-24'] as $i => $ruim) {
            $this->postJson('/api/app/v1/cliente/cadastro', $base + ['firebase_id_token' => "fake:+554299990010{$i}", 'cpf' => $ruim])
                ->assertStatus(422)->assertJsonValidationErrors('cpf');
        }
        $this->assertSame(0, Cliente::query()->where('nome', 'Maria Exemplo')->count());

        $this->postJson('/api/app/v1/cliente/cadastro', $base + ['firebase_id_token' => 'fake:+5542999900200', 'cpf' => '529.982.247-25'])
            ->assertCreated();
        $this->assertSame('52998224725', Cliente::query()->where('nome', 'Maria Exemplo')->value('cpf'));
    }

    /** R3-G-04: `clientes.numero` é varchar(10); o endereço inline aceitava 20 e virava 500 no Postgres. */
    public function test_endereco_inline_com_numero_acima_da_coluna_recebe_422(): void
    {
        [$user] = $this->cenario(false);
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/app/v1/perfil/endereco', ['endereco' => 'Rua das Flores', 'numero' => '1234 fundos B'])
            ->assertStatus(422)->assertJsonValidationErrors('numero');
        $this->putJson('/api/app/v1/perfil/endereco', ['endereco' => 'Rua das Flores', 'numero' => '1234-B fds'])
            ->assertOk();
    }

    /** R3-F-05: pagar o pedido de OUTRO cliente é 404 — não 422 pela validação do corpo. */
    public function test_r3_f_05_pagar_pedido_alheio_e_404_antes_de_validar_o_corpo(): void
    {
        [$user, $produto, , $empresa, $condicao, $endereco] = $this->cenario(false);
        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/app/v1/pedidos', $this->payloadDoContrato($endereco->id, $condicao->id, $produto->id))
            ->json('data.id');

        $outro = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'user_id' => $outro->id]);

        $this->actingAs($outro, 'sanctum')->postJson("/api/app/v1/pedidos/{$id}/pagar", [])->assertNotFound();
    }

    /** R3-E-01: o preço Gás do Povo não sai só porque o payload pediu. */
    public function test_r3_e_01_gas_do_povo_sem_programa_ou_sem_beneficiario_e_recusado(): void
    {
        [$user, $produto, $cliente, $empresa, $condicao, $endereco] = $this->cenario(false);
        $this->actingAs($user, 'sanctum');
        $payload = $this->payloadDoContrato($endereco->id, $condicao->id, $produto->id, ['gasdopovo' => true]);

        // Programa desligado.
        $this->postJson('/api/app/v1/pedidos', $payload)->assertStatus(422)->assertJsonValidationErrors(['gasdopovo']);

        // Programa ligado, cliente NÃO beneficiário.
        EmpresaConfig::create(['empresa_id' => $empresa->id, 'dados' => ['app' => ['gaspovo_ativo' => true]]]);
        $this->postJson('/api/app/v1/pedidos', $payload)->assertStatus(422)->assertJsonValidationErrors(['gasdopovo']);

        // Programa ligado e beneficiário marcado pela revenda: aceito, com o preço do programa.
        $cliente->forceFill(['gasdopovo' => true])->save();
        $id = $this->postJson('/api/app/v1/pedidos', $payload)->assertCreated()->json('data.id');
        $this->assertEquals(50.0, (float) Pedido::query()->find($id)->valor_venda);
    }
}
