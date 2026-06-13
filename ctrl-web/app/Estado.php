<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Estado
 *
 * @property int|null $COD_IBGE
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property string $UF
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Cidade[] $cidades
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estado whereCODIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estado whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estado whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estado whereUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Estado whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Estado extends Model
{

    public $incrementing = false;
    protected $primaryKey = 'uf';
    protected $fillable = ['descricao', 'cod_ibge'];

    public function cidades()
    {
        return $this->hasMany('App\Cidade', 'uf');
    }

    public function selectEstados($empresa)
    {
        return $this->orderBy('descricao')->pluck('descricao','uf');
    }

}
