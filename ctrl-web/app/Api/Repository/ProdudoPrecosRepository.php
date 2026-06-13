<?php

namespace App\Api\Repository;

use App\Api\Models\ProdutoCondicaoPagamento;

class ProdudoPrecosRepository extends BaseRepository
{

    /**
     * ProdutoCondicaoPagamento constructor.
     */
    public function __construct()
    {
        parent::__construct(ProdutoCondicaoPagamento::class);
    }

}
