<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\PedidoOperacaoImportacao
 *
 * @property-read \App\PedidoOperacao $pedidoOperacao
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int $pedidooperacao_id
 * @property int $erp_id
 * @property int $user_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacaoImportacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacaoImportacao whereErpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacaoImportacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacaoImportacao wherePedidooperacaoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacaoImportacao whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacaoImportacao whereUserId($value)
 */
class PedidoOperacaoImportacao extends Model
{
    protected $table = 'pedidooperacaoimportacoes';

    protected $fillable = [
        'erp_id', 'pedidooperacao_id', 'user_id'
    ];

    public function pedidoOperacao()
    {
        return $this->belongsTo(PedidoOperacao::class);
    }
}
