<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Produtoleiimposto
 *
 * @property float $ALIQESTADUAL
 * @property float $ALIQIMP
 * @property float $ALIQMUNICIPAL
 * @property float $ALIQNAC
 * @property string $CHAVE
 * @property string|null $CREATED_AT
 * @property string|null $DESCRICAO
 * @property string $EX
 * @property int $ID
 * @property int $NCM
 * @property int $TABELA
 * @property string|null $UF
 * @property string|null $UPDATED_AT
 * @property string $VERSAO
 * @property-read \App\Estado $uf
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereALIQESTADUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereALIQIMP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereALIQMUNICIPAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereALIQNAC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereCHAVE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereEX($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereNCM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereTABELA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereVERSAO($value)
 * @mixin \Eloquent
 * @property string|null $FIM
 * @property string|null $INICIO
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Produtoleiimposto whereINICIO($value)
 */
class Produtoleiimposto extends Model
{

    protected $fillable = ['uf', 'ncm', 'ex', 'tabela', 'descricao', 'aliqnac', 'aliqimp',
        'aliqestadual', 'aliqmunicipal', 'chave', 'versao'];

    public function uf()
    {
        return $this->belongsTo('App\Estado');
    }

}
