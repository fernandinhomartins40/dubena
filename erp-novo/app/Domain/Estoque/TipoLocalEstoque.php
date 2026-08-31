<?php

namespace App\Domain\Estoque;

/**
 * F3-06 — que espécie de LUGAR é um setor de estoque.
 *
 * `setores` tem descrição e `ativo`, e mais nada. Isso faz com que coisas de
 * naturezas diferentes convivam na mesma lista sem distinção:
 *
 *  - o depósito da revenda, que é um endereço físico;
 *  - o estoque em poder de um franqueado, criado automaticamente como
 *    "Em poder de Fulano" na primeira carga (`CargaFranqueadoService`);
 *  - o que estiver no veículo, que se move.
 *
 * A consequência prática aparece no seletor: o operador que vai lançar uma
 * entrada de mercadoria vê "Em poder de João" ao lado de "Depósito central" e
 * pode escolher qualquer um. Um lançamento no lugar errado não dá erro — dá um
 * saldo que não bate, descoberto no inventário.
 *
 * E a consequência de produto é mais séria: `custodia()` é o que permite a uma
 * revenda perguntar "quanto do meu estoque está fora do depósito?", que hoje
 * não tem resposta porque a pergunta não é expressável.
 */
enum TipoLocalEstoque: string
{
    /** Depósito/armazém da empresa — o caso comum. */
    case DEPOSITO = 'DEPOSITO';

    /** Ponto de venda com estoque próprio. */
    case LOJA = 'LOJA';

    /** Estoque em poder de uma pessoa (franqueado, vendedor em campo). */
    case CUSTODIA_PESSOA = 'CUSTODIA_PESSOA';

    /** Estoque a bordo de um veículo — move-se com ele. */
    case VEICULO = 'VEICULO';

    public function rotulo(): string
    {
        return match ($this) {
            self::DEPOSITO => 'Depósito',
            self::LOJA => 'Loja',
            self::CUSTODIA_PESSOA => 'Em poder de pessoa',
            self::VEICULO => 'Veículo',
        };
    }

    /**
     * É um lugar fixo da empresa, onde se lança entrada de mercadoria?
     *
     * Os que não são: o que está com uma pessoa ou num veículo chegou lá por um
     * movimento (carga, transferência), e lançar entrada direto ali criaria
     * mercadoria do nada num lugar que deveria ter recebido de algum outro.
     */
    public function aceitaEntradaDireta(): bool
    {
        return $this === self::DEPOSITO || $this === self::LOJA;
    }

    /** O saldo aqui está fora do controle físico direto da empresa? */
    public function eCustodia(): bool
    {
        return $this === self::CUSTODIA_PESSOA || $this === self::VEICULO;
    }
}
