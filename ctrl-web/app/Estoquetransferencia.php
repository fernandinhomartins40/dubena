<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquetransferencia
 *
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property string $DATAHORACOMPETENCIA
 * @property int $DESTINOSETOR_ID
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $OBSERVACOES
 * @property int $ORIGEMSETOR_ID
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property-read \App\Setor $destinoSetor
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquetransferenciaitem[] $estoqueTransferenciaItem
 * @property-read \App\Setor $origemSetor
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereDATAHORACOMPETENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereDESTINOSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereOBSERVACOES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereORIGEMSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquetransferencia whereUSERID($value)
 * @mixin \Eloquent
 */
class Estoquetransferencia extends Model
{
    protected $fillable = ['grupo_id', 'empresa_id', 'user_id', 'origemsetor_id', 
        'destinosetor_id', 'datahora', 'datahoracompetencia', 'observacoes'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function origemSetor()
    {
        return $this->belongsTo('App\Setor', 'origemsetor_id');
    }

    public function destinoSetor()
    {
        return $this->belongsTo('App\Setor', 'destinosetor_id');
    }
    
    public function estoqueTransferenciaItem()
    {
        return $this->hasMany('App\Estoquetransferenciaitem');
    }

}
