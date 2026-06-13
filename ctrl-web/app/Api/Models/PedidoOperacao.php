<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\PedidoOperacao
 *
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int $ativo
 * @property string $descricao
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacao whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacao whereDescricao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoOperacao whereUpdatedAt($value)
 */
class PedidoOperacao extends ApiModel
{
    protected $table = 'pedidooperacoes';

    protected $fillable = [
        'ativo', 'descricao'
    ];
}


