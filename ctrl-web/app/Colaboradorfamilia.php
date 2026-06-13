<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Colaboradorfamilia
 *
 * @property string|null $ATIVO
 * @property int $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property string|null $DATANASCIMENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $NOME
 * @property int $PARENTESCO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Parentesco $parentesco
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereDATANASCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia wherePARENTESCOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Colaboradorfamilia whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Colaboradorfamilia extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'colaborador_id', 'parentesco_id', 
        'nome', 'datanascimento', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function colaborador()
    {
        return $this->belongsTo('App\Colaborador');
    }

    public function parentesco()
    {
        return $this->belongsTo('App\Parentesco');
    }

}
