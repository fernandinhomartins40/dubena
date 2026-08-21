<?php

namespace App\Models\Venda;

use App\Domain\Tenant\BelongsToTenant;
use App\Domain\Venda\SituacaoSolicitacao;
use App\Models\Cliente\Cliente;
use App\Models\Pedido\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitação de venda do campo para a Central — F3.
 *
 * Rascunho com efeito ZERO: não move estoque, não gera financeiro, não entra na
 * fila de distribuição. O Pedido só nasce quando a Central aprova.
 */
class PedidoSolicitacao extends Model
{
    use BelongsToTenant;

    protected $table = 'pedido_solicitacoes';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'solicitante_user_id', 'colaborador_id',
        'cliente_id', 'setor_id', 'condicaopagamento_id', 'itens',
        'desconto_solicitado', 'justificativa', 'observacao', 'situacao',
        'decidido_por_user_id', 'decidido_em', 'desconto_aprovado',
        'motivo_decisao', 'pedido_id',
    ];

    protected function casts(): array
    {
        return [
            'itens' => 'array',
            'desconto_solicitado' => 'decimal:2',
            'desconto_aprovado' => 'decimal:2',
            'decidido_em' => 'datetime',
            'situacao' => SituacaoSolicitacao::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
