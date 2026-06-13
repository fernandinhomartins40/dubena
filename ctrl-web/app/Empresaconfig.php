<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Empresaconfig
 *
 * @property string $ANDROIDENVIATODOS
 * @property string $ANDROIDUTILIZA
 * @property int|null $CCCARTAO_ID
 * @property int|null $CCDESPESASDESCONTOS_ID
 * @property int|null $CCDESPESASJUROS_ID
 * @property int|null $CCFRETE_ID
 * @property int|null $CCRECEITASDESCONTOS_ID
 * @property int|null $CCRECEITASJUROS_ID
 * @property int|null $CCVALEGAS_ID
 * @property int|null $CENTROCUSTO_ID
 * @property int|null $CONTACHECKTROCO
 * @property int|null $CONTADEVOLUCAOCHEQUE
 * @property string|null $CREATED_AT
 * @property int $DIASTRABALHADOSEMANA
 * @property string|null $EMAILASSUNTO
 * @property string|null $EMAILCORPO
 * @property string|null $EMAILNOMEREMENTE
 * @property string|null $EMAILPORTASMTP
 * @property string|null $EMAILREMETENTE
 * @property string|null $EMAILREQUERAUTENTICACAO
 * @property string|null $EMAILREQUERCONEXAOTLS
 * @property string|null $EMAILSENHA
 * @property string|null $EMAILSERVIDORSMTP
 * @property string|null $EMAILUSUARIO
 * @property int $EMPRESA_ID
 * @property int|null $FRETEMODALIDADE
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $IMPRESSAOAUTOMATICA
 * @property string|null $IMPRESSAOMODELO
 * @property string|null $IMPRESSAOPORTA
 * @property int|null $IMPRESSAOQTDVIASPEDIDO
 * @property int|null $IMPRESSAOTIPO
 * @property string|null $KEYGOOGLEMAPS
 * @property int|null $MAXIMOPARCELAS
 * @property string|null $MENSAGEMDUPLICATA
 * @property string|null $MENSAGEMGASBOLSO
 * @property int|null $NFCECLIENTE_ID
 * @property int|null $NFOPERACOES_ID
 * @property int|null $OPERACAO_RESSARCIMENTO
 * @property int|null $OPERACAODISK
 * @property int|null $PCCARTAO_ID
 * @property int|null $PCDESPESASDESCONTO_ID
 * @property int|null $PCDESPESASJURO_ID
 * @property int|null $PCFRETE_ID
 * @property int|null $PCRECEITADESCONTO_ID
 * @property int|null $PCRECETAJURO_ID
 * @property int|null $PCVALEGAS_ID
 * @property string $PEDIDOCONTROLATEMPOLIGACOES
 * @property string $PEDIDOEMITENFCE
 * @property int|null $PEDIDOOPERACAO_ID
 * @property int|null $PEDIDOSTATUSPADRAO
 * @property string $PEDIDOVALIDACARTAO
 * @property int|null $PEDIDOVALIDACARTAODIAS
 * @property float|null $PERCENTUALDISTRIBUICAORESUL
 * @property float|null $PERCENTUALENCARGOS
 * @property float|null $PERCENTUALPROVISAODEVEDORES
 * @property float|null $PERCENTUALREMUNERACAOCAPITAL
 * @property string $PERMITEESTOQUENEGATIVO
 * @property int|null $PLANOCONTA_ID
 * @property int|null $PRESENCACOMPRADOR
 * @property int|null $QNDDIASINATIVOCOMPRA
 * @property string|null $SENHAMESTRE
 * @property int|null $SETOR_RESSARCIMENTO
 * @property int $SETORPRINCIPAL_ID
 * @property int|null $TELACONTROLAKM
 * @property int|null $TEMPOENTREGA
 * @property int|null $TEMPOURGENTE
 * @property int|null $TRANSPORTADORPADRAO_ID
 * @property string|null $UPDATED_AT
 * @property string $VALIDAATRASO
 * @property string $VALIDACORDENADASENTREGA
 * @property string $VALIDAGASBOLSO
 * @property-read \App\Centrocusto $centrocusto
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Cliente $nfcecliente
 * @property-read \App\Nfoperacao $pedidoOp
 * @property-read \App\Planoconta $planoconta
 * @property-read \App\Cliente $transportadorPadrao
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereANDROIDENVIATODOS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereANDROIDUTILIZA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCCCARTAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCCDESPESASDESCONTOSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCCDESPESASJUROSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCCFRETEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCCRECEITASDESCONTOSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCCRECEITASJUROSID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCCVALEGASID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCONTACHECKTROCO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCONTADEVOLUCAOCHEQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereDIASTRABALHADOSEMANA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILASSUNTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILCORPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILNOMEREMENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILPORTASMTP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILREMETENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILREQUERAUTENTICACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILREQUERCONEXAOTLS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILSENHA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILSERVIDORSMTP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILUSUARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereFRETEMODALIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereIMPRESSAOAUTOMATICA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereIMPRESSAOMODELO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereIMPRESSAOPORTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereIMPRESSAOQTDVIASPEDIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereIMPRESSAOTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereKEYGOOGLEMAPS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereMAXIMOPARCELAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereMENSAGEMDUPLICATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereMENSAGEMGASBOLSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereNFCECLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereNFOPERACOESID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereOPERACAODISK($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereOPERACAORESSARCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePCCARTAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePCDESPESASDESCONTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePCDESPESASJUROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePCFRETEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePCRECEITADESCONTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePCRECETAJUROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePCVALEGASID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePEDIDOCONTROLATEMPOLIGACOES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePEDIDOEMITENFCE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePEDIDOOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePEDIDOSTATUSPADRAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePEDIDOVALIDACARTAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePEDIDOVALIDACARTAODIAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePERCENTUALDISTRIBUICAORESUL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePERCENTUALENCARGOS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePERCENTUALPROVISAODEVEDORES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePERCENTUALREMUNERACAOCAPITAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePERMITEESTOQUENEGATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig wherePRESENCACOMPRADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereQNDDIASINATIVOCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereSENHAMESTRE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereSETORPRINCIPALID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereSETORRESSARCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereTELACONTROLAKM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereTEMPOENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereTEMPOURGENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereTRANSPORTADORPADRAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereVALIDAATRASO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereVALIDACORDENADASENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereVALIDAGASBOLSO($value)
 * @mixin \Eloquent
 * @property int|null $QUANT_PADRAO
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereQUANTPADRAO($value)
 * @property string|null $EMAILKEYGOOGLE
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresaconfig whereEMAILKEYGOOGLE($value)
 */
class Empresaconfig extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfcecliente_id', 'planoconta_id', 'centrocusto_id',
        'pccartao_id', 'pcreceitadesconto_id', 'pcrecetajuro_id', 'pcdespesasdesconto_id', 'pcdespesasjuro_id',
        'ccvalegas_id', 'pcvalegas_id', 'nfoperacoes_id', 'mensagemgasbolso', 'tempoentrega', 'tempourgente',
        'validacordenadasentrega', 'validagasbolso', 'validaatraso', 'androidenviatodos', 'mensagemduplicata',
        'senhamestre', 'percentualencargos', 'percentualprovisaodevedores', 'percentualremuneracaocapital',
        'percentualdistribuicaoresul', 'permiteestoquenegativo',
        'emailnomeremente', 'emailremetente', 'emailusuario', 'emailsenha', 'telacontrolakm',
        'emailservidorsmtp', 'emailportasmtp', 'emailrequerautenticacao', 'emailrequerconexaotls',
        'emailassunto', 'emailcorpo', 'impressaotipo', 'impressaomodelo', 'pedidostatuspadrao',
        'impressaoporta', 'impressaoautomatica', 'impressaoqtdviaspedido', 'pedidovalidacartao',
        'pedidovalidacartaodias', 'pedidocontrolatempoligacoes', 'androidutiliza', 'tempoidenchamada',
        'diastrabalhadosemana', 'keygooglemaps', 'pedidoemitenfce', 'maximoparcelas', 'contachecktroco',
        'operacaodisk', 'contadevolucaocheque', 'integracaopgto', 'qnddiasinativocompra', 'setorprincipal_id',
        'presencacomprador', 'fretemodalidade', 'ccfrete_id', 'pcfrete_id', 'ccreceitasdescontos_id', 'ccreceitasjuros_id',
        'ccdespesasjuros_id', 'ccdespesasdescontos_id', 'cccartao_id', 'setor_ressarcimento', 'operacao_ressarcimento', 'pedidooperacao_id',
        'transportadorpadrao_id', 'quant_padrao', 'emailkeygoogle', 'pedidooperacaoappnf_id', 'presencacompradorappnf',
        'fretemodalidadeappnf', 'transportadorappnf_id', 'contaappnf_id', 'client_id', 'client_secret', 'chavepix', 'validapixentrega',
        'maloteconta_id',
        'nfoperacaoconvenio_id', 'presencacompradorconvenionf', 'fretemodalidadeconvenionf', 'transportadorconvenionf_id', 
        'contaconvenionf_id', 'pcconvenio_id', 'ccconvenio_id', 'ccfreteconvenio_id', 'pcfreteconvenio_id' , 'setorconvenio_id',
        'veiculoconvenio_id', 'condicaopagamentoconvenio_id', 'fatorpotencialvenda', 'emaildiretoria', 'emailcomercial',
        'condicaopagamentofretegp_id', 'ccfretegp_id', 'pcfretegp_id', 'produtogp_id', 'condicaopagamentogp_id', 'valorfretegp'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo', 'grupo_id');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa', 'empresa_id');
    }

    public function nfcecliente()
    {
        return $this->belongsTo('App\Cliente', 'nfcecliente_id');
    }

    public function planoconta()
    {
        return $this->belongsTo('App\Planoconta', 'planoconta_id');
    }

    public function centrocusto()
    {
        return $this->belongsTo('App\Centrocusto', 'centrocusto_id');
    }

    public function pedidoOp()
    {
        return $this->belongsTo('App\Nfoperacao', 'pedidooperacao_id');
    }

    public function transportadorPadrao()
    {
        return $this->belongsTo('App\Cliente', 'transportadorpadrao_id');
    }

    public function transportadorAppnf()
    {
        return $this->belongsTo('App\Cliente', 'transportadorappnf_id');
    }

    public function contaAppnf()
    {
        return $this->belongsTo('App\Conta', 'contaappnf_id');
    }

    public function maloteConta()
    {
        return $this->belongsTo('App\Conta', 'maloteconta_id');
    }

    public static function getForSession($empresa_id)
    {
        $config = static::where('empresa_id', $empresa_id)->first();

        if (isset($config->senhamestre) && $config->senhamestre != null)
            unset($config->senhamestre);

        if (isset($config->client_id)) {
            unset($config->client_id);
            unset($config->client_secret);
        }

        return $config;
    }

    public function planocontaConvenio()
    {
        return $this->belongsTo('App\Planoconta', 'pcconvenio_id');
    }

    public function centrocustoConvenio()
    {
        return $this->belongsTo('App\Centrocusto', 'ccconvenio_id');
    }

    public function transportadorConvenionf()
    {
        return $this->belongsTo('App\Cliente', 'transportadorconvenionf_id');
    }

    public function contaConvenionf()
    {
        return $this->belongsTo('App\Conta', 'contaconvenionf_id');
    }    

    public function veiculoConvenionf()
    {
        return $this->belongsTo('App\Veiculo', 'veiculoconvenio_id');
    }    

    public function condicaopagamentoConvenionf()
    {
        return $this->belongsTo('App\Condicaopagamento', 'condicaopagamentoconvenio_id');
    }    


}
