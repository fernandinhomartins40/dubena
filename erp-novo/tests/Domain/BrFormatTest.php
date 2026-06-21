<?php

namespace Tests\Domain;

use App\Domain\Shared\BrFormat;
use PHPUnit\Framework\TestCase;

/**
 * Garante que a conversão string-BR → número (substituta dos *Oracle) é correta.
 * É a peça que o ETL usa para limpar o dado legado.
 */
class BrFormatTest extends TestCase
{
    public function test_converte_valores_br_para_numero(): void
    {
        $this->assertSame(1234.56, BrFormat::toNumber('1.234,56'));
        $this->assertSame(1234.56, BrFormat::toNumber('R$ 1.234,56'));
        $this->assertSame(12.5, BrFormat::toNumber('12,50 %'));
        $this->assertSame(0.0, BrFormat::toNumber('R$ 0,00'));
        $this->assertSame(1234.56, BrFormat::toNumber(1234.56));
        $this->assertNull(BrFormat::toNumber(''));
        $this->assertNull(BrFormat::toNumber(null));
        $this->assertSame(-50.0, BrFormat::toNumber('-50,00'));
    }

    public function test_converte_data_br_para_carbon(): void
    {
        $this->assertSame('2025-12-31', BrFormat::toDate('31/12/2025')->toDateString());
        $this->assertSame('2025-12-31', BrFormat::toDate('2025-12-31')->toDateString());
        $this->assertNull(BrFormat::toDate(''));
        $this->assertNull(BrFormat::toDate(null));
    }

    public function test_format_volta_para_br_quando_necessario(): void
    {
        $this->assertSame('1.234,56', BrFormat::format(1234.56));
        $this->assertSame('0,00', BrFormat::format(null));
    }
}
