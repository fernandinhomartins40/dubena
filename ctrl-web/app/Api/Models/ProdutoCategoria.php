<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\ProdutoCategoria
 *
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string $descricao
 * @property int $ativo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoria whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoria whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoria whereDescricao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoria whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoria whereUpdatedAt($value)
 */
class ProdutoCategoria extends ApiModel
{
    protected $table = 'produtocategorias';

    protected $fillable = [
        'descricao', 'ativo'
    ];
}


