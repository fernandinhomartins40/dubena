<?php

namespace App\Enums;


abstract class LogSenhaStatus
{
    const Correto   = "Correto";
    const Incorreto = "Incorreto";

    public static function getFromID($id)
    {
        if ($id == 1) return self::Correto;
        if ($id == 2) return self::Incorreto;
    }
}
