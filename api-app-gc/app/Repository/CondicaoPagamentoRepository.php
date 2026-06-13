<?php

namespace App\Repository;

use App\CondicaoPagamento;

class CondicaoPagamentoRepository extends BaseRepository
{

    /**
     * CondicaoPagamento constructor.
     */
    public function __construct()
    {
        parent::__construct(CondicaoPagamento::class);
    }
}