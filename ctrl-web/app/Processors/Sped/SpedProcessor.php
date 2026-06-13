<?php

namespace App\Processors\Sped;

use Session;
use App\Empresa;
use App\Services\CarbonCustom as Carbon;

/**
 * 
 */
class SpedProcessor
{

    private $regsLines;
    private $allRegs;
    private $errors;
    private $basePath;
    private $requestView;
    private $totalBloco;
    private $blocos;
    private $totais;

    public function __construct($data, $allowedRegs)
    {
        if (empty($data['mesano'])) {
            throw new \Exception("O campo Mes/Ano é obrigatório");
        } else {
            $data['datainicio'] = Carbon::createFromFormat('m/Y', $data['mesano'])->startOfMonth()->startOfDay();
            $data['datafim'] = Carbon::createFromFormat('m/Y', $data['mesano'])->endOfMonth()->endOfDay();
        }

        if (is_null($data['tipoescrit'])) {
            throw new \Exception("Selecione o tipo de escrituração (Original ou Substituto)");
        }

        $this->allRegs = collect([]);
        $this->blocos = (object) [];
        $this->totais = [];

        $data['nf'] = $this->getNf($data);
        $data['empresa'] = $this->getEmpresa();
        $data['datainicio'] = $data['datainicio']->format('d/m/Y H:i:s');
        $data['datafim'] = $data['datafim']->format('d/m/Y H:i:s');

        $this->createBlocos($data, $allowedRegs);
    }

    public function get()
    {
        if ($this->hasErrors()) {
            return "NOK" . $this->getErrors();
        }
        return "OK|" . $this->generateFile();
    }

    private function getEmpresa()
    {
        $empresa = Empresa::select(['grupo_id', 'razao_social', 'nome_fantasia', 'cnpj',
                            'inscricao_estadual', 'numero', 'complemento', 'telefone1',
                            'telefone2', 'email', 'rua_id', 'cidade_id', 'cep', 'bairro_id',
                            'ativo', 'uf', 'nome_informal', 'cnae', 'depd', 'depr', 'regiao_id',
                            'prt', 'prr', 'prd', 'capacidadearmazenamento', 'codigoibgepais',
                            'nfecreditosimplesnacional', 'nfcecreditosimplesnacional', 'spedemite',
                            'spedperfil', 'spedatividade', 'spedregistro1010',
                            'spedincidenciatributaria', 'spedapropriacaocredito', 'spedtipocontribuicao',
                            'spedregimecumulativo', 'contnome', 'contcpf', 'contcnpj', 'contcrc',
                            'conttelefone', 'contfax', 'contemail', 'contuf', 'contcidade_id',
                            'contbairro_id', 'contcep', 'contrua_id', 'contnumero', 'contcomplemento',
                            'contratonome', 'contratorg', 'contratocpf', 'latitude', 'longitude',
                            'matriz', 'registro_anp', 'distribuidora', 'suframa', 'inscricao_municipal',
                            'inscricao_estadual_st'])
                        ->where('id', Session::get('empresa_padrao')->id)
                        ->with('cidade', 'rua', 'bairro', 'contCidade', 'contBairro', 'contRua')
                        ->get()->first();
        if (!$empresa->spedemite) {
            throw new \Exception("Esta empresa não está habilitada para emitir sped");
        }
        return $empresa;
    }

