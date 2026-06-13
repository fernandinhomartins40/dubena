<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\ProdutoImportacao
 *
 * @property-read \App\Produto $produto
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string|null $caminhoimagem
 * @property string|null $mensagemsemestoque
 * @property int $semestoque
 * @property int $erp_id
 * @property int $user_id
 * @property int $produto_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereCaminhoimagem($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereErpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereMensagemsemestoque($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereProdutoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereSemestoque($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereUserId($value)
 * @property int|null $produtocategoriaimportacao_id
 * @property int|null $ativo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoImportacao whereProdutocategoriaimportacaoId($value)
 */
class ProdutoImportacao extends Model
{
    protected $table = 'produtoimportacoes';

    protected $fillable = [
        'produto_id', 'erp_id', 'user_id', 'avaliable', "ativo"
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
