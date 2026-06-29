<?php

namespace App\Models\Pedido;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Comprovação de entrega (P7) — foto e/ou assinatura + quem recebeu. TENANT.
 * Os paths apontam para o storage PRIVADO (nunca public).
 */
class PedidoComprovacao extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> herda empresa_id do pedido na criação sem tenant ativo. */
    protected $tenantParent = ['pedido_id' => 'pedidos'];

    protected $table = 'pedido_comprovacoes';

    protected $fillable = [
        'empresa_id', 'pedido_id', 'entregador_user_id', 'recebido_por',
        'foto_path', 'assinatura_path', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }
}
