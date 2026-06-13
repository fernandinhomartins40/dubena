<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\PedidoSituacaoImportacao
 *
 * @property-read \App\PedidoSituacao $pedidoSituacao
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int $erp_id
 * @property int $pedidosituacao_id
 * @property int $user_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacaoImportacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacaoImportacao whereErpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacaoImportacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacaoImportacao wherePedidosituacaoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacaoImportacao whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacaoImportacao whereUserId($value)
 * @property int $ativo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoSituacaoImportacao whereAtivo($value)
 */
class PedidoSituacaoImportacao extends Model
{
    protected $table = 'pedidosituacaoimportacoes';

    protected $fillable = [
        'erp_id', 'pedidosituacao_id', 'user_id', 'ativo'
    ];

    public function pedidoSituacao()
    {
        return $this->belongsTo(PedidoSituacao::class);
    }
}
