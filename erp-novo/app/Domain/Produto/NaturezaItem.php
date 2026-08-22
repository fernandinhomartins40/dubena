<?php

namespace App\Domain\Produto;

/**
 * O que um item VENDIDO é, de fato.
 *
 * Antes disto tudo era "produto", inclusive o que não tem existência física.
 * Medido em produção: o item "Manutenção e Instalação" ficou com saldo de
 * estoque de **−2 unidades** — o sistema deu baixa de algo que nunca entrou e
 * nunca vai entrar. Um estoque negativo permanente e sem sentido.
 *
 * A natureza governa três comportamentos que não podem ser iguais:
 *
 *   PRODUTO  → tem estoque, tem NCM, sai em NF-e com ICMS.
 *   SERVICO  → NÃO tem estoque, é ISS/NFS-e (municipal), não entra na NF-e
 *              de produto. Ex.: instalação, manutenção, teste de estanqueidade.
 *   TAXA     → não tem estoque nem é mercadoria: é um valor cobrado (entrega,
 *              deslocamento). Existe como item para aparecer no cupom e no
 *              extrato do cliente, mas nunca movimenta armazém.
 */
enum NaturezaItem: string
{
    case PRODUTO = 'produto';
    case SERVICO = 'servico';
    case TAXA = 'taxa';

    /**
     * Movimenta armazém?
     *
     * É a pergunta que o PedidoService precisava fazer e não fazia: ele
     * percorria TODOS os itens dando baixa, sem distinguir natureza.
     */
    public function movimentaEstoque(): bool
    {
        return $this === self::PRODUTO;
    }

    /** Sai em NF-e de mercadoria (ICMS)? Serviço e taxa não. */
    public function ehMercadoria(): bool
    {
        return $this === self::PRODUTO;
    }

    /**
     * Tributa por ISS (competência municipal)?
     *
     * Só marca a natureza — a emissão de NFS-e varia por município e fica para
     * quando um cliente do SaaS precisar.
     */
    public function tributaIss(): bool
    {
        return $this === self::SERVICO;
    }

    /** Exige NCM/CEST? Só mercadoria. */
    public function exigeClassificacaoFiscal(): bool
    {
        return $this === self::PRODUTO;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::PRODUTO => 'Produto',
            self::SERVICO => 'Serviço',
            self::TAXA => 'Taxa',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(
            fn (self $n) => ['valor' => $n->value, 'rotulo' => $n->rotulo()],
            self::cases(),
        );
    }
}
