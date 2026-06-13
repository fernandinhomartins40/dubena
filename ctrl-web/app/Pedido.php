<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Pedido
 *
 * @property int $ATENDENTEUSER_ID
 * @property int $CLIENTE_ID
 * @property int|null $COLABORADOR_ID
 * @property int $CONDICAOPAGAMENTO_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property string|null $DATAHORAACAO
 * @property string|null $DATAHORAENVIOENTREGADOR
 * @property string|null $DATAHORAPREVISAOENTREGA
 * @property int $EMPRESA_ID
 * @property int $ENTREGABAIRRO_ID
 * @property int $ENTREGACIDADE_ID
 * @property string|null $ENTREGACOMPLEMENTO
 * @property string|null $ENTREGADATAHORA
 * @property int|null $ENTREGADORUSER_ID
 * @property float|null $ENTREGALATITUDE
 * @property float|null $ENTREGALONGITUDE
 * @property int $ENTREGANUMERO
 * @property string|null $ENTREGAPONTOREFERENCIA
 * @property int $ENTREGARUA_ID
 * @property int|null $ENTREGASETOR_ID
 * @property float|null $ENTREGATAXA
 * @property string|null $ENTREGATELEFONE
 * @property string $ENTREGAURGENTE
 * @property int|null $FINANCEIRO_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float|null $LATITUDE
 * @property float|null $LONGITUDE
 * @property int|null $MOTIVONAOVENDA_ID
 * @property int|null $NFCE_ID
 * @property string $NFCEGEROU
 * @property string|null $NFCPFCNPJ
 * @property int|null $NFTIPO
 * @property string|null $NUMEROCARTAO
 * @property string|null $OBSERVACAO
 * @property int|null $PEDIDOMOTIVOATRASO_ID
 * @property int $PEDIDOOPERACAO_ID
 * @property int $PEDIDOSITUACAO_ID
 * @property string|null $UPDATED_AT
 * @property float|null $VALORDESCONTO
 * @property float $VALORVENDA
 * @property-read \App\User $atendenteUser
 * @property-read \App\Cliente $cliente
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Condicaopagamento $condicaopagamento
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Conveniofechamentopedido[] $convenioFechamentoPedido
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Setor $entregaSetor
 * @property-read \App\Bairro $entregabairro
 * @property-read \App\Cidade $entregacidade
 * @property-read \App\User $entregadorUser
 * @property-read \App\Rua $entregarua
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Motivonaovenda $motivoNaoVenda
 * @property-read \App\Nfemitida $nfEmitida
 * @property-read \App\Pedidomotivoatraso $pedidoMotivoAtraso
 * @property-read \App\Pedidooperacao $pedidoOperacao
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Pedidosituacaohistorico[] $pedidoSituacaoHisorico
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Pedidoitem[] $pedidoitem
 * @property-read \App\Pedidosituacao $pedidosituacao
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Posvendapesquisa[] $posVendaPesquisa
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @property-read \App\Setor $setor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Vendaativacliente[] $vendaAtivaCliente
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereATENDENTEUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereDATAHORAACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereDATAHORAENVIOENTREGADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereDATAHORAPREVISAOENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGABAIRROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGACIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGACOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGADATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGADORUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGALATITUDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGALONGITUDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGANUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGAPONTOREFERENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGARUAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGASETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGATAXA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGATELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGAURGENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereLATITUDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereLONGITUDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereMOTIVONAOVENDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereNFCEGEROU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereNFCEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereNFCPFCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereNFTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereNUMEROCARTAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido wherePEDIDOMOTIVOATRASOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido wherePEDIDOOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido wherePEDIDOSITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereVALORDESCONTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereVALORVENDA($value)
 * @mixin \Eloquent
 * @property string|null $ENTREGACEP
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereENTREGACEP($value)
 * @property int|null $APIPEDIDO_ID
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereAPIPEDIDOID($value)
 */
class Pedido extends Model
{
    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $fillable = [
        'grupo_id', 'empresa_id', 'cliente_id', 'entregarua_id',
        'entregabairro_id', 'entregacidade_id', 'entregasetor_id', 'atendenteuser_id',
        'entregadoruser_id', 'condicaopagamento_id', 'pedidooperacao_id', 'pedidosituacao_id',
        'financeiro_id', 'pedidomotivoatraso_id', 'motivonaovenda_id', 'datahora', 'datahoraacao',
        'datahoraprevisaoentrega', 'datahoraenvioentregador', 'entregadatahora', 'entreganumero',
        'entregacomplemento', 'entregapontoreferencia', 'entregaurgente', 'entregatelefone',
        'entregataxa', 'entregatrocopara', 'entregatroco', 'valorvenda', 'valordesconto',
        'observacao', 'automatico', 'latitude', 'longitude', 'entregalatitude', 'entregalongitude',
        'nfcegerou', 'nfce_id', 'numerocartao', 'colaborador_id', 'nfcpfcnpj', 'nftipo',
        'customedio', 'entregacep', 'apipedido_id', 'gasdopovo', 'financeiroentregagp_id'
    ];

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

    public function entregarua()
    {
        return $this->belongsTo('App\Rua');
    }

    public function entregabairro()
    {
        return $this->belongsTo('App\Bairro');
    }

    public function entregacidade()
    {
        return $this->belongsTo('App\Cidade');
    }

    public function entregaSetor()
    {
        return $this->belongsTo('App\Setor', 'entregasetor_id');
    }

    public function atendenteUser()
    {
        return $this->belongsTo('App\User', 'atendenteuser_id');
    }

    public function entregadorUser()
    {
        return $this->belongsTo('App\User', 'entregadoruser_id');
    }

    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador', 'colaborador_id');
    }

    public function condicaopagamento()
    {
        return $this->belongsTo('App\Condicaopagamento');
    }

    public function pedidoOperacao()
    {
        return $this->belongsTo('App\Pedidooperacao', 'pedidooperacao_id');
    }

    public function pedidosituacao()
    {
        return $this->belongsTo('App\Pedidosituacao');
    }

    public function financeiro()
    {
        return $this->belongsTo('App\Financeiro');
    }

    public function pedidoMotivoAtraso()
    {
        return $this->belongsTo('App\Pedidomotivoatraso');
    }

    public function motivoNaoVenda()
    {
        return $this->belongsTo('App\Motivonaovenda');
    }

    public function convenioFechamentoPedido()
    {
        return $this->hasMany('App\Conveniofechamentopedido');
    }

    public function vendaAtivaCliente()
    {
        return $this->hasMany('App\Vendaativacliente');
    }

    public function posVendaPesquisa()
    {
        return $this->hasMany('App\Posvendapesquisa');
    }

    public function pedidoSituacaoHisorico()
    {
        return $this->hasMany('App\Pedidosituacaohistorico');
    }

    public function pedidoitem()
    {
        return $this->hasMany('App\Pedidoitem');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor', 'entregasetor_id');
    }

    public function nfEmitida()
    {
        return $this->belongsTo('App\Nfemitida', 'nfce_id');
    }

    public function financeiroentregagp()
    {
        return $this->belongsTo('App\Financeiro', 'financeiroentregagp_id', 'id');
    }

}
