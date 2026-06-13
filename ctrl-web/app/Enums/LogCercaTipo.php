<?php

namespace App\Enums;


abstract class LogCercaTipo
{
    const Inside = 1;
    const Outside = 2;

    public static function createFrom($id)
    {
        if ($id == 1) return self::Inside;
        if ($id == 2) return self::Outside;
    }

    public static function getDesc($id)
    {
        if ($id == self::Inside) return "Dentro";
        if ($id == self::Outside) return "Fora";
    }
}
