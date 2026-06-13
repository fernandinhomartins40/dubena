<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clientecontato
 *
 * @property string|null $ACAO
 * @property int $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int|null $RESPONSAVEL_ID
 * @property int $SITUACAO_ID
 * @property int $TIPO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \App\Clientecontatosituacao $contatosituacao
 * @property-read \App\Clientecontatotipo $contatotipo
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereRESPONSAVELID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereSITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereTIPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clientecontato whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Clientecontato extends Model
{
  protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'situacao_id', 'tipo_id', 'responsavel_id', 'datahora', 'acao' ];

  public function empresasGrupo()
  {
      return $this->belongsTo('App\EmpresasGrupo');
  }
  public function empresa()
  {
      return $this->belongsTo('App\Empresa');
  }
  public function contatotipo()
  {
      return $this->belongsTo('App\Clientecontatotipo', 'tipo_id');
  }
  public function cliente()
  {
      return $this->belongsTo('App\Cliente', 'cliente_id');
  }
  public function contatosituacao()
  {
      return $this->belongsTo('App\Clientecontatosituacao', 'situacao_id');
  }
}
