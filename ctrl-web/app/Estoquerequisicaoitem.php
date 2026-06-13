<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estoquerequisicaoitem
 *
 * @property string|null $CREATED_AT
 * @property float $CUSTOMEDIO
 * @property string $ENTRADASAIDA
 * @property int $ESTOQUEREQUISICAO_ID
 * @property int $ID
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property int $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Estoquerequisicao $estoquerequisicao
 * @property-read \App\Produto $produto
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem whereCUSTOMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem whereENTRADASAIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem whereESTOQUEREQUISICAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estoquerequisicaoitem whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estoquerequisicaoitem extends Model
{

    protected $fillable = ['estoquerequisicao_id', 'produto_id', 'setor_id',
        'quantidade', 'customedio', 'entradasaida'];

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

    public function estoquerequisicao()
    {
        return $this->belongsTo('App\Estoquerequisicao');
    }

}
