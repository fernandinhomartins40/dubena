<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:32
 */

namespace App\Api\Repository;

use App\Api\Models\CondicaoPagamento;

class CondPgtoRepository extends BaseRepository
{

    /**
     * GeneralConfigRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(CondicaoPagamento::class);
    }
}
