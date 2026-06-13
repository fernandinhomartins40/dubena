<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquerequisicao
 *
 * @property int $CANCELADO
 * @property int|null $CENTROCUSTO_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $OBSERVACOES
 * @property int|null $PLANOCONTA_ID
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property-read \App\Centrocusto $centrocusto
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquerequisicaoitem[] $estoqueRequisicaoItem
 * @property-read \App\Planoconta $planoconta
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereCANCELADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereCENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereOBSERVACOES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao wherePLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicao whereUSERID($value)
 * @mixin \Eloquent
 */
class Estoquerequisicao extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'user_id', 'observacoes', 'datahora', 'centrocusto_id', 'planoconta_id'];

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

    public function estoqueRequisicaoItem()
    {
        return $this->hasMany('App\Estoquerequisicaoitem');
    }

    public function centrocusto()
    {
        return $this->belongsTo('App\Centrocusto');
    }

    public function planoconta()
    {
        return $this->belongsTo('App\Planoconta');
    }

}
