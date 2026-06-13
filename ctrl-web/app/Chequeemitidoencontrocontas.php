<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequeemitidoencontrocontas
 *
 * @property int|null $CHEQUEEMITIDO_ID
 * @property string|null $CREATED_AT
 * @property int $FINANCEIROPARCELA_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property float $VALORTOTAL
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitidoencontrocontas[] $chequeEmitidoEncontroContas
 * @property-read \App\Chequeemitido $chequeemitido
 * @property-read \App\Financeiroparcela $financeiroparcela
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidoencontrocontas whereCHEQUEEMITIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidoencontrocontas whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidoencontrocontas whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidoencontrocontas whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidoencontrocontas whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitidoencontrocontas whereVALORTOTAL($value)
 * @mixin \Eloquent
 */
class Chequeemitidoencontrocontas extends Model
{
	protected $fillable = ['chequeemitido_id', 'numerocheque', 'financeiroparcela_id', 'valortotal'];

	public function chequeemitido()
	{
		return $this->belongsTo('App\Chequeemitido');
	}

	public function financeiroparcela()
	{
		return $this->belongsTo('App\Financeiroparcela');
	}
    public function chequeEmitidoEncontroContas()
    {
        return $this->hasMany('App\Chequeemitidoencontrocontas');
    }
}
