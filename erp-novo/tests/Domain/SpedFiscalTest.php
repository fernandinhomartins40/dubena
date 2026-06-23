<?php

namespace Tests\Domain;

use App\Domain\Fiscal\SituacaoNota;
use App\Domain\Fiscal\SpedFiscalService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE C7d — SPED Fiscal. Geração de texto pura (testável no CI); o layout dos
 * registros é legislado (porte do legado). A validação final é no PVA da Receita.
 */
class SpedFiscalTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_arquivo_com_registros_essenciais(): void
    {
        $empresa = Empresa::factory()->create(['razao_social' => 'Empresa SPED', 'cnpj' => '12345678000199', 'uf' => 'SP']);
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $produto = Produto::create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Botijão P13', 'ncm' => '27111910',
            'preco_venda' => 110, 'custo_medio' => 90, 'ativo' => true,
        ]);

        $nota = NotaFiscal::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'modelo' => '55', 'tipo' => 'S', 'serie' => 1, 'numero' => 1001,
            'chave' => str_pad('35', 44, '1'),
            'situacao' => SituacaoNota::AUTORIZADA->value,
            'valor_produtos' => 1100, 'valor_total' => 1100, 'valor_icms' => 198,
            'emitida_em' => '2026-06-10 10:00:00',
        ]);
        $nota->itens()->create([
            'produto_id' => $produto->id, 'numero_item' => 1, 'quantidade' => 10,
            'valor_unitario' => 110, 'valor_total' => 1100, 'desconto' => 0,
            'cfop' => '5102', 'cst_icms' => '00', 'bc_icms' => 1100, 'aliq_icms' => 18, 'valor_icms' => 198,
        ]);

        $sped = app(SpedFiscalService::class)->gerar($empresa, '2026-06-01', '2026-06-30');

        // Abertura e blocos essenciais presentes, no formato |REG|...|.
        $this->assertStringContainsString('|0000|', $sped);
        $this->assertStringContainsString('Empresa SPED', $sped);
        $this->assertStringContainsString('|0200|', $sped);     // item
        $this->assertStringContainsString('|C100|', $sped);     // nota
        $this->assertStringContainsString('|C170|', $sped);     // item da nota
        $this->assertStringContainsString('|9999|', $sped);     // encerramento

        // Cada linha começa e termina com pipe.
        foreach (array_filter(explode("\r\n", $sped)) as $linha) {
            $this->assertStringStartsWith('|', $linha);
            $this->assertStringEndsWith('|', $linha);
        }
    }

    public function test_periodo_sem_notas_gera_apenas_estrutura(): void
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $sped = app(SpedFiscalService::class)->gerar($empresa, '2026-01-01', '2026-01-31');

        $this->assertStringContainsString('|0000|', $sped);
        $this->assertStringContainsString('|9999|', $sped);
    }
}
