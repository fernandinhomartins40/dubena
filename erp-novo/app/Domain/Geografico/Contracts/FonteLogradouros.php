<?php

namespace App\Domain\Geografico\Contracts;

/**
 * Fonte de logradouros por município.
 *
 * Contrato separado do driver para o CI não depender de rede: o teste usa
 * `FonteLogradourosFake` e exercita o algoritmo de varredura de verdade,
 * inclusive o caso do teto de resultados.
 */
interface FonteLogradouros
{
    /**
     * Busca logradouros de uma cidade cujo nome contém $termo.
     *
     * O retorno é a lista crua da fonte; cada item traz ao menos
     * `logradouro`, `bairro` e `cep`.
     *
     * @return list<array{logradouro:string, bairro:string, cep:string}>
     */
    public function buscar(string $uf, string $cidade, string $termo): array;

    /**
     * Quantos resultados a fonte devolve no máximo por consulta.
     *
     * Existe no contrato porque o algoritmo PRECISA saber: ao bater o teto, a
     * resposta veio truncada em silêncio e é obrigatório refinar a busca — sem
     * isso a importação perde ruas sem nunca perceber.
     */
    public function teto(): int;
}
