<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Logistica\Contracts\TracadorRota;
use App\Domain\Logistica\Drivers\SemTracado;
use App\Domain\Logistica\Drivers\TracadorRotaCacheado;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Logistica\RotaCache;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L6 — acompanhamento do consumidor: posição do entregador + traçado com CACHE
 * PERSISTENTE (rotas_cache): miss consulta o driver e salva; hit não consulta
 * mais; célula de ~100 m compartilha o trajeto; anti-IDOR (só o cliente dono).
 */
class RotaEntregadorConsumidorTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $userCliente;

    private Cliente $cliente;

    private User $entregador;

    private int $pedidoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->userCliente = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->userCliente->id, 'latitude' => -25.3900, 'longitude' => -51.4600,
        ]);
        $this->entregador = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);

        $setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 100, 10);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);

        $this->pedidoId = app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $setor->id,
            'entregador_user_id' => $this->entregador->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 1]])->id;

        EntregadorPosicao::create([
            'empresa_id' => $this->empresa->id, 'entregador_user_id' => $this->entregador->id,
            'latitude' => -25.3862, 'longitude' => -51.4868, 'atualizado_em' => now(),
        ]);
    }

    /** Fake que conta chamadas — substitui a Google. */
    private function fakeTracador(): object
    {
        return new class implements TracadorRota
        {
            public int $chamadas = 0;

            public function tracar(float $a, float $b, float $c, float $d): ?array
            {
                $this->chamadas++;

                return ['polyline' => 'poly_teste', 'distancia_km' => 4.3, 'duracao_min' => 10.0];
            }
        };
    }

    public function test_consumidor_ve_posicao_tracado_e_eta(): void
    {
        $fake = $this->fakeTracador();
        $this->app->bind(TracadorRota::class, fn () => new TracadorRotaCacheado($fake));

        $this->actingAs($this->userCliente, 'sanctum')
            ->getJson("/api/app/v1/pedidos/{$this->pedidoId}/rota-entregador")
            ->assertOk()
            ->assertJsonPath('data.posicao.lat', -25.3862)
            ->assertJsonPath('data.destino.lat', -25.39)
            ->assertJsonPath('data.polyline', 'poly_teste')
            ->assertJsonPath('data.duracao_min', 10)
            ->assertJsonPath('data.entregador.nome', $this->entregador->name);
    }

    public function test_cache_persistente_evita_segunda_chamada_google(): void
    {
        $fake = $this->fakeTracador();
        $this->app->bind(TracadorRota::class, fn () => new TracadorRotaCacheado($fake));

        // 2 polls do app → 1 única chamada "Google"; a 2ª sai do banco.
        $this->actingAs($this->userCliente, 'sanctum')->getJson("/api/app/v1/pedidos/{$this->pedidoId}/rota-entregador")->assertOk();
        $this->actingAs($this->userCliente, 'sanctum')->getJson("/api/app/v1/pedidos/{$this->pedidoId}/rota-entregador")->assertOk();

        $this->assertSame(1, $fake->chamadas);
        $this->assertSame(1, RotaCache::count());
        $this->assertSame(1, (int) RotaCache::first()->hits);
    }

    public function test_celula_de_100m_compartilha_o_trajeto(): void
    {
        $fake = $this->fakeTracador();
        $cacheado = new TracadorRotaCacheado($fake);

        $cacheado->tracar(-25.38620, -51.48680, -25.39000, -51.46000);
        // Origem ~30 m ao lado (mesma célula de 3 casas) → hit, sem nova chamada.
        $r = $cacheado->tracar(-25.38640, -51.48660, -25.39020, -51.45990);

        $this->assertSame(1, $fake->chamadas);
        $this->assertSame('poly_teste', $r['polyline']);
    }

    public function test_cache_serve_trajetos_aprendidos_mesmo_sem_key(): void
    {
        // Aprende com o driver ligado…
        (new TracadorRotaCacheado($this->fakeTracador()))->tracar(-25.386, -51.486, -25.390, -51.460);
        // …e continua servindo com o driver DESLIGADO (SemTracado).
        $r = (new TracadorRotaCacheado(new SemTracado))->tracar(-25.386, -51.486, -25.390, -51.460);

        $this->assertSame('poly_teste', $r['polyline']);
    }

    public function test_outro_cliente_nao_acessa_a_rota(): void
    {
        $outroUser = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'user_id' => $outroUser->id,
        ]);

        $this->actingAs($outroUser, 'sanctum')
            ->getJson("/api/app/v1/pedidos/{$this->pedidoId}/rota-entregador")
            ->assertNotFound();
    }
}
