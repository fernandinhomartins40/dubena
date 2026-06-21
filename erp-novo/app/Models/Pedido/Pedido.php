<?php

namespace App\Models\Pedido;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pedido — escopo por empresa. Valores decimal nativo. A situação carrega o
 * efeito (máquina de estados); a transição é orquestrada pelo PedidoService.
 */
class Pedido extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'cliente_id', 'pedidooperacao_id', 'pedidosituacao_id',
        'setor_id', 'atendente_user_id', 'entregador_user_id', 'financeiro_id',
        'datahora', 'datahora_acao', 'entrega_urgente', 'entrega_telefone',
        'entrega_taxa', 'entrega_troco_para', 'valor_venda', 'valor_desconto',
        'observacao', 'estoque_movimentado',
    ];

    protected function casts(): array
    {
        return [
            'datahora' => 'datetime',
            'datahora_acao' => 'datetime',
            'entrega_urgente' => 'boolean',
            'entrega_taxa' => 'decimal:2',
            'entrega_troco_para' => 'decimal:2',
            'valor_venda' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'estoque_movimentado' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function situacao(): BelongsTo
    {
        return $this->belongsTo(PedidoSituacao::class, 'pedidosituacao_id');
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(PedidoOperacao::class, 'pedidooperacao_id');
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function historico(): HasMany
    {
        return $this->hasMany(PedidoSituacaoHistorico::class);
    }
}
