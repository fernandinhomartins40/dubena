<?php

namespace Tests\Domain;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Tenant\TenantContext;
use App\Models\Caixa\Conta;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * BASELINE C4 — regras de caixa que a auditoria apontou como ausentes:
 *  - movimento em caixa FECHADO é recusado (lançamento exige caixa aberto);
 *  - lançamento em caixa fechado AUTORIZADO é permitido (retroativo);
 *  - estorno funciona mesmo com o caixa fechado (correção);
 *  - baixa de títulos em LOTE (tudo-ou-nada).
 */
class CaixaRegrasC4Test extends TestCase
{
    use RefreshDatabase;

    private CaixaService $caixa;

    private FinanceiroService $financeiro;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caixa = app(CaixaService::class);
        $this->financeiro = app(FinanceiroService::class);
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
    }

    private function conta(bool $aberta = true): Conta
    {
        $conta = $this->caixa->criarConta([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Caixa',
            'saldo_inicial' => 100,
        ]);
        if (! $aberta) {
            $this->caixa->abrir($conta->id);   // registra o fechamento aberto
            $this->caixa->fechar($conta->id);  // …para poder fechar de verdade
        }

        return $conta->refresh();
    }

    public function test_movimento_em_caixa_fechado_e_recusado(): void
    {
        $conta = $this->conta(aberta: false);

        $this->expectException(ValidationException::class);
        $this->caixa->movimentar($conta->id, 50, CaixaService::AJUSTE, ['origem' => 'teste']);
    }

    public function test_lancamento_em_caixa_fechado_autorizado_e_permitido(): void
    {
        $conta = $this->conta(aberta: false);

        $mov = $this->caixa->lancarEmCaixaFechado($conta->id, 50, CaixaService::AJUSTE, ['origem' => 'retroativo']);

        $this->assertEqualsWithDelta(150.0, (float) $conta->refresh()->saldo_atual, 0.001);
        $this->assertSame(CaixaService::AJUSTE, $mov->tipo);
    }

    public function test_estorno_funciona_com_caixa_fechado(): void
    {
        $conta = $this->conta(aberta: true);
        $mov = $this->caixa->movimentar($conta->id, 30, CaixaService::AJUSTE, ['origem' => 'x']);
        $this->caixa->abrir($conta->id);
        $this->caixa->fechar($conta->id);

        // Estorno é correção: não pode ser bloqueado pelo caixa fechado.
        $this->caixa->estornar($mov->id);

        $this->assertEqualsWithDelta(100.0, (float) $conta->refresh()->saldo_atual, 0.001);
    }

    public function test_baixa_de_titulos_em_lote(): void
    {
        $conta = $this->conta(aberta: true);

        // 3 títulos a receber (1 parcela cada).
        $parcelas = [];
        foreach ([100, 200, 300] as $valor) {
            $fin = $this->financeiro->criar([
                'empresa_id' => $this->empresa->id,
                'grupo_id' => $this->empresa->grupo_id,
                'pagarreceber' => 'R',
                'descricao' => "Titulo {$valor}",
                'valor' => $valor,
                'origem' => 'teste',
            ]);
            $parcelas[] = ['parcela_id' => $fin->parcelas->first()->id];
        }

        $movimentos = $this->caixa->baixarTitulos($conta->id, $parcelas);

        $this->assertCount(3, $movimentos);
        // 100 inicial + 100 + 200 + 300 = 700.
        $this->assertEqualsWithDelta(700.0, (float) $conta->refresh()->saldo_atual, 0.001);
        // Invariante preservado.
        $this->assertEqualsWithDelta(
            (float) $conta->saldo_atual,
            $this->caixa->saldoDerivado($conta->id),
            0.001,
        );
    }

    public function test_baixa_em_lote_vazia_bloqueia(): void
    {
        $conta = $this->conta();
        $this->expectException(ValidationException::class);
        $this->caixa->baixarTitulos($conta->id, []);
    }
}
