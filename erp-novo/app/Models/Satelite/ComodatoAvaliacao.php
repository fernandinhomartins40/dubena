<?php

namespace App\Models\Satelite;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A foto periódica de um cliente com comodato: posse, compra e giro.
 *
 * É a série histórica que permite dizer "caiu" em vez de só "está baixo" — e
 * mostrar ao operador a evolução, que é o que sustenta a conversa com o cliente.
 */
class ComodatoAvaliacao extends Model
{
    use BelongsToTenant;

    protected $table = 'comodato_avaliacoes';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'referencia', 'em_posse',
        'comprado_janela', 'dias_janela', 'pedidos_janela', 'giro',
        'baseline_giro', 'variacao', 'dias_sem_compra', 'classificacao', 'motivo',
    ];

    protected function casts(): array
    {
        return [
            'referencia' => 'date',
            'em_posse' => 'decimal:3',
            'comprado_janela' => 'decimal:3',
            'giro' => 'decimal:3',
            'baseline_giro' => 'decimal:3',
            'variacao' => 'decimal:3',
            'dias_janela' => 'integer',
            'pedidos_janela' => 'integer',
            'dias_sem_compra' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Precisa de averiguação humana. */
    public function preocupante(): bool
    {
        return in_array($this->classificacao, ['ATENCAO', 'CRITICO'], true);
    }
}
