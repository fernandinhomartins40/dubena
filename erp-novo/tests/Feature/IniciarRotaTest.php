<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Logistica\JornadaService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PapelSituacao;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L6 — Iniciar rota: move as entregas PENDENTES do entregador para a situação
 * marcada com o papel EM_ROTA, de forma idempotente, e exige jornada ativa.
 *
 * F3-04A mudou o contrato: a situação de deslocamento é CONFIGURADA (papel
 * declarado), não mais procurada por `LIKE` na descrição nem criada em silêncio
 * quando a busca falha. Ver `PapelSituacaoEmRotaTest` para o porquê.
 */
class IniciarRotaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $entregador;

    private Setor $setor;

    private Produto $produto;

    private PedidoSituacao $pendente;

    private PedidoSituacao $emRota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->entregador = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10);
        $this->pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        // F3-04A: a situação de deslocamento agora é configurada, não inventada.
        $this->emRota = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create([
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Saiu para entrega',
            'papel' => PapelSituacao::EM_ROTA->value,
        ]);

        // Jornada ativa (exigida pelo endpoint).
        app(JornadaService::class)->iniciar($this->entregador, null);
    }

    private function pedido(): Pedido
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $this->pendente->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $this->entregador->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1]]);
    }

    public function test_iniciar_rota_move_pendentes_para_saiu_para_entrega(): void
    {
        $p1 = $this->pedido();
        $p2 = $this->pedido();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')
            ->assertOk()
            ->assertJsonPath('data.iniciados', 2);

        // A situação com papel EM_ROTA foi aplicada aos pedidos.
        $this->assertSame($this->emRota->id, $p1->refresh()->pedidosituacao_id);
        $this->assertSame($this->emRota->id, $p2->refresh()->pedidosituacao_id);
        $this->assertSame(EfeitoPedido::PENDENTE, $this->emRota->efeito);
    }

    public function test_iniciar_rota_e_idempotente(): void
    {
        $this->pedido();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')->assertOk()
            ->assertJsonPath('data.iniciados', 1);

        // Segunda chamada: nada a mover.
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')->assertOk()
            ->assertJsonPath('data.iniciados', 0);
    }

    /**
     * O nome da situação deixou de importar: quem decide é o papel.
     *
     * Antes, uma situação chamada "Em rota de entrega" era encontrada por
     * `LIKE '%rota%'`; com outro nome qualquer, não seria.
     */
    public function test_usa_a_situacao_com_papel_seja_qual_for_a_descricao(): void
    {
        $this->emRota->update(['descricao' => 'Camino al cliente']);
        $p = $this->pedido();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')->assertOk();

        $this->assertSame($this->emRota->id, $p->refresh()->pedidosituacao_id);
    }

    /** Sem papel configurado, a ação pede configuração em vez de inventar. */
    public function test_sem_papel_configurado_recusa_sem_criar_situacao(): void
    {
        $this->emRota->update(['papel' => PapelSituacao::NENHUM->value]);
        $this->pedido();
        $antes = PedidoSituacao::query()->count();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')
            ->assertStatus(422);

        $this->assertSame($antes, PedidoSituacao::query()->count());
    }

    public function test_sem_jornada_ativa_rejeita(): void
    {
        app(JornadaService::class)->encerrar(app(JornadaService::class)->jornadaAtiva($this->entregador->id));

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')
            ->assertStatus(422);
    }

    public function test_api_sem_accept_json_nao_da_500(): void
    {
        // Fix do shouldRenderJsonWhen: sem Accept, o 401 deve continuar 401 (JSON),
        // não um 500 de Route [login] not defined.
        $this->post('/api/app/v1/entregador/rota/iniciar')->assertStatus(401);
    }
}
