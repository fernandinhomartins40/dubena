<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Financeiroparcela
 *
 * @property int|null $AGRUPADOR_FINANCEIRO_ID
 * @property int|null $AGRUPAMENTO_STATUS
 * @property string $BAIXADO
 * @property string $BOLETOGERADO
 * @property string|null $CREATED_AT
 * @property string $DATACOMPETENCIA
 * @property string|null $DATAHORABAIXA
 * @property string $DATAVENCIMENTO
 * @property float $DESCONTO
 * @property int $EMPRESA_ID
 * @property int $FINANCEIRO_ID
 * @property int|null $FINANCEIROTAXA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float $JUROS
 * @property string|null $MOTIVOCANCELAMENTO
 * @property float $MULTA
 * @property int $NUMERO
 * @property string $PAGARRECEBER
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property float $VALOREFETIVADO
 * @property int|null $agrupador_financeiro_id
 * @property int|null $agrupamento_status
 * @property string $baixado
 * @property string $boletogerado
 * @property string|null $created_at
 * @property string $datacompetencia
 * @property string|null $datahorabaixa
 * @property string $datavencimento
 * @property float $desconto
 * @property int $empresa_id
 * @property int $financeiro_id
 * @property int|null $financeirotaxa_id
 * @property int $grupo_id
 * @property int $id
 * @property float $juros
 * @property string|null $motivocancelamento
 * @property float $multa
 * @property int $numero
 * @property string $pagarreceber
 * @property string|null $updated_at
 * @property float $valor
 * @property float $valorefetivado
 * @property-read \App\Boleto $boleto
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boletohistorico[] $boletoHistorico
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boletoremessafinanceiro[] $boletoRemessaFinanceiro
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitidoencontrocontas[] $chequeEmitidoEncontroContas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitidofinanceiro[] $chequeEmitidoFinanceiro
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebidoencontrocontas[] $chequeRecebidoEncontroContas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebidofinanceiro[] $chequeRecebidoFinanceiro
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contamovimento[] $contaMovimento
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Financeiro $financeiroTaxa
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereAGRUPADORFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereAGRUPAMENTOSTATUS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereBAIXADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereBOLETOGERADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereDATACOMPETENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereDATAHORABAIXA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereDESCONTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereFINANCEIROTAXAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereJUROS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereMOTIVOCANCELAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereMULTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela wherePAGARRECEBER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Financeiroparcela whereVALOREFETIVADO($value)
 * @mixin \Eloquent
 */
class Financeiroparcela extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $fillable = ['grupo_id', 'empresa_id', 'financeiro_id', 'numero',
        'datavencimento', 'datahorapagamento', 'datacompetencia', 'valor', 'multa',
        'juros', 'desconto', 'valorefetivado', 'pagarreceber', 'baixado', 'agrupamento_status', 
        'agrupador_financeiro_id', 'motivocancelamento', 'boletogerado', 'financeirotaxa_id'];

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
        return $this->belongsTo('App\Financeiro', 'financeiro_id');
    }
    public function boleto()
    {
        return $this->hasOne('App\Boleto');
    }

    public function boletoHistorico()
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

    public function chequeRecebidoEncontroContas()
    {
        return $this->hasMany('App\Chequerecebidoencontrocontas');
    }

    public function chequeEmitidoEncontroContas()
    {
        return $this->hasMany('App\Chequeemitidoencontrocontas');
    }

    public function contaMovimento()
    {
        return $this->hasMany('App\Contamovimento');
    }

    public function financeiroTaxa()
    {
        return $this->belongsTo('App\Financeiro', "financeirotaxa_id");
    }

}
