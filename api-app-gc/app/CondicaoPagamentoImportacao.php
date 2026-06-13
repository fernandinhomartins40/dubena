<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\CondicaoPagamentoImportacao
 *
 * @property-read \App\CondicaoPagamento $condicaoPagamento
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int $erp_id
 * @property int $user_id
 * @property int $condicaopagamento_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamentoImportacao whereCondicaopagamentoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamentoImportacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamentoImportacao whereErpId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamentoImportacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamentoImportacao whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamentoImportacao whereUserId($value)
 * @property int $ativo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamentoImportacao whereAtivo($value)
 */
class CondicaoPagamentoImportacao extends Model
{
    protected $table = 'condicaopagamentoimportacoes';

    protected $fillable = [
        'erp_id', 'user_id', 'condicaopagamento_id', 'ativo'
    ];

    public function condicaoPagamento()
    {
        return $this->belongsTo(CondicaoPagamento::class);
    }
}
