<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Ocorrenciasremessas
 *
 * @property string|null $ALLOWED_USER
 * @property string $CODIGO
 * @property int $CODIGO_BANCO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string $SEED
 * @property int $TIPO
 * @property string|null $UPDATED_AT
 * @property string $USO_BANCO
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereALLOWEDUSER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereCODIGOBANCO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereSEED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ocorrenciasremessas whereUSOBANCO($value)
 * @mixin \Eloquent
 */
class Ocorrenciasremessas extends Model
{
    protected $fillable = ['tipo', 'codigo_banco', 'codigo', 'descricao', 'uso_banco', 'seed', 'allowed_user'];
}
