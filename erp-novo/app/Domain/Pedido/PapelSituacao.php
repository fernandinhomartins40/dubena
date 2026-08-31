<?php

namespace App\Domain\Pedido;

/**
 * F3-04A — o PAPEL operacional de uma situação de pedido, declarado.
 *
 * `EfeitoPedido` governa a máquina de estados (o que a transição faz com
 * estoque e financeiro) e tem só três valores. Isso está certo e não muda aqui.
 *
 * O problema é outro: existem momentos operacionais que o efeito não distingue.
 * "Aguardando separação" e "Saiu para entrega" são ambos `PENDENTE` — nenhum
 * baixa estoque —, mas só o segundo significa que a mercadoria está na rua.
 *
 * Sem uma marca explícita, o código que precisava dessa distinção procurava a
 * situação pela DESCRIÇÃO:
 *
 *     LIKE '%saiu%' OR LIKE '%rota%' OR LIKE '%caminho%'
 *
 * Isso funciona para uma revenda que escreve em português e escolheu essas
 * palavras. Para a segunda revenda — que chama de "Em trânsito", ou opera em
 * espanhol — a busca não acha nada, e o sistema **criava uma situação nova**
 * para conseguir continuar. O resultado é o pior possível: duas situações
 * concorrentes significando a mesma coisa, uma delas invisível na configuração
 * que o cliente montou.
 *
 * `papel` é a resposta: a intenção fica declarada no cadastro, em vez de
 * adivinhada no texto. Toda situação nasce `NENHUM` — o papel é uma afirmação
 * deliberada de quem configura, não um default que o código infere.
 */
enum PapelSituacao: string
{
    /** Situação comum: existe, mas não marca nenhum momento operacional especial. */
    case NENHUM = 'NENHUM';

    /**
     * A mercadoria saiu para entrega.
     *
     * É o gatilho de "iniciar rota" no app do entregador, e o que o cliente vê
     * como "a caminho". Exatamente um por grupo — duas situações disputando o
     * papel deixariam a ação operacional sem alvo definido.
     */
    case EM_ROTA = 'EM_ROTA';

    /** Rótulo para telas de configuração. */
    public function rotulo(): string
    {
        return match ($this) {
            self::NENHUM => 'Sem papel especial',
            self::EM_ROTA => 'Saiu para entrega (em rota)',
        };
    }

    /**
     * Papéis que só podem ser usados uma vez por grupo.
     *
     * @return list<self>
     */
    public static function exclusivos(): array
    {
        return [self::EM_ROTA];
    }
}
