<?php

namespace App\Processors\Sped;

use App\Helpers\Utils\NfUtil;

/**
 * Classe abstrata de Registros
 */
abstract class AbstractReg
{

    /**
     * total Retenção ICMS ST
     * @var float
     */
    public $VL_TOT_RETENCAO_ST = 0;

    /**
     * total Credito ICMS
     * @var float
     */
    public $VL_TOT_CREDITOS = 0;

    /**
     * total Debito ICMS
     * @var float
     */
    public $VL_TOT_DEBITOS = 0;

    /**
     * total Credito ICMS ST
     * @var float
     */
    public $VL_TOT_DEVOL_ST = 0;

    /**
     * total Debito ICMS ST
     * @var float
     */
    public $VL_TOT_RESSARC_ST = 0;

    protected static $delimiter;
    protected $path;
    protected $line;
    protected $errors;
    protected $childrens;
    protected $regs = [];
    protected $retornaMultiplo = false;
    protected $aValidation;
    protected $orderOfFields;
    protected $isMultiple;
    protected $genericError;
    protected $defaults;
    protected $exceptions;
    protected $models;
    protected $isChildren = false;
    public static $numRegStatic;
    public $numReg;
    public $bloco;
    public $none;
    public $total;

    /**
     * seta o array das validações dos campos
     */
    abstract protected function getValidationArray();

    /**
     * 
     * @param string $opt
     * @param array $data
     * @param array $childrens
     * @return $this
     * @throws \Exception
     */
    public function __construct($opt, $data = [], $childrens = [])
    {
        if (is_array($opt)) {
            $this->reAssign($opt);
            $reg = $this->toEmptyStr();
            return $reg->treatExceptions($reg)->validateAttributes()->setLine();
        } else {
            $path = $opt;
        }

        if (is_array($data) && isset($data['retornaMultiplo']) && $data['retornaMultiplo']) {
            $this->retornaMultiplo = true;
        }

        if (!is_array($data)) {
            $d = (array) $data;
        } else {
            $d = $data;
        }
        if (array_key_exists('isChildren', $d) && $d['isChildren']) {
            $this->isChildren = true;
        }

        $this->path = $path;
        self::$delimiter = "|";
        $this->setNumReg();

        $aValid = $this->getValidationArray();
        $this->orderOfFields = array_keys($aValid);
        $this->aValidation = $this->toStd($aValid);

        $this->childrens = $childrens;

        $this->none = false;
        $this->total = 0;

        $this->line = self::$delimiter;
        $this->setDefaultsAttrs();

        $reg = $this->setAttributes($data);

        if (is_null($reg)) {
            throw new \Exception("setAttributes não pode retornar null");
        }

        //Se não, realiza o restante das validações e gera a linha
        $reg = $reg->toEmptyStr();
        return $reg->treatExceptions($reg)->validateAttributes()->setLine();
    }

    protected function getTotal()
    {
        $total = isset($this->regs) && count($this->regs) > 0 ? 0 : 1;
        if (isset($this->regs)) {
            foreach ($this->regs as $reg) {
                $total += $reg->getTotal();
            }
        }
        return $total;
    }

    /**
     *
     * Seta o código do Registro
     *
     */
    protected function setNumReg()
    {
        $pathClass = explode('\\', get_class($this));
        $i = count($pathClass) - 1;
        if (!isset($pathClass[$i])) {
            throw new \Exception("Não foi possível verificar o nome do registro");
        }

        $this->numReg = substr($pathClass[$i], 3);
        static::setNumRegStatic($this->numReg);
        $i = count($pathClass) - 2;
        if (!isset($pathClass[$i])) {
            throw new \Exception("Não foi possível verificar o bloco do registro");
        }

        $this->bloco = $pathClass[$i];
    }

    /**
     *
     * Seta o código do Registro
     *
     * @param $numReg
     */
    protected static function setNumRegStatic($numReg) {
        static::$numRegStatic = $numReg;
    }

