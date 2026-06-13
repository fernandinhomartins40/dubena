<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Veiculotipo
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int|null $TIPORASTREAMENTO
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotipo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotipo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotipo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotipo whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotipo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotipo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotipo whereTIPORASTREAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotipo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Veiculotipo extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'ativo', 'tiporastreamento'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function selectTipo($empresa)
    {
        return $this->where(['empresa_id'=>$empresa,'ativo'=>1])->pluck('descricao','id');
    }

}
