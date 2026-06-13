<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Cupom
 *
 * @property-read \App\Empresa $empresa
 */
class Cupom extends Model
{
    protected $connection = 'sgcm_api';
    protected $table = 'cupons';

    protected $fillable = [
        "datainicio", "datafim", "empresa_id", "valor", "tipo", "limiteuso", "ativo", "codigo", "notificado"
    ];
}
