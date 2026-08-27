<?php

namespace App\Models\Apoio;

class ClienteContatoSituacao extends CadastroApoio
{
    protected $table = 'clientecontatosituacoes';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo'];
}
