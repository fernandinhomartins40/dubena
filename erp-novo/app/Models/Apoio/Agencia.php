<?php

namespace App\Models\Apoio;

class Agencia extends CadastroApoio
{
    protected $table = 'agencias';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo', 'banco_id', 'numero', 'digito'];
}
