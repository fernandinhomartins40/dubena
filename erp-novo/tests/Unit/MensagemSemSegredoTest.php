<?php

namespace Tests\Unit;

use App\Domain\Shared\MensagemSemSegredo;
use PHPUnit\Framework\TestCase;

/** PIE P2-E-08 — a chave do Google e o endereço não chegam ao log. */
class MensagemSemSegredoTest extends TestCase
{
    public function test_mascara_chave_e_endereco_da_url_na_mensagem_do_curl(): void
    {
        $original = 'cURL error 28: Operation timed out for https://maps.googleapis.com/maps/api/geocode/json'
            .'?address=Rua%20das%20Flores%2C%20123&key=AIzaSyFICTICIA';

        $limpa = MensagemSemSegredo::limpar($original);

        $this->assertStringNotContainsString('AIzaSyFICTICIA', $limpa);
        $this->assertStringNotContainsString('Flores', $limpa);
        $this->assertStringContainsString('cURL error 28', $limpa);
        $this->assertStringContainsString('key=***', $limpa);
    }
}
