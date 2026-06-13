<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Bairro
 *
 * @property int $CIDADE_ID
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Cidade $cidade
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Empresa[] $empresas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Rua[] $ruas
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Bairro whereCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Bairro whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Bairro whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Bairro whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Bairro whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Bairro whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Bairro extends Model
{
    protected $fillable = ['descricao', 'ativo', 'grupo_id', 'cidade_id'];

    public function cidade()
    {
        return $this->belongsTo('App\Cidade');
    }

    public function empresas()
    {
        return $this->hasMany('App\Empresa');
    }

    public function ruas()
    {
        return $this->hasMany('App\Rua');
    }

    public function ignorados()
    {
        return $this->morphToMany(self::class, "model", "inconsistencia_ignorada", "model_id", "ignored_id")
            ->wherePivot("ignored_type", self::class)
            ->withTimestamps();
    }
}
