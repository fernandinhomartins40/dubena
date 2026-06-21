<?php

namespace App\Models\Apoio;

class TipoPessoa extends CadastroApoio
{
    protected $table = 'tipopessoas';

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'tipopessoacadastro'];
}
