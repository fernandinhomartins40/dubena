<?php

namespace App\Api\Repository;

use App\Api\Models\ProdutoCategoria;
use App\Api\Repository\BaseRepository;

class ProdutoCategoriaRepo extends BaseRepository
{

    public function __construct()
    {
        parent::__construct(new ProdutoCategoria());
    }

}
