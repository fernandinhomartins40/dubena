<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * F7 — idempotência: base da operação offline.
 *
 * O app em rota enfileira quando perde sinal e reenvia depois. Se a primeira
 * tentativa chegou mas a resposta se perdeu, o reenvio não pode criar um segundo
 * pedido. Estes testes fixam isso.
 */
class IdempotenciaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $vendedor;

    private Cliente $cliente;

    private Produto $produto;

    private Setor $setor;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->vendedor = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'email' => 'campo@teste.com', 'password' => Hash::make('segredo123'),
        ]);
        Colaborador::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->vendedor->id, 'vinculo' => 'franqueado',
        ]);
        $this->setor = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'preco_venda' => 100,
        ]);
        $this->cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 1]);

        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10);

        $this->token = $this->postJson('/api/app/v1/login', [
            'email' => 'campo@teste.com', 'password' => 'segredo123', 'device_id' => 'dev-idem',
        ])->json('token');
    }

    /** @param array<string,string> $extra */
    private function solicitar(array $extra = [])
    {
        return $this->withHeaders(array_merge(['Authorization' => 'Bearer '.$this->token], $extra))
            ->postJson('/api/app/v1/entregador/solicitacoes', [
                'cliente_id' => $this->cliente->id,
                'setor_id' => $this->setor->id,
                'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
                'desconto_solicitado' => 10,
            ]);
    }

    public function test_sem_a_chave_cada_envio_cria_um_registro(): void
    {
        // Comportamento de sempre: o cabeçalho é opt-in, e sem ele nada muda
        // para quem já usava a rota.
        $this->solicitar()->assertCreated();
        $this->solicitar()->assertCreated();

        $this->assertDatabaseCount('pedido_solicitacoes', 2);
    }

    public function test_mesma_chave_nao_duplica_e_devolve_a_primeira_resposta(): void
    {
        $primeira = $this->solicitar(['Idempotency-Key' => 'uuid-rota-1'])->assertCreated();
        $segunda = $this->solicitar(['Idempotency-Key' => 'uuid-rota-1'])->assertCreated();

        // Uma só solicitação, e o app recebe o mesmo id — pode seguir o fluxo
        // como se a primeira resposta não tivesse se perdido.
        $this->assertDatabaseCount('pedido_solicitacoes', 1);
        $this->assertSame($primeira->json('data.id'), $segunda->json('data.id'));
    }

    public function test_chave_reusada_com_outro_conteudo_e_recusada(): void
    {
        $this->solicitar(['Idempotency-Key' => 'uuid-rota-2'])->assertCreated();

        // Mesmo uuid, payload diferente: é bug do cliente. Devolver a resposta
        // antiga esconderia a divergência.
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'Idempotency-Key' => 'uuid-rota-2'])
            ->postJson('/api/app/v1/entregador/solicitacoes', [
                'cliente_id' => $this->cliente->id,
                'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 99]],
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('pedido_solicitacoes', 1);
    }

    public function test_chaves_diferentes_criam_registros_diferentes(): void
    {
        $this->solicitar(['Idempotency-Key' => 'uuid-a'])->assertCreated();
        $this->solicitar(['Idempotency-Key' => 'uuid-b'])->assertCreated();

        $this->assertDatabaseCount('pedido_solicitacoes', 2);
    }

    public function test_falha_solta_a_chave_para_nova_tentativa(): void
    {
        // Payload inválido → 422. A chave não pode ficar presa: um erro
        // momentâneo deixaria o app travado nele para sempre.
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'Idempotency-Key' => 'uuid-retry'])
            ->postJson('/api/app/v1/entregador/solicitacoes', [])
            ->assertStatus(422);

        $this->assertDatabaseMissing('requisicoes_idempotentes', ['chave' => 'uuid-retry']);

        // Mesma chave agora funciona.
        $this->solicitar(['Idempotency-Key' => 'uuid-retry'])->assertCreated();
    }

    public function test_a_chave_e_por_empresa(): void
    {
        $this->solicitar(['Idempotency-Key' => 'uuid-compartilhado'])->assertCreated();

        // Outra empresa usando a mesma string de chave não colide.
        $outra = Empresa::factory()->create();
        $outroUser = User::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'email' => 'outro@teste.com', 'password' => Hash::make('segredo123'),
        ]);
        Colaborador::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'user_id' => $outroUser->id, 'vinculo' => 'franqueado',
        ]);
        $clienteOutra = Cliente::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);
        $produtoOutra = Produto::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id, 'preco_venda' => 50,
        ]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $outra->grupo_id]);

        // Token direto, sem passar pela rota de login: o request de login herdaria
        // o usuário já autenticado neste teste e o token sairia da empresa errada
        // — foi o que fez a primeira versão medir outra coisa.
        $tokenOutra = $outroUser->createToken('app-outra', ['role:entregador'])->plainTextToken;

        // Imediatamente antes do request: o usuário autenticado persiste entre
        // requests do mesmo teste, e sem limpar aqui o Bearer abaixo seria
        // ignorado em favor do vendedor da PRIMEIRA empresa — o teste passaria a
        // medir outra coisa (a colisão que ele deveria descartar).
        app('auth')->forgetGuards();

        $this->withHeaders(['Authorization' => 'Bearer '.$tokenOutra, 'Idempotency-Key' => 'uuid-compartilhado'])
            ->postJson('/api/app/v1/entregador/solicitacoes', [
                'cliente_id' => $clienteOutra->id,
                'itens' => [['produto_id' => $produtoOutra->id, 'quantidade' => 1]],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('pedido_solicitacoes', 2);
    }
}
