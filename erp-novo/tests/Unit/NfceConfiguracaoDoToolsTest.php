<?php

namespace Tests\Unit;

use App\Domain\Fiscal\Drivers\NFePHPSefazDriver;
use App\Models\Fiscal\ConfigFiscal;
use RuntimeException;
use Tests\TestCase;

/**
 * Ao assinar uma NFC-e o NFePHP acrescenta o QR Code e EXIGE CSC e CSCid na
 * configuração (`Common\Tools::addQRCode`). Eles não iam: toda NFC-e real
 * falharia na assinatura, mesmo com o certificado instalado.
 *
 * A prova completa é na SEFAZ de homologação, com o certificado A1 do
 * cliente; aqui se prova o que é nosso — a configuração entregue à biblioteca.
 */
class NfceConfiguracaoDoToolsTest extends TestCase
{
    private array $emit = ['ambiente' => 2, 'razao_social' => 'Revenda', 'cnpj' => '04190715000105', 'uf' => 'PR'];

    private function fiscal(?string $id, ?string $token): ConfigFiscal
    {
        return (new ConfigFiscal)->forceFill(['csc_id' => $id, 'csc_token' => $token, 'ambiente' => 2]);
    }

    public function test_nfce_leva_csc_e_cscid(): void
    {
        $config = NFePHPSefazDriver::configuracaoDoTools($this->emit, $this->fiscal('000001', 'CSC-SECRETO'), 65);

        $this->assertSame('CSC-SECRETO', $config['CSC']);
        $this->assertSame('000001', $config['CSCid']);
        $this->assertSame(2, $config['tpAmb']);
    }

    public function test_nfce_sem_csc_falha_dizendo_onde_cadastrar(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('aba Fiscal da empresa');

        NFePHPSefazDriver::configuracaoDoTools($this->emit, $this->fiscal(null, null), 65);
    }

    /** NF-e (55) não usa CSC — e não pode passar a exigir. */
    public function test_nfe_nao_exige_csc(): void
    {
        $config = NFePHPSefazDriver::configuracaoDoTools($this->emit, $this->fiscal(null, null), 55);

        $this->assertArrayNotHasKey('CSC', $config);
    }
}
