<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Agencia
 *
 * @property int $AGENCIA
 * @property int $AGENCIADIGITO
 * @property string|null $ATIVO
 * @property int $BAIRRO_ID
 * @property int $BANCO_ID
 * @property string|null $CEP
 * @property int $CIDADE_ID
 * @property string|null $COMPLEMENTO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property string|null $EMAIL
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $NUMERO
 * @property string|null $PONTOREFERENCIA
 * @property int|null $POSTOBENEFICIARIO
 * @property int|null $RUA_ID
 * @property string|null $UF
 * @property string|null $UPDATED_AT
 * @property-read \App\Bairro $bairro
 * @property-read \App\Banco $banco
 * @property-read \App\Cidade $cidade
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\EmpresasGrupo[] $empresasGrupos
 * @property-read \App\Rua $rua
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Agenciatelefone[] $telefones
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereAGENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereAGENCIADIGITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereBAIRROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereBANCOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereCOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereEMAIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia wherePONTOREFERENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia wherePOSTOBENEFICIARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereRUAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Agencia whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Agencia extends Model
{


    protected $fillable = ['grupo_id', 'banco_id', 'cidade_id', 'bairro_id', 'agencia', 'agenciadigito',
        'postobeneficiario', 'descricao', 'ativo', 'rua_id', 'numero', 'complemento', 'email', 'cep', 'uf', 'pontoreferencia'];

    public function empresasGrupos()
    {
        return $this->hasMany('App\EmpresasGrupo');
    }

    public function banco()
    {
        return $this->belongsTo('App\Banco');
    }

    public function cidade()
    {
        return $this->belongsTo('App\Cidade');
    }

    public function bairro()
    {
        return $this->belongsTo('App\Bairro');
    }

    public function telefones()
    {
        return $this->hasMany('App\Agenciatelefone');
    }

    public function rua()
    {
        return $this->belongsTo('App\Rua', 'rua_id');
    }

}
