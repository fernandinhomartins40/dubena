<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfemitida
 *
 * @property string|null $CANCELAMENTOEVEPROTOCOLORET
 * @property string|null $CANCELAMENTOEVEXMLRETORNO
 * @property string|null $CANCELAMENTOMOTIVO
 * @property int|null $CENTROCUSTO_ID
 * @property int|null $CFOP
 * @property string|null $CHAVEACESSO
 * @property int|null $CHAVEACESSODV
 * @property string|null $CHAVEACESSOREF
 * @property int|null $CLIENTE_ID
 * @property int|null $CODCDV
 * @property int|null $CODCNF
 * @property int|null $CODCRT
 * @property int|null $CONDICAOPAGAMENTO_ID
 * @property string|null $CONTIGENCIADATAHORA
 * @property string|null $CONTIGENCIAJUSTIFICATIVA
 * @property string|null $CREATED_AT
 * @property string|null $DATAHORAAUTORIZACAO
 * @property string $DATAHORAEMISSAO
 * @property string|null $DATAHORAENTRADASAIDA
 * @property string|null $DESCRICAOFINANCEIRO
 * @property string|null $DESCRICAOOPERACAO
 * @property string|null $DESCRICAOSITUACAO
 * @property string|null $DESTBAIRRO
 * @property int|null $DESTCEP
 * @property int|null $DESTCIDADE_ID
 * @property int|null $DESTCIDADECODIGOIBGE
 * @property string|null $DESTCIDADENOME
 * @property string|null $DESTCNPJ
 * @property string|null $DESTCOMPLEMENTO
 * @property string|null $DESTCPF
 * @property string|null $DESTEMAIL
 * @property string|null $DESTENDERECO
 * @property string|null $DESTIE
 * @property int|null $DESTINDICADORIE
 * @property string|null $DESTNUMERO
 * @property int|null $DESTPAISCODIGOIBGE
 * @property string|null $DESTPAISNOME
 * @property string|null $DESTRAZAOSOCIAL
 * @property string|null $DESTTELEFONE
 * @property string|null $DESTUF
 * @property string|null $DPECID
 * @property string|null $DPECREGISTRO
 * @property string|null $DPECREGISTRODATAHORA
 * @property string|null $DPECXMLRETORNO
 * @property int|null $EMISSAO
 * @property string|null $EMITBAIRRO
 * @property int|null $EMITCEP
 * @property int|null $EMITCIDADE_ID
 * @property int|null $EMITCIDADECODIGOIBGE
 * @property string|null $EMITCIDADENOME
 * @property string|null $EMITCNAE
 * @property string|null $EMITCNPJ
 * @property string|null $EMITCOMPLEMENTO
 * @property string|null $EMITCPF
 * @property string|null $EMITENDERECO
 * @property string|null $EMITIE
 * @property string|null $EMITINSCRICAOMUNICIPAL
 * @property string|null $EMITNOMEFANTASIA
 * @property string|null $EMITNUMERO
 * @property int|null $EMITPAISCODIGOIBGE
 * @property string|null $EMITPAISNOME
 * @property string|null $EMITRAZAOSOCIAL
 * @property string|null $EMITTELEFONE
 * @property string|null $EMITUF
 * @property int|null $EMITUFCODIGOIBGE
 * @property int $EMPRESA_ID
 * @property string|null $EPECPROTOCOLO
 * @property string|null $EPECSTATUSEVENTO
 * @property string|null $EPECXML
 * @property string $EXISTERATEIO
 * @property int|null $FINANCEIRO_ID
 * @property int|null $FORMAPAGAMENTO
 * @property int|null $FRETECENTROCUSTO_ID
 * @property string|null $FRETECIDADENOME
 * @property int|null $FRETECLIENTE_ID
 * @property string|null $FRETECNPJ
 * @property int|null $FRETECONDICAOPAGAMENTO_ID
 * @property string|null $FRETECPF
 * @property string|null $FRETEENDERECOCOMPL
 * @property int|null $FRETEFINANCEIRO_ID
 * @property string $FRETEMAISNF
 * @property int|null $FRETEMODALIDADE
 * @property string|null $FRETEPLACA
 * @property string|null $FRETEPLACAUF
 * @property int|null $FRETEPLANOCONTA_ID
 * @property string|null $FRETERAZAOSOCIAL
 * @property string|null $FRETIE
 * @property string|null $FRETUF
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $INFORMACAOADICIONALFISCO
 * @property string|null $INFORMACAOCOMPLEMENTAR
 * @property string $INUTILIZARCANCELAR
 * @property string|null $NATUREZASPED
 * @property int $NFEFINALIDADE
 * @property string $NFMODELO
 * @property int $NFNUMERO
 * @property int|null $NFOPERACAO_ID
 * @property int $NFPROCESSOEMISSAO
 * @property string $NFSERIE
 * @property int|null $NFSITUACAO_ID
 * @property int|null $NFSITUACAOANTERIOR_ID
 * @property int $NFTIPOAMBIENTE
 * @property int $NFTIPOEMISSAO
 * @property int|null $NFTIPOIMPRESSAO
 * @property string $NFVERSAOPROCESSAMENTO
 * @property int|null $NITEM
 * @property string|null $NUMERORECIBOENVIO
 * @property int|null $PLANOCONTA_ID
 * @property string|null $PLANOCONTADATA
 * @property string|null $PLANOCONTADESCRICAO
 * @property int|null $PRESENCACOMPRADOR
 * @property string|null $PRODUTOSJSON
 * @property string|null $PROTOCOLO
 * @property string|null $PROTOCOLORETEVECARTACORRECAO
 * @property string|null $PROTOCOLORETORNOCANCELAMENTO
 * @property string|null $STATUSEVENTO
 * @property int|null $TIPO
 * @property string|null $UPDATED_AT
 * @property int|null $USER_ID
 * @property float|null $VBC
 * @property float|null $VBCFUNRURAL
 * @property float|null $VBCST
 * @property float|null $VCOFINS
 * @property float|null $VDESC
 * @property float|null $VFCP
 * @property float|null $VFCPST
 * @property float|null $VFCPSTRET
 * @property float|null $VFCPUFDEST
 * @property float|null $VFRETE
 * @property float|null $VFUNRURAL
 * @property float|null $VICMS
 * @property float|null $VICMSDESON
 * @property float|null $VICMSUFDEST
 * @property float|null $VICMSUFREMET
 * @property float|null $VII
 * @property float|null $VIPI
 * @property float|null $VNF
 * @property float|null $VOUTRO
 * @property float|null $VPFUNRURAL
 * @property float|null $VPIS
 * @property float|null $VPROD
 * @property float|null $VSEG
 * @property float|null $VST
 * @property string|null $XML
 * @property string|null $XMLASSINADO
 * @property string|null $XMLRETORNO
 * @property string|null $XMLRETORNOCANCELAMENTO
 * @property string|null $XMLRETORNOCOMPLETO
 * @property string|null $XMLRETORNOCOMPLETOPATH
 * @property string|null $XMLRETORNOEVENTOCARTACORRECAO
 * @property-read \App\Centrocusto $centroCusto
 * @property-read \App\Cliente $cliente
 * @property-read \App\Condicaopagamento $condicaoPagamento
 * @property-read \App\Cidade $destCidade
 * @property-read \App\Cidade $emitCidade
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Centrocusto $freteCentroCusto
 * @property-read \App\Cliente $freteCliente
 * @property-read \App\Financeiro $freteFinanceiro
 * @property-read \App\Planoconta $fretePlanoConta
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitidacartacorrecao[] $nfEmitidaCartaCorrecao
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitidaitem[] $nfEmitidaItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitidaparcela[] $nfEmitidaParcela
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitidavolume[] $nfEmitidaVolume
 * @property-read \App\Nfoperacao $nfOperacao
 * @property-read \App\Nfsituacao $nfSituacao
 * @property-read \App\Nfsituacao $nfSituacaoAnterior
 * @property-read \App\Planoconta $planoConta
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCANCELAMENTOEVEPROTOCOLORET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCANCELAMENTOEVEXMLRETORNO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCANCELAMENTOMOTIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCFOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCHAVEACESSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCHAVEACESSODV($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCHAVEACESSOREF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCODCDV($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCODCNF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCODCRT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCONTIGENCIADATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCONTIGENCIAJUSTIFICATIVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDATAHORAAUTORIZACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDATAHORAEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDATAHORAENTRADASAIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESCRICAOFINANCEIRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESCRICAOOPERACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESCRICAOSITUACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTBAIRRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTCIDADECODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTCIDADENOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTCOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTCPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTEMAIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTENDERECO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTINDICADORIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTPAISCODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTPAISNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTRAZAOSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDESTUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDPECID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDPECREGISTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDPECREGISTRODATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereDPECXMLRETORNO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITBAIRRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITCIDADECODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITCIDADENOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITCNAE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITCOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITCPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITENDERECO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITINSCRICAOMUNICIPAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITNOMEFANTASIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITPAISCODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITPAISNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITRAZAOSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMITUFCODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEPECPROTOCOLO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEPECSTATUSEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEPECXML($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereEXISTERATEIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFORMAPAGAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETECENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETECIDADENOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETECLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETECNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETECONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETECPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETEENDERECOCOMPL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETEFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETEMAISNF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETEMODALIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETEPLACA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETEPLACAUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETEPLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETERAZAOSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereFRETUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereINFORMACAOADICIONALFISCO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereINFORMACAOCOMPLEMENTAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereINUTILIZARCANCELAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNATUREZASPED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFEFINALIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFMODELO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFPROCESSOEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFSERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFSITUACAOANTERIORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFSITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFTIPOAMBIENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFTIPOEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFTIPOIMPRESSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFVERSAOPROCESSAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNITEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNUMERORECIBOENVIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida wherePLANOCONTADATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida wherePLANOCONTADESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida wherePLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida wherePRESENCACOMPRADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida wherePRODUTOSJSON($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida wherePROTOCOLO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida wherePROTOCOLORETEVECARTACORRECAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida wherePROTOCOLORETORNOCANCELAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereSTATUSEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVBCFUNRURAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVBCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVDESC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVFCP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVFCPST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVFCPSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVFCPUFDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVFRETE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVFUNRURAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVICMSDESON($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVICMSUFDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVICMSUFREMET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVII($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVNF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVOUTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVPFUNRURAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVSEG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereVST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereXML($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereXMLASSINADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereXMLRETORNO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereXMLRETORNOCANCELAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereXMLRETORNOCOMPLETO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereXMLRETORNOCOMPLETOPATH($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereXMLRETORNOEVENTOCARTACORRECAO($value)
 * @mixin \Eloquent
 * @property string|null $NFC_TPAG
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereNFCTPAG($value)
 * @property int $IDDEST
 * @property int $INDFINAL
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereIDDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitida whereINDFINAL($value)
 */
class Nfemitida extends Model
{
    #region Revisionable
    use \App\Services\RevisionsTraitService;

    protected $identity = 'empresa_id';

    protected $revisionCreationsEnabled = true;

    protected $dontKeepRevisionOf = [
        // Definições
        "nftipoambiente", "nfnumero", "nfserie", "informacaoadicionalfisco", 
        // Emitente
        "emitrazaosocial", "emitnomefantasia", "emitie", "emitcnpj", "emitinscricaomunicipal", "emitcnae",
        "codcrt", "emitpaisnome", "emitufcodigoibge", "emituf", "emitcidadecodigoibge", "emitcidade_id",
        "emitbairro", "emitendereco", "emitnumero", "emitcep", "emitcomplemento", "emittelefone",
        // Destinatario
        "destrazaosocial", "destie", "destpaiscodigoibge", "destpaisnome", "destuf", "destcidadenome",
        "destbairro", "destendereco", "destnumero", "destcep", "destcomplemento", "desttelefone", "destemail",
        // Frete
        "freterazaosocial", "fretie", "fretecnpj", "fretecpf", "fretuf", "fretecidadenome", "freteenderecocompl",
        // Financeiro
        "vprod", "vipi", "vbc", "vicms", "vbcst", "vst", "vicmsdeson", "vnf", "vcofins", "vpis",
        "vfcp", "vfcpst",
        // Operações/Status
        "dpecxmlretorno", "cancelamentoevexmlretorno", "xmlretornocompleto", "xmlretornocompletopath",
        "xmlretornoeventocartacorrecao", "epecxml", "xmlassinado", "produtosjson",
        "xmlretornocancelamento", "xml", "xmlretorno",
        // Outros
        "descricaooperacao"
    ];
    #endregion

    public $avista;
    public $verProc;
    public $destIdEstrangeiro;
    public $destISUF;
    public $destIM;
    
    protected $fillable = ['grupo_id', 'empresa_id', 'nfoperacao_id', 'chaveacesso',
        'chaveacessodv', 'cfop', 'descricaooperacao', 'formapagamento', 'nfmodelo',
        'nfserie', 'nfnumero', 'datahoraemissao', 'datahoraentradasaida', 'tipo',
        'nftipoimpressao', 'nftipoemissao', 'nftipoambiente', 'nfefinalidade',
        'nfprocessoemissao', 'nfversaoprocessamento', 'emitcnpj', 'emitcpf', 'emitrazaosocial',
        'emitnomefantasia', 'emitendereco', 'emitnumero', 'emitcomplemento', 'emitbairro',
        'emitcidade_id', 'emitcidadenome', 'emitcidadecodigoibge', 'emituf',
        'emitufcodigoibge', 'emitcep', 'emitpaisnome', 'emitpaiscodigoibge', 'emittelefone',
        'emitie', 'emitinscricaomunicipal', 'emitcnae', 'destcpf', 'destcnpj', 'destrazaosocial',
        'destendereco', 'destnumero', 'destcomplemento', 'destbairro', 'destcidade_id',
        'destcidadenome', 'destcidadecodigoibge', 'destuf', 'destcep', 'destpaiscodigoibge',
        'destpaisnome', 'desttelefone', 'destie', 'destindicadorie', 'destemail',
        'fretemodalidade', 'fretecpf', 'fretecnpj', 'freterazaosocial', 'freteenderecocompl',
        'fretecidadenome', 'fretuf', 'fretie', 'freteplaca', 'freteplacauf', 'informacaocomplementar',
        'informacaoadicionalfisco', 'cliente_id', 'codcnf', 'codcdv', 'codcrt', 'nitem',
        'vbc', 'vicms', 'vbcst', 'vst', 'vprod', 'vfrete', 'vseg', 'vdesc', 'vii', 'vipi',
        'vpis', 'vcofins', 'voutro', 'vnf', 'xml', 'xmlretorno', 'nfsituacao_id', 'protocolo',
        'protocoloretornocancelamento', 'xmlretornocancelamento', 'contigenciadatahora',
        'contigenciajustificativa', 'dpecxmlretorno', 'dpecregistro', 'dpecregistrodatahora',
        'dpecid', 'cancelamentoevexmlretorno', 'cancelamentoeveprotocoloret', 'cancelamentomotivo',
        'numeroreciboenvio', 'statusevento', 'financeiro_id', 'fretefinanceiro_id',
        'planoconta_id', 'centrocusto_id', 'freteplanoconta_id', 'fretecentrocusto_id',
        'user_id', 'fretecliente_id', 'condicaopagamento_id', 'vbcfunrural', 'vpfunrural',
        'vfunrural', 'xmlretornocompleto', 'xmlretornocompletopath', 'xmlretornoeventocartacorrecao',
        'protocoloretevecartacorrecao', 'chaveacessoref', 'nfsituacaoanterior_id', 'epecprotocolo',
        'epecxml', 'epecstatusevento', 'presencacomprador', 'emissao', 'descricaofinanceiro',
        'inutilizarcancelar', 'datahoraautorizacao', 'fretemaisnf', 'existerateio',
        'fretecondicaopagamento_id', 'xmlassinado', 'planocontadescricao', 'naturezasped', 'planocontadata',
        'vfcpstret', 'vfcpst', 'vicmsufremet', 'vicmsufdest', 'vfcpufdest', 'vicmsdeson', 'vfcp',
        'descricaosituacao', 'produtosjson', "nfc_tpag", "iddest", "indfinal"];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function nfOperacao()
    {
        return $this->belongsTo('App\Nfoperacao');
    }

    public function emitCidade()
    {
        return $this->belongsTo('App\Cidade', 'emitcidade_id');
    }

    public function destCidade()
    {
        return $this->belongsTo('App\Cidade', 'destcidade_id');
    }

    public function cliente()
    {
        return $this->belongsTo('App\Cliente', 'cliente_id');
    }

    public function freteCliente()
    {
        return $this->belongsTo('App\Cliente', 'fretecliente_id');
    }

    public function nfSituacao()
    {
        return $this->belongsTo('App\Nfsituacao', 'nfsituacao_id');
    }

    public function nfSituacaoAnterior()
    {
        return $this->belongsTo('App\Nfsituacao', 'nfsituacaoanterior_id');
    }

    public function financeiro()
    {
        return $this->belongsTo('App\Financeiro', 'financeiro_id');
    }

    public function freteFinanceiro()
    {
        return $this->belongsTo('App\Financeiro', 'fretefinanceiro_id');
    }

    public function planoConta()
    {
        return $this->belongsTo('App\Planoconta', 'planoconta_id');
    }

    public function fretePlanoConta()
    {
        return $this->belongsTo('App\Planoconta', 'freteplanoconta_id');
    }

    public function centroCusto()
    {
        return $this->belongsTo('App\Centrocusto', 'centrocusto_id');
    }

    public function freteCentroCusto()
    {
        return $this->belongsTo('App\Centrocusto', 'fretecentrocusto_id');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function condicaoPagamento()
    {
        return $this->belongsTo('App\Condicaopagamento', 'condicaopagamento_id');
    }

    function nfEmitidaVolume()
    {
        return $this->hasMany('App\Nfemitidavolume');
    }

    function nfEmitidaParcela()
    {
        return $this->hasMany('App\Nfemitidaparcela');
    }

    function nfEmitidaItem()
    {
        return $this->hasMany(Nfemitidaitem::class);
    }

    function nfEmitidaCartaCorrecao()
    {
        return $this->hasMany(Nfemitidacartacorrecao::class);
    }

    public function update(array $attributes = array(), array $options = array())
    {
        if (isset($attributes['nfsituacao_id'])) {
            $msgError = 'Não foi possível atualizar a situação da NF, contate o suporte!';
            try {
                $sit = \DB::table('nfsituacaos')->where('id', $attributes['nfsituacao_id'])->get()->first();
                if (!is_null($sit)) {
                    $statement = 'UPDATE nfemitidas set descricaosituacao = \'' . $sit->msgerroreceita . '\' where id = ' . $this->id;
                    \DB::statement($statement);
                } else {
                    throw new \Exception("A situação " . "[sit: " . $attributes['nfsituacao_id'] . "] não foi cadastrada, por favor contate o suporte!");
                }
            } catch (\Exception $ex) {
                $msgError .= ' ' . $ex->getMessage();
                throw new \Exception($msgError);
            }
        }
        return parent::update($attributes, $options);
    }
}
