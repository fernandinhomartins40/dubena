<?php

namespace Tests\Domain;

use App\Domain\Fiscal\SituacaoNota;
use App\Domain\Fiscal\SpedContribuicoesService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FASE C7d — EFD-Contribuições (PIS/COFINS). Geração de texto pura (CI). */
class SpedContribuicoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_efd_contribuicoes_com_bloco_m(): void
    {
        $empresa = Empresa::factory()->create(['razao_social' => 'Empresa PIS', 'cnpj' => '12345678000199', 'uf' => 'SP']);
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $produto = Produto::create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Botijão P13', 'ncm' => '27111910',
            'preco_venda' => 110, 'custo_medio' => 90, 'ativo' => true,
        ]);

        $nota = NotaFiscal::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'modelo' => '55', 'tipo' => 'S', 'serie' => 1, 'numero' => 2001,
            'chave' => str_pad('35', 44, '1'),
            'situacao' => SituacaoNota::AUTORIZADA->value,
            'valor_produtos' => 1000, 'valor_total' => 1000,
            'valor_pis' => 16.50, 'valor_cofins' => 76.00,
            'emitida_em' => '2026-06-10 10:00:00',
        ]);
        $nota->itens()->create([
            'produto_id' => $produto->id, 'numero_item' => 1, 'quantidade' => 10,
            'valor_unitario' => 100, 'valor_total' => 1000, 'desconto' => 0, 'cfop' => '5102',
            'aliq_pis' => 1.65, 'valor_pis' => 16.50, 'aliq_cofins' => 7.60, 'valor_cofins' => 76.00,
        ]);

        $sped = app(SpedContribuicoesService::class)->gerar($empresa, '2026-06-01', '2026-06-30');

        $this->assertStringContainsString('|0000|', $sped);
        $this->assertStringContainsString('|C100|', $sped);
        $this->assertStringContainsString('|C170|', $sped);
        $this->assertStringContainsString('|M200|', $sped);   // consolidação PIS
        $this->assertStringContainsString('|M600|', $sped);   // consolidação COFINS
        $this->assertStringContainsString('16,50', $sped);    // PIS apurado
        $this->assertStringContainsString('76,00', $sped);    // COFINS apurado
        $this->assertStringContainsString('|9999|', $sped);

        foreach (array_filter(explode("\r\n", $sped)) as $linha) {
            $this->assertStringStartsWith('|', $linha);
            $this->assertStringEndsWith('|', $linha);
        }
    }
}
