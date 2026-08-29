<?php

namespace App\Models\Apoio;

class Banco extends CadastroApoio
{
    protected $table = 'bancos';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo', 'codigo', 'site'];
}
