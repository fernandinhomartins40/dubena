<?php

namespace App\Domain\Geografico\Drivers;

use App\Domain\Geografico\Contracts\FonteLogradouros;
use App\Domain\Identidade\NormalizadorTexto;

/**
 * Fonte falsa de logradouros — o CI não pode depender da rede.
 *
 * Reproduz o comportamento que importa do serviço real, incluindo o TETO
 * silencioso: a busca casa por trecho do nome e a lista devolvida é cortada sem
 * qualquer sinal. É isso que permite testar se o refino de fato recupera as
 * ruas que uma varredura ingênua perderia.
 */
class FonteLogradourosFake implements FonteLogradouros
{
    /** @var list<array{logradouro:string, bairro:string, cep:string}> */
    private array $acervo;

    /** Consultas feitas — o teste usa para provar que o refino ocorreu. */
    public int $consultas = 0;

    /** @param  list<array{logradouro:string, bairro:string, cep:string}>  $acervo */
    public function __construct(array $acervo = [], private int $teto = 50)
    {
        $this->acervo = $acervo;
    }

    public function teto(): int
    {
        return $this->teto;
    }

    public function buscar(string $uf, string $cidade, string $termo): array
    {
        $this->consultas++;

        // Espelha o HTTP 400 do serviço real para termo curto.
        if (mb_strlen($termo) < 3) {
            return [];
        }

        $alvo = NormalizadorTexto::basico($termo);

        $casaram = array_values(array_filter(
            $this->acervo,
            fn ($item) => str_contains(NormalizadorTexto::basico($item['logradouro']), $alvo),
        ));

        // Trunca SEM avisar — exatamente como a API real.
        return array_slice($casaram, 0, $this->teto);
    }
}
