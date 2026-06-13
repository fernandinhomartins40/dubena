<?php

namespace App\Processors\Nfe\Tributacao\Base;

use \Exception;

class ValidationBase
{

    /**
     * @param $name
     * @throws Exception
     */
    public function __get($name)
    {
        if (! is_object($name)) {
            throw new Exception("Atributo \"$name\" não existe na classe: " . get_class($this));
        } else {
            throw new Exception("Erro ao capturar atributos");
        }
    }

    /**
     * @param $name
     * @param $value
     * @throws Exception
     */
    public function __set($name, $value)
    {
        if (! is_object($name) && ! is_object($value)) {
            throw new Exception("Atributo setar \"$value\" ao \"$name\"");
        } else {
            throw new Exception("Erro ao setar atributos");
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return get_class($this);
    }

}