    /**
     * 
     * sobrescreve-lo somente em caso de haver validações na classe filha
     * transforma os valores que ficaram nulos em string vazias
     * 
     * @return $this
     */
    public function toEmptyStr()
    {
        foreach ($this as $key => $value) {
            $this->$key = $value === null ? "" : $value;
        }
        return $this;
    }

    /*
     * sobrescreve-lo somente em caso de haver validações na classe filha
     * retorna linha
     */

    final public function getLine()
    {
        if (isset($this->regs) && count($this->regs) > 0) {
            $this->line = "";
            $i = count($this->regs);
            foreach ($this->regs as $reg) {
                $i--;
                $this->line .= $reg->getLine() . ($i === 0 ? "" : "\r\n");
            }
        }
        return $this->line;
    }

    /*
     * chamar essa função somente ao gerar uma nova linha
     * passar por parâmetro todos os atributos da linha que serão separados pelo delimitador
     * retorna a linha da sped
     */

    protected function setLine()
    {
        $lineCompleted = self::$delimiter . $this->numReg . self::$delimiter;
        if (!is_array($this->orderOfFields)) {
            throw new \Exception("O atributo orderOfFields deve ser um array. Registro: " . get_class($this));
        }

        foreach ($this->orderOfFields as $line) {
            $lineCompleted .= trim($this->{$line}) . self::$delimiter;
        }

        $this->line = trim($lineCompleted);

        return $this;
    }

    /*
     * sobrescreve-lo somente em caso de haver validações na classe filha
     * não obrigatório na classe filha
     * retorna a própria classe permitindo chamar outra função a partir do mesmo
     */
    protected function validaRegistro()
    {
        return $this;
    }

    /*
     * sobrescreve-lo somente em caso de haver atributos na classe filha
     * não obrigatório na classe filha
     * passar como parâmetro os dados vindos da controller
     * retorna a própria classe permitindo chamar outra função a partir do mesmo
     */
    protected function setAttributes($data = [])
    {
        return $this;
    }

    /*
     * adiciona uma quebra de linha no atributo $line
     * chamar somente em casos onde o mesmo Registro gera mais de uma linha
     * retorna a própria classe permitindo chamar outra função a partir do mesmo
     */

    final protected function breakLine()
    {
        $this->line .= "\r\n|";
        return $this;
    }

    /*
     * adiciona erros a string de erros
     * não retorna nada
     */

    final public function addError($error)
    {
        $this->errors .= "* Registro $this->numReg - $error \r\n";
    }

    /*
     * retorna a string de erros
     */

    final public function getErrors()
    {
        if (isset($this->regs)) {
            foreach ($this->regs as $reg) {
                $this->errors .= $reg->getErrors();
            }
        }
        if (isset($this->childrens)) {
            foreach ($this->childrens as $regs) {
                foreach ($regs as $key => $reg) {
                    if (class_exists($key)) {
                        $this->errors .= $reg->getErrors();
                    } else {
                        unset($this->childrens[$key]);
                    }
                }
            }
        }
        return $this->errors;
    }

    public function __set($name, $value)
    {
        throw new \Exception("Atributo \"$name\" não existe para setar \"$value\" no registro $this->numReg");
    }

    public function __get($name)
    {
        throw new \Exception("Atributo \"$name\" não existe no registro $this->numReg");
    }

    public function __toString()
    {
        return $this->numReg;
    }

