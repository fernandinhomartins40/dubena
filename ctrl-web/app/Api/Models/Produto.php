<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Produto
 *
 * @property-read \App\ProdutoCategoria $categoria
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string $descricao
 * @property string $ativo
 * @property int|null $produtocategoria_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereDescricao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereProdutocategoriaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereUpdatedAt($value)
 * @property string $caminhoimagem
 * @property-read \App\ProdutoImportacao $imported
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereCaminhoimagem($value)
 * @property mixed|null $thumbnail
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produto whereThumbnail($value)
 */
class Produto extends ApiModel
{
    protected $table = 'produtos';

    protected $fillable = [
        'descricao', 'ativo', 'produtocategoria_id', "thumbnail", "ordem"
    ];

    public function categoria()
    {
        return $this->belongsTo(ProdutoCategoria::class);
    }

    public function imported()
    {
        return $this->belongsTo(ProdutoImportacao::class)->whereAtivo(true);
    }
}


