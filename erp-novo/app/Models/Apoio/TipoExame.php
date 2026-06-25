<?php

namespace App\Models\Apoio;

/** Tipo de exame ocupacional (ASO) — cadastro de apoio do RH (F04). */
class TipoExame extends CadastroApoio
{
    protected $table = 'tipos_exame';

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'admissional'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'admissional' => 'boolean'];
    }
}
