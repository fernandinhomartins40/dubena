<?php

namespace Tests\Domain;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\ConciliacaoService;
use App\Domain\Financeiro\OfxParser;
use App\Domain\Relatorio\RelatorioService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE C8 — conciliação bancária (OFX) e parser. Tudo testável no CI.
 */
class ConciliacaoTest extends TestCase
{
    use RefreshDatabase;

    private function ofx(array $trns): string
    {
        $blocos = '';
        foreach ($trns as $t) {
            $blocos .= "<STMTTRN><TRNTYPE>{$t['tipo']}</TRNTYPE><DTPOSTED>{$t['data']}</DTPOSTED>"
                ."<TRNAMT>{$t['valor']}</TRNAMT><FITID>{$t['id']}</FITID><MEMO>{$t['memo']}</MEMO></STMTTRN>";
        }

        return "OFXHEADER:100\n<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>{$blocos}</BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>";
    }

    public function test_parser_extrai_transacoes(): void
    {
        $ofx = $this->ofx([
            ['tipo' => 'CREDIT', 'data' => '20260610', 'valor' => '150.00', 'id' => 'A1', 'memo' => 'Deposito'],
            ['tipo' => 'DEBIT', 'data' => '20260611', 'valor' => '-50.00', 'id' => 'A2', 'memo' => 'Tarifa'],
        ]);

        $trns = (new OfxParser)->transacoes($ofx);

        $this->assertCount(2, $trns);
        $this->assertSame('A1', $trns[0]['fitid']);
        $this->assertSame(150.0, $trns[0]['valor']);
        $this->assertSame('2026-06-10', $trns[0]['data']);
        $this->assertSame(-50.0, $trns[1]['valor']);
    }

    public function test_concilia_casando_por_valor_e_data(): void
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $caixa = app(CaixaService::class);

        $conta = $caixa->criarConta([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Banco', 'saldo_inicial' => 0,
        ]);
        // 2 movimentos no ERP.
        $caixa->movimentar($conta->id, 150.00, CaixaService::AJUSTE, ['origem' => 't', 'datahora' => '2026-06-10 09:00:00']);
        $caixa->movimentar($conta->id, 30.00, CaixaService::AJUSTE, ['origem' => 't', 'datahora' => '2026-06-12 09:00:00']);

        // OFX: 150 (casa) + 999 (só no banco).
        $ofx = $this->ofx([
            ['tipo' => 'CREDIT', 'data' => '20260610', 'valor' => '150.00', 'id' => 'B1', 'memo' => 'Dep'],
            ['tipo' => 'CREDIT', 'data' => '20260615', 'valor' => '999.00', 'id' => 'B2', 'memo' => 'Outro'],
        ]);

        $r = app(ConciliacaoService::class)->conciliar($conta->id, $ofx, '2026-06-01', '2026-06-30');

        $this->assertSame(1, $r['resumo']['conciliados']);
        $this->assertCount(1, $r['ofx_pendentes']);   // o 999
        $this->assertCount(1, $r['erp_pendentes']);   // o 30
        $this->assertSame(150.0, $r['conciliados'][0]['valor']);
    }

    public function test_csv_gera_cabecalho_e_linhas(): void
    {
        $csv = app(RelatorioService::class)->csv([
            ['produto' => 'P13', 'quantidade' => 5],
            ['produto' => 'P45', 'quantidade' => 2],
        ]);

        $this->assertStringContainsString('produto;quantidade', $csv);
        $this->assertStringContainsString('P13;5', $csv);
    }
}
