<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Contamovimentotipo
 *
 * @property string|null $ATIVO
 * @property string $CARTAO
 * @property string $CHEQUE
 * @property string|null $CONVENIO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $PAGARRECEBER
 * @property string|null $UPDATED_AT
 * @property string|null $VALEGAS
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Contamovimento[] $contaMovimento
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereCARTAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereCHEQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereCONVENIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo wherePAGARRECEBER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Contamovimentotipo whereVALEGAS($value)
 * @mixin \Eloquent
 */
class Contamovimentotipo extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'cheque', 'cartao',
        'pagarreceber', 'ativo', 'convenio', 'valegas'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function contaMovimento()
    {
        return $this->hasMany('App\Contamovimento');
    }

}
