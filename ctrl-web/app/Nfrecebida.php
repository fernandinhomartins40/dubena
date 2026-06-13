<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfrecebida
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
 * @property string|null $INUTILIZARCANCELAR
 * @property string|null $NATUREZASPED
 * @property int $NFEFINALIDADE
 * @property string $NFMODELO
 * @property int $NFNUMERO
 * @property int|null $NFOPERACAO_ID
 * @property int $NFPROCESSOEMISSAO
 * @property string $NFSERIE
 * @property int|null $NFSITUACAO_ID
 * @property int|null $NFSITUACAOANTERIOR_ID
 * @property string $NFSUBSERIE
 * @property int $NFTIPOAMBIENTE
 * @property int $NFTIPOEMISSAO
 * @property int|null $NFTIPOIMPRESSAO
 * @property string $NFVERSAOPROCESSAMENTO
 * @property int|null $NITEM
 * @property int|null $NUMERORECIBOENVIO
 * @property int|null $PLANOCONTA_ID
 * @property string|null $PLANOCONTADATA
 * @property string|null $PLANOCONTADESCRICAO
 * @property int|null $PRESENCACOMPRADOR
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
 * @property-read \App\Nfoperacao $nfOperacao
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfrecebidaitem[] $nfRecebidaItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfrecebidaparcela[] $nfRecebidaParcela
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfrecebidavolume[] $nfRecebidaVolume
 * @property-read \App\Nfsituacao $nfSituacao
 * @property-read \App\Nfsituacao $nfSituacaoAnterior
 * @property-read \App\Planoconta $planoConta
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @property-read \App\Setor $setor
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCANCELAMENTOEVEPROTOCOLORET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCANCELAMENTOEVEXMLRETORNO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCANCELAMENTOMOTIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCFOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCHAVEACESSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCHAVEACESSODV($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCHAVEACESSOREF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCODCDV($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCODCNF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCODCRT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCONTIGENCIADATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCONTIGENCIAJUSTIFICATIVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDATAHORAAUTORIZACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDATAHORAEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDATAHORAENTRADASAIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESCRICAOFINANCEIRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESCRICAOOPERACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESCRICAOSITUACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTBAIRRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTCIDADECODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTCIDADENOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTCOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTCPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTEMAIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTENDERECO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTINDICADORIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTPAISCODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTPAISNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTRAZAOSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDESTUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDPECID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDPECREGISTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDPECREGISTRODATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereDPECXMLRETORNO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITBAIRRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITCIDADECODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITCIDADENOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITCNAE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITCOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITCPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITENDERECO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITINSCRICAOMUNICIPAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITNOMEFANTASIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITPAISCODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITPAISNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITRAZAOSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMITUFCODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEPECPROTOCOLO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEPECSTATUSEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEPECXML($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereEXISTERATEIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFORMAPAGAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETECENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETECIDADENOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETECLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETECNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETECONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETECPF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETEENDERECOCOMPL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETEFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETEMAISNF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETEMODALIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETEPLACA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETEPLACAUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETEPLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETERAZAOSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereFRETUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereINFORMACAOADICIONALFISCO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereINFORMACAOCOMPLEMENTAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereINUTILIZARCANCELAR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNATUREZASPED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFEFINALIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFMODELO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFOPERACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFPROCESSOEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFSERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFSITUACAOANTERIORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFSITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFSUBSERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFTIPOAMBIENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFTIPOEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFTIPOIMPRESSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNFVERSAOPROCESSAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNITEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereNUMERORECIBOENVIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida wherePLANOCONTADATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida wherePLANOCONTADESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida wherePLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida wherePRESENCACOMPRADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida wherePROTOCOLO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida wherePROTOCOLORETEVECARTACORRECAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida wherePROTOCOLORETORNOCANCELAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereSTATUSEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereTIPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVBC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVBCFUNRURAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVBCST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVCOFINS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVDESC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVFCP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVFCPST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVFCPSTRET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVFCPUFDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVFRETE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVFUNRURAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVICMS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVICMSDESON($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVICMSUFDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVICMSUFREMET($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVII($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVIPI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVNF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVOUTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVPFUNRURAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVPIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVSEG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereVST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereXML($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereXMLASSINADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereXMLRETORNO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereXMLRETORNOCANCELAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereXMLRETORNOCOMPLETO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereXMLRETORNOCOMPLETOPATH($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereXMLRETORNOEVENTOCARTACORRECAO($value)
 * @mixin \Eloquent
 * @property string|null $PRODUTOSJSON
 * @property int $TIPOLANCAMENTO
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida wherePRODUTOSJSON($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereTIPOLANCAMENTO($value)
 * @property int $IDDEST
 * @property int $INDFINAL
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereIDDEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebida whereINDFINAL($value)
 */
class Nfrecebida extends Model
{
    #region Revisions
    use \App\Services\RevisionsTraitService;

    protected $identity = 'empresa_id';

    protected $revisionCreationsEnabled = true;

    protected $dontKeepRevisionOf = [
        // Definições
        "nfmodelo", 
        // Destinatario
        "destrazaosocial", "destie", "destpaiscodigoibge", "destpaisnome", "destuf", "destcidadenome",
        "destbairro", "destendereco", "destnumero", "destcep", "destcomplemento", "desttelefone", "destemail",
        // Emitente
        "emitrazaosocial", "emitnomefantasia", "emitie", "emitcnpj", "emitinscricaomunicipal", "emitcnae",
        "codcrt", "emitpaisnome", "emitufcodigoibge", "emituf", "emitcidadecodigoibge", "emitcidade_id",
        "emitbairro", "emitendereco", "emitnumero", "emitcep", "emitcomplemento", "emittelefone",
        // Frete
        "freterazaosocial", "fretie", "fretecnpj", "fretecpf", "fretuf", "fretecidadenome", "freteenderecocompl",
        // Financeiro
        "vprod", "vipi", "vbc", "vicms", "vbcst", "vst", "vicmsdeson", "vnf", "vcofins", "vpis",
        "vfcp", "vfcpst",
        // Operações/Status
        "dpecxmlretorno", "cancelamentoevexmlretorno", "xmlretornocompleto", "xmlretornocompletopath",
        "xmlretornoeventocartacorrecao", "epecxml", "xmlassinado", "xmlretornocancelamento", "xml", "xmlretorno",
        // Outros
        "descricaooperacao", "produtosjson",
    ];
    #endregion

    public $avista;
    public $verProc;
    public $destIdEstrangeiro;
    public $destISUF;
    public $destIM;

    protected $fillable = ['grupo_id', 'empresa_id', 'nfoperacao_id', 'chaveacesso', 'chaveacessodv',
        'cfop', 'descricaooperacao', 'formapagamento', 'nfmodelo', 'nfserie', 'nfsubserie', 'nfnumero',
        'datahoraemissao', 'datahoraentradasaida', 'tipo', 'nftipoimpressao', 'nftipoemissao',
        'nftipoambiente', 'nfefinalidade', 'nfprocessoemissao', 'nfversaoprocessamento', 'emitcnpj',
        'emitcpf', 'emitrazaosocial', 'emitnomefantasia', 'emitendereco', 'emitnumero',
        'emitcomplemento', 'emitbairro', 'emitcidade', 'emitcidade_id', 'emitcidadenome',
        'emitcidadecodigoibge', 'emituf', 'emitufcodigoibge', 'emitcep', 'emitpaisnome',
        'emitpaiscodigoibge', 'emittelefone', 'emitie', 'emitinscricaomunicipal', 'emitcnae',
        'destcpf', 'destcnpj', 'destrazaosocial', 'destendereco', 'destnumero',
        'destcomplemento', 'destbairro', 'destcidade_id', 'destcidadenome', 'destcidadecodigoibge',
        'destuf', 'destcep', 'destpaiscodigoibge', 'destpaisnome', 'desttelefone', 'destie',
        'destindicadorie', 'destemail', 'fretemodalidade', 'fretecpf', 'fretecnpj',
        'freterazaosocial', 'freteenderecocompl', 'fretecidadenome', 'fretuf', 'fretie',
        'freteplaca', 'freteplacauf', 'informacaocomplementar', 'informacaoadicionalfisco',
        'cliente_id', 'codcnf', 'codcdv', 'codcrt', 'nitem', 'vbc', 'vicms', 'vbcst', 'vst',
        'vprod', 'vfrete', 'vseg', 'vdesc', 'vii', 'vipi', 'vpis', 'vcofins', 'voutro', 'vnf', 'xml',
        'xmlretorno', 'nfsituacao_id', 'protocolo', 'protocoloretornocancelamento',
        'xmlretornocancelamento', 'contigenciadatahora', 'contigenciajustificativa',
        'dpecxmlretorno', 'dpecregistro', 'dpecregistrodatahora', 'dpecid',
        'cancelamentoevexmlretorno', 'cancelamentoeveprotocoloret', 'cancelamentomotivo',
        'numeroreciboenvio', 'statusevento', 'formavendacodigo', 'financeiro_id',
        'fretefinanceiro_id', 'planoconta_id', 'centrocusto_id', 'freteplanoconta_id',
        'fretecentrocusto_id', 'setor_id', 'user_id', 'comissao', 'fretecliente_id',
        'condicaopagamento_id', 'vbcfunrural', 'vpfunrural', 'vfunrural',
        'xmlretornocompleto', 'xmlretornocompletopath', 'xmlretornoeventocartacorrecao',
        'protocoloretevecartacorrecao', 'chaveacessoref', 'nfsituacaoanterior_id',
        'epecprotocolo', 'epecxml', 'epecstatusevento', 'presencacomprador', 'emissao',
        'descricaofinanceiro', 'inutilizarcancelar', 'datahoraautorizacao', 'fretemaisnf',
        'existerateio', 'fretecondicaopagamento_id', 'xmlassinado', 'planocontadescricao',
        'naturezasped', 'planocontadata', 'descricaosituacao', 'vfcpstret', 'vfcpst',
        'vicmsufremet', 'vicmsufdest', 'vfcpufdest', 'vicmsdeson', 'vfcp', 'produtosjson',
        'tipolancamento', "iddest", "indfinal"
    ];

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

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function condicaoPagamento()
    {
        return $this->belongsTo('App\Condicaopagamento', 'condicaopagamento_id');
    }

    function nfRecebidaVolume()
    {
        return $this->hasMany('App\Nfrecebidavolume');
    }

    function nfRecebidaParcela()
    {
        return $this->hasMany('App\Nfrecebidaparcela');
    }

    function nfRecebidaItem()
    {
        return $this->hasMany('App\Nfrecebidaitem');
    }

    public function update(array $attributes = array(), array $options = array())
    {
        if (isset($attributes['nfsituacao_id'])) {
            $msgError = 'Não foi possível atualizar a situação da NF, contate o suporte!';
            try {
                $sit = \DB::table('nfsituacaos')->where('id', $attributes['nfsituacao_id'])->get()->first();
                if (!is_null($sit)) {
                    $statement = 'UPDATE nfrecebidas set descricaosituacao = \'' . $sit->msgerroreceita . '\' where id = ' . $this->id;
                    \DB::statement($statement);
                } else {
                    throw new \Exception($msgError);
                }
            } catch (\Exception $ex) {
                $msgError .= ' ' . $ex->getMessage();
                throw new \Exception($msgError);
            }
        }
        return parent::update($attributes, $options);
    }

}
