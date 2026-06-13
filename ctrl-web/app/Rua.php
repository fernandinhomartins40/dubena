<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Rua
 *
 * @property string|null $ATIVO
 * @property int|null $BAIRRO_ID
 * @property string|null $CEP
 * @property int $CIDADE_ID
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $IMPORTACAOCEP_ID
 * @property string $NFECOMPL
 * @property string|null $UPDATED_AT
 * @property-read \App\Cidade $cidade
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Estado $estado
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereBAIRROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereIMPORTACAOCEPID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereNFECOMPL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Rua whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Rua extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'bairro_id', 'cidade_id', 'descricao', 'ativo', 'cep', 'importacaocep_id', 'nfecompl'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function cidade()
    {
        return $this->belongsTo('App\Cidade');
    }

    public function estado()
    {
        return $this->belongsTo('App\Estado');
    }

    public function ignorados()
    {
        return $this->morphToMany(self::class, "model", "inconsistencia_ignorada", "model_id", "ignored_id")
            ->wherePivot("ignored_type", self::class)
            ->withTimestamps();
    }
}
