<?php

namespace App\Enums;


abstract class AppNotificationStatus
{
    const Pendente = "pendente";
    const Enviando = "enviando";

    // public static function createFrom($id)
    // {
    //     if ($id == 1) return self::Inside;
    //     if ($id == 2) return self::Outside;
    // }

    public static function getDesc($status)
    {
        if ($status == self::Pendente) return "Pendente";
        if ($status == self::Enviando) return "Enviando...";
    }
}
