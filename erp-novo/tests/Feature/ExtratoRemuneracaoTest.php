<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Venda\ExtratoRemuneracaoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorComissao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F5 — extrato de remuneração do campo.
 *
 * A conta já existia (`ComissaoService`, com percentual e repasse); o que
 * faltava era o franqueado poder ver o próprio ganho. Estes testes fixam os dois
 * modelos e a regra de que só venda concretizada conta.
 */
class ExtratoRemuneracaoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $franqueado;

    private Colaborador $colaborador;

    private Setor $setor;

    private Produto $produto;

    private Cliente $cliente;

    private PedidoSituacao $pendente;

    private PedidoSituacao $concluido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->franqueado = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->colaborador = Colaborador::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->franqueado->id, 'vinculo' => 'franqueado',
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
        $this->pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 1]);
        $this->concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 2]);

        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 1000, 10);
    }

    private function venda(bool $concretizar = true, int $qtd = 2): void
    {
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id,
            'setor_id' => $this->setor->id,
            'pedidosituacao_id' => $concretizar ? $this->concluido->id : $this->pendente->id,
        ], [[
            'produto_id' => $this->produto->id, 'quantidade' => $qtd, 'preco_unitario' => 100,
        ]]);

        $pedido->forceFill(['entregador_user_id' => $this->franqueado->id])->save();
    }

    private function extrato(): array
    {
        return app(ExtratoRemuneracaoService::class)->doColaborador(
            (int) $this->empresa->id,
            (int) $this->franqueado->id,
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );
    }

    public function test_sem_regra_cadastrada_o_extrato_vem_zerado(): void
    {
        $this->venda();

        $r = $this->extrato();

        // Zerado e não omitido: o franqueado precisa perceber que falta
        // configuração, não achar que não vendeu.
        $this->assertSame(0.0, $r['total']['total']);
        $this->assertSame([], $r['pedidos']);
    }

    public function test_percentual_sobre_a_venda(): void
    {
        ColaboradorComissao::create([
            'empresa_id' => $this->empresa->id, 'colaborador_id' => $this->colaborador->id,
            'tipo_comissao' => 1, 'percentual' => 10, 'ativo' => true,
        ]);

        $this->venda();   // 2 x 100 = 200; 10% = 20

        $r = $this->extrato();

        $this->assertSame(20.0, $r['total']['percentual']);
        $this->assertSame(20.0, $r['total']['total']);
        $this->assertCount(1, $r['pedidos']);
    }

    public function test_repasse_e_o_modelo_de_franquia(): void
    {
        // tipo 2: a empresa retém um fixo por unidade e o resto é do franqueado.
        // 2 x 100 = 200; empresa fica com 70/un = 140; sobra 60.
        ColaboradorComissao::create([
            'empresa_id' => $this->empresa->id, 'colaborador_id' => $this->colaborador->id,
            'tipo_comissao' => 2, 'empresa_valor' => 70, 'ativo' => true,
        ]);

        $this->venda();

        $r = $this->extrato();

        $this->assertSame(60.0, $r['total']['repasse']);
        $this->assertSame(60.0, $r['total']['total']);
    }

    public function test_pedido_pendente_nao_entra(): void
    {
        ColaboradorComissao::create([
            'empresa_id' => $this->empresa->id, 'colaborador_id' => $this->colaborador->id,
            'tipo_comissao' => 1, 'percentual' => 10, 'ativo' => true,
        ]);

        $this->venda(concretizar: false);

        // Só conta o que baixou estoque: pendente ainda pode ser cancelado, e
        // pagar por ele inflaria o extrato.
        $this->assertSame(0.0, $this->extrato()['total']['total']);
    }

    public function test_extrato_soma_varios_pedidos(): void
    {
        ColaboradorComissao::create([
            'empresa_id' => $this->empresa->id, 'colaborador_id' => $this->colaborador->id,
            'tipo_comissao' => 1, 'percentual' => 10, 'ativo' => true,
        ]);

        $this->venda();
        $this->venda();

        $r = $this->extrato();

        $this->assertCount(2, $r['pedidos']);
        $this->assertSame(40.0, $r['total']['total']);
    }

    public function test_a_rota_do_app_devolve_o_extrato_do_proprio_usuario(): void
    {
        ColaboradorComissao::create([
            'empresa_id' => $this->empresa->id, 'colaborador_id' => $this->colaborador->id,
            'tipo_comissao' => 1, 'percentual' => 10, 'ativo' => true,
        ]);
        $this->venda();

        $token = $this->franqueado->createToken('app', ['role:entregador'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/app/v1/entregador/extrato')
            ->assertOk()
            ->assertJsonPath('data.total.total', 20);
    }
}
