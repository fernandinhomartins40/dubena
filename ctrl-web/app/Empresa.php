<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Empresa
 *
 * @property string|null $ATIVO
 * @property int $BAIRRO_ID
 * @property int|null $CAPACIDADEARMAZENAMENTO
 * @property string $CEP
 * @property string|null $CERTIFICADODIGITAL
 * @property int $CIDADE_ID
 * @property string|null $CNAE
 * @property string|null $CNPJ
 * @property int|null $CODIGOIBGEPAIS
 * @property string|null $COMPLEMENTO
 * @property int|null $CONTBAIRRO_ID
 * @property string|null $CONTCEP
 * @property int|null $CONTCIDADE_ID
 * @property string|null $CONTCNPJ
 * @property string|null $CONTCOMPLEMENTO
 * @property string|null $CONTCPF
 * @property string|null $CONTCRC
 * @property string|null $CONTEMAIL
 * @property string|null $CONTFAX
 * @property string|null $CONTINGENCIADATAHORA
 * @property string $CONTINGENCIAEMISSAO
 * @property string|null $CONTINGENCIAJUSTIFICATIVA
 * @property string|null $CONTNOME
 * @property string|null $CONTNUMERO
 * @property string|null $CONTRATOCPF
 * @property string|null $CONTRATONOME
 * @property string|null $CONTRATORG
 * @property int|null $CONTRUA_ID
 * @property string|null $CONTTELEFONE
 * @property string|null $CONTUF
 * @property string|null $CREATED_AT
 * @property string $DEPD
 * @property string $DEPR
 * @property string|null $DISTRIBUIDORA
 * @property string|null $EMAIL
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $INSCRICAO_ESTADUAL
 * @property string|null $INSCRICAO_ESTADUAL_ST
 * @property string|null $INSCRICAO_MUNICIPAL
 * @property float|null $LATITUDE
 * @property string|null $LOGO
 * @property mixed|null $LOGOIMG
 * @property float|null $LONGITUDE
 * @property string $MATRIZ
 * @property float|null $NFCECREDITOSIMPLESNACIONAL
 * @property int|null $NFCECRT
 * @property string $NFCEEMITE
 * @property int|null $NFCEMODELO
 * @property int|null $NFCENUMERO
 * @property int|null $NFCENUMEROHOMOLOGACAO
 * @property int|null $NFCESERIE
 * @property int|null $NFCETIPOAMBIENTE
 * @property int|null $NFCETIPOEMISSAO
 * @property string|null $NFCETOKEN
 * @property string|null $NFCETOKEN_PROD
 * @property string|null $NFCETOKENID
 * @property string|null $NFCETOKENID_PROD
 * @property float|null $NFCEVALORLIMITE
 * @property float|null $NFECREDITOSIMPLESNACIONAL
 * @property int|null $NFECRT
 * @property string $NFEEMITE
 * @property int|null $NFEMODELO
 * @property int|null $NFENUMERO
 * @property int|null $NFENUMEROHOMOLOGACAO
 * @property string|null $NFESENHAPFX
 * @property int|null $NFESERIE
 * @property int|null $NFETIPOAMBIENTE
 * @property int|null $NFETIPOEMISSAO
 * @property string|null $NOME_FANTASIA
 * @property string|null $NOME_INFORMAL
 * @property string|null $NUMERO
 * @property string $PRD
 * @property string $PRR
 * @property string $PRT
 * @property string $RAZAO_SOCIAL
 * @property int|null $REGIAO_ID
 * @property string|null $REGISTRO_ANP
 * @property int|null $RUA_ID
 * @property int|null $SPEDAPROPRIACAOCREDITO
 * @property int|null $SPEDATIVIDADE
 * @property string $SPEDEMITE
 * @property int|null $SPEDINCIDENCIATRIBUTARIA
 * @property string|null $SPEDPERFIL
 * @property int|null $SPEDREGIMECUMULATIVO
 * @property string|null $SPEDREGISTRO1010
 * @property int|null $SPEDTIPOCONTRIBUICAO
 * @property string|null $SUFRAMA
 * @property string|null $TELEFONE1
 * @property string|null $TELEFONE2
 * @property string $UF
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Atualizacaoprecos[] $atualizacaoPreco
 * @property-read \App\Bairro $bairro
 * @property-read \App\Cidade $cidade
 * @property-read \App\Bairro $contBairro
 * @property-read \App\Cidade $contCidade
 * @property-read \App\Rua $contRua
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Spedcontribuicoescredito[] $creditos
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Empresaconfig[] $empresaConfigs
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Estado $estado
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Inventario[] $inventario
 * @property-read \App\Regiao $regional
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @property-read \App\Rua $rua
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\User[] $users
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereBAIRROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCAPACIDADEARMAZENAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCERTIFICADODIGITAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCNAE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCODIGOIBGEPAIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTBAIRROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTCOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTCPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTCRC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTEMAIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTFAX($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTINGENCIADATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTINGENCIAEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTINGENCIAJUSTIFICATIVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTRATOCPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTRATONOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTRATORG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTRUAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCONTUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereDEPD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereDEPR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereDISTRIBUIDORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereEMAIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereINSCRICAOESTADUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereINSCRICAOESTADUALST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereINSCRICAOMUNICIPAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereLATITUDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereLOGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereLOGOIMG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereLONGITUDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereMATRIZ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCECREDITOSIMPLESNACIONAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCECRT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCEEMITE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCEMODELO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCENUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCENUMEROHOMOLOGACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCESERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCETIPOAMBIENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCETIPOEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCETOKEN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCETOKENID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCETOKENIDPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCETOKENPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFCEVALORLIMITE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFECREDITOSIMPLESNACIONAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFECRT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFEEMITE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFEMODELO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFENUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFENUMEROHOMOLOGACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFESENHAPFX($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFESERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFETIPOAMBIENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNFETIPOEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNOMEFANTASIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNOMEINFORMAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa wherePRD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa wherePRR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa wherePRT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereRAZAOSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereREGIAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereREGISTROANP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereRUAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSPEDAPROPRIACAOCREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSPEDATIVIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSPEDEMITE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSPEDINCIDENCIATRIBUTARIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSPEDPERFIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSPEDREGIMECUMULATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSPEDREGISTRO1010($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSPEDTIPOCONTRIBUICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSUFRAMA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereTELEFONE1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereTELEFONE2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereUPDATEDAT($value)
 * @mixin \Eloquent
 * @property int|null $SATTIPOAMBIENTE
 * @property string|null $USASAT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereSATTIPOAMBIENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresa whereUSASAT($value)
 */
class Empresa extends Model
{
    use \App\Services\RevisionsTraitService;

    protected $identity = 'grupo_id';

    protected $dontKeepRevisionOf = ['logo', 'latitude', 'longitude', 'logoimg', 'matriz'];

    protected $fillable = ['grupo_id', 'razao_social', 'nome_fantasia', 'cnpj',
        'inscricao_estadual', 'numero', 'complemento', 'telefone1',
        'telefone2', 'email', 'rua_id', 'cidade_id', 'cep', 'bairro_id', 'ativo', 'uf', 'logo',
        'nome_informal', 'cnae', 'depd', 'depr', 'regiao_id',
        'prt', 'prr', 'prd', 'capacidadearmazenamento', 'contingenciaemissao',
        'contingenciadatahora', 'contingenciajustificativa',
        'codigoibgepais', 'nfeemite', 'nfemodelo', 'nfeserie', 'nfenumero',
        'nfenumerohomologacao', 'nfecrt', 'nfecreditosimplesnacional',
        'nfetipoemissao', 'nfetipoambiente', 'nfceemite', 'nfcemodelo', 'nfceserie',
        'nfcenumero', 'nfcenumerohomologacao', 'nfcecrt',
        'nfcecreditosimplesnacional', 'nfcevalorlimite', 'nfcetipoemissao',
        'nfcetipoambiente', 'spedemite', 'spedperfil', 'spedatividade', 'spedregistro1010',
        'spedincidenciatributaria', 'spedapropriacaocredito', 'spedtipocontribuicao',
        'spedregimecumulativo', 'contnome', 'contcpf', 'contcnpj', 'contcrc', 'conttelefone',
        'contfax', 'contemail', 'contuf', 'contcidade_id', 'contbairro_id', 'contcep',
        'contrua_id', 'contnumero', 'contcomplemento', 'contratonome', 'contratorg', 'contratocpf', 
        'latitude', 'longitude', 'matriz', 'registro_anp', 'distribuidora', 'suframa', 'inscricao_municipal',
        'nfcetoken', 'nfcetokenid', 'inscricao_estadual_st', 'nfesenhapfx', 'nfcetoken_prod', 'nfcetokenid_prod',
        'sattipoambiente', 'usasat', 'geraibscbs'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\Empresasgrupo','grupo_id');
    }

    public function bairro()
    {
        return $this->belongsTo('App\Bairro', 'bairro_id');
    }

    public function cidade()
    {
        return $this->belongsTo('App\Cidade', 'cidade_id');
    }

    public function users()
    {
        return $this->belongsToMany('App\User')->withTimestamps();
    }

    public function estado()
    {
        return $this->belongsTo('App\Estado', 'uf');
    }

    public function rua()
    {
        return $this->belongsTo('App\Rua', 'rua_id');
    }

    public function contRua()
    {
        return $this->belongsTo('App\Rua', 'contrua_id');
    }
    
    public function contBairro()
    {
        return $this->belongsTo('App\Bairro', 'contbairro_id');
    }

    public function contCidade()
    {
        return $this->belongsTo('App\Cidade', 'contcidade_id');
    }

    public function empresaConfigs(){
        return $this->hasMany('App\Empresaconfig');
    }

    public function regional(){
        return $this->belongsTo('App\Regiao','regiao_id');
    }

    public function inventario()
    {
        return $this->hasMany('App\Inventario');
    }

    public function creditos()
    {
        return $this->hasMany('App\Spedcontribuicoescredito');
    }

    public function atualizacaoPreco()
    {
        return $this->hasMany('App\Atualizacaoprecos');
    }
}

