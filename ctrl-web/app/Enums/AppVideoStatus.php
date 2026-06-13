<?php

namespace App\Enums;


abstract class AppVideoStatus
{
    const Enviado = 1;
    const EmProcessamento = 2;
    const Processado = 3;
    const ErroProcessamento = 4;
    const Sincronizado = 5;

    public static function getDesc($status)
    {
        if ($status == self::Enviado) return "Enviado";
        if ($status == self::EmProcessamento) return "Processando Arquivo";
        if ($status == self::Processado) return "Processado";
        if ($status == self::ErroProcessamento) return "Erro: ";
        if ($status == self::Sincronizado) return "Sincronizado";
    }
}
