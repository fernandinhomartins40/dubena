<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\ClienteTelefone
 *
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int $cliente_id
 * @property string $telefone
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteTelefone whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteTelefone whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteTelefone whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteTelefone whereTelefone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteTelefone whereUpdatedAt($value)
 * @property-read \App\ClienteImportacao $cliente
 */
class ClienteTelefone extends ApiModel
{
    protected $table = 'clientetelefones';

    protected $fillable = [
        'cliente_id', 'telefone'
    ];

    public function cliente()
    {
        return $this->belongsTo(ClienteImportacao::class, "cliente_id");
    }
}


