<?php

namespace App\Api\Repository;

use App\Api\Models\CondicaoPagamento;

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
