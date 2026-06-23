<?php

namespace Tests\Domain;

use App\Domain\Rh\ComissaoService;
use App\Models\Rh\ColaboradorComissao;
use App\Models\Rh\ComissaoExcecao;
use PHPUnit\Framework\TestCase;

/**
 * BASELINE C5 — comissão (CASO DE OURO). Replica, centavo a centavo, a fórmula do
 * legado (ReportcomissoesController). Cálculo puro, sem banco: a regra é instanciada
 * em memória. Se a fórmula mudar e divergir do legado, este teste quebra.
 */
class ComissaoServiceTest extends TestCase
{
    private ComissaoService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ComissaoService;
    }

    private function regra(int $tipo, float $percentual = 0, float $empresaValor = 0): ColaboradorComissao
    {
        $r = new ColaboradorComissao([
            'tipo_comissao' => $tipo,
            'percentual' => $percentual,
            'empresa_valor' => $empresaValor,
        ]);
        // Sem exceções carregadas.
        $r->setRelation('excecoes', collect());

        return $r;
    }

    public function test_tipo_1_percentual_sobre_o_valor(): void
    {
        // 5 un x R$100 = 500; sem desconto; 10% → 50,00.
        $r = $this->svc->calcularItem([
            'preco_unitario' => 100, 'quantidade' => 5, 'valor_venda' => 500, 'valor_desconto' => 0,
        ], $this->regra(1, percentual: 10));

        $this->assertSame(500.0, $r['base']);
        $this->assertSame(50.0, $r['percentual']);
        $this->assertSame(0.0, $r['repasse']);
    }

    public function test_tipo_2_repasse_retem_valor_fixo_por_unidade(): void
    {
        // 5 un x R$100 = 500; empresa fica com R$80/un → repasse = 500 - 400 = 100,00.
        $r = $this->svc->calcularItem([
            'preco_unitario' => 100, 'quantidade' => 5, 'valor_venda' => 500, 'valor_desconto' => 0,
        ], $this->regra(2, empresaValor: 80));

        $this->assertSame(0.0, $r['percentual']);
        $this->assertSame(100.0, $r['repasse']);
    }

    public function test_desconto_do_pedido_e_rateado_proporcionalmente(): void
    {
        // valor item 500, venda 450, desconto 50 → bruto 500; proporcional = 500 - (500/500)*50 = 450.
        // tipo 1 10% → 45,00.
        $r = $this->svc->calcularItem([
            'preco_unitario' => 100, 'quantidade' => 5, 'valor_venda' => 450, 'valor_desconto' => 50,
        ], $this->regra(1, percentual: 10));

        $this->assertSame(450.0, $r['base']);
        $this->assertSame(45.0, $r['percentual']);
    }

    public function test_desconto_de_convenio_reduz_a_base(): void
    {
        // 500 com convênio 20% → 400; tipo 1 10% → 40,00.
        $r = $this->svc->calcularItem([
            'preco_unitario' => 100, 'quantidade' => 5, 'valor_venda' => 500, 'valor_desconto' => 0,
            'comissao_convenio' => 20,
        ], $this->regra(1, percentual: 10));

        $this->assertSame(400.0, $r['base']);
        $this->assertSame(40.0, $r['percentual']);
    }

    public function test_excecao_tipo_1_sobrescreve_percentual(): void
    {
        $regra = $this->regra(1, percentual: 10);
        $exc = new ComissaoExcecao(['tipo_excecao' => 1, 'valor_excecao' => 5]);
        $regra->setRelation('excecoes', collect([$exc]));

        // 500 * 5% = 25,00 (usa a exceção, não os 10% da regra).
        $r = $this->svc->calcularItem([
            'preco_unitario' => 100, 'quantidade' => 5, 'valor_venda' => 500, 'valor_desconto' => 0,
        ], $regra);

        $this->assertSame(25.0, $r['percentual']);
    }

    public function test_total_colaborador_soma_percentual_e_repasse(): void
    {
        $item = ['preco_unitario' => 100, 'quantidade' => 5, 'valor_venda' => 500, 'valor_desconto' => 0];

        $total = $this->svc->totalColaborador([
            [$item, $this->regra(1, percentual: 10)], // 50 percentual
            [$item, $this->regra(2, empresaValor: 80)], // 100 repasse
        ]);

        $this->assertSame(50.0, $total['percentual']);
        $this->assertSame(100.0, $total['repasse']);
        $this->assertSame(150.0, $total['total']);
    }
}
