<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 03/09/2018
 * Time: 16:34
 */

namespace App\Api\Repository;


use App\Api\Models\Feriado;

class FeriadoRepository extends BaseRepository
{
    /**
     * FeriadoRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(Feriado::class);
    }

    /**
     * @param $id
     * @return mixed
     */
    protected static function byUser($id)
    {
        return static::whereUserId($id)->get();
    }
}
