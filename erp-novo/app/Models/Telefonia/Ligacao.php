<?php

namespace App\Models\Telefonia;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de ligação — o HISTÓRICO da bina (T4.4).
 *
 * Diferente de `ChamadaEntrante`, esta linha sobrevive: é dela que sai qualquer
 * relatório de atendimento, e é o `pedido_id` que permite medir a conversão do
 * call-center (quantas ligações viraram venda).
 */
class Ligacao extends Model
{
    use BelongsToTenant;

    protected $table = 'telefonia_ligacoes';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'telefone', 'cliente_id', 'user_id',
        'atendida', 'rejeitada', 'motivo', 'pedido_id',
    ];

    protected function casts(): array
    {
        return [
            'atendida' => 'boolean',
            'rejeitada' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
