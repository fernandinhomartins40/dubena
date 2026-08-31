<?php

namespace Tests\Domain;

use App\Domain\Cobranca\Cnab\CnabHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * F5-03 — a matemática bancária validada por **vetores independentes da
 * implementação**.
 *
 * ## O que a medição encontrou
 *
 * O teste que existia comparava a função **com ela mesma**:
 *
 * ```php
 * $this->assertSame(CnabHelper::modulo10($x), CnabHelper::modulo10($x));
 * ```
 *
 * Isso passa com qualquer algoritmo, inclusive um que devolvesse sempre zero. E
 * o vizinho não era melhor: `modulo11` só verificava que o retorno era `int`, e
 * o fator de vencimento só conferia o **comprimento** — quatro dígitos —, nunca
 * o valor.
 *
 * É a mesma classe de defeito do guardião de FK que passou meses aprovando uma
 * lista vazia: **verde sem verificar nada**. Aqui o custo é maior, porque um
 * dígito verificador errado produz boleto que o banco recusa na compensação, ou
 * pior, que é aceito e creditado na conta de outro.
 *
 * ## De onde vêm os números deste arquivo
 *
 * Foram calculados a partir da **especificação FEBRABAN**, não do código PHP:
 *
 *  - **módulo 10**: pesos 2 e 1 alternados da direita para a esquerda; produto
 *    maior que 9 tem os dígitos somados; DV = 10 − (soma mod 10), e 0 quando o
 *    resto é zero;
 *  - **fator de vencimento**: dias corridos desde **07/10/1997** mais 1000. O
 *    caso 03/07/2000 → **2000** é o exemplo publicado pela própria FEBRABAN, e
 *    serve de âncora: se a data-base estiver errada no código, este caso falha.
 *
 * Por isso os valores estão escritos como literais. Derivá-los em PHP dentro do
 * teste recriaria a tautologia com outra roupa.
 */
class CnabVetoresOficiaisTest extends TestCase
{
    /**
     * Campos da linha digitável do exemplo clássico FEBRABAN, com o DV que a
     * especificação manda calcular para cada um.
     *
     * @return list<array{string, int}>
     */
    public static function vetoresModulo10(): array
    {
        return [
            ['0019050095', 9],
            ['4014481606', 9],
            ['9068093503', 9],

            // Casos de fronteira, onde implementações erradas costumam divergir.
            ['0', 0],          // soma zero → DV zero, e NÃO 10 (o erro clássico)
            ['1', 8],          // 1×2 = 2 → 10 − 2 = 8
            ['5', 9],          // 5×2 = 10 → soma os dígitos: 1+0 = 1 → 10 − 1 = 9
            ['9', 1],          // 9×2 = 18 → 1+8 = 9 → 10 − 9 = 1
            ['55', 4],         // alterna os pesos: 5×1 + (5×2 → 1) = 6 → 10 − 6 = 4
        ];
    }

    #[DataProvider('vetoresModulo10')]
    public function test_modulo10_bate_com_a_especificacao(string $entrada, int $dvEsperado): void
    {
        $this->assertSame($dvEsperado, CnabHelper::modulo10($entrada));
    }

    /**
     * Fator de vencimento: dias desde 07/10/1997 + 1000.
     *
     * @return list<array{string, string}>
     */
    public static function vetoresFatorVencimento(): array
    {
        return [
            // A data-base em pessoa: dia zero → 1000.
            ['1997-10-07', '1000'],

            // O exemplo publicado pela FEBRABAN. Se a data-base do código
            // estiver errada por um dia sequer, este caso acusa.
            ['2000-07-03', '2000'],

            ['2002-05-01', '2667'],
            ['2022-05-25', '9996'],
        ];
    }

    #[DataProvider('vetoresFatorVencimento')]
    public function test_fator_de_vencimento_bate_com_a_especificacao(string $data, string $esperado): void
    {
        $this->assertSame($esperado, CnabHelper::fatorVencimento($data));
    }

    /**
     * Data anterior à base não tem fator — devolve zeros em vez de número
     * negativo, que viraria lixo no código de barras.
     */
    public function test_data_anterior_a_base_nao_produz_fator_negativo(): void
    {
        $this->assertSame('0000', CnabHelper::fatorVencimento('1990-01-01'));
    }

    /**
     * Depois de 21/02/2025 o fator estoura 9999 e a FEBRABAN manda reiniciar em
     * 1000. Sem essa regra o campo transborda e o boleto sai inválido.
     */
    public function test_fator_reinicia_em_1000_apos_estourar(): void
    {
        $fator = CnabHelper::fatorVencimento('2030-01-01');

        $this->assertSame(4, strlen($fator));
        $this->assertGreaterThanOrEqual(1000, (int) $fator, 'o fator reiniciado nunca é menor que 1000');
        $this->assertLessThanOrEqual(9999, (int) $fator);
    }

    /**
     * DV do código de barras: a regra manda trocar 0, 10 e 11 por 1 — nunca
     * deixar passar um desses.
     */
    public function test_dv_do_codigo_de_barras_nunca_e_zero_dez_ou_onze(): void
    {
        // Varre um espaço grande em vez de um caso só: a regra existe
        // justamente para as entradas raras que caem em 0/10/11, e um único
        // exemplo tem toda a chance de não ser uma delas.
        for ($i = 0; $i < 200; $i++) {
            $base = str_pad((string) ($i * 7919), 43, '0', STR_PAD_LEFT);
            $dv = CnabHelper::modulo11($base, 9, true);

            $this->assertGreaterThanOrEqual(1, $dv);
            $this->assertLessThanOrEqual(9, $dv);
        }
    }

    /**
     * A linha digitável tem 47 dígitos, e os três primeiros campos carregam cada
     * um o seu DV módulo 10 — que este teste reconfere pela especificação.
     *
     * Sem reconferir, "47 dígitos" aceitaria uma linha inteira de zeros.
     */
    public function test_linha_digitavel_carrega_os_dv_corretos_nos_tres_campos(): void
    {
        $barras = '00193373700000001000500940144816060680935031';
        $this->assertSame(44, strlen($barras), 'o código de barras tem 44 posições');

        $linha = preg_replace('/\D/', '', CnabHelper::linhaDigitavel($barras));

        $this->assertSame(47, strlen((string) $linha));

        // Campo 1: posições 1-9 da linha + DV na 10ª.
        $campo1 = substr((string) $linha, 0, 9);
        $this->assertSame(
            CnabHelper::modulo10($campo1),
            (int) substr((string) $linha, 9, 1),
            'o DV do campo 1 tem de fechar pelo módulo 10',
        );

        // Campo 2: posições 11-20 + DV na 21ª.
        $campo2 = substr((string) $linha, 10, 10);
        $this->assertSame(
            CnabHelper::modulo10($campo2),
            (int) substr((string) $linha, 20, 1),
            'o DV do campo 2 tem de fechar pelo módulo 10',
        );

        // Campo 3: posições 22-31 + DV na 32ª.
        $campo3 = substr((string) $linha, 21, 10);
        $this->assertSame(
            CnabHelper::modulo10($campo3),
            (int) substr((string) $linha, 31, 1),
            'o DV do campo 3 tem de fechar pelo módulo 10',
        );
    }
}
