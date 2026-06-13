<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Vendaativa
 *
 * @property string|null $ATIVO
 * @property string $COMPREVISAO
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property string $DESCRICAOFILTRO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Vendaativacliente[] $vendaativacliente
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereCOMPREVISAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereDESCRICAOFILTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativa whereUSERID($value)
 * @mixin \Eloquent
 */
class Vendaativa extends Model
{

    protected $fillable = ['grupo_id','empresa_id','user_id','datahora','descricaofiltro','comprevisao','ativo'];

    public function vendaativacliente()
    {
        return $this->hasMany('App\Vendaativacliente');
    }
}
