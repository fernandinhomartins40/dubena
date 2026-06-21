<?php

namespace App\Models\Estoque;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fechamento de estoque por período: saldo inicial/final por setor×produto.
 */
class EstoqueFechamento extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'estoquefechamentos';

    protected $fillable = [
        'empresa_id', 'setor_id', 'produto_id',
        'data_fechamento', 'saldo_inicial', 'saldo_final', 'aberto',
    ];

    protected function casts(): array
    {
        return [
            'data_fechamento' => 'date',
            'saldo_inicial' => 'decimal:3',
            'saldo_final' => 'decimal:3',
            'aberto' => 'boolean',
        ];
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
