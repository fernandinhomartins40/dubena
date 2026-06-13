<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Layoutbanco
 *
 * @property string $ATIVO
 * @property int $BOLETOPOSICOESNOSSONUMERO
 * @property int $CNAB
 * @property int $CODIGO_BANCO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property int $MAXIMODIASBAIXADEVOLUCAO
 * @property int $MAXIMODIASPROTESTO
 * @property int $MINIMODIASBAIXADEVOLUCAO
 * @property int $MINIMODIASPROTESTO
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereBOLETOPOSICOESNOSSONUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereCNAB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereCODIGOBANCO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereMAXIMODIASBAIXADEVOLUCAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereMAXIMODIASPROTESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereMINIMODIASBAIXADEVOLUCAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereMINIMODIASPROTESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Layoutbanco whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Layoutbanco extends Model
{
    protected $fillable = ['ativo', 'cnab', 'minimodiasprotesto', 'maximodiasprotesto', 
    						'minimodiasbaixadevolucao', 'maximodiasbaixadevolucao', 
    						'boletoposicoesnossonumero', 'codigo_banco', 'descricao'];
}
