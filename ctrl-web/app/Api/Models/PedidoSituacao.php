<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\PedidoSituacao
 *
 * @mixin \Eloquent
 * @property int $id
 * @property string $descricao
 * @property int $pendente
 * @property int $ementrega
 * @property int $entregue
 * @property int $cancelado
 * @property int $ativo
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereCancelado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereDescricao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereEmentrega($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereEntregue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao wherePendente($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereUpdatedAt($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\PedidoSituacaoImportacao[] $imported
 * @property string|null $info
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacao whereInfo($value)
 */
class PedidoSituacao extends ApiModel
{
    protected $table = 'pedidosituacoes';

    protected $fillable = [
        'descricao', 'pendente', 'ementrega', 'entregue', 'cancelado', 'ativo', 'info'
    ];

    public function imported()
    {
        return $this->hasMany(PedidoSituacaoImportacao::class, "pedidosituacao_id")->whereAtivo(true);
    }
}


