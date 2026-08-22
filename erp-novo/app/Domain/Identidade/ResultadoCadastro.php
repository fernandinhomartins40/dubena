<?php

namespace App\Domain\Identidade;

use App\Models\Cliente\Cliente;

/**
 * O que aconteceu numa tentativa de cadastro.
 *
 * A porta que chamou precisa saber se ganhou um cliente novo ou reencontrou um
 * existente — o app, por exemplo, diz "bem-vindo de volta" em vez de "cadastro
 * criado". E `emRevisao` deixa a tela avisar, sem alarme, que o cadastro pode
 * ser duplicado: informação para o operador, nunca obstáculo.
 */
final class ResultadoCadastro
{
    /** @param list<string> $motivos */
    public function __construct(
        public readonly Cliente $cliente,
        public readonly bool $criado,
        public readonly bool $identificado,
        public readonly int $escore = 0,
        public readonly array $motivos = [],
        public readonly bool $emRevisao = false,
    ) {}

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'id' => $this->cliente->id,
            'nome' => $this->cliente->nome,
            'criado' => $this->criado,
            // Quando true, o cliente já existia e foi reconhecido pelos traços.
            'identificado' => $this->identificado,
            'escore' => $this->escore,
            'motivos' => $this->motivos,
            'em_revisao' => $this->emRevisao,
        ];
    }
}
