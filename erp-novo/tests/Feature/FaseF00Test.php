<?php

namespace Tests\Feature;

use App\Domain\Apoio\CadastroSlugs;
use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Relatorio\RelatorioService;
use App\Models\Caixa\ContaMovimento;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Fase F00 — correções estruturais, contratos e bugs bloqueadores.
 * Cada teste ancora-se num achado da auditoria (docs/AUDITORIA_PARIDADE_MODERNIZACAO.md).
 */
class FaseF00Test extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    // ── F00.1 — strftime → SQL agnóstico ──────────────────────────────────────
    public function test_clientes_aniversariantes_nao_usa_strftime_e_filtra_por_mes(): void
    {
        [$user, $empresa] = $this->suporte();
        Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => 'Marco', 'datanascimento' => '1990-03-10']);
        Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => 'Junho', 'datanascimento' => '1985-06-20']);

        $linhas = app(RelatorioService::class)->clientesAniversariantes($empresa->id, 3);

        $this->assertCount(1, $linhas);
        $this->assertSame('Marco', $linhas[0]['nome']);
    }

    // ── F00.2 — slugs Lookup × CadastroApoio unificados ───────────────────────
    public function test_slug_canonico_resolve_alias_de_tipo_pessoa(): void
    {
        $this->assertSame('tipos-pessoa', CadastroSlugs::canonico('tipo-pessoa'));
        $this->assertSame('tipos-pessoa', CadastroSlugs::canonico('tipos-pessoa'));
        $this->assertSame('estados-civis', CadastroSlugs::canonico('estadocivil'));
        // slug desconhecido volta como veio
        $this->assertSame('xpto', CadastroSlugs::canonico('xpto'));
    }

    public function test_lookup_aceita_slug_antigo_e_canonico_para_tipo_pessoa(): void
    {
        [$user] = $this->suporte();

        // Ambos os slugs devem responder 200 (mesma entidade) — sem 404 de contrato.
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/lookups/tipo-pessoa')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/lookups/tipos-pessoa')->assertOk();
    }

    // ── F00.3 — StubController removido (nenhuma rota 501) ────────────────────
    public function test_stub_controller_nao_existe_mais(): void
    {
        // O arquivo do controller órfão deve ter sido removido na F00...
        $this->assertFileDoesNotExist(
            app_path('Http/Controllers/Api/Admin/StubController.php'),
            'StubController órfão deveria ter sido removido na F00.'
        );

        // ...e nenhuma rota pode referenciá-lo.
        $referenciaStub = collect(Route::getRoutes())
            ->contains(fn ($r) => str_contains((string) ($r->getActionName() ?? ''), 'StubController'));
        $this->assertFalse($referenciaStub, 'Nenhuma rota deve apontar para StubController.');
    }

    // ── F00.4 — histórico do cliente deriva de Pedidos ────────────────────────
    public function test_historico_do_cliente_retorna_pedidos_reais(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        // Cria um pedido concretizado para o cliente via service de venda.
        $situacao = PedidoSituacao::factory()->create([
            'grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::PENDENTE,
        ]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100]);
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 2]]);

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/clientes/{$cliente->id}/historico")
            ->assertOk();

        $data = $resp->json('data');
        $this->assertNotEmpty($data, 'Histórico deveria derivar de Pedidos (não mais vazio).');
        $this->assertSame($pedido->id, $data[0]['pedido_id']);
        $this->assertSame(1, $data[0]['itens']);
    }

    // ── F00.5 — IDOR: não baixar/estornar de outra empresa ────────────────────
    public function test_nao_baixa_parcela_de_outra_empresa(): void
    {
        // Empresa A: cria a parcela. Empresa B: tenta baixar via sua conta.
        [$userA, $empresaA] = $this->suporte();
        [$userB, $empresaB] = $this->suporte();

        $financeiroA = app(FinanceiroService::class)->criar([
            'empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id, 'pagarreceber' => 'R', 'valor' => 200,
        ]);
        $parcelaA = $financeiroA->parcelas->first();

        $contaB = app(CaixaService::class)->criarConta([
            'empresa_id' => $empresaB->id, 'grupo_id' => $empresaB->grupo_id, 'descricao' => 'Caixa B', 'saldo_inicial' => 0,
        ]);

        // Tenant B ativo tenta baixar a parcela de A → deve falhar SEM vazar.
        // Após a F02 (FinanceiroParcela tenant-scoped), a parcela de A é invisível
        // para B: o findOrFail no service retorna 404 (antes era 422 via revalidação
        // explícita). Qualquer 4xx que NÃO baixe a parcela satisfaz a garantia.
        $resp = $this->actingAs($userB, 'sanctum')
            ->withHeader('X-Empresa-Id', (string) $empresaB->id)
            ->postJson("/api/admin/caixa/{$contaB->id}/baixar", ['parcela_id' => $parcelaA->id]);

        $this->assertContains($resp->status(), [404, 422], 'Deve recusar baixa cross-tenant.');

        // A parcela de A continua em aberto.
        $this->assertFalse($parcelaA->refresh()->baixado);
    }

    public function test_nao_estorna_movimento_de_outra_empresa(): void
    {
        [$userA, $empresaA] = $this->suporte();
        [$userB, $empresaB] = $this->suporte();

        // Movimento pertence à empresa A (criarConta com saldo inicial gera ABERTURA).
        $contaA = app(CaixaService::class)->criarConta([
            'empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id, 'descricao' => 'Caixa A', 'saldo_inicial' => 50,
        ]);
        $movA = ContaMovimento::withoutTenant()->where('conta_id', $contaA->id)->firstOrFail();

        // Tenant B tenta estornar o movimento de A → 404 (global scope não enxerga).
        $this->actingAs($userB, 'sanctum')
            ->withHeader('X-Empresa-Id', (string) $empresaB->id)
            ->postJson("/api/admin/caixa/movimentos/{$movA->id}/estornar")
            ->assertNotFound();
    }

    // ── F00.6 — rotas órfãs expostas ──────────────────────────────────────────
    public function test_rotas_orfas_estao_registradas(): void
    {
        $rotas = collect(Route::getRoutes())->map->uri()->all();

        foreach ([
            'api/admin/financeiro/lancamentos/agrupar',
            'api/admin/financeiro/lancamentos/{id}/desagrupar',
            'api/admin/financeiro/lancamentos/{id}/reparcelar',
            'api/admin/caixa/{contaId}/baixar-titulos',
            'api/admin/caixa/{contaId}/lancar-fechado',
            'api/admin/fiscal/nf-entrada',
            'api/admin/fiscal/nf-entrada/importar',
            'api/admin/fiscal/nf-entrada/{id}/processar',
        ] as $rota) {
            $this->assertContains($rota, $rotas, "Rota ausente: {$rota}");
        }
    }

    public function test_reparcelar_titulo_em_aberto_gera_novo_titulo(): void
    {
        [$user, $empresa] = $this->suporte();
        $f = app(FinanceiroService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 300,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/financeiro/lancamentos/{$f->id}/reparcelar", ['num_parcelas' => 3])
            ->assertCreated()
            ->assertJsonPath('data.valor', fn ($v) => abs((float) $v - 300.0) < 0.01);
    }
}
