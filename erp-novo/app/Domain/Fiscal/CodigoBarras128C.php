<?php

namespace App\Domain\Fiscal;

/**
 * Código de barras Code 128, subconjunto C — o padrão do DANFE (T4.2/item 8).
 *
 * **Por que não reaproveitar o I2of5 do boleto.** Os dois codificam dígitos, mas
 * o Manual de Integração da NF-e é explícito: a chave de acesso no DANFE vai em
 * **Code 128C**. Um leitor configurado para DANFE não decodifica I2of5, e a
 * chave lida errada no destino é uma mercadoria que não circula. São padrões
 * diferentes para documentos diferentes — não é duplicação evitável.
 *
 * **Por que não uma biblioteca.** Mesmo motivo do boleto: o caso é estreito e
 * totalmente especificado (44 dígitos, sempre pares, sempre subconjunto C), e
 * desenhar as barras em `<div>` dispensa a extensão `gd` no container.
 *
 * **Estrutura do símbolo:** START C (105), os 22 pares de dígitos, o dígito
 * verificador (módulo 103 ponderado pela posição) e STOP. Cada caractere são 6
 * elementos alternando barra/espaço, cujas larguras somam 11 módulos.
 */
class CodigoBarras128C
{
    /**
     * Larguras dos 6 elementos de cada valor do Code 128 (0..106).
     *
     * Tabela da especificação ISO/IEC 15417 — não alterar sem conferir contra
     * ela. O índice é o VALOR do caractere; no subconjunto C, os valores 0..99
     * representam diretamente o par de dígitos "00".."99".
     */
    private const PADROES = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '233111',
    ];

    /** START C. */
    private const START_C = 105;

    /** Padrão de parada: 7 elementos (o único com barra final de 2 módulos). */
    private const STOP = '2331112';

    /**
     * Desenha a chave de acesso como HTML.
     *
     * @param  string  $chave  os 44 dígitos da chave de acesso da NF-e
     * @param  int  $altura  altura em pixels (o DANFE pede ~13 mm ≈ 50 px)
     * @param  int  $modulo  largura do módulo em pixels
     *
     * @throws \InvalidArgumentException se a chave não tiver 44 dígitos
     */
    public function html(string $chave, int $altura = 50, int $modulo = 1): string
    {
        $chave = preg_replace('/\D/', '', $chave) ?? '';

        // 44 é exato, não "pelo menos": a chave da NF-e tem tamanho fixo, e
        // aceitar outro tamanho aqui só produziria um símbolo ilegível no
        // destino, descoberto tarde demais.
        if (strlen($chave) !== 44) {
            throw new \InvalidArgumentException('A chave de acesso deve ter 44 dígitos.');
        }

        // O subconjunto C codifica PARES; 44 é par, então nunca há sobra.
        $valores = [];
        for ($i = 0; $i < 44; $i += 2) {
            $valores[] = (int) substr($chave, $i, 2);
        }

        $barras = $this->desenhar(self::PADROES[self::START_C], $altura, $modulo);

        foreach ($valores as $v) {
            $barras .= $this->desenhar(self::PADROES[$v], $altura, $modulo);
        }

        $barras .= $this->desenhar(self::PADROES[$this->verificador($valores)], $altura, $modulo);
        $barras .= $this->desenhar(self::STOP, $altura, $modulo);

        return "<div style=\"height:{$altura}px;line-height:0;font-size:0;white-space:nowrap\">{$barras}</div>";
    }

    /**
     * Dígito verificador do Code 128: soma do START mais cada valor multiplicado
     * pela sua posição (1-based), módulo 103.
     *
     * @param  list<int>  $valores
     */
    public function verificador(array $valores): int
    {
        $soma = self::START_C;

        foreach ($valores as $i => $v) {
            $soma += $v * ($i + 1);
        }

        return $soma % 103;
    }

    /**
     * Converte um padrão de larguras em barras alternadas: o 1º elemento é
     * sempre barra (preta), o 2º espaço, e assim por diante.
     */
    private function desenhar(string $padrao, int $altura, int $modulo): string
    {
        $html = '';

        for ($i = 0; $i < strlen($padrao); $i++) {
            $largura = ((int) $padrao[$i]) * $modulo;
            $cor = $i % 2 === 0 ? '#000' : '#fff';
            $html .= "<div style=\"display:inline-block;width:{$largura}px;height:100%;background:{$cor}\"></div>";
        }

        return $html;
    }

    /**
     * Chave formatada em grupos de 4, como o DANFE exibe abaixo do barcode.
     */
    public function chaveFormatada(string $chave): string
    {
        $chave = preg_replace('/\D/', '', $chave) ?? '';

        return trim(chunk_split($chave, 4, ' '));
    }
}
