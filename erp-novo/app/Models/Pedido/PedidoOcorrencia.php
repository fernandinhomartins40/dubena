<?php

namespace App\Models\Pedido;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Ocorrência de entrega (P7) — imprevisto registrado pelo entregador. TENANT.
 */
class PedidoOcorrencia extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> herda empresa_id do pedido na criação sem tenant ativo. */
    protected $tenantParent = ['pedido_id' => 'pedidos'];

    protected $table = 'pedido_ocorrencias';

    protected $fillable = [
        'empresa_id', 'pedido_id', 'entregador_user_id', 'tipo', 'descricao',
        'foto_path', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }
}
