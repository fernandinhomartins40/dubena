<?php

namespace Tests\Domain;

use App\Domain\Shared\CalculoParcelasService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Regra central de parcelamento (substitui calculoParcelas() do legado).
 * Verifica: divisão por nº de parcelas, sobra na última, e por configuração.
 */
class CalculoParcelasTest extends TestCase
{
    public function test_divide_em_parcelas_iguais_com_sobra_na_ultima(): void
    {
        $svc = new CalculoParcelasService();
        $base = CarbonImmutable::parse('2025-01-10');

        // 100,00 em 3x → 33,33 + 33,33 + 33,34 (soma exata)
        $parcelas = $svc->calcular(100.00, $base, numParcelas: 3, diasPrimeira: 30, intervalo: 30);

        $this->assertCount(3, $parcelas);
        $this->assertSame(33.33, $parcelas[0]->valor);
        $this->assertSame(33.34, $parcelas[2]->valor);
        $this->assertSame(100.00, round(array_sum(array_map(fn ($p) => $p->valor, $parcelas)), 2));
        $this->assertSame('2025-02-09', $parcelas[0]->vencimento->toDateString());
    }

    public function test_usa_configuracao_de_percentual_quando_existe(): void
    {
        $svc = new CalculoParcelasService();
        $base = CarbonImmutable::parse('2025-01-01');

        $config = [
            ['percentual' => 50.0, 'dias' => 0],
            ['percentual' => 50.0, 'dias' => 30],
        ];
        $parcelas = $svc->calcular(200.00, $base, parcelasConfig: $config);

        $this->assertCount(2, $parcelas);
        $this->assertSame(100.00, $parcelas[0]->valor);
        $this->assertSame(100.00, $parcelas[1]->valor);
        $this->assertSame('2025-01-31', $parcelas[1]->vencimento->toDateString());
    }

    public function test_a_vista_gera_uma_parcela(): void
    {
        $svc = new CalculoParcelasService();
        $parcelas = $svc->calcular(80.00, CarbonImmutable::parse('2025-03-01'), numParcelas: 1, diasPrimeira: 0);

        $this->assertCount(1, $parcelas);
        $this->assertSame(80.00, $parcelas[0]->valor);
    }
}
