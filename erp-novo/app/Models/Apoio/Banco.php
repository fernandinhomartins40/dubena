<?php

namespace App\Models\Apoio;

class Banco extends CadastroApoio
{
    protected $table = 'bancos';

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'codigo', 'site'];
}
