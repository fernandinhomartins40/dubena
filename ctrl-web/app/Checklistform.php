<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Checklistform
 *
 * @property string|null $ATIVO
 * @property int $CHECKLISTTIPO_ID
 * @property string|null $CREATED_AT
 * @property string|null $DATAFIM
 * @property string|null $DATAINICIO
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Checklistpesquisa[] $checkListPesquisa
 * @property-read \App\Checklisttipo $checkListTipo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Checklist[] $checklist
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereCHECKLISTTIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereDATAFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereDATAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklistform whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Checklistform extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'checklisttipo_id', 'descricao', 'datainicio',
        'datafim', 'ativo'];

    public function checklist()
    {
        return $this->hasMany('App\Checklist');
    }

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo','grupo_id');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa','empresa_id');
    }

    public function checkListTipo()
    {
        return $this->belongsTo('App\Checklisttipo','checklisttipo_id');
    }

    public function checkListPesquisa()
    {
        return $this->hasMany('App\Checklistpesquisa');
    }

}
