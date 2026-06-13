<?php

/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpDynamicAsStaticMethodCallInspection */

namespace App\Api\Repository;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @mixin Model
 * Class BaseRepository
 * @package App\Repository
 * @mixin \Eloquent|Builder
 */
abstract class BaseRepository
{
     /**
      * Model variable
      * @var
      */
    protected $model;

    /**
     * Model static variable
     * @var
     */
    protected static $staticModel;

    /**
     * Constructor taking a eloquent instance model
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    public function __construct($model = null) {
        $this->model = new $model();
        static::$staticModel = $this->model;
    }

    /**
     * Retrieve a record from the database, if not find throws exception
     *
     * @param $id
     * @param string $description
     * @return mixed
     * @throws \Exception
     */
    public static function findOrFail($id, $description = "Registro")
    {
        $record = (static::staticInstance())->find($id);

        if (! $record) {
            throw new \Exception($description . " não foi encontrado na base de dados.", 66666);
        }

        return $record;
    }

    /**
     * @param $data
     * @param $user_id
     */
    public static function link($data, $user_id)
    {
        $actives = collect([]);
        foreach ($data as $d) {
            $d["user_id"] = $user_id;
            $actives->push(static::activateOrCreate($d));
        }
        static::inativeNotUsed($actives->pluck("id"), $user_id);
    }

    /**
     * @param $data
     * @return mixed
     */
    protected static function activateOrCreate($data, array $extraData = [])
    {
        return static::updateOrCreate($data, array_merge(["ativo" => true], $extraData));
    }

    /**
     * @param $ids
     * @param $user_id
     * @return mixed
     */
    protected static function inativeNotUsed($ids, $user_id)
    {
        return static::updateNotUsed($ids, $user_id, ["ativo" => false]);
    }

    /**
     * @param $ids
     * @param $user_id
     * @param $data
     * @return mixed
     */
    protected static function updateNotUsed($ids, $user_id, $data)
    {
        return static::whereNotIn("id", $ids)->whereUserId($user_id)->update($data);
    }

    /**
     * @param array|Collection $ids
     * @param $user_id
     */
    protected static function deleteNotUsed($ids, $user_id)
    {
        static::whereNotIn("id", $ids)->whereUserId($user_id)->delete();
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
            if (method_exists((new static), $method)) {
                return (new static)->$method(...$parameters);
        }
        /** @var Model $staticModel */
        return (static::instance()::$staticModel)->$method(...$parameters);
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }
        return (new static)->$method(...$parameters);
    }

    /**
     * @return BaseRepository
     */
    protected static function instance()
    {
        return (new static);
    }

    /**
     * @return mixed
     */
    protected static function staticInstance()
    {
        /** @var string $staticModel */
        return (new static)::$staticModel;
    }

    /**
     * @return mixed
     */
    public static function getToLink()
    {
        return static::select("descricao", "id")->whereAtivo(true)->orderBy("descricao")->get();
    }
}
