<?php

namespace App\Models\Apoio;

class TelefoneTipo extends CadastroApoio
{
    protected $table = 'telefonetipos';

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'celular'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'celular' => 'boolean'];
    }
}