    //adiciona filhos. Aceita array ou string.
    //caso sejam passados arrays, a index dos parâmetros 
    // devem corresponder ao index dos registros filhos passados
    protected function addChildren($child, $pars, $isChildren = true)
    {
        if (!is_array($child)) {
            $child = [$child];
        }

        if (!is_array($pars)) {
            $pars = [$pars];
        }

        $countChild = count($child);

        for ($i = 0; $i < $countChild; $i++) {
            $class = $this->path . '\\' . $child[$i];

            if (!class_exists($class)) {
                throw new \Exception("Classe $class não foi encontrada");
            }

            $par = isset($pars[$i]) ? $pars[$i] : $pars[0];
            if ($isChildren) {
                if (!isset($this->childrens[$child[$i]]) && !$this->isChildren) {
                    throw new \Exception("Registro filho " . $child[$i] . " não foi "
                    . "configurado no registro " . $this->numReg);
                }
                if (is_array($par)) {
                    $par['isChildren'] = true;
                } else {
                    $par->isChildren = true;
                }
                
                if (!array_key_exists($child[$i], $this->childrens)) {
                    $this->childrens[$child[$i]] = [];
                }
                
                array_push($this->childrens[$child[$i]], new $class($this->path, $par));
            } else {
                array_push($this->regs, new $class($par));
            }
        }
    }

    public function getChildrens()
    {
        return $this->childrens;
    }

    public function getMultipleRegs()
    {
        return $this->regs;
    }

    public function returnMultiple()
    {
        return isset($this->retornaMultiplo) && $this->retornaMultiplo;
    }

    public function getField($str)
    {
        return $this->$str;
    }

    public function setIndMov($ind)
    {
        if (isset($this->IND_MOV)) {
            $this->IND_MOV = $ind;
        }
    }

    protected function addReg($reg)
    {
        $arr = [];
        foreach (get_object_vars($reg) as $key => $value) {
            $arr[$key] = $value;
        }

        $arr['regs'] = [];
        $this->addChildren("Reg" . $this->numReg, [$arr], false);

        $this->clearToMultiple();
    }

    /**
     * Gera o objeto de validação básica do registro
     * 
     * @param mixed ...$args 
     * <i>possíveis parametros:</i>
     * <p> Descrição do campo que aparecerá pro usuário em caso de erro </p>
     * <p> tamanho máximo do campo </p>
     * <p> define se o campo precisa ter no máximo ou exatamente o tamanho </p>
     * <p> obrigatoriedade do campo (O, OC, N) </p>
     * <p> array com valores aceitos pelo campo, não passar caso não possua </p>
     * <p> function para validações específicas</p>
     * @return static
     */
    protected static function getBaseVR(...$args)
    {
        $newArgs = (object) [];

        $args = Util::toAssoc($args);

        if (is_array($args[0])) {
            $args = Util::toAssoc($args[0], true);
        }

        $newArgs->description = $args[0];
        $newArgs->max_size = false;
        $newArgs->exact_size = false;
        $newArgs->requirement = "O";
        $newArgs->accepted = false;

        if (array_key_exists(1, $args)) {
            if (is_callable($args[1])) {
                $args[5] = $args[1];
            } else {
                $newArgs->max_size = $args[1];
            }
        }

        if (array_key_exists(2, $args)) {
            if (is_callable($args[2])) {
                $args[5] = $args[2];
            } else {
                $newArgs->exact_size = $args[2];
            }
        }

        if (array_key_exists(3, $args)) {
            if (is_callable($args[3])) {
                $args[5] = $args[3];
            } else {
                $newArgs->requirement = $args[3];
            }
        }

        if (array_key_exists(4, $args)) {
            if (is_callable($args[4])) {
                $args[5] = $args[4];
            } else {
                $newArgs->accepted = $args[4];
            }
        }

        $newArgs->callable = array_key_exists(5, $args) && is_callable($args[5]) ? $args[5] : null;

        return static::treatArgsBaseVR($newArgs);
    }

    private static function treatArgsBaseVR($newArgs)
    {
        $msgEx = "";
        if (0 >= $newArgs->max_size && $newArgs->max_size !== false) {
            $msgEx = "\$size não pode ser menor que zero na validação do registro " . self::numRegStatic;
        }

        if (!is_array($newArgs->accepted) && !is_bool($newArgs->accepted)) {
            $msgEx = "Accepted precisa ser bool ou array";
        }

        if ($msgEx !== "") {
            throw new \Exception($msgEx);
        }

        return $newArgs;
    }

