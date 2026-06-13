<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:32
 */

namespace App\Api\Repository;


use App\Api\Models\GeneralConfig;

class GeneralConfigRepository extends BaseRepository
{

    /**
     * GeneralConfigRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(GeneralConfig::class);
    } /** @noinspection PhpSignatureMismatchDuringInheritanceInspection */

    /**
     *
     * @param $data
     * @return GeneralConfigRepository|\Illuminate\Database\Eloquent\Model
     */
    public static function updateOrCreate($data)
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $config = static::first();
        if ($config) {
            $config->update($data);
            return $config;
        } else {
            return static::create($data);
        }
    }

}
