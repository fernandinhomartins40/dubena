<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Beneficiario
 *
 * @property string $CODBENEF
 * @property string|null $CREATED_AT
 * @property string|null $DATAFIM
 * @property string|null $DATAINICIO
 * @property string $DESCRICAO
 * @property int $ID
 * @property string|null $UF
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Beneficiario whereCODBENEF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Beneficiario whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Beneficiario whereDATAFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Beneficiario whereDATAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Beneficiario whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Beneficiario whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Beneficiario whereUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Beneficiario whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Beneficiario extends Model
{
    protected $fillable = [
        "codbenef", "descricao", "datainicio", "datafim"
    ];
}
