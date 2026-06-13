<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\EmpresasGrupo
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $ID
 * @property string|null $LOGO
 * @property mixed|null $LOGOIMG
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Cidade[] $cidades
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Empresa[] $empresas
 * @method static \Illuminate\Database\Eloquent\Builder|\App\EmpresasGrupo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\EmpresasGrupo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\EmpresasGrupo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\EmpresasGrupo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\EmpresasGrupo whereLOGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\EmpresasGrupo whereLOGOIMG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\EmpresasGrupo whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class EmpresasGrupo extends Model
{

    protected $fillable = ['descricao', 'ativo', 'logo'];

    public function cidades()
    {
        return $this->hasMany('App\Cidade');
    }

    public function empresas()
    {
        return $this->hasMany('App\Empresa');
    }

}
