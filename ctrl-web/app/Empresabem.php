<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Empresabem
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DATACADASTRO
 * @property int $DEPRECIACAODIAS
 * @property float $DEPRECIACAOPORCENTAGEM
 * @property float $DEPRECIACAOVALOR
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $NUMEROSERIE
 * @property int|null $TIPODEPRECIACAO
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property float $VALORATUAL
 * @property float $VALORORIGINAL
 * @property-read \App\Empresa $empresa
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Empresabemdepreciacao[] $empresaBemDepreciacao
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereDATACADASTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereDEPRECIACAODIAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereDEPRECIACAOPORCENTAGEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereDEPRECIACAOVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereNUMEROSERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereTIPODEPRECIACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereVALORATUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Empresabem whereVALORORIGINAL($value)
 * @mixin \Eloquent
 */
class Empresabem extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'valor', 'valororiginal',
        'numeroserie', 'valoratual', 'depreciacaovalor', 'depreciacaoporcentagem',
        'depreciacaodias', 'ativo', 'datacadastro'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresaBemDepreciacao()
    {
        return $this->hasMany('App\Empresabemdepreciacao');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

}
