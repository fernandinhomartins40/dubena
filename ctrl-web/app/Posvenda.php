<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Posvenda
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DATAHORAFIM
 * @property string $DATAHORAINICIO
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Posvendapesquisa[] $posVendaPesquisa
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Posvendapergunta[] $posvendaperguntas
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereDATAHORAFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereDATAHORAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvenda whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Posvenda extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'datahorainicio',
        'datahorafim', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function posvendaperguntas()
    {
        return $this->hasMany('App\Posvendapergunta');
    }

    public function posVendaPesquisa()
    {
        return $this->hasMany('App\Posvendapesquisa');
    }

}
