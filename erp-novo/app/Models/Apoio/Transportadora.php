<?php

namespace App\Models\Apoio;

class Transportadora extends CadastroApoio
{
    protected $table = 'transportadoras';

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'cnpj', 'telefone'];
}
