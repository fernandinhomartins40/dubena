<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Monitoramentochamadas
 *
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $TELEFONE
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Empresa $nomeempresa
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Monitoramentochamadas whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Monitoramentochamadas whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Monitoramentochamadas whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Monitoramentochamadas whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Monitoramentochamadas whereTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Monitoramentochamadas whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Monitoramentochamadas extends Model
{
	protected $fillable = ['empresa_id', 'grupo_id', 'telefone'];


	public function empresasGrupo()
	{
		return $this->belongsTo('App\EmpresasGrupo');
	}

	public function empresa()
	{
		return $this->belongsTo('App\Empresa');
	}

	public function nomeempresa(){
		return $this->belongsTo('App\Empresa', 'empresa_id')->select(['nome_informal', 'id']);
	}
}
