<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequerecebido
 *
 * @property int|null $ADIANTAMENTOCONTA_ID
 * @property string|null $AGENCIA
 * @property int|null $BAIXACONTA_ID
 * @property int $BANCO_ID
 * @property int $CHEQUESITUACAO_ID
 * @property int|null $CHEQUESITUACAOANTERIOR_ID
 * @property string|null $CREATED_AT
 * @property string $DATACOMPETENCIA
 * @property string|null $DATADEPOSITO
 * @property string|null $DATADEVOLUCAO
 * @property string $DATAEMISSAO
 * @property string|null $DATAPAGAMENTO
 * @property string $DATAVENCIMENTO
 * @property int|null $DEPOSITOCONTA_ID
 * @property float|null $DIFERENCAVALOR
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $NUMEROCHEQUE
 * @property string $NUMEROCONTA
 * @property string|null $OBSERVACAO
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property-read \App\Conta $baixaConta
 * @property-read \App\Banco $banco
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebidoencontrocontas[] $chequeRecebidoEncontroContas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebidofinanceiro[] $chequeRecebidoFinanceiro
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebidotransferencia[] $chequeRecebidoTransferencia
 * @property-read \App\Chequesituacao $chequeSituacaoAnterior
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequesituacaohistorico[] $chequeSituacaoHistorico
 * @property-read \App\Chequesituacao $chequesituacao
 * @property-read \App\Conta $depositoConta
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereADIANTAMENTOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereAGENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereBAIXACONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereBANCOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereCHEQUESITUACAOANTERIORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereCHEQUESITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereDATACOMPETENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereDATADEPOSITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereDATADEVOLUCAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereDATAEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereDATAPAGAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereDEPOSITOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereDIFERENCAVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereNUMEROCHEQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereNUMEROCONTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequerecebido whereVALOR($value)
 * @mixin \Eloquent
 */
class Chequerecebido extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $revisionCreationsEnabled = true;

    protected $fillable = ['grupo_id', 'empresa_id', 'banco_id', 'chequesituacao_id',
        'chequesituacaoanterior_id', 'depositoconta_id', 'baixaconta_id', 'numeroconta',
        'numerocheque', 'dataemissao', 'datavencimento', 'datacompetencia', 'datapagamento',
        'datadeposito', 'datadevolucao', 'valor', 'observacao', 'diferencavalor', 'adiantamentoconta_id', 'agencia'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function banco()
    {
        return $this->belongsTo('App\Banco');
    }

    public function chequesituacao()
    {
        return $this->belongsTo('App\Chequesituacao');
    }

    public function chequeSituacaoAnterior()
    {
        return $this->belongsTo('App\Chequesituacao', 'chequesituacaoanterior_id');
    }

    public function depositoConta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function baixaConta()
    {
        return $this->belongsTo('App\Conta', 'baixaconta_id');
    }
    
    public function chequeRecebidoFinanceiro()
    {
        return $this->hasMany('App\Chequerecebidofinanceiro');
    }

    public function chequeRecebidoTransferencia()
    {
        return $this->hasMany('App\Chequerecebidotransferencia');
    }

    public function chequeRecebidoEncontroContas()
    {
        return $this->hasMany('App\Chequerecebidoencontrocontas');
    }

    public function chequeSituacaoHistorico()
    {
        return $this->hasMany('App\Chequesituacaohistorico');
    }
}
