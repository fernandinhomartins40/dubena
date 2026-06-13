<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Tipopessoa
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $TIPOPESSOACADASTRO
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Colaborador[] $colaborador
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipopessoa whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipopessoa whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipopessoa whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipopessoa whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipopessoa whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipopessoa whereTIPOPESSOACADASTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipopessoa whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Tipopessoa extends Model
{
    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'tipopessoacadastro'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function colaborador()
    {
        return $this->hasMany('App\Colaborador');
    }

    public function selectTipoPessoa($grupo){
        return $this->where(["grupo_id"=>$grupo,"ativo"=>1])->orderBy('descricao')->pluck("descricao","id");
    }

}
