<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Conveniofechamento
 *
 * @property int $CLIENTE_ID
 * @property int|null $CONDICAOPAGAMENTO_ID
 * @property string|null $CREATED_AT
 * @property string $DATAEMISSAO
 * @property string $DATAVENCIMENTO
 * @property int $EMPRESA_ID
 * @property int|null $FINANCEIRO_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property-read \App\Cliente $cliente
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Conveniofechamentopedido[] $convenioFechamentoPedido
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Financeiro $financeiro
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereDATAEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamento whereVALOR($value)
 * @mixin \Eloquent
 */
class Conveniofechamento extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'cliente_id', 'financeiro_id', 'dataemissao',
        'datavencimento', 'valor','condicaopagamento_id', 'nfemitida_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function financeiro()
    {
        return $this->belongsTo('App\Financeiro');
    }

    public function cliente()
    {
        return $this->belongsTo('App\Cliente','cliente_id');
    }

    public function nfemitida()
    {
        return $this->belongsTo('App\Nfemitida','nfemitida_id');
    }

    public function convenioFechamentoPedido()
    {
        return $this->hasMany('App\Conveniofechamentopedido');
    }

}
