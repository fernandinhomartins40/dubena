<?php

namespace App\Repository;

/**
 * @mixin Model|\Eloquent|Collection
 * Class BaseRepository
 * @package App\Repository
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
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection|static[]|static|null
     * @throws \Exception
     */
    public static function findOrFail($id, $description = "Registro")
    {
        $record = (static::staticInstance())->find($id);

        if (! $record) {
            throw new \Exception($description . " não foi encontrado na base de dados.");
        }

        return $record;
    }

    public static function has($id)
    {
        return static::whereRaw(static::getKeyName() . " = " . $id)->count() > 0;
    }

    /**
     * @return mixed
     */
    public static function findIn($ids)
    {
        return static::whereIn(static::getKeyName(), $ids)->get();
    }

    /**
     * @return mixed
     */
    public static function toOrder($id)
    {
        return static::selectRaw("*, " . static::getKeyName() . " as id")->find($id);
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
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
        return (static::instance())->$method(...$parameters);
    }

    /**
     * @return BaseRepository
     */
    protected static function instance()
    {
        return (new static);
    }

    /**
     * @return \Eloquent
     */
    protected static function staticInstance()
    {
        /** @var string $staticModel */
        return (static::instance())::$staticModel;
    }
}
