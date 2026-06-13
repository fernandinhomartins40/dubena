<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfcest
 *
 * @property string $CEST
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string|null $NCM
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcest whereCEST($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcest whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcest whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcest whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcest whereNCM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfcest whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Nfcest extends Model
{


    protected $fillable = ['cest', 'ncm', 'descricao', 'ativo'];

}