    protected function validateAttributes()
    {
        if ($this->retornaMultiplo) {
            return $this;
        }
        foreach ($this->aValidation as $attribute => $validation) {
            if ($this->has($attribute, true)) {
                $value = $this->{$attribute};

                if ($attribute === 'IND_MOV' && !Util::hasIn($value, ["0", "1"])) {
                    $this->addError("Indicador de movimento inválido");
                } elseif ($attribute === 'IND_MOV') {
                    continue;
                } else {
                    $this->validateAttr($value, $validation, $attribute);
                }
            }
        }
        return $this;
    }

    protected function validateAttr($value, $validation, $key)
    {
        if ($this->hasExceptionIn($key)) {
            return $this;
        }
        $this->validateAttrSize(mb_strlen($value), $validation, $key);
        $this->validateRequirement($value, $validation, $key);

        if ($validation->accepted) {
            $this->validateAccepted($value, $validation, $key);
        }

        if (is_callable($validation->callable)) {
            call_user_func_array($validation->callable, [$value]);
        }
        return $this;
    }

    protected function validateAttrSize($len, $valid, $key)
    {
        $size = $valid->max_size;
        $required = ($valid->requirement === "OC" && $len !== 0) || ($valid->requirement === "O");

        if ($size === false) {
            $isDiff = false;
            $error = "";
        } elseif ($valid->exact_size) {
            $isDiff = $len !== $size;
            $error = " deve possuir exatamente ";
        } else {
            $isDiff = $len > $size;
            $error = " não deve possuir mais que ";
        }

        if ($required && $isDiff) {
            $this->addError($valid->description . $error . $size . " caracteres.");
        }

        return $this;
    }

    protected function validateRequirement($attr, $validation, $key)
    {
        if ($validation->requirement == "O" && mb_strlen($attr) === 0) {
            $err = "O campo " . $validation->description
                    . " é obrigatório.";
            $this->addError($err);
        }
        return $this;
    }

    protected function validateAccepted($attr, $validation, $key)
    {
        if ($validation->requirement == "O" && !Util::hasIn($attr, $validation->accepted)) {
            $err = "O campo " . $validation->description
                    . " só aceita os caracteres ["
                    . implode(', ', $validation->accepted)
                    . "].";
            $this->addError($err);
        }
        return $this;
    }

    protected function hasExceptionIn($key)
    {
        if (!is_array($this->exceptions)) {
            return false;
        }
        $isValidated = true;
        foreach ($this->exceptions as $e) {
            if (in_array($this->{$e['field']}, $e['value']) && in_array($key, $e['remains'])) {
                $isValidated = false;
                break;
            }
        }
        return $isValidated;
    }

    protected function has($attribute, $ex = false)
    {
        if (!property_exists($this, $attribute)) {
            if ($ex) {
                $msg = "A classe " . get_class($this) . " não possúi o atributo " . $attribute;
                throw new \Exception($msg);
            } else {
                return false;
            }
        }
        return true;
    }

    protected function toStd($array)
    {
        return (object) $array;
    }

    protected function toArray($object = null)
    {
        if ($object === null) {
            return get_object_vars($this);
        }
        return (array) $object;
    }

    protected function __clone()
    {
        $this->regs = [];
    }

    protected function reAssign($class)
    {
        foreach ($class as $key => $value) {
            $this->{$key} = $value;
        }
        $this->retornaMultiplo = false;
    }

    protected function setGenericError($error)
    {
        $this->genericError = "** $error \r\n";
    }

    public function getGenericError()
    {
        return $this->genericError ? $this->genericError : "";
    }

