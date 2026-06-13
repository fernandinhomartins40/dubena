<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Segmento
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Colaborador[] $colaborador
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Comissaoexcecoes[] $comissaoexcecoes
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Segmento whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Segmento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Segmento whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Segmento whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Segmento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Segmento whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Segmento extends Model
{


    protected $fillable = ['grupo_id', 'descricao', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function colaborador()
    {
        return $this->hasMany('App\Colaborador');
    }

    public function comissaoexcecoes()
    {
        return $this->hasMany('App\Comissaoexcecoes');
    }

    public function selectSegmento($grupo){
        return $this->where(['grupo_id'=>$grupo,"ativo"=>1])->orderBy('descricao')->pluck("descricao","id");
    }

}
