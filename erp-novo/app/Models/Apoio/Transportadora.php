<?php

namespace App\Models\Apoio;

class Transportadora extends CadastroApoio
{
    protected $table = 'transportadoras';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo', 'cnpj', 'telefone'];
}
