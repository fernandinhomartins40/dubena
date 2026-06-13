<?php
namespace App\Services;

use Eduardokum\LaravelBoleto\Cnab\Remessa\Cnab400\Banco\Caixa as RemessaCaixa;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Eduardokum\LaravelBoleto\Util;

class RemessaCaixaService extends RemessaCaixa
{

    private $statusGeral;

    public function setStatus($status)
    {
        $this->statusGeral = $status;
    }
    
    public function addBoleto(BoletoContract $boleto)
    {
        $this->iniciaDetalhe();

        $this->add(1, 1, '1');
        $this->add(2, 3, strlen(Util::onlyNumbers($this->getBeneficiario()->getDocumento())) == 14 ? '02' : '01');
        $this->add(4, 17, Util::formatCnab('9L', $this->getBeneficiario()->getDocumento(), 14));
        $this->add(18, 21, Util::formatCnab('9', $this->getAgencia(), 4));
        $this->add(22, 27, Util::formatCnab('9', $this->getCodigoCliente(), 6));
        $this->add(28, 28, '2'); // ‘1’ = Banco Emite ‘2’ = Cliente Emite
        $this->add(29, 29, '0'); // ‘0’ = Postagem pelo Beneficiário ‘1’ = Pagador via Correio ‘2’ = Beneficiário via Agência CAIXA ‘3’ = Pagador via e-mail
        $this->add(30, 31, '00');
        // $this->add(32, 56, Util::formatCnab('X', $boleto->getNumeroControle(), 25)); // numero de controle ORIGINAL
        $this->add(32, 56, Util::formatCnab('X', $boleto->getNumeroDocumento(), 25)); // numero de controle
        // dd($boleto->getNossoNumeroBoleto());
        $this->add(57, 73, Util::formatCnab('9', $boleto->getNossoNumero(), 17));
        $this->add(74, 76, '');
        $this->add(77, 106, '');
        $this->add(107, 108, Util::formatCnab('9', $this->getCarteiraNumero(), 2));
        $this->add(109, 110, $this->statusGeral);

        $this->add(111, 120, Util::formatCnab('X', $boleto->getNumeroDocumento(), 10));
        $this->add(121, 126, $boleto->getDataVencimento()->format('dmy'));
        $this->add(127, 139, Util::formatCnab('9', $boleto->getValor(), 13, 2));
        $this->add(140, 142, $this->getCodigoBanco());
        $this->add(143, 147, '00000');
        $this->add(148, 149, $boleto->getEspecieDocCodigo());
        $this->add(150, 150, $boleto->getAceite());
        $this->add(151, 156, $boleto->getDataDocumento()->format('dmy'));
        $this->add(157, 158, self::INSTRUCAO_SEM);
        $this->add(159, 160, self::INSTRUCAO_SEM);
        
        if ($boleto->getDiasProtesto() > 0) {
            $this->add(157, 158, self::INSTRUCAO_PROTESTAR_VENC_XX);
        } elseif ($boleto->getDiasBaixaAutomatica() > 0) {
            $this->add(157, 158, self::INSTRUCAO_DEVOLVER_VENC_XX);
        }
        $juros = 0;
        if ($boleto->getJuros() > 0) {
            $juros = Util::percent($boleto->getValor(), $boleto->getJuros())/30;
        }
        $this->add(161, 173, Util::formatCnab('9', $juros, 13, 2));
        // $this->add(174, 179, $boleto->getDesconto() > 0 ? $boleto->getDataDesconto()->format('dmy') : '000000'); ORIGINAL
        $this->add(174, 179, '000000');
        // $this->add(180, 192, Util::formatCnab('9', $boleto->getDesconto(), 13, 2)); ORIGNAL
        $this->add(180, 192, Util::formatCnab('9', 0, 13, 2));
        $this->add(193, 205, Util::formatCnab('9', 0, 13, 2));
        // $this->add(206, 218, Util::formatCnab('9', 0, 13, 2)); original
        $this->add(206, 218, Util::formatCnab('9', $boleto->getDesconto(), 13, 2));
        $this->add(219, 220, strlen(Util::onlyNumbers($boleto->getPagador()->getDocumento())) == 14 ? '02' : '01');
        $this->add(221, 234, Util::formatCnab('9L', $boleto->getPagador()->getDocumento(), 14));
        $this->add(235, 274, Util::formatCnab('X', $boleto->getPagador()->getNome(), 40));
        $this->add(275, 314, Util::formatCnab('X', $boleto->getPagador()->getEndereco(), 40));
        $this->add(315, 326, Util::formatCnab('X', $boleto->getPagador()->getBairro(), 12));
        $this->add(327, 334, Util::formatCnab('9L', $boleto->getPagador()->getCep(), 8));
        $this->add(335, 349, Util::formatCnab('X', $boleto->getPagador()->getCidade(), 15));
        $this->add(350, 351, Util::formatCnab('X', $boleto->getPagador()->getUf(), 2));
        $this->add(352, 357, $boleto->getJurosApos() === false ? '000000' : $boleto->getDataVencimento()->copy()->addDays($boleto->getJurosApos())->format('dmy'));
        $this->add(358, 367, Util::formatCnab('9', Util::nFloat($boleto->getMulta()), 10, 2)); # modificado por: Lucas S Veque
        // $this->add(358, 367, Util::formatCnab('9', Util::percent($boleto->getValor(), $boleto->getMulta()), 10, 2)); ORIGINAL
        $this->add(368, 389, Util::formatCnab('X', $boleto->getSacadorAvalista() ? $boleto->getSacadorAvalista()->getNome() : '', 22));
        $this->add(390, 391, '00');
        $this->add(392, 393, Util::formatCnab('9', $boleto->getDiasProtesto($boleto->getDiasBaixaAutomatica()), 2));
        // Código da Moeda - Código adotado para identificar a moeda referenciada no Título. Informar fixo: ‘1’ = REAL
        $this->add(394, 394, Util::formatCnab('9', 1, 1));
        $this->add(395, 400, Util::formatCnab('9', $this->iRegistros + 1, 6));

        return $this;
    }
}
