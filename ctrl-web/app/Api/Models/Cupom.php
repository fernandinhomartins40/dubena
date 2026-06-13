<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Cupom
 *
 * @mixin \Eloquent
 */
class Cupom extends ApiModel
{
    protected $table = 'cupons';

    protected $fillable = ["datainicio", "datafim", "empresa_id", "valor", "tipo", "limiteuso", "ativo", "codigo"];
}


