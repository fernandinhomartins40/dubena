<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\CondicaoPagamento
 *
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string $descricao
 * @property int $tipo
 * @property string $ativo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamento whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamento whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamento whereDescricao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamento whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamento whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamento whereUpdatedAt($value)
 * @property string $caminhoimagem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\CondicaoPagamentoImportacao[] $imported
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamento whereCaminhoimagem($value)
 * @property int $ordem
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CondicaoPagamento whereOrdem($value)
 */
class CondicaoPagamento extends Model
{
    protected $table = 'condicaopagamentos';

    protected $fillable = [
        'descricao',
        'tipo',
        'ativo',
        'caminhoimagem',
        'ordem',
        'gasdopovo'
    ];

    public function imported()
    {
        return $this->hasMany(CondicaoPagamentoImportacao::class, "condicaopagamento_id")->whereAtivo(true);
    }
}
