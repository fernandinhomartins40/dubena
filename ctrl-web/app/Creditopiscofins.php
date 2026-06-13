<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Creditopiscofins
 *
 * @property string|null $CODIGO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property int $IDENTIFICADOR
 * @property int|null $PARENT_IDENTIFICADOR
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Creditopiscofins whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Creditopiscofins whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Creditopiscofins whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Creditopiscofins whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Creditopiscofins whereIDENTIFICADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Creditopiscofins wherePARENTIDENTIFICADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Creditopiscofins whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Creditopiscofins extends Model
{
    protected $fillable = ["identificador", "descricao", "codigo", "parent_identificador"];
}
