<?php

namespace App\Models\Pedido;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avaliação de um pedido feita pelo cliente no app (F3). Nota 1–5 + mensagem,
 * ou `ignorado` quando o cliente dispensa. Herda empresa do pedido.
 */
class PedidoAvaliacao extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herda empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['pedido_id' => 'pedidos'];

    protected $table = 'pedido_avaliacoes';

    protected $fillable = ['empresa_id', 'pedido_id', 'rating', 'mensagem', 'ignorado'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'ignorado' => 'boolean',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