    private function getNf($data)
    {
        $datainicio = $data['datainicio']->format('Y-m-d H:i:s');
        $datafim = $data['datafim']->format('Y-m-d H:i:s');
        $dateFormat = 'yyyy-mm-dd hh24:mi:ss';
        $empresa_id = Session::get('empresa_padrao')->id;

        $sql = "SELECT * FROM ( SELECT " . $this->columnsDB() . ", 'emitida' as tiponf, "
                . " nfemitida_id as nf_id, '' as nfsubserie "
                . " FROM nfemitidas nf INNER JOIN empresas e ON e.id = nf.empresa_id "
                . " INNER JOIN nfemitidaitems ite ON ite.nfemitida_id = nf.id "
                . " INNER JOIN produtos pro ON pro.id = ite.cProd "
                . " INNER JOIN unidademedidas un ON un.sigla = ite.ucom "
                . " INNER JOIN nfoperacaos op ON op.id = ite.nfoperacao_id "
                . " LEFT JOIN nfsituacaos nfs ON nfs.id = nf.nfsituacao_id "
                . " LEFT JOIN condicaopagamentos pgto ON pgto.id = nf.condicaopagamento_id "
                . " LEFT JOIN nfimpostos imp ON imp.nfoperacao_id = op.id "
                . " LEFT JOIN creditopiscofins tipocred ON imp.piscofinstipocredito = tipocred.id "
                . " LEFT JOIN creditopiscofins piscofinsnat ON imp.piscofinsnatreceita = piscofinsnat.id "
                . " LEFT JOIN creditopiscofins tipocredbc ON imp.piscofinstipobccredito = tipocredbc.id "
                . " LEFT JOIN planocontas pc on pc.id = nf.planoconta_id"
                . " LEFT JOIN clientes dest on dest.id = nf.cliente_id"
                . " WHERE e.id = $empresa_id"
                . " AND datahoraentradasaida "
                . " BETWEEN TO_DATE('$datainicio', '$dateFormat') AND TO_DATE('$datafim', '$dateFormat') UNION ALL "
                . " SELECT " . $this->columnsDB() . ", 'recebida' as tiponf, nfrecebida_id as nf_id, nfsubserie "
                . " FROM nfrecebidas nf INNER JOIN empresas e ON e.id = nf.empresa_id "
                . " INNER JOIN nfrecebidaitems ite ON ite.nfrecebida_id = nf.id "
                . " INNER JOIN produtos pro ON pro.id = ite.cProd "
                . " INNER JOIN unidademedidas un ON un.sigla = ite.ucom "
                . " INNER JOIN nfoperacaos op ON op.id = ite.nfoperacao_id "
                . " LEFT JOIN nfsituacaos nfs ON nfs.id = nf.nfsituacao_id "
                . " LEFT JOIN condicaopagamentos pgto ON pgto.id = nf.condicaopagamento_id "
                . " LEFT JOIN nfimpostos imp ON imp.nfoperacao_id = op.id "
                . " LEFT JOIN creditopiscofins tipocred ON imp.piscofinstipocredito = tipocred.id "
                . " LEFT JOIN creditopiscofins piscofinsnat ON imp.piscofinsnatreceita = piscofinsnat.id "
                . " LEFT JOIN creditopiscofins tipocredbc ON imp.piscofinstipobccredito = tipocredbc.id "
                . " LEFT JOIN planocontas pc on pc.id = nf.planoconta_id"
                . " LEFT JOIN clientes dest on dest.id = nf.cliente_id"
                . " WHERE e.id = $empresa_id "
                . " AND datahoraentradasaida "
                . " BETWEEN TO_DATE('$datainicio', '$dateFormat') AND TO_DATE('$datafim', '$dateFormat'))";

        return collect(\DB::select($sql));
    }

