<?php

namespace App\Models\Apoio;

/**
 * Cargo como cadastro de apoio (F04) — exposto via /cadastros/cargos. Aponta para
 * a mesma tabela `cargos` do RH; adiciona o extra salario_base ao padrão de apoio.
 */
class Cargo extends CadastroApoio
{
    protected $table = 'cargos';

    protected $fillable = ['grupo_id', 'descricao', 'ativo', 'salario_base'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'salario_base' => 'decimal:2'];
    }
}
