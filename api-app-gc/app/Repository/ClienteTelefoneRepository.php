<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 19/07/2018
 * Time: 17:51
 */

namespace App\Repository;


use App\ClienteTelefone;
use Illuminate\Support\Collection;

/**
 * Class ClienteTelefoneRepository
 * @package App\Repository
 * @method static Collection whereClienteId($id)
 * @method first()
 * @mixin ClienteTelefone
 */
class ClienteTelefoneRepository extends BaseRepository
{

    /**
     * ClienteTelefoneRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(ClienteTelefone::class);
    }

    /**
     * @param $phone
     * @return bool
     */
    public static function hasClientPhone($phone)
    {
        $first = static::getCount($phone)->first();

        return $first && $first->count;
    }

    /**
     * @param $data
     * @param bool $has
     * @return ClienteTelefoneRepository|\Illuminate\Database\Eloquent\Model|null
     */
    public static function createIfNotExists($data, $has = false)
    {
        if ($has) {
            static::whereTelefone($data["telefone"])->update($data);
            return null;
        }

        if (static::hasClientPhone($data["telefone"])) {
            return null;
        }

        return static::create($data);
    }

    /**
     * @param $phone
     * @return ClienteTelefone[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Query\Builder[]|\Illuminate\Support\Collection
     */
    public static function getCount($phone)
    {
        return ClienteTelefone::whereTelefone($phone)->selectRaw('count(*) as count')->get();
    }

    /**
     * @param $phone
     * @return ClienteTelefone|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|null|object
     */
    public static function firstPhone($phone)
    {
        return ClienteTelefone::whereTelefone($phone)->first();
    }

    /**
     * @param $phone
     * @param $client_id
     * @return ClienteTelefone|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|null|object
     */
    public static function byClientAndNumber($phone, $client_id)
    {
        return ClienteTelefone::whereTelefone($phone)->whereClienteId($client_id)->first();
    }
}
