<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Cupom
 *
 * @mixin \Eloquent
 */
class Cupom extends Model
{
    protected $table = 'cupons';

    protected $fillable = ["datainicio", "datafim", "empresa_id", "valor", "tipo", "limiteuso", "ativo", "codigo"];
}
