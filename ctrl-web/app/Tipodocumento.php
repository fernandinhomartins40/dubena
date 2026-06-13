<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Tipodocumento
 *
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipodocumento whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipodocumento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipodocumento whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipodocumento whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipodocumento whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipodocumento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Tipodocumento whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Tipodocumento extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'ativo'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

}