    private function columnsDB()
    {
        return "ite.ucom, ite.cprod, ite.vpis, ite.vprod, ite.vuncom, ite.cean, ite.picms, ite.nfoperacao_id, "
                . " ite.vbcst, ite.vicmsst, "
                . " un.descricao as unidade_medida, op.descricaofiscal, op.spedvenda, "
                . " pro.ncm, pro.nfetipoitem, pro.nfeextipi, pro.nfecodgen, pro.nfecodlst, "
                . " pro.nfedescricaofiscal as produto, nf.destcpf, nf.destcnpj, nf.destrazaosocial, "
                . " nf.destendereco, nf.destnumero, nf.destcomplemento, nf.destbairro, "
                . " nf.destcidade_id, nf.destcidadenome, nf.destcidadecodigoibge, nf.destuf, "
                . " nf.destcep, nf.destpaiscodigoibge, nf.destpaisnome, nf.desttelefone, nf.destie, "
                . " nf.cliente_id, nf.nfsituacao_id, nf.nfmodelo, nfs.msgerroreceita, "
                . " nf.chaveacesso, nf.chaveacessoref, nf.datahoraentradasaida, nf.datahoraemissao, nf.tipo, "
                . " nf.nftipoemissao, nf.nfserie, nf.fretemodalidade, nf.nfnumero, "
                . " nf.vbc as vbcnf, nf.vbcst as vbcstnf, nf.vcofins as vcofinsnf, nf.vdesc as vdescnf, nf.vnf, "
                . " nf.vfrete as vfretenf, nf.vicms as vicmsnf, nf.vst as vstnf, nf.vipi as vipinf, nf.voutro, "
                . " nf.vseg, nf.vpis as vpisnf, nf.vprod as vprodnf, ite.cfop, ite.pcofins, "
                . " ite.cstcofins, ite.cstpis, ite.vbcpis, ite.vbccofins, ite.vcofins, ite.vdesc, "
                . " ite.ppis, ite.pipi, ite.cst, ite.cstipi, ite.qcom, ite.vbc, ite.vbcstret, "
                . " ite.vbcipi, ite.vicms, ite.vicmsstret, ite.vipi, ite.id as item_id, "
                . " imp.piscofinsgeracredito, imp.nfcofinsaliqcred, imp.nfpisaliqcred, "
                . " tipocred.codigo as piscofinstipocredito, tipocredbc.codigo as piscofinstipobccredito, "
                . " piscofinsnat.codigo as piscofinsnatreceita, pc.finalizador as pcfinalizador, "
                . " pc.nivel as pcnivel, nf.planoconta_id, nf.naturezasped, nf.planocontadescricao, "
                . " nf.planocontadata, dest.suframa, protocoloretornocancelamento, nf.nfefinalidade, "
                . " pgto.tipo as tipopagamento, ite.predbc, ite.vicmsdeson, nf.presencacomprador,"
                . " nf.emitcidadecodigoibge ";
    }

    /**
     * 
     * cria os registros com base no array $allowedRegs
     */
    private function createBlocos($data, $allowedRegs)
    {
        $this->basePath = str_replace(DIRECTORY_SEPARATOR, '\\', __NAMESPACE__) . '\\' . $allowedRegs['basePath'];
        $this->requestView = $data;
        unset($allowedRegs['basePath']);
        $indMov = false;

        foreach ($allowedRegs as $bloco => $args) {

            $this->blocos->{$bloco} = (object) [
                        'name'  => $bloco,
                        'count' => 0
            ];

            if (!isset($args['regs'])) {
                throw new \Exception("Nenhum Registro foi definido para o bloco $bloco");
            }

            $this->createRegs($bloco, $args['regs'], $data, count($args['regs']));

            //registros que terminam com 001 são só para indicar se existe registros filhos ou não
            $regIndMov = substr($bloco, 5, 6) . '001';

            if ($this->totalBloco <= 2 && $bloco !== 'Bloco9') {
                $indMov = "1";
                $replaced = $regIndMov . "|0|";
                $replace = $regIndMov . "|1|";
            } elseif ($bloco !== 'Bloco9') {
                $indMov = "0";
                $replaced = $regIndMov . "|1|";
                $replace = $regIndMov . "|0|";
            }

            $reg = $this->allRegs->get("Reg" . $regIndMov)->first();

            if (!is_null($reg) && is_numeric($indMov)) {
                $reg->setIndMov($indMov);
                $this->regsLines = str_replace($replaced, $replace, $this->regsLines);
            }
            $indMov = false;
        }

        return $this;
    }

