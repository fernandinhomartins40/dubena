<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Regiao
 *
 * @property string $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Empresa[] $empresas
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Regiao whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Regiao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Regiao whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Regiao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Regiao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Regiao whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Regiao extends Model
{
    protected $fillable = ['grupo_id','descricao', 'ativo'];

    public function empresas()
    {
        return $this->hasMany('App\Empresa');
    }
}
