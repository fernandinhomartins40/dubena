<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Conta
 *
 * @property string|null $AGENCIA
 * @property string|null $ATIVO
 * @property int|null $BANCO_ID
 * @property string|null $BOLETOACEITE
 * @property int|null $BOLETOBYTE
 * @property string|null $BOLETOCARTEIRA
 * @property string|null $BOLETOCEDENTE
 * @property string|null $BOLETOCEDENTEDIGITO
 * @property string $BOLETOCOMPROVANTEENTREGA
 * @property string $BOLETOCORRESPONDENTE
 * @property int|null $BOLETOCORRESPONDENTEBANCO_ID
 * @property int|null $BOLETODIASPROTESTO
 * @property string $BOLETOEMITE
 * @property string|null $BOLETOESPECIE
 * @property string|null $BOLETOINSTRUCOES
 * @property float|null $BOLETOJUROS
 * @property float|null $BOLETOMULTA
 * @property int|null $BOLETOPROTESTO_BAIXADEVOLUCAO
 * @property int|null $BOLETOREMESSASEQUENCIA
 * @property int|null $BOLETOSEQUENCIA
 * @property string $CONTA
 * @property int $CONTATIPO_ID
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property string $FECHADO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int|null $LAYOUTBANCO_ID
 * @property float $SALDOATUAL
 * @property float $SALDOINICIAL
 * @property string|null $UPDATED_AT
 * @property-read \App\Banco $banco
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boletohistorico[] $boletoHistoricos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boletoremessafinanceiro[] $boletoRemessaFinanceiros
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boleto[] $boletos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitido[] $chequeEmitidos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebido[] $chequeRecebidoAdiantamento
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebido[] $chequeRecebidoBaixado
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebido[] $chequeRecebidoDepositado
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequerecebido[] $chequeRecebidos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contafechamento[] $contaFechamentos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contamovimento[] $contaMovimentos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contareabertura[] $contaReaberturas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contatalao[] $contaTalaos
 * @property-read \App\Contatipo $contaTipo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contatransferencia[] $contaTransferencias
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contauser[] $contaUsers
 * @property-read \App\Banco $correspondente
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereAGENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBANCOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOACEITE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOBYTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOCARTEIRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOCEDENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOCEDENTEDIGITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOCOMPROVANTEENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOCORRESPONDENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOCORRESPONDENTEBANCOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETODIASPROTESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOEMITE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOESPECIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOINSTRUCOES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOJUROS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOMULTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOPROTESTOBAIXADEVOLUCAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOREMESSASEQUENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereBOLETOSEQUENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereCONTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereCONTATIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereFECHADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereLAYOUTBANCOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereSALDOATUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereSALDOINICIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conta whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Conta extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $revisionCreationsEnabled = true;

    protected $dontKeepRevisionOf = ["saldoatual"];

    protected $fillable = ['grupo_id', 'empresa_id', 'banco_id', 'contatipo_id', 'agencia',
        'conta', 'saldoinicial', 'saldoatual', 'descricao', 'boletosequencia', 'boletoemite',
        'boletocarteira', 'boletobyte', 'boletomulta', 'boletojuros', 'boletoaceite', 'boletoespecie',
        'boletoremessasequencia', 'boletocedente', 'boletocedentedigito', 'boletocomprovanteentrega',
        'boletoinstrucoes', 'boletocorrespondente','layoutbanco_id', 'boletoprotesto_baixadevolucao',
        'boletocorrespondentebanco_id', 'fechado', 'ativo', 'boletodiasprotesto', 'codigoofx'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function contaTipo()
    {
        return $this->belongsTo('App\Contatipo');
    }

    public function banco()
    {
        return $this->belongsTo('App\Banco', 'banco_id');
    }

    public function correspondente()
    {
        return $this->belongsTo('App\Banco', 'boletocorrespondentebanco_id');
    }

    public function boletos()
    {
        return $this->hasMany('App\Boleto');
    }

    public function boletoHistoricos()
    {
        return $this->hasMany('App\Boletohistorico');
    }

    public function boletoRemessas()
    {
        $this->hasMany('App\Boletoremessa');
    }

    public function boletoRemessaFinanceiros()
    {
        return $this->hasMany('App\Boletoremessafinanceiro');
    }

    public function chequeEmitidos()
    {
        return $this->hasMany('App\Chequeemitido');
    }

    public function chequeRecebidoBaixado()
    {
        return $this->hasMany('App\Chequerecebido', 'baixaconta_id');
    }

    public function chequeRecebidos()
    {
        return $this->hasMany('App\Chequerecebido');
    }

    public function chequeRecebidoDepositado()
    {
        return $this->hasMany('App\Chequerecebido', 'depositoconta_id');
    }

    public function chequeRecebidoAdiantamento()
    {
        return $this->hasMany('App\Chequerecebido', 'adiantamentoconta_id');
    }

    public function contaFechamentos()
    {
        return $this->hasMany('App\Contafechamento');
    }

    public function contaMovimentos()
    {
        return $this->hasMany('App\Contamovimento');
    }

    public function contaReaberturas()
    {
        return $this->hasMany('App\Contareabertura');
    }

    public function contaTalaos()
    {
        return $this->hasMany('App\Contatalao');
    }

    public function contaTransferencias()
    {
        return $this->hasMany('App\Contatransferencia');
    }
    public function contaUsers()
    {
        return $this->hasMany('App\Contauser');
    }

    public function contaExtratoconfigs()
    {
        return Contaextratoconfig::where('conta_id', $this->id)->with(['cliente', 'contaMovimentoTipo', 'condicaoPagamento', 'planoConta', 'centroCusto', 'contaOrigem'])->get();
    }
}
