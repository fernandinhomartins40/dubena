<?php

namespace App\Models\Pagamento;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Transação de cartão (NSU/bandeira/parcelas) — C4. Escopo por empresa. */
class CartaoTransacao extends Model
{
    use BelongsToTenant;

    protected $table = 'cartao_transacoes';

    protected $fillable = [
        'empresa_id', 'pedido_id', 'conta_id', 'bandeira', 'tipo', 'nsu', 'autorizacao',
        'parcelas', 'valor_bruto', 'taxa_percentual', 'valor_liquido', 'situacao',
    ];

    protected function casts(): array
    {
        return [
            'parcelas' => 'integer',
            'valor_bruto' => 'decimal:2',
            'taxa_percentual' => 'decimal:2',
            'valor_liquido' => 'decimal:2',
        ];
    }
}
