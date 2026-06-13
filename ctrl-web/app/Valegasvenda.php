<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Valegasvenda
 *
 * @property string $CANCELADO
 * @property int $CLIENTE_ID
 * @property int|null $CONDICAOPAGAMENTO_ID
 * @property string|null $CREATED_AT
 * @property string $DATAVENDA
 * @property int $EMPRESA_ID
 * @property int|null $FINANCEIRO_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $PREVENDA
 * @property int $PRODUTO_ID
 * @property int|null $QUANTIDADE
 * @property string|null $UPDATED_AT
 * @property float|null $VALORTOTAL
 * @property float|null $VALORUNITARIO
 * @property-read \App\Cliente $Cliente
 * @property-read \App\Empresa $empresa
 * @property-read \App\Produto $produto
 * @property-read \App\Valegas $valegas
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereCANCELADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereCONDICAOPAGAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereDATAVENDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda wherePREVENDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereVALORTOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Valegasvenda whereVALORUNITARIO($value)
 * @mixin \Eloquent
 */
class Valegasvenda extends Model
{
    protected $fillable = [
      'grupo_id',
      'empresa_id',
      'cliente_id',
      'financeiro_id',
      'condicaopagamento_id',
      'produto_id',
      'quantidade',
      'valorunitario',
      'valortotal',
      'datavenda',
      'prevenda',
      'prevendaquantidade',
      'cancelado'
    ];

    public function empresa(){
      return $this->belongsTo('App\Empresa');
    }

    public function Cliente(){
      return $this->belongsTo('App\Cliente');
    }

    public function valegas(){
      return $this->belongsTo('App\Valegas');
    }
    
    public function produto(){
      return $this->belongsTo('App\Produto');
    }
}
