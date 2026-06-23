<?php

namespace App\Models\Apoio;

class Agencia extends CadastroApoio
{
    protected $table = 'agencias';

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'banco_id', 'numero', 'digito'];
}
