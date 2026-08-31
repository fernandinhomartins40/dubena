<?php

namespace App\Domain\Pedido;

/**
 * F3-05 — por qual porta o pedido entrou.
 *
 * O pedido não registrava isso. Quatro caminhos criam pedido hoje — o painel
 * admin, o app do consumidor, o app do entregador (venda em campo) e a ponte do
 * app legado — e no banco os quatro ficam idênticos.
 *
 * Perguntas que a revenda faz e o sistema não responde:
 *
 *  - "quanto do meu faturamento já vem do app?" — que é exatamente a decisão de
 *    investir ou não no canal digital;
 *  - "o ticket do telefone é maior que o do balcão?";
 *  - "esse pedido veio de onde?", quando algo deu errado nele.
 *
 * A alternativa que o legado usava para responder parte disso era um booleano
 * paralelo por canal (`*_app`, `*_web`), que é justamente o que F3-05 manda
 * substituir por uma dimensão: booleanos paralelos permitem estados
 * impossíveis (dois verdadeiros ao mesmo tempo) e exigem uma coluna nova a cada
 * canal que se acrescenta.
 */
enum CanalVenda: string
{
    /** Pedido lançado por atendente no painel — balcão ou telefone. */
    case INTERNO = 'INTERNO';

    /** App do consumidor. */
    case APP_CLIENTE = 'APP_CLIENTE';

    /** Venda em campo, feita pelo entregador. */
    case CAMPO = 'CAMPO';

    /** Central de vendas (televendas com fila e roteiro). */
    case CENTRAL = 'CENTRAL';

    /** Apps legados, pela ponte — sai quando eles forem desligados. */
    case LEGADO = 'LEGADO';

    /** Origem não registrada: pedidos anteriores a F3-05. */
    case DESCONHECIDO = 'DESCONHECIDO';

    public function rotulo(): string
    {
        return match ($this) {
            self::INTERNO => 'Atendimento interno',
            self::APP_CLIENTE => 'App do cliente',
            self::CAMPO => 'Venda em campo',
            self::CENTRAL => 'Central de vendas',
            self::LEGADO => 'App legado',
            self::DESCONHECIDO => 'Origem não registrada',
        };
    }

    /** O pedido nasceu de uma ação do próprio cliente, sem atendente? */
    public function eAutoatendimento(): bool
    {
        return $this === self::APP_CLIENTE;
    }
}
