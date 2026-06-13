<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquefechamento
 *
 * @property string|null $CREATED_AT
 * @property string $DATAHORAFECHAMENTO
 * @property int $EMPRESA_ID
 * @property string $FECHAMENTOMENSAL
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $REABERTO
 * @property string|null $REABERTODATAHORA
 * @property string|null $REABERTOMOTIVO
 * @property int|null $REABERTOUSER_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquefechamentosetor[] $estoqueFechamentoSetor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquefisicosetor[] $estoqueFisicoSetor
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereDATAHORAFECHAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereFECHAMENTOMENSAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereREABERTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereREABERTODATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereREABERTOMOTIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereREABERTOUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquefechamento whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estoquefechamento extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'fechamentomensal', 'datahorafechamento', 
        'reaberto', 'reabertouser_id', 'reabertomotivo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function estoqueFechamentoSetor()
    {
        return $this->hasMany('App\Estoquefechamentosetor');
    }

    public function estoqueFisicoSetor()
    {
        return $this->hasMany('App\Estoquefisicosetor');
    }
    
    public function user()
    {
        return $this->belongsTo('App\User');
    }

}
