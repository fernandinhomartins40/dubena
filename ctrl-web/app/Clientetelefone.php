<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clientetelefone
 *
 * @property int $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $TELEFONE
 * @property int $TELEFONETIPO_ID
 * @property string|null $UPDATED_AT
 * @property string $WHATSAPP
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Cliente[] $cliente
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Telefonetipo $telefonetipo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereTELEFONETIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientetelefone whereWHATSAPP($value)
 * @mixin \Eloquent
 */
class Clientetelefone extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'cliente_id', 'telefone',
        'telefonetipo_id', 'whatsapp'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function cliente()
    {
        return $this->hasMany('App\Cliente','cliente_id');
    }

    public function telefonetipo()
    {
        return $this->belongsTo('App\Telefonetipo');
    }

    public function teltipo()
    {
        return $this->belongsTo('App\Telefonetipo', "telefonetipo_id")->select("id", "descricao");
    }

}
