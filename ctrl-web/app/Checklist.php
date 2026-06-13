<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Checklist
 *
 * @property string|null $ATIVO
 * @property int $CHECKLISTFORM_ID
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Checklistform $checklistform
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Checklistpergunta[] $checklistpergunta
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklist whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklist whereCHECKLISTFORMID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklist whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklist whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklist whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklist whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklist whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Checklist whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Checklist extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'checklistform_id', 'descricao', 'ativo'];

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

    public function checklistpergunta()
    {
        return $this->hasMany('App\Checklistpergunta');
    }

}
