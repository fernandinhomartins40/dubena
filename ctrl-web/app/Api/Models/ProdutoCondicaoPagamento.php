<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\ProdutoCondicaoPagamento
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property float $valor
 * @property int $condicaopagamentoimportacao_id
 * @property int $produtoimportacao_id
 * @property int|null $user_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCondicaoPagamento whereCondicaopagamentoimportacaoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCondicaoPagamento whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCondicaoPagamento whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCondicaoPagamento whereProdutoimportacaoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCondicaoPagamento whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCondicaoPagamento whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ProdutoCondicaoPagamento whereValor($value)
 * @mixin \Eloquent
 */
class ProdutoCondicaoPagamento extends ApiModel
{
    protected $table = "produtocondicaopagamentos";

    protected $fillable = [
        "valor", "produtoimportacao_id", "condicaopagamentoimportacao_id", "user_id"
    ];

    public function produto()
    {
        $this->hasOne(ProdutoImportacao::class, "produtoimportacao_id");
    }

    public function condicaoPagamento()
    {
        $this->hasOne(CondicaoPagamentoImportacao::class, "condicaopagamentoimportacao_id");
    }
}


