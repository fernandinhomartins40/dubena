<?php

namespace App\Repository;

use App\ProdutoCondicaoPagamento;

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