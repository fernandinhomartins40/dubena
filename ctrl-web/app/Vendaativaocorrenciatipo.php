<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Vendaativaocorrenciatipo
 *
 * @property string|null $ATIVO
 * @property string $CLIENTE_ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Vendaativaocorrencia[] $vendaAtivaOcorrencia
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrenciatipo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrenciatipo whereCLIENTEATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrenciatipo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrenciatipo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrenciatipo whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrenciatipo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrenciatipo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrenciatipo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Vendaativaocorrenciatipo extends Model
{


    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'cliente_ativo', 'ativo'];

    public function empresasGrupos()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function vendaAtivaOcorrencia()
    {
        return $this->hasMany('App\Vendaativaocorrencia');
    }

}
