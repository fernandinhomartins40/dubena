<?php

namespace App\Models\Pedido;

use App\Domain\Tenant\BelongsToTenant;
use App\Domain\Pedido\EfeitoPedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoSituacaoHistorico extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['pedido_id' => 'pedidos'];

    use HasFactory;

    protected $table = 'pedidosituacaohistorico';

    protected $fillable = ['pedido_id', 'pedidosituacao_id', 'user_id', 'efeito', 'datahora'];

    protected function casts(): array
    {
        return ['efeito' => EfeitoPedido::class, 'datahora' => 'datetime'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
