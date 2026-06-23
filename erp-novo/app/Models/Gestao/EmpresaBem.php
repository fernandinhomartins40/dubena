<?php

namespace App\Models\Gestao;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Bem do ativo imobilizado (com depreciação) — C11. Escopo por empresa. */
class EmpresaBem extends Model
{
    use BelongsToTenant;

    protected $table = 'empresa_bens';

    protected $fillable = [
        'empresa_id', 'descricao', 'data_aquisicao', 'valor_aquisicao',
        'taxa_depreciacao_anual', 'valor_residual', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_aquisicao' => 'date',
            'valor_aquisicao' => 'decimal:2',
            'taxa_depreciacao_anual' => 'decimal:2',
            'valor_residual' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }
}
