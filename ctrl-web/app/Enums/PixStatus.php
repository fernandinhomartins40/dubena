<?php

namespace App\Enums;


abstract class PixStatus
{
    const Ativa             = "ATIVA";
    const Concluida         = "CONCLUIDA";
    const RemovidaPsp       = "REMOVIDA_PELO_PSP";
    const RemovidaRecebedor = "REMOVIDA_PELO_USUARIO_RECEBEDOR";
}
