<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/** Operação fiscal (natureza × CFOP × movimentação) — C12. Escopo por grupo. */
class OperacaoFiscal extends Model
{
    use BelongsToGrupo;

    protected $table = 'operacoes_fiscais';

    protected $fillable = [
        'tenant_account_id', 'grupo_id', 'descricao', 'descricao_fiscal', 'cfop',
        'movimenta_estoque', 'movimenta_financeiro', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'movimenta_estoque' => 'boolean',
            'movimenta_financeiro' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
