<?php

namespace Tests\Domain;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Logistica\Contracts\MatrizDistancia;
use App\Domain\Logistica\Contracts\TracadorRota;
use App\Domain\Logistica\Drivers\HaversineDriver;
use App\Domain\Logistica\Drivers\SemTracado;
use App\Domain\Logistica\RoteirizadorService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * L5 — RoteirizadorService: sequência por vizinho-mais-próximo a partir da posição
 * do entregador; distância/ETA acumulados; paradas sem geo vão ao fim.
 */
class RoteirizadorServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoteirizadorService $svc;

    private Setor $setor;

    private Produto $produto;

    private int $empresaId;

    private int $grupoId;

    private User $entregador;

    private ?PedidoSituacao $pendente = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Fake SELETIVO (broadcasts): Event::fake() global mataria os model events
        // do Eloquent (creating da BelongsToTenant -> empresa_id ficaria null).
        Event::fake([
            \App\Domain\Logistica\Events\PedidoEntrouNaFila::class,
            \App\Domain\Logistica\Events\PedidoAtribuido::class,
            \App\Domain\Pedido\Events\PedidoStatusAtualizado::class,
        ]);
        // Drivers explícitos (não depende de env/Google no teste).
        $this->svc = new RoteirizadorService(new HaversineDriver, new SemTracado);

        $this->setor = Setor::factory()->create();
        $this->empresaId = $this->setor->empresa_id;
        $this->grupoId = $this->setor->grupo_id;
        app(TenantContext::class)->set($this->empresaId, $this->grupoId);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'preco_venda' => 100]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10.0);
        $this->entregador = User::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);
    }

    private function pedidoEm(?float $lat, ?float $lng): int
    {
        $this->pendente ??= PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->grupoId]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'latitude' => $lat, 'longitude' => $lng,
        ]);
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $this->pendente->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $this->entregador->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1, 'preco_unitario' => 100]]);

        return $pedido->id;
    }

    private function posicionar(float $lat, float $lng): void
    {
        EntregadorPosicao::create([
            'empresa_id' => $this->empresaId, 'entregador_user_id' => $this->entregador->id,
            'latitude' => $lat, 'longitude' => $lng, 'atualizado_em' => now(),
        ]);
    }

    public function test_sequencia_visita_o_mais_proximo_primeiro(): void
    {
        // Entregador no ponto A. Pedido perto (B) e pedido longe (C).
        $this->posicionar(-25.390, -51.460);
        $longe = $this->pedidoEm(-25.500, -51.600)  ; // ~18 km
        $perto = $this->pedidoEm(-25.392, -51.462)  ; // ~300 m

        $rota = $this->svc->rotaDoEntregador($this->empresaId, $this->entregador->id);

        $this->assertCount(2, $rota['paradas']);
        $this->assertSame($perto, $rota['paradas'][0]['pedido_id']);
        $this->assertSame($longe, $rota['paradas'][1]['pedido_id']);
        $this->assertSame(1, $rota['paradas'][0]['sequencia']);
        $this->assertGreaterThan(0, $rota['distancia_total_km']);
        $this->assertSame($rota['paradas'][0]['pedido_id'], $rota['proximo']['pedido_id']);
    }

    public function test_paradas_sem_geo_vao_ao_final(): void
    {
        $this->posicionar(-25.390, -51.460)  ;
        $comGeo = $this->pedidoEm(-25.392, -51.462)  ;
        $semGeo = $this->pedidoEm(null, null);

        $rota = $this->svc->rotaDoEntregador($this->empresaId, $this->entregador->id);

        $this->assertSame($comGeo, $rota['paradas'][0]['pedido_id']);
        $this->assertSame($semGeo, $rota['paradas'][1]['pedido_id']);
        $this->assertNull($rota['paradas'][1]['lat']);
    }

    public function test_driver_e_resolvido_por_env_haversine_por_padrao(): void
    {
        // Sem GOOGLE_MAPS_KEY, o container entrega o Haversine e o SemTracado.
        config()->set('services.geocoding.key', null);
        $this->assertInstanceOf(HaversineDriver::class, app(MatrizDistancia::class));
        $this->assertInstanceOf(SemTracado::class, app(TracadorRota::class));
    }

    public function test_paradas_incluem_polyline_quando_tracador_disponivel(): void
    {
        // Fake da Routes API: devolve polyline e métricas reais por trecho.
        $fake = new class implements TracadorRota
        {
            public function tracar(float $a, float $b, float $c, float $d): ?array
            {
                return ['polyline' => 'abc123_encoded', 'distancia_km' => 2.5, 'duracao_min' => 7.0];
            }
        };
        $svc = new RoteirizadorService(new HaversineDriver, $fake);

        $this->posicionar(-25.390, -51.460);
        $this->pedidoEm(-25.392, -51.462);

        $rota = $svc->rotaDoEntregador($this->empresaId, $this->entregador->id);

        $this->assertSame('abc123_encoded', $rota['paradas'][0]['polyline']);
        $this->assertSame(2.5, $rota['paradas'][0]['distancia_trecho_km']);
        $this->assertSame(7.0, $rota['paradas'][0]['duracao_trecho_min']);
    }

    public function test_sem_tracador_polyline_e_null(): void
    {
        $this->posicionar(-25.390, -51.460);
        $this->pedidoEm(-25.392, -51.462);

        $rota = $this->svc->rotaDoEntregador($this->empresaId, $this->entregador->id);

        $this->assertNull($rota['paradas'][0]['polyline']);
        $this->assertGreaterThan(0, $rota['paradas'][0]['distancia_trecho_km']); // Haversine segue valendo
    }
}
