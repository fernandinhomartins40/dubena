<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\ProdutoCategoriaImportacao
 *
 * @property-read \App\ProdutoCategoria $categoria
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int $erp_id
 * @property string|null $caminhoimagem
 * @property int $produtocategoria_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoriaImportacao whereCaminhoimagem($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoriaImportacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoriaImportacao whereErpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoriaImportacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoriaImportacao whereProdutocategoriaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoriaImportacao whereUpdatedAt($value)
 * @property int $ativo
 * @property int $user_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoriaImportacao whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCategoriaImportacao whereUserId($value)
 */
class ProdutoCategoriaImportacao extends Model
{
    protected $table = 'produtocategoriaimportacoes';

    protected $fillable = [
        'erp_id', 'caminhoimagem', 'produtocategoria_id'
    ];

    public function categoria()
    {
        return $this->belongsTo(ProdutoCategoria::class);
    }
}
