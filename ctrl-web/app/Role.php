<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Role
 *
 * @property string $APARECE_USER
 * @property string $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property int $TIPO_ID
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Role whereAPARECEUSER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Role whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Role whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Role whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Role whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Role whereTIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Role whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Role extends Model
{
    protected $fillable = ["descricao", "aparece_user", "ativo", "tipo_id", "created_at", "updated_at"];
}
