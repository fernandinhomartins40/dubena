<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Veiculodocumento
 *
 * @property string $ALERTA
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string $NUMERO
 * @property int $TIPODOCUMENTO_ID
 * @property string|null $UPDATED_AT
 * @property int $VEICULO_ID
 * @property string|null $VENCIMENTO
 * @property-read \App\Tipodocumento $tipodocumento
 * @property-read \App\Veiculo $veiculo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereALERTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereTIPODOCUMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereVEICULOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculodocumento whereVENCIMENTO($value)
 * @mixin \Eloquent
 */
class Veiculodocumento extends Model
{

    protected $fillable = ['veiculo_id', 'descricao', 'numero', 'vencimento', 'alerta',
        'tipodocumento_id'];

    public function veiculo()
    {
        return $this->belongsTo('App\Veiculo');
    }

    public function tipodocumento()
    {
        return $this->belongsTo('App\Tipodocumento');
    }

}
