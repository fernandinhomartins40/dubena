<?php

namespace App\Models\Apoio;

class ClienteContatoTipo extends CadastroApoio
{
    protected $table = 'clientecontatotipos';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo'];
}
