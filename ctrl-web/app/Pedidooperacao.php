<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Pedidooperacao
 *
 * @property string|null $ATIVO
 * @property string $CONVENIO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property string $DISK
 * @property int $EMPRESA_ID
 * @property string $GASBOLSO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $PDV
 * @property string|null $UPDATED_AT
 * @property string $VENDADIRETA
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereCONVENIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereDISK($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereGASBOLSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao wherePDV($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidooperacao whereVENDADIRETA($value)
 * @mixin \Eloquent
 */
class Pedidooperacao extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'ativo', 'convenio',
        'gasbolso', 'disk', 'vendadireta', 'pdv', 'portaria'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

}
