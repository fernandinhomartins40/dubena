<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequerecebidoencontrocontas
 *
 * @property int $CHEQUERECEBIDO_ID
 * @property string|null $CREATED_AT
 * @property int $FINANCEIROPARCELA_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property float $VALORTOTAL
 * @property-read \App\Chequerecebido $chequerecebido
 * @property-read \App\Financeiroparcela $financeiroparcela
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidoencontrocontas whereCHEQUERECEBIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidoencontrocontas whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidoencontrocontas whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidoencontrocontas whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidoencontrocontas whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebidoencontrocontas whereVALORTOTAL($value)
 * @mixin \Eloquent
 */
class Chequerecebidoencontrocontas extends Model
{
	protected $fillable = ['chequerecebido_id', 'numerocheque', 'financeiroparcela_id', 'valortotal'];
	
	public function chequerecebido()
	{
		return $this->belongsTo('App\Chequerecebido');
	}

	public function financeiroparcela()
	{
		return $this->belongsTo('App\Financeiroparcela');
	}
}
