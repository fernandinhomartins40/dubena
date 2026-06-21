<?php

namespace App\Etl\Contracts;

use App\Etl\Support\InvariantResult;

/**
 * Uma verificação de integridade da migração (o "portão" do cutover).
 * Ex.: contagem origem=destino; Σ movimentos = saldo; zero FK órfã.
 */
interface Invariant
{
    public function nome(): string;

    public function verificar(): InvariantResult;
}
