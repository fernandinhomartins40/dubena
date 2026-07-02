<?php

namespace Tests\Domain;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Missao\GeradorMissaoService;
use App\Domain\Missao\MissaoService;
use App\Domain\Missao\VendaCampoService;
use App\Domain\Logistica\JornadaService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Logistica\LogisticaConfig;
use App\Models\Missao\Missao;
use App\Models\Missao\MissaoAtribuicao;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * L7/L8 — fluxo de missões: geração por ociosidade, execução (visita com evidência
 * obrigatória, trilha, adiamento, conclusão) e venda em campo (pedido CONCLUIDO
 * com estoque baixado, tenant-scoped).
 */
class MissaoFluxoTest extends TestCase
{
    use RefreshDatabase;

    private MissaoService $svc;

    private Setor $setor;

    private Produto $produto;

    private int $empresaId;

    private int $grupoId;

    private User $entregador;

    protected function setUp(): void
    {
        parent::setUp();
        // Fake SELETIVO (broadcasts): Event::fake() global mataria os model events
        // do Eloquent (creating da BelongsToTenant → empresa_id ficaria null).
        Event::fake([
            \App\Domain\Logistica\Events\PedidoEntrouNaFila::class,
            \App\Domain\Logistica\Events\PedidoAtribuido::class,
            \App\Domain\Pedido\Events\PedidoStatusAtualizado::class,
        ]);
        Storage::fake('local');
        $this->svc = app(MissaoService::class);

        $this->setor = Setor::factory()->create();
        $this->empresaId = $this->setor->empresa_id;
        $this->grupoId = $this->setor->grupo_id;
        app(TenantContext::class)->set($this->empresaId, $this->grupoId);

        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'preco_venda' => 120]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10.0);
        $this->entregador = User::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);
    }

    private function missao(array $attr = []): Missao
    {
        return Missao::create(array_merge([
            'empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId,
            'tipo' => 'prospeccao', 'titulo' => 'Prospecção bairro centro', 'exige_foto' => true, 'ativo' => true,
        ], $attr));
    }

    private function atribuicao(array $missaoAttr = []): MissaoAtribuicao
    {
        return MissaoAtribuicao::create([
            'empresa_id' => $this->empresaId,
            'missao_id' => $this->missao($missaoAttr)->id,
            'entregador_user_id' => $this->entregador->id,
            'status' => 'atribuida',
        ]);
    }

    public function test_visita_exige_foto_quando_missao_exige(): void
    {
        $atr = $this->svc->iniciar($this->atribuicao());

        $this->expectException(ValidationException::class);
        $this->svc->registrarVisita($atr, ['status' => 'visitada'], null);
    }

    public function test_visita_com_foto_grava_evidencia_no_storage_privado(): void
    {
        $atr = $this->svc->iniciar($this->atribuicao());

        $visita = $this->svc->registrarVisita($atr, [
            'status' => 'interessado', 'latitude' => -25.39, 'longitude' => -51.46,
        ], UploadedFile::fake()->image('fachada.jpg'), 'fachada');

        $this->assertSame('interessado', $visita->status);
        $ev = $visita->evidencias()->first();
        $this->assertNotNull($ev);
        Storage::disk('local')->assertExists($ev->foto_path);
    }

    public function test_trilha_em_lote_e_metricas(): void
    {
        $atr = $this->svc->iniciar($this->atribuicao(['exige_foto' => false]));

        $n = $this->svc->registrarTrilha($atr, [
            ['latitude' => -25.390, 'longitude' => -51.460],
            ['latitude' => -25.391, 'longitude' => -51.461],
            ['latitude' => -25.392, 'longitude' => -51.462],
        ]);
        $this->svc->registrarVisita($atr, ['status' => 'visitada'], null);

        $m = $this->svc->metricas($atr);

        $this->assertSame(3, $n);
        $this->assertSame(3, $m['pontos_trilha']);
        $this->assertSame(1, $m['visitas_total']);
        $this->assertGreaterThan(0, $m['distancia_km']);
    }

    public function test_adiar_registra_motivo_e_fica_pendente_de_aprovacao(): void
    {
        $atr = $this->svc->iniciar($this->atribuicao());

        $adiada = $this->svc->adiar($atr, 'nova_entrega', 'Chamado urgente');

        $this->assertSame('adiada', $adiada->status);
        $this->assertSame('pendente', $adiada->adiamento_aprovacao);
        $this->assertSame('nova_entrega', $adiada->adiamento_motivo);
    }

    public function test_proxima_casa_sugere_cliente_geocodificado_mais_proximo_nao_visitado(): void
    {
        $atr = $this->svc->iniciar($this->atribuicao(['exige_foto' => false]));
        $perto = Cliente::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'latitude' => -25.391, 'longitude' => -51.461]);
        $longe = Cliente::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'latitude' => -25.500, 'longitude' => -51.600]);

        $sugestao = $this->svc->proximaCasa($atr, -25.390, -51.460);
        $this->assertSame($perto->id, $sugestao['cliente_id']);

        // Visitou o perto → sugere o longe.
        $this->svc->registrarVisita($atr, ['status' => 'visitada', 'cliente_id' => $perto->id], null);
        $sugestao2 = $this->svc->proximaCasa($atr, -25.390, -51.460);
        $this->assertSame($longe->id, $sugestao2['cliente_id']);
    }

    public function test_gerador_atribui_missao_a_entregador_ocioso_em_jornada(): void
    {
        LogisticaConfig::create(['empresa_id' => $this->empresaId, 'ociosidade_min' => 0]);
        $this->missao(); // missão ativa sem área (vale em toda a praça)
        app(JornadaService::class)->iniciar($this->entregador, null);
        EntregadorPosicao::create([
            'empresa_id' => $this->empresaId, 'entregador_user_id' => $this->entregador->id,
            'latitude' => -25.39, 'longitude' => -51.46, 'atualizado_em' => now(),
        ]);

        $criadas = app(GeradorMissaoService::class)->gerarParaEmpresa($this->empresaId);

        $this->assertSame(1, $criadas);
        $this->assertNotNull($this->svc->atribuicaoAtiva($this->entregador->id));

        // Não empilha: segunda rodada não cria outra.
        $this->assertSame(0, app(GeradorMissaoService::class)->gerarParaEmpresa($this->empresaId));
    }

    public function test_gerador_ignora_entregador_com_entregas_ativas(): void
    {
        LogisticaConfig::create(['empresa_id' => $this->empresaId, 'ociosidade_min' => 0]);
        $this->missao();
        app(JornadaService::class)->iniciar($this->entregador, null);

        // Entrega ativa → não é ocioso.
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->grupoId]);
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);
        app(\App\Domain\Pedido\PedidoService::class)->criar([
            'empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId, 'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $pendente->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $this->entregador->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1]]);

        $this->assertSame(0, app(GeradorMissaoService::class)->gerarParaEmpresa($this->empresaId));
    }

    public function test_venda_em_campo_cria_pedido_concluido_e_baixa_estoque(): void
    {
        $atr = $this->svc->iniciar($this->atribuicao(['exige_foto' => false]));
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresaId, 'grupo_id' => $this->grupoId]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->grupoId]);

        $visita = $this->svc->registrarVisita($atr, ['status' => 'venda', 'cliente_id' => $cliente->id], null);
        $visita = app(VendaCampoService::class)->venderGas($atr, $visita, [
            'cliente_id' => $cliente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
        ], $this->entregador->id);

        $this->assertNotNull($visita->pedido_id);
        $pedido = $visita->pedido;
        $this->assertSame($this->empresaId, (int) $pedido->empresa_id);
        $this->assertSame(240.0, (float) $pedido->valor_venda); // 2 × 120 (preço server-side)
        $this->assertTrue($pedido->estoque_movimentado);
        // Estoque baixado: 100 - 2 = 98.
        $this->assertEqualsWithDelta(98, app(EstoqueService::class)->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
    }
}
