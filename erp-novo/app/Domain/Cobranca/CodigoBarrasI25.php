<?php

namespace App\Domain\Cobranca;

/**
 * Código de barras Interleaved 2 of 5 — o padrão do boleto bancário brasileiro
 * (FEBRABAN), desenhado em HTML puro para o PDF (T4.6).
 *
 * **Por que não uma biblioteca.** O I2of5 do boleto é um caso estreito e
 * totalmente especificado: 44 dígitos, sem dígito verificador embutido, largura
 * fina/grossa na razão 1:3. Renderizá-lo são ~40 linhas de barras `<div>`, que o
 * dompdf desenha sem precisar de extensão de imagem (o `gd` pode não estar no
 * container). Uma dependência nova aqui traria mais superfície que valor.
 *
 * **O que é inegociável:** um boleto com barcode errado é PIOR que boleto
 * nenhum — o cliente tenta pagar, o caixa recusa, e a culpa recai sobre a
 * revenda. Por isso a validação de entrada é rígida e as regras estão testadas.
 */
class CodigoBarrasI25
{
    /**
     * Padrões do I2of5: para cada dígito, 5 barras onde 0 = fina e 1 = grossa.
     * Tabela do padrão — não alterar sem conferir contra a especificação.
     */
    private const PADROES = [
        '0' => '00110', '1' => '10001', '2' => '01001', '3' => '11000', '4' => '00101',
        '5' => '10100', '6' => '01100', '7' => '00011', '8' => '10010', '9' => '01010',
    ];

    /**
     * Desenha o código de barras como HTML.
     *
     * @param  string  $codigo  os 44 dígitos do código de barras do boleto
     * @param  int  $altura  altura em pixels (padrão FEBRABAN: 13 mm ≈ 50 px)
     *
     * @throws \InvalidArgumentException se o código não for válido
     */
    public function html(string $codigo, int $altura = 50): string
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';

        if ($codigo === '') {
            throw new \InvalidArgumentException('Código de barras vazio.');
        }

        // O I2of5 codifica os dígitos EM PARES: um número ímpar de dígitos
        // produziria um código ilegível pelo leitor do caixa.
        if (strlen($codigo) % 2 !== 0) {
            $codigo = '0'.$codigo;
        }

        $barras = '';

        // Guarda inicial: fina-fina-fina-fina.
        $barras .= $this->barra(false, true).$this->barra(false, false)
                 .$this->barra(false, true).$this->barra(false, false);

        // Intercala os dígitos dos pares: o 1º vira barra, o 2º vira espaço.
        for ($i = 0; $i < strlen($codigo); $i += 2) {
            $a = self::PADROES[$codigo[$i]] ?? null;
            $b = self::PADROES[$codigo[$i + 1]] ?? null;

            if ($a === null || $b === null) {
                throw new \InvalidArgumentException('Código de barras com caractere inválido.');
            }

            for ($j = 0; $j < 5; $j++) {
                $barras .= $this->barra($a[$j] === '1', true);   // barra (preta)
                $barras .= $this->barra($b[$j] === '1', false);  // espaço (branco)
            }
        }

        // Guarda final: grossa-fina-fina.
        $barras .= $this->barra(true, true).$this->barra(false, false).$this->barra(false, true);

        return "<div style=\"height:{$altura}px;line-height:0;font-size:0;white-space:nowrap\">{$barras}</div>";
    }

    /**
     * Uma barra ou espaço.
     *
     * A razão 1:3 entre fina e grossa é o que o leitor usa para distinguir os
     * bits — mudar esses números quebra a leitura.
     */
    private function barra(bool $grossa, bool $preta): string
    {
        $largura = $grossa ? 3 : 1;
        $cor = $preta ? '#000' : '#fff';

        return "<div style=\"display:inline-block;width:{$largura}px;height:100%;background:{$cor}\"></div>";
    }
}
