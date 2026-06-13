<?php

namespace App\Repository;

use App\ProdutoCategoria;
use App\Repository\BaseRepository;

class ProdutoCategoriaRepo extends BaseRepository
{

    public function __construct()
    {
        parent::__construct(new ProdutoCategoria());
    }

}