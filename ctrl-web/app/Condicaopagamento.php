<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Condicaopagamento
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $DIAS_PRIMEIRA
 * @property int $EMPRESA_ID
 * @property int|null $FORNECEDOR_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int|null $INTERVALO
 * @property string|null $NFC_TPAG
 * @property int|null $NUM_PARCELAS
 * @property float|null $TAXA
 * @property string $TIPO
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Cliente[] $cliente
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Colaboradorcomissao[] $colaboradorComissao
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Condicaopagamentoparcela[] $condicaoPagamentoParcela
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Financeiro[] $financeiro
 * @property-read \App\Cliente $fornecedor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitida[] $nfEmitida
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Valegasvenda[] $valeGasVenda
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereDIASPRIMEIRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereFORNECEDORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereINTERVALO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereNFCTPAG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereNUMPARCELAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereTAXA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Condicaopagamento whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Condicaopagamento extends Model
{

    protected $fillable = [
        'grupo_id',
        'empresa_id',
        'descricao',
        'dias_primeira',
        'num_parcelas',
        'intervalo',
        'contamovimentotipo_id',
        'nfc_tpag',
        'tipo',
        'taxa',
        'ativo',
        'fornecedor_id',
        'enviaappnf',
        'pedidosituacaoappnf_id',
        'appnfceauto'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function colaboradorComissao()
    {
        return $this->hasMany('App\Colaboradorcomissao');
    }

    public function condicaoPagamentoParcela()
    {
        return $this->hasMany('App\Condicaopagamentoparcela');
    }

    public function financeiro()
    {
        return $this->hasMany('App\Financeiro');
    }

    public function valeGasVenda()
    {
        return $this->hasMany('App\Valegasvenda');
    }

    public function nfEmitida()
    {
        return $this->hasMany('App\Nfemitida');
    }

    public function cliente()
    {
        return $this->belongsToMany('App\Cliente')->withTimestamps();
    }

    public function fornecedor()
    {
        return $this->belongsTo('App\Cliente', 'fornecedor_id');
    }

    public function pedidosituacao()
    {
        return $this->belongsTo('App\Pedidosituacao', 'pedidosituacaoappnf_id');
    }

    public function contaMovimentoTipo()
    {
        return $this->belongsTo('App\Contamovimentotipo', 'contamovimentotipo_id');
    }
}
