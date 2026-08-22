<?php

namespace App\Models\Logistica;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma regra de cobrança de entrega.
 *
 * Uma linha por regra, avaliadas por prioridade: assim a revenda combina
 * "grátis acima de R$ 150" com "bairro X custa R$ 5" sem que uma anule a outra.
 */
class TaxaEntrega extends Model
{
    use BelongsToTenant;

    protected $table = 'taxas_entrega';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'descricao', 'criterio',
        'bairro_id', 'cidade_id',
        'faixa_de', 'faixa_ate', 'valor', 'isenta',
        'custo_estimado', 'prioridade', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'faixa_de' => 'decimal:2',
            'faixa_ate' => 'decimal:2',
            'valor' => 'decimal:2',
            'custo_estimado' => 'decimal:2',
            'isenta' => 'boolean',
            'ativo' => 'boolean',
            'prioridade' => 'integer',
        ];
    }

    public function bairro(): BelongsTo
    {
        return $this->belongsTo(Bairro::class);
    }

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class);
    }

    /** Margem da entrega: o que se cobra menos o que custa. */
    public function getMargemAttribute(): ?float
    {
        if ($this->custo_estimado === null) {
            return null;
        }

        return (float) $this->valor - (float) $this->custo_estimado;
    }
}
