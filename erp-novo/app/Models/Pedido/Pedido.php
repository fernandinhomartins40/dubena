<?php

namespace App\Models\Pedido;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Monitora\Veiculo;
use App\Models\User;
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
        'condicaopagamento_id',
        'setor_id', 'atendente_user_id', 'entregador_user_id', 'veiculo_id', 'financeiro_id',
        'datahora', 'datahora_acao', 'entrega_urgente', 'entrega_telefone',
        'entrega_taxa', 'entrega_troco_para', 'valor_venda', 'valor_desconto',
        'observacao', 'estoque_movimentado',
        'gasdopovo',
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

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregador_user_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
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

    /** Condicao de pagamento — o `condicaopagamento_id` ja estava no fillable. */
    public function condicao(): BelongsTo
    {
        return $this->belongsTo(CondicaoPagamento::class, 'condicaopagamento_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function historico(): HasMany
    {
        return $this->hasMany(PedidoSituacaoHistorico::class);
    }

    /**
     * Notas fiscais VIVAS (não-canceladas) do pedido. Existe para o `withExists`
     * do index/kanban resolver `tem_nf` em UMA query, em vez de um exists() por
     * linha na serialização (N+1 apontado em PF-2). Não é tenant-scoped na relação
     * porque o Pedido pai já é.
     */
    public function notasVivas(): HasMany
    {
        return $this->hasMany(NotaFiscal::class)
            ->where('situacao', '!=', 'CANCELADA');
    }
}
