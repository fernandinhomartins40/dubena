<?php

namespace App\Domain\Tenant;

/**
 * O que aconteceu numa passada do agendador pelas empresas.
 *
 * Existe para que "não fiz nada" deixe de ser indistinguível de "não havia o
 * que fazer": o comando sabe quantas empresas processou, quais pulou por falta
 * de identidade e quais falharam.
 */
final class ResultadoDaAutomacao
{
    /** @var array<int, mixed> empresa_id => o que o trabalho devolveu */
    public array $retornos = [];

    /** @var list<int> empresas dentro da fronteira e sem identidade de automação */
    public array $semIdentidade = [];

    /** @var array<int, string> empresa_id => mensagem do erro */
    public array $falhas = [];

    public function processadas(): int
    {
        return count($this->retornos);
    }

    /** Soma dos retornos numéricos (posições ingeridas, missões geradas…). */
    public function total(): int|float
    {
        return array_sum(array_filter($this->retornos, 'is_numeric'));
    }

    public function limpo(): bool
    {
        return $this->falhas === [] && $this->semIdentidade === [];
    }
}
