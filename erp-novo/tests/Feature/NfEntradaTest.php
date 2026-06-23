<?php

namespace Tests\Feature;

use App\Domain\Fiscal\NfEntradaService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\Financeiro;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * FASE C7c — NF de entrada. Import do XML (Standardize, PHP puro → testável no CI)
 * + processamento que dá entrada no estoque e gera financeiro a pagar.
 */
class NfEntradaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private NfEntradaService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->svc = app(NfEntradaService::class);
    }

    private function xmlNfe(string $cProd, string $xProd, float $qtd, float $vUn): string
    {
        $vProd = number_format($qtd * $vUn, 2, '.', '');
        $chave = str_pad('351', 44, '7');

        return <<<XML
        <nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
          <NFe>
            <infNFe Id="NFe{$chave}" versao="4.00">
              <ide><nNF>123</nNF><serie>1</serie><dhEmi>2026-06-01T10:00:00-03:00</dhEmi></ide>
              <emit><CNPJ>12345678000199</CNPJ><xNome>Fornecedor Teste</xNome></emit>
              <det nItem="1">
                <prod>
                  <cProd>{$cProd}</cProd><xProd>{$xProd}</xProd><NCM>27111910</NCM>
                  <CFOP>1102</CFOP><qCom>{$qtd}</qCom><vUnCom>{$vUn}</vUnCom><vProd>{$vProd}</vProd>
                </prod>
              </det>
              <total><ICMSTot><vProd>{$vProd}</vProd><vNF>{$vProd}</vNF></ICMSTot></total>
            </infNFe>
          </NFe>
        </nfeProc>
        XML;
    }

    public function test_importa_xml_e_registra_nota_e_itens(): void
    {
        $produto = Produto::create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Botijão P13', 'preco_venda' => 110, 'custo_medio' => 90, 'ativo' => true,
        ]);

        $nota = $this->svc->importarXml(
            $this->empresa->id,
            $this->empresa->grupo_id,
            $this->xmlNfe((string) $produto->id, 'Botijão P13', 10, 90.0),
        );

        $this->assertSame('123', $nota->numero);
        $this->assertSame('Fornecedor Teste', $nota->emitente_nome);
        $this->assertEqualsWithDelta(900.0, (float) $nota->valor_total, 0.001);
        $this->assertSame(1, $nota->itens()->count());
        // Item casou com o produto pelo código.
        $this->assertSame($produto->id, $nota->itens()->first()->produto_id);
    }

    public function test_processar_da_entrada_no_estoque_e_gera_financeiro(): void
    {
        $produto = Produto::create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'P13', 'preco_venda' => 110, 'custo_medio' => 90, 'ativo' => true,
        ]);
        $setor = Setor::create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Depósito', 'ativo' => true]);

        $nota = $this->svc->importarXml(
            $this->empresa->id, $this->empresa->grupo_id,
            $this->xmlNfe((string) $produto->id, 'P13', 10, 90.0),
        );

        $this->svc->processar($nota, $setor->id);

        // Estoque entrou.
        $saldo = EstoqueSaldo::withoutGlobalScopes()->where('setor_id', $setor->id)->where('produto_id', $produto->id)->first();
        $this->assertNotNull($saldo);
        $this->assertEqualsWithDelta(10.0, (float) $saldo->quantidade, 0.001);

        // Financeiro a pagar gerado.
        $fin = Financeiro::query()->where('origem', 'nf_entrada')->where('origem_id', $nota->id)->first();
        $this->assertNotNull($fin);
        $this->assertSame('P', $fin->pagarreceber);
        $this->assertEqualsWithDelta(900.0, (float) $fin->valor, 0.001);

        // Idempotente: processar de novo não duplica.
        $this->svc->processar($nota->refresh(), $setor->id);
        $this->assertSame(1, Financeiro::query()->where('origem', 'nf_entrada')->count());
    }

    public function test_chave_duplicada_bloqueia(): void
    {
        $xml = $this->xmlNfe('X1', 'Item', 1, 10.0);
        $this->svc->importarXml($this->empresa->id, $this->empresa->grupo_id, $xml);

        $this->expectException(ValidationException::class);
        $this->svc->importarXml($this->empresa->id, $this->empresa->grupo_id, $xml);
    }
}
