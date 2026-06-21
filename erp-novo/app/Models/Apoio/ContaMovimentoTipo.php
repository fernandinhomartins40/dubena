<?php

namespace App\Models\Apoio;

class ContaMovimentoTipo extends CadastroApoio
{
    protected $table = 'contamovimentotipos';

    protected $fillable = [
        'grupo_id', 'descricao', 'ativo',
        'pagarreceber', 'cheque', 'cartao', 'valegas', 'convenio',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'cheque' => 'boolean',
            'cartao' => 'boolean',
            'valegas' => 'boolean',
            'convenio' => 'boolean',
        ];
    }
}
