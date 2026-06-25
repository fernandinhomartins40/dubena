<?php

namespace Tests\Feature;

use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Fiscal\SpedContribuicoesService;
use App\Domain\Fiscal\SpedFiscalService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Fiscal\CartaCorrecao;
use App\Models\Fiscal\InutilizacaoFiscal;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F09 — Fiscal: inutilização, carta de correção (CCE), SPED ampliado e IBPT.
 * Driver SEFAZ Fake (CI). A homologação real é gate externo (FISCAL_DRIVER=nfephp).
 */
class FaseF09FiscalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    private function notaAutorizada(Empresa $empresa): NotaFiscal
    {
        $situacao = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::PENDENTE]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100, 'ncm' => '27111910']);
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 3]]);

        return app(\App\Domain\Fiscal\FiscalService::class)->emitirDoPedido($pedido, ModeloDocumento::NFE);
    }

    // ── Inutilização de faixa ──
    public function test_inutiliza_faixa_via_api(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/fiscal/inutilizacoes', [
            'modelo' => 55, 'serie' => 1, 'numero_inicial' => 10, 'numero_final' => 15,
            'justificativa' => 'Numeracao saltada por falha de sistema',
        ])->assertCreated()->assertJsonPath('data.homologada', true);

        $this->assertSame(1, InutilizacaoFiscal::query()->count());
    }

    public function test_inutilizacao_exige_justificativa_minima(): void
    {
        [$user] = $this->suporte();
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/fiscal/inutilizacoes', [
            'modelo' => 55, 'serie' => 1, 'numero_inicial' => 1, 'numero_final' => 2, 'justificativa' => 'curta',
        ])->assertStatus(422);
    }

    // ── Carta de correção ──
    public function test_carta_de_correcao_incrementa_sequencia(): void
    {
        [$user, $empresa] = $this->suporte();
        $nota = $this->notaAutorizada($empresa);

        $r1 = $this->actingAs($user, 'sanctum')->postJson("/api/admin/notas/{$nota->id}/carta-correcao", [
            'correcao' => 'Correcao do endereco de entrega do destinatario',
        ])->assertCreated();
        $this->assertSame(1, $r1->json('data.sequencia'));

        $r2 = $this->actingAs($user, 'sanctum')->postJson("/api/admin/notas/{$nota->id}/carta-correcao", [
            'correcao' => 'Segunda correcao referente a natureza da operacao',
        ])->assertCreated();
        $this->assertSame(2, $r2->json('data.sequencia'));

        $this->assertSame(2, CartaCorrecao::query()->where('nota_fiscal_id', $nota->id)->count());
    }

    public function test_cce_so_em_nota_autorizada(): void
    {
        [$user, $empresa] = $this->suporte();
        $nota = NotaFiscal::query()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'modelo' => 55, 'tipo' => 'S',
            'serie' => 1, 'numero' => 0, 'situacao' => 'RASCUNHO',
        ]);
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/notas/{$nota->id}/carta-correcao", [
            'correcao' => 'Tentativa de CCE em rascunho deve falhar',
        ])->assertStatus(422);
    }

    // ── SPED Fiscal ampliado (E110 apuração, C190 analítico, H inventário) ──
    public function test_sped_fiscal_inclui_apuracao_analitico_e_inventario(): void
    {
        [, $empresa] = $this->suporte();
        $this->notaAutorizada($empresa);

        $arquivo = app(SpedFiscalService::class)->gerar($empresa, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertStringContainsString('|C190|', $arquivo, 'Falta o registro analítico C190.');
        $this->assertStringContainsString('|E110|', $arquivo, 'Falta a apuração do ICMS (E110).');
        $this->assertStringContainsString('|E100|', $arquivo);
        $this->assertStringContainsString('|H005|', $arquivo, 'Falta o inventário (H005).');
        $this->assertStringContainsString('|H010|', $arquivo);
    }

    // ── SPED Contribuições ampliado (M210/M610 detalhe) ──
    public function test_sped_contribuicoes_inclui_detalhe_m210_m610(): void
    {
        [, $empresa] = $this->suporte();
        $this->notaAutorizada($empresa);

        $arquivo = app(SpedContribuicoesService::class)->gerar($empresa, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertStringContainsString('|M210|', $arquivo, 'Falta o detalhe da apuração PIS (M210).');
        $this->assertStringContainsString('|M610|', $arquivo, 'Falta o detalhe da apuração COFINS (M610).');
    }

    // ── IBPT: command importa CSV ──
    public function test_ibpt_atualizar_importa_csv(): void
    {
        $csv = "ncm;ex;tipo;descricao;nacionalfederal;importadosfederal;estadual;municipal;vigenciainicio;vigenciafim;chave;versao;fonte\n"
            ."27111910;;0;GLP;5.00;10.00;18.00;0.00;01/01/2026;31/12/2026;ABC;26.1.A;IBPT\n";
        $path = tempnam(sys_get_temp_dir(), 'ibpt').'.csv';
        file_put_contents($path, $csv);

        $this->artisan('ibpt:atualizar', ['--arquivo' => $path])->assertSuccessful();

        $reg = DB::table('ibpt_aliquotas')->where('ncm', '27111910')->first();
        $this->assertNotNull($reg);
        $this->assertEqualsWithDelta(5.00, (float) $reg->nacional, 0.001);
        $this->assertEqualsWithDelta(18.00, (float) $reg->estadual, 0.001);

        @unlink($path);
    }
}
