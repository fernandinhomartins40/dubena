<?php

namespace App\Processors\Nfe\Tributacao\Base;

use \Exception;

/**
 *
 * @author Jeferson
 */
class IbsCbsBase extends ValidationBase
{

    /**
     * se true, quando houver erro retorna um dd() quando alguma tag obrigatória não foi preenchida
     * @var bool
     */
    private $debug = true;

    /**
     * código da CST do IBS
     * @var string|int
     */
    public $CST;

    /**
     * código da Classificação Tributária do IBS
     * @var string|int
     */
    public $cClassTrib;

    /**
     * Valor da BC do IBS
     * @var float
     */
    public $vBC;

    /**
     * Percentual da Redução de BC do IBS
     * @var float
     */
    public $pRedBC = 0;

    /**
     * Alíquota do imposto:
     * <i>Alíquota do IBS.</i>
     * @var float
     */
    public $gIBSUF_pIBSUF;
    public $gIBSMun_pIBSMun;
    public $gCBS_pCBS;

    /**
     * valor do IBS
     * @var float
     */
    public $gIBSUF_vIBSUF;
    public $gIBSMun_vIBSMun;
    public $gCBS_vCBS;


    public $qBCMono;
    public $adRemIBS;
    public $adRemCBS;
    public $vIBSMono;
    public $vCBSMono;


    /**
     * Quantidade tributada retida anteriormente
     * @var float
     */
    public $qBCMonoRet;

    /**
     * Aliquota ad rem do imposto retido anteriormente
     * @var float
     */
    public $adRemIBSRet;
    public $adRemCBSRet;

    /**
     * Valor do IBS retido anteriormente
     * @var float
     */
    public $vIBSMonoRet;
    public $vCBSMonoRet;

    /**
     * Seta para null todos os campos que não são usadas no CST 00
     */
    private function unsetFor00()
    {
        $this->qBCMonoRet = null;
        $this->adRemIBSRet = null;
        $this->vIBSMonoRet = null;
        $this->adRemCBSRet = null;
        $this->vCBSMonoRet = null;
        $this->qBCMono = null;
        $this->adRemIBS = null;
        $this->adRemCBS = null;
        $this->vIBSMono = null;
        $this->vCBSMono = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 620
     */
    private function unsetFor620()
    {
        $this->vBC = null;
        $this->pRedBC = null;
        $this->gIBSUF_pIBSUF = null;
        $this->gIBSUF_vIBSUF = null;
        $this->gIBSMun_pIBSMun = null;
        $this->gIBSMun_vIBSMun = null;
        $this->gCBS_pCBS = null;
        $this->gCBS_vCBS = null;
    }

    /**
     * Seta para null todos os campos que não são usadas no CST 410
     */
    private function unsetFor410()
    {
        $this->qBCMonoRet = null;
        $this->adRemIBSRet = null;
        $this->vIBSMonoRet = null;
        $this->adRemCBSRet = null;
        $this->vCBSMonoRet = null;
        $this->qBCMono = null;
        $this->adRemIBS = null;
        $this->adRemCBS = null;
        $this->vIBSMono = null;
        $this->vCBSMono = null;
        
        $this->vBC = null;
        $this->pRedBC = null;
        $this->gIBSUF_pIBSUF = null;
        $this->gIBSUF_vIBSUF = null;
        $this->gIBSMun_pIBSMun = null;
        $this->gIBSMun_vIBSMun = null;
        $this->gCBS_pCBS = null;
        $this->gCBS_vCBS = null;
    }

    /**
     * Limpa valores do IBS dependendo do código da CST
     * @return IbsBase
     * @throws Exception
     */
    public function clearUnusedTags()
    {
        $cst = strval($this->CST);

        if ($cst === '000') {
            $this->unsetFor00();
        } elseif ($cst === '620') {
            $this->unsetFor620();
        }  elseif ($cst === '410') {
            $this->unsetFor410();
        } 

        return $this->validate();
    }

    /**
     * seta para '' as tags que estão nulas
     * função usada ao instanciar a classe, após armazenar alguns valores básicos das csts
     */
    public function toEmptyTags()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_null($value))
                $this->{$key} = '';
        }
    }

    /**
     * valida os campos das csts que são obrigarótias
     * @return $this
     * @throws Exception
     */
    public function validate()
    {
        $vars = get_object_vars($this);
        $errors = "";

        foreach ($vars as $key => $value) {
            $isAllowedFields = !hasStrIn($key, ["allIbs", "debug", "ibs"]);
            $hasError = (!is_null($value) && empty($value) && !$value <= 0) && $isAllowedFields;
            if ($hasError) {
                $errors .= "Campo $key é obrigatório para o cst $this->CST. \n";
            }

            if (!is_null($value) && !empty($value) && $this->isFloatKey($key)) {
                $decimals = strlen(substr(strrchr($value, "."), 1));
                if ($decimals <= 1)
                    $decimals = 2;
                $this->{$key} = sprintf('%0.' . $decimals . 'f', $value);
            }
        }

        if ($errors !== "") {
            throw new \Exception($errors);
        }

        return $this;
    }

    /**
     * @param $key
     * @return bool
     */
    public function isFloatKey($key)
    {
        $notFloat = [
            'debug',
            'CST',
            'cClassTrib'
        ];
        return !in_array(strval($key), $notFloat);
    }
}
