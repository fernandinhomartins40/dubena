<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\PedidoAvaliacao
 *
 * @property int $id
 * @property int $pedido_id
 * @property string $mensagem
 * @property float $rating
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\User[] $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoAvaliacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoAvaliacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoAvaliacao whereMensagem($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoAvaliacao wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoAvaliacao whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoAvaliacao whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PedidoAvaliacao extends ApiModel
{

    protected $table = 'pedidoavaliacoes';

    protected $fillable = [
        'pedido_id', 'mensagem', 'rating'
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }
}


