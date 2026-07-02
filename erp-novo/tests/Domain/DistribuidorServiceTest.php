<?php

namespace Tests\Domain;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Logistica\CentralService;
use App\Domain\Logistica\DistribuidorService;
use App\Domain\Logistica\JornadaService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Logistica\LogisticaConfig;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Monitora\Veiculo;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * L3 — DistribuidorService: ranqueia por proximidade + carga; respeita raio/teto;
 * melhorEntregador ignora inelegíveis. Cliente em Guarapuava (-25.39, -51.46).
 */
class DistribuidorServiceTest extends TestCase
{
    use RefreshDatabase;

    private DistribuidorService $dist;

    private JornadaService $jornadas;

    private CentralService $central;

    private Setor $setor;

    private Produto $produto;

    private int $empresaId;

    private int $grupoId;

    private ?PedidoSituacao $pendente = null;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->dist = app(DistribuidorService::class);
        $this->jornadas = app(JornadaService::class);
        $this->central = app(CentralService::class);

        $this->setor = Setor::factory()->create();
        $this->empresaId = $this->setor->empresa_id;
        $this->grupoId = $this->setor->grupo_id;
        app(TenantContext::class)->set($this->empresaId, $this->grupoId);

        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'preco_venda' => 100]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10.0);
    }

    private function entregadorEmJornada(float $lat, float $lng): User
    {
        $u = User::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);
        $veiculo = Veiculo::create([
            'empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId,
            'placa' => 'AAA'.fake()->numberBetween(1000, 9999), 'ativo' => true,
        ]);
        $this->jornadas->iniciar($u, $veiculo->id);
        EntregadorPosicao::create([
            'empresa_id' => $this->empresaId, 'entregador_user_id' => $u->id,
            'latitude' => $lat, 'longitude' => $lng, 'atualizado_em' => now(),
        ]);

        return $u;
    }

    private function pedidoPara(float $lat, float $lng): Pedido
    {
        $this->pendente ??= PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->grupoId]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId,
            'latitude' => $lat, 'longitude' => $lng,
        ]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $this->pendente->id, 'setor_id' => $this->setor->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1, 'preco_unitario' => 100]]);
    }

    public function test_mais_proximo_fica_no_topo(): void
    {
        $perto = $this->entregadorEmJornada(-25.390, -51.460); // ~no cliente
        $longe = $this->entregadorEmJornada(-25.500, -51.600); // ~18 km

        $ranking = $this->dist->ranquear($this->pedidoPara(-25.390, -51.460));

        $this->assertSame($perto->id, $ranking[0]['entregador_user_id']);
        $this->assertSame($longe->id, $ranking[1]['entregador_user_id']);
        $this->assertLessThan($ranking[0]['score'], $ranking[1]['score']);
    }

    public function test_carga_desempata_quando_distancia_igual(): void
    {
        $a = $this->entregadorEmJornada(-25.390, -51.460);
        $b = $this->entregadorEmJornada(-25.390, -51.460);
        // 'a' já tem 1 pedido ativo → 'b' (carga 0) deve vir na frente.
        $this->central->atribuir($this->pedidoPara(-25.390, -51.460), $a->id);

        $ranking = $this->dist->ranquear($this->pedidoPara(-25.390, -51.460));

        $this->assertSame($b->id, $ranking[0]['entregador_user_id']);
    }

    public function test_raio_maximo_torna_inelegivel(): void
    {
        LogisticaConfig::create(['empresa_id' => $this->empresaId, 'modo' => 'auto', 'raio_maximo_km' => 5]);
        $this->entregadorEmJornada(-25.500, -51.600); // ~18 km > 5

        $pedido = $this->pedidoPara(-25.390, -51.460);
        $ranking = $this->dist->ranquear($pedido);

        $this->assertFalse($ranking[0]['elegivel']);
        $this->assertNull($this->dist->melhorEntregador($pedido));
    }

    public function test_sem_entregadores_em_jornada_ranking_vazio(): void
    {
        $this->assertSame([], $this->dist->ranquear($this->pedidoPara(-25.390, -51.460)));
    }

    public function test_modo_auto_atribui_ao_melhor_via_job(): void
    {
        LogisticaConfig::create(['empresa_id' => $this->empresaId, 'modo' => 'auto']);
        $perto = $this->entregadorEmJornada(-25.390, -51.460);
        $this->entregadorEmJornada(-25.500, -51.600);

        $pedido = $this->pedidoPara(-25.390, -51.460);
        // Roda o job de auto-atribuição diretamente (a fila é sync no teste).
        (new \App\Domain\Logistica\Jobs\AtribuirPedidoJob($pedido->id, $this->empresaId, $this->grupoId))
            ->handle($this->central, $this->dist, app(TenantContext::class));

        $this->assertSame($perto->id, $pedido->refresh()->entregador_user_id);
    }

    public function test_modo_sugerir_nao_atribui_automaticamente(): void
    {
        LogisticaConfig::create(['empresa_id' => $this->empresaId, 'modo' => 'sugerir']);
        $this->entregadorEmJornada(-25.390, -51.460);

        $pedido = $this->pedidoPara(-25.390, -51.460);
        (new \App\Domain\Logistica\Jobs\AtribuirPedidoJob($pedido->id, $this->empresaId, $this->grupoId))
            ->handle($this->central, $this->dist, app(TenantContext::class));

        $this->assertNull($pedido->refresh()->entregador_user_id);
    }
}
