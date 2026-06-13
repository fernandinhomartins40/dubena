<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:32
 */

namespace App\Api\Repository;

use App\Api\Models\UserPolylines;

class UserPolylinesRepository extends BaseRepository
{

    /**
     * UserRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(UserPolylines::class);
    }

    /**
     * @param $user_id
     * @return mixed
     */
    public static function byUser($user_id)
    {
        return static::whereUserId($user_id)
            ->selectRaw("latitude as lat, longitude as lng")
            ->get();
    }

    /**
     * @param $user_id
     * @param $polygons
     * @return bool
     */
    public static function savePoligons($user_id, $polygons)
    {
        static::whereUserId($user_id)->delete();

        foreach ($polygons as $polygon) {
            static::create([
                "latitude"  => $polygon->lat,
                "longitude" => $polygon->lng,
                "user_id"   => $user_id
            ]);
        }
        return true;
    }
}
