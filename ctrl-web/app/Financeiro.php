<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Financeiro
 *
 * @property string|null $CARTAOAUTORIZACAO
 * @property string|null $CARTAONSU
 * @property int $CLIENTE_ID
 * @property int|null $CONDICAOPAGAMENTO_ID
 * @property string|null $CREATED_AT
 * @property string|null $DATACOMPETENCIA
 * @property string|null $DATAEMISSAO
 * @property string|null $DESCRICAO
 * @property string|null $DOCUMENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $PAGARRECEBER
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property-read \App\Financeiro $agrupadorFinanceiro
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boleto[] $boleto
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boletoremessafinanceiro[] $boletoRemessaFinanceiro
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boletohistorico[] $boletohistorico
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitidofinanceiro[] $chequeEmitidoFinanceiro
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebidofinanceiro[] $chequeRecebidoFinanceiro
 * @property-read \App\Cliente $cliente
 * @property-read \App\Condicaopagamento $condicaoPagamento
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Conveniofechamento[] $convenioFechamento
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Financeiroparcela[] $financeiroparcela
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitida[] $nfEmitida
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Financeiroparcela[] $parcelas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Financeirorateio[] $rateios
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Valegasvenda[] $valeGasVenda
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereCARTAOAUTORIZACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereCARTAONSU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereDATACOMPETENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereDATAEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereDOCUMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro wherePAGARRECEBER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiro whereVALOR($value)
 * @mixin \Eloquent
 */
class Financeiro extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $fillable = ['grupo_id', 'empresa_id', 'cliente_id', 'condicaopagamento_id',
        'descricao', 'documento', 'valor', 'dataemissao', 'datacompetencia', 'pagarreceber',
        'cartaoautorizacao', 'cartaonsu'];

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

    public function condicaoPagamento()
    {
        return $this->belongsTo('App\Condicaopagamento', 'condicaopagamento_id');
    }

    public function boleto()
    {
        return $this->hasMany('App\Boleto');
    }

    public function boletohistorico()
    {
        return $this->hasMany('App\Boletohistorico');
    }

    public function boletoRemessaFinanceiro()
    {
        return $this->hasMany('App\Boletoremessafinanceiro');
    }

    public function chequeEmitidoFinanceiro()
    {
        return $this->hasMany('App\Chequeemitidofinanceiro');
    }

    public function chequeRecebidoFinanceiro()
    {
        return $this->hasMany('App\Chequerecebidofinanceiro');
    }

    public function convenioFechamento()
    {
        return $this->hasMany('App\convenioFechamento');
    }

    public function agrupadorFinanceiro()
    {
        return $this->belongsTo('App\Financeiro', 'agrupadorfinanceiro_id');
    }

    public function parcelas()
    {
        return $this->hasMany('App\Financeiroparcela');
    }

    public function rateios()
    {
        return $this->hasMany('App\Financeirorateio');
    }

    public function valeGasVenda()
    {
        return $this->hasMany('App\Valegasvenda');
    }

    public function nfEmitida()
    {
        return $this->hasMany('App\Nfemitida');
    }

    public function financeiroparcela()
    {
        return $this->hasMany('App\Financeiroparcela');
    }
}
