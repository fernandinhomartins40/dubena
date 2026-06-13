<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Valegas
 *
 * @property int $CLIENTE_ID
 * @property string $CODIGO
 * @property string|null $CREATED_AT
 * @property string|null $DATABAIXA
 * @property string|null $DATAGERACAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int|null $PEDIDO_ID
 * @property int|null $PREVENDASEQUENCIA
 * @property int $PRODUTO_ID
 * @property string|null $UPDATED_AT
 * @property int $VALEGASSITUACAO_ID
 * @property int $VALEGASVENDA_ID
 * @property-read \App\Cliente $cliente
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Produto $produto
 * @property-read \App\Valegassituacao $valeGasSituacao
 * @property-read \App\Valegasvenda $valegasvenda
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereDATABAIXA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereDATAGERACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas wherePEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas wherePREVENDASEQUENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereVALEGASSITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegas whereVALEGASVENDAID($value)
 * @mixin \Eloquent
 */
class Valegas extends Model
{

    protected $fillable = [
        'valegassituacao_id','grupo_id','empresa_id', 'cliente_id', 'produto_id', 'valegasvenda_id',
        'pedido_id', 'codigo', 'datagerecao', 'databaixa', 'prevendasequencia', 'created_at', 'updated_at'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function valeGasSituacao(){
      return $this->belongsTo('App\Valegassituacao','valegassituacao_id');
    }

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto','produto_id');
    }

    public function valegasvenda()
    {
        return $this->belongsTo('App\Valegasvenda');
    }

}
