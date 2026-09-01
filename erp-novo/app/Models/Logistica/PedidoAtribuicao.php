<?php

namespace App\Models\Logistica;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Pedido\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trilha de uma decisão de atribuição de entrega (L1). Imutável na prática (só
 * inserção). Tenant-scoped.
 */
class PedidoAtribuicao extends Model
{
    use BelongsToTenant;

    protected $table = 'pedido_atribuicoes';

    protected $fillable = [
        'empresa_id', 'pedido_id', 'de_entregador_user_id', 'para_entregador_user_id',
        'veiculo_id', 'operador_user_id', 'acao', 'automatico', 'motivo',
        // F6-06 — a regra que decidiu. Sem isto no fillable o dado e descartado
        // em silencio, e a trilha volta a guardar so o rotulo.
        'regra', 'regra_parametros', 'score',
    ];

    protected function casts(): array
    {
        return [
            'automatico' => 'boolean',
            'regra_parametros' => 'array',
            'score' => 'float',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function paraEntregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'para_entregador_user_id');
    }
}
