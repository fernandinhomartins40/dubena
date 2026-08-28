<?php

namespace App\Models\Financeiro;

use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Model;

/**
 * Condição de pagamento (à vista / N parcelas com intervalo) — C4.
 * Escopada por grupo. O FinanceiroService usa num_parcelas/intervalo/dias_primeira
 * para parcelar o título do pedido.
 */
class CondicaoPagamento extends Model
{
    use BelongsToGrupo;

    protected $table = 'condicaopagamentos';

    protected $fillable = [
        'tenant_account_id', 'grupo_id', 'descricao', 'num_parcelas', 'intervalo_dias',
        'dias_primeira', 'a_vista', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'num_parcelas' => 'integer',
            'intervalo_dias' => 'integer',
            'dias_primeira' => 'integer',
            'a_vista' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
