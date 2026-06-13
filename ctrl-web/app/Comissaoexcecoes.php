<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Comissaoexcecoes
 *
 * @property int $COLABORADORCOMISSAO_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property int $SEGMENTO_ID
 * @property int $TIPOEXCECAO
 * @property string|null $UPDATED_AT
 * @property float $VALOREXCECAO
 * @property-read \App\Colaboradorcomissao $colaboradorcomissao
 * @property-read \App\Segmento $segmento
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comissaoexcecoes whereCOLABORADORCOMISSAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comissaoexcecoes whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comissaoexcecoes whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comissaoexcecoes whereSEGMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comissaoexcecoes whereTIPOEXCECAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comissaoexcecoes whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comissaoexcecoes whereVALOREXCECAO($value)
 * @mixin \Eloquent
 */
class Comissaoexcecoes extends Model
{
	protected $fillable = ['segmento_id', 'colaboradorcomissao_id', 'valorexcecao', 'tipoexcecao', 'valorexcecaoapp'];
	
	public function segmento()
	{
		return $this->belongsTo('App\Segmento');
	}

	public function colaboradorcomissao()
	{
		return $this->belongsTo('App\Colaboradorcomissao');
	}

}
