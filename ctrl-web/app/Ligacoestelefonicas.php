<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Ligacoestelefonicas
 *
 * @property int|null $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property string|null $DATAHORAFIM
 * @property string|null $DATAHORAINICIO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $LINHA
 * @property int|null $MOTIVONAOVENDA_ID
 * @property int|null $PEDIDO_ID
 * @property string $REJEITADA
 * @property string $TELEFONE
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupos
 * @property-read \App\Motivonaovenda $motivonaovenda
 * @property-read \App\Pedido $pedido
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereDATAHORAFIM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereDATAHORAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereLINHA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereMOTIVONAOVENDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas wherePEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereREJEITADA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Ligacoestelefonicas whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Ligacoestelefonicas extends Model
{
    protected $fillable = [
        'empresa_id', 'grupo_id', 'linha', 'cliente_id', 'pedido_id', 'motivonaovenda_id',
        'datahorafim', 'datahorainicio', 'telefone', 'rejeitada', 'user_id', 'atendida'
    ];

    public function empresasGrupos()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function pedido()
    {
        return $this->belongsTo('App\Pedido');
    }

    public function motivonaovenda()
    {
        return $this->belongsTo('App\Motivonaovenda');
    }
}
