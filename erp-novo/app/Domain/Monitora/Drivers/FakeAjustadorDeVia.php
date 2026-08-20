<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Monitora\Contracts\AjustadorDeVia;

/**
 * Ajustador para DEV/CI: não encaixa nada e devolve `null`.
 *
 * `null` é o mesmo que o driver real devolve quando não consegue ajustar, então
 * a suíte exercita o caminho de degradação — a linha fica reta e o resto da
 * apuração continua correto. Testes que precisam de encaixe injetam pontos em
 * `$resposta`.
 */
class FakeAjustadorDeVia implements AjustadorDeVia
{
    /** @var list<array{lat:float,lng:float}>|null resposta fixa (para teste) */
    public ?array $resposta = null;

    /** Quantas vezes foi chamado — permite ao teste provar que o cache poupou chamadas. */
    public int $chamadas = 0;

    /** @param  list<array{lat:float,lng:float}>  $pontos */
    public function ajustar(array $pontos): ?array
    {
        $this->chamadas++;

        return $this->resposta;
    }
}
