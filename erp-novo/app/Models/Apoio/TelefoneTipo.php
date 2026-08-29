<?php

namespace App\Models\Apoio;

class TelefoneTipo extends CadastroApoio
{
    protected $table = 'telefonetipos';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'ativo', 'celular'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'celular' => 'boolean'];
    }
}
