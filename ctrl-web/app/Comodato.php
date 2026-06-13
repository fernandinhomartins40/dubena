<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Comodato
 *
 * @property string|null $ATIVO
 * @property int $CLIENTE_ID
 * @property string|null $CPFREPRESENTANTE
 * @property string|null $CREATED_AT
 * @property string $DATACONTRATO
 * @property string $DATAVENCIMENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $NOMEREPRESENTANTE
 * @property string|null $NUMERONOTA
 * @property string|null $OBSERVACAO
 * @property string|null $RGREPRESENTANTE
 * @property int $TIPO
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Comodatoitem[] $comodatoprodutos
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereCPFREPRESENTANTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereDATACONTRATO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereNOMEREPRESENTANTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereNUMERONOTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereRGREPRESENTANTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Comodato whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Comodato extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'cliente_id', 'datacontrato', 'datavencimento',
        'numeronota', 'observacao', 'entradasaida', 'ativo', 'tipo', 'cpfrepresentante', 'rgrepresentante', 'nomerepresentante'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function comodatoprodutos()
    {
        return $this->hasMany('App\Comodatoitem');
    }
}
