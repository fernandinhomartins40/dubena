<?php

namespace App\Models\Apoio;

class Feriado extends CadastroApoio
{
    protected $table = 'feriados';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo', 'data', 'recorrente'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'recorrente' => 'boolean', 'data' => 'date'];
    }
}
