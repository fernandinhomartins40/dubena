<?php

namespace App\Models\Estoque;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo materializado por setor×produto. É derivável do histórico (invariante
 * Σ estoquehistorico = estoquesaldos.quantidade), mantido para leitura rápida.
 */
class EstoqueSaldo extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'estoquesaldos';

    protected $fillable = [
        'empresa_id', 'setor_id', 'produto_id',
        'quantidade', 'quantidade_minima', 'quantidade_maxima', 'custo_medio',
    ];

    /** Custo só pode sair por uma projeção autorizada. */
    protected $hidden = ['custo_medio'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'quantidade_minima' => 'decimal:3',
            'quantidade_maxima' => 'decimal:3',
            'custo_medio' => 'decimal:4',
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