    private function createRegs($bloco, $regs, $data)
    {
        if (is_string($regs)) {
            $regs = [$regs];
        }

        $this->totalBloco = 0;
        $s = '\\';

        foreach ($regs as $className => $reg) {
            $pathBloco = $this->basePath . $s . $bloco;

            $arrayChildrens = isset($reg['childrens']) ? $reg['childrens'] : [];

            if (!is_array($reg)) {
                $className = $reg;
            } else if (isset($reg['multiple']) && $reg['multiple']) {
                $reg = $className;
                $data['retornaMultiplo'] = true;
            }

            $pathOfClass = $pathBloco . $s . $className;

            if (!class_exists($pathOfClass)) {
                throw new \Exception("Classe $pathOfClass não foi encontrada");
            }

            $data['total'] = $this->totalBloco;
            $data['blocos'] = $this->blocos;
            $data['totais'] = $this->totais;
            $data['allRegs'] = $this->allRegs;

            $childrens = [];
            foreach ($arrayChildrens as $key => $child) {
                if (is_numeric($key)) {
                    $key = $child;
                }
                $childrens[$key] = [];
            }

            $regClass = new $pathOfClass($pathBloco, $data, $childrens);

            if (!isset($this->allRegs->$className)) {
                $this->allRegs[$className] = collect([]);
            }

            if ($regClass->returnMultiple()) {
                $this->addMultiple($className, $regClass->getMultipleRegs());
            } else {
                $this->addReg($className, $regClass);
            }

            $data['retornaMultiplo'] = false;
            $this->addChildrens($className, $regClass);
        }
    }

    // add multiple regs when has children or multiple regs
    private function addMultiple($class, $regs)
    {
        foreach ($regs as $reg) {
            $this->addReg($class, $reg);
            $this->addChildrens($class, $reg);
        }
    }

    private function addChildrens($class, $reg)
    {
        foreach ($reg->getChildrens() as $regs) {
            $this->addMultiple($class, $regs);
        }
    }

    public function generateFile()
    {

        $cnpj = str_replace(['-', '.', '/'], "", Session::get('empresa_padrao')->cnpj);
        $datafim = Carbon::parse(insertDataOracle($this->requestView['datafim']));
        $datafim = substr(getDescricaoMes($datafim->month), 0, 3) . $datafim->year;
        $filename = "EFD_" . $cnpj . "-" . $datafim . ".txt";

        $handle = fopen($filename, 'w+');

        fwrite($handle, $this->getLines());
        fclose($handle);

        return url('/sped.dowload/' . $filename);
    }

    private function addReg($className, $reg)
    {
        if ($reg === null) {
            throw new \Exception("Registro não encontrado: " . $className);
        }

        if (!isset($this->totais[strval($reg->numReg)])) {
            $this->totais[$reg->numReg] = (object) ['count' => 0];
        }

        if (!$reg->none) {
            $this->totais[$reg->numReg]->count++;

            $this->allRegs[$className]->push($reg);

            $this->totalBloco++;

            $bloco = $reg->bloco;
            $this->blocos->{$bloco}->count++;

            $this->addError($reg->getErrors(), $reg->getGenericError());

            $this->regsLines .= $reg->getLine() . "\r\n";
        } else {
            unset($this->allRegs[$className]);
        }
    }

    /**
     * retorna uma excessão quando um atributo que não existe na classe tenta ser chamado
     */
    public function __get($name)
    {
        throw new \Exception("Atributo \"$name\" não existe");
    }

    /*
     * adiciona erros a string de erros
     * não retorna nada
     */

    private function addError($errors, $generic)
    {
        if (strlen($errors) > 0) {
            $this->errors .= $generic . $errors;
        }
    }

    /**
     * retorna todas as linhas dos registros
     */
    public function getLines()
    {
        return $this->regsLines;
    }

    /**
     * retorna todos os registros gerados pelas classes
     */
    public function getAllRegs()
    {
        return $this->allRegs;
    }

    /**
     * retorna os erros
     */
    public function getErrors()
    {
        return $this->errors;
    }

    public function hasErrors()
    {
        return $this->errors ? true : false;
    }

}
