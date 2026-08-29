<?php

namespace App\Models\Apoio;

class TipoPessoa extends CadastroApoio
{
    protected $table = 'tipopessoas';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo', 'tipopessoacadastro'];
}