    protected function clearToMultiple()
    {
        foreach ($this->defaults as $key => $value) {
            if (!in_array($key, ['defaults', 'regs', 'none', 'exceptions'])) {
                $this->{$key} = $value;
            }
        }

        if (count($this->childrens)) {
            $this->childrens = $this->defaults['childrens'];
            foreach ($this->childrens as $key => $child) {
                $this->childrens[$key] = [];
            }
        }
    }

    protected function setDefaultsAttrs()
    {
        $this->defaults = get_object_vars($this);
    }

    protected function getCodSit($nf)
    {
        if (NfUtil::isValid($nf->nfsituacao_id) && !$nf->protocoloretornocancelamento) {
            if ($nf->nfefinalidade == 2) {
                return "06";
            } else {
                return "00";
            }
        } elseif (NfUtil::isCanceled($nf->nfsituacao_id)) {
            return "02";
        } elseif (NfUtil::isDeny($nf->nfsituacao_id)) {
            return "04";
        } else {
            return false;
        }
    }

    protected function treatExceptions($reg)
    {
        if (!is_array($reg->exceptions)) {
            return $reg;
        }
        foreach ($reg->exceptions as $e) {
            if (in_array($reg->{$e['field']}, $e['value'])) {
                foreach (get_object_vars($reg) as $key => $self) {
                    $notRemains = isset($e['remains']) ? !in_array($key, $e['remains']) : true;
                    if ($notRemains && in_array($key, $reg->orderOfFields)) {
                        $reg->{$key} = "";
                    }
                }
            }
        }

        return $reg;
    }

    protected function generateBloco9($data)
    {
        $total9900 = 1;

        foreach ($data['totais'] as $key => $reg) {
            if ($reg->count) {
                $this->REG_BLC = $key;
                $this->QTD_REG_BLC = $reg->count;
                $total9900++;
                $this->addReg($this);
            }
        }

        $this->REG_BLC = '9990';
        $this->QTD_REG_BLC = 1;
        $total9900++;
        $this->addReg($this);

        $this->REG_BLC = '9999';
        $this->QTD_REG_BLC = 1;
        $total9900++;
        $this->addReg($this);

        $this->REG_BLC = '9900';
        $this->QTD_REG_BLC = $total9900;
        $this->addReg($this);
    }

    protected function setValuesOfCredDeb($item, $COD_SIT)
    {
        $allowCodSit = $COD_SIT !== "07" && $COD_SIT !== "01";
        $childrens = collect($this->childrens);
        $aRegGeraDeb = [
            'C190', 'C320', 'C390', 'C490', 'C590', 'C690', 'C790', 'C850',
            'C890', 'D190', 'D300', 'D390', 'D410', 'D590', 'D690', 'D696'
        ];
        $geraDeb = false;
        foreach ($aRegGeraDeb as $value) {
            if ($childrens->get("Reg" . $value)){
                $geraDeb = true;
                break;
            }
        }

        if (Util::geraDebitoICMS($item->cfop) && $allowCodSit && $geraDeb) {
            $this->VL_TOT_DEBITOS += $item->vicms;
        }
        $aRegGeraCred = ['C190', 'C590', 'D190', 'D590'];
        $geraCred = false;
        foreach ($aRegGeraCred as $value) {
            if ($childrens->get("Reg" . $value)){
                $geraCred = true;
                break;
            }
        }
        if (Util::geraCredICMS($item->cfop) && $geraCred) {
            $this->VL_TOT_CREDITOS += $item->vicms;
        }
        if (Util::geraRessarcST($item->cfop)) {
            $this->VL_TOT_RESSARC_ST += $item->vicmsst;
        }
        if (Util::geraRetencaoST($item->cfop)) {
            $this->VL_TOT_RETENCAO_ST += $item->vicmsst;
        }
        if (Util::geraDevolST($item->cfop)) {
            $this->VL_TOT_DEVOL_ST += $item->vicmsst;
        }
    }
}
