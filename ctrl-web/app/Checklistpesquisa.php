<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Checklistpesquisa
 *
 * @property int $CHECKLISTFORM_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORAPESQUISA
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $OBSERVACOES
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Checklistpesquisaresposta[] $checkListPesquisaResposta
 * @property-read \App\Checklistform $checklistform
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisa whereCHECKLISTFORMID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisa whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisa whereDATAHORAPESQUISA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisa whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisa whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisa whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisa whereOBSERVACOES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistpesquisa whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Checklistpesquisa extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'checklistform_id', 'observacoes', 
        'datahorapesquisa'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function checklistform()
    {
        return $this->belongsTo('App\Checklistform','checklistform_id');
    }

    public function checkListPesquisaResposta()
    {
        return $this->hasMany('App\Checklistpesquisaresposta');
    }

}
