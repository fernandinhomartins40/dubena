<?php

namespace App\Models\Pedido;

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PapelSituacao;
use App\Domain\Tenant\BelongsToGrupo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Situação do pedido com EFEITO explícito (PENDENTE/CONCLUIDO/CANCELADO) e
 * PAPEL operacional declarado (F3-04A).
 *
 * O efeito governa a máquina de estados; o papel diz qual momento operacional a
 * situação representa — "Saiu para entrega" e "Aguardando separação" são ambos
 * PENDENTE, mas só o primeiro significa mercadoria na rua. Sem o papel, o
 * código adivinhava isso pela descrição em português.
 *
 * Escopo por grupo (config compartilhada). Legado: pedidosituacaos.
 */
class PedidoSituacao extends Model
{
    use BelongsToGrupo;
    use HasFactory;

    protected $table = 'pedidosituacoes';

    protected $fillable = ['tenant_account_id', 'grupo_id', 'descricao', 'efeito', 'papel', 'cor', 'padrao_tela_pedido', 'ordem', 'ativo'];

    protected function casts(): array
    {
        return [
            'efeito' => EfeitoPedido::class,
            'papel' => PapelSituacao::class,
            'padrao_tela_pedido' => 'boolean',
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
