<?php

namespace App\Models\Pedido;

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Situação do pedido com EFEITO explícito (PENDENTE/CONCLUIDO/CANCELADO).
 * Escopo por grupo (config compartilhada). Legado: pedidosituacaos.
 */
class PedidoSituacao extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'pedidosituacoes';

    protected $fillable = ['grupo_id', 'descricao', 'efeito', 'padrao_tela_pedido', 'ordem', 'ativo'];

    protected function casts(): array
    {
        return [
            'efeito' => EfeitoPedido::class,
            'padrao_tela_pedido' => 'boolean',
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
