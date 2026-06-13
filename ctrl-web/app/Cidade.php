<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Cidade
 *
 * @property int|null $COD_IBGE
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int|null $GRUPO_ID
 * @property int $ID
 * @property string|null $UF
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Bairro[] $bairros
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Empresa[] $empresas
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Estado $estado
 * @property-read \App\Estado $estados
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Rua[] $ruas
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cidade whereCODIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cidade whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cidade whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cidade whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cidade whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cidade whereUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Cidade whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Cidade extends Model
{
    protected $fillable = ['descricao', 'uf', 'grupo_id', 'cod_ibge'];

    public function bairros()
    {
        return $this->hasMany('App\Bairro');
    }

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresas()
    {
        return $this->hasMany('App\Empresa');
    }

    public function ruas()
    {
        return $this->hasMany('App\Rua');
    }
    public function estados()
    {
        return $this->belongsTo('App\Estado', 'uf');
    }
    public function estado()
    {
        return $this->belongsTo('App\Estado', 'uf');
    }
}
