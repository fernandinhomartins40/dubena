<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Pedido
 *
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string $observacoes
 * @property int $condicaopagamento_id
 * @property float $desconto_cupons
 * @property int $pedidosituacao_id
 * @property int $pedidooperacao_id
 * @property int $cliente_id
 * @property int $endereco_id
 * @property int $erp_id
 * @property int $user_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereCondicaopagamentoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereEnderecoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereErpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereObservacoes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido wherePedidooperacaoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido wherePedidosituacaoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereUserId($value)
 * @property string|null $datahoraenvioentregador
 * @property string|null $datahoraentrega
 * @property string|null $datahoracancelamento
 * @property string|null $datahoraprevisao
 * @property-read \App\ClienteImportacao $cliente
 * @property-read \App\CondicaoPagamento $condicaoPagamento
 * @property-read \App\ClienteEndereco $endereco
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\PedidoItem[] $items
 * @property-read \App\PedidoSituacao $situacao
 * @property-read \App\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereDatahoracancelamento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereDatahoraentrega($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereDatahoraenvioentregador($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereDatahoraprevisao($value)
 * @property int|null $colaborador_id
 * @property-read \App\ClienteImportacao $clienteWithPhone
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereColaboradorId($value)
 * @property int $nao_avaliado
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedido whereNaoAvaliado($value)
 */
class Pedido extends ApiModel
{
    protected $table = 'pedidos';

    protected $fillable = [
        "observacoes",
        "condicaopagamento_id",
        "pedidosituacao_id",
        "nao_avaliado",
        "cliente_id",
        "endereco_id",
        "erp_id",
        "user_id",
        "datahoraenvioentregador",
        "datahorentrega",
        "datahoracancelamento",
        "datahoraprevisao",
        "codigogb",
        "placa",
        "cupom_id",
        "desconto_cupons",
        "pago",
        "expirado",
        "gasdopovo",
        "valorfrete"
    ];

    public function items()
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function cliente()
    {
        return $this->belongsTo(ClienteImportacao::class, "cliente_id");
    }

    public function cupom()
    {
        return $this->belongsTo(Cupom::class, "cupom_id");
    }

    public function condicaoPagamento()
    {
        return $this->belongsTo(CondicaoPagamento::class, "condicaopagamento_id");
    }

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function situacao()
    {
        return $this->belongsTo(PedidoSituacao::class, "pedidosituacao_id");
    }

    public function endereco()
    {
        return $this->belongsTo(ClienteEndereco::class, "endereco_id");
    }
}


