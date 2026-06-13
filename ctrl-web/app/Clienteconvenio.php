<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clienteconvenio
 *
 * @property int $CLIENTE_ID
 * @property float $COMISSAO
 * @property int $COMISSAODESTINO
 * @property string|null $CPFREPRESENTANTE
 * @property string|null $CREATED_AT
 * @property string|null $DATACONTRATO
 * @property int $DIAFECHAMENTO
 * @property int $DIAVENCIMENTO
 * @property int $ID
 * @property int $LIMITECOMPRA
 * @property string|null $NOMEREPRESENTANTE
 * @property string|null $RGREPRESENTANTE
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereCOMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereCOMISSAODESTINO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereCPFREPRESENTANTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereDATACONTRATO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereDIAFECHAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereDIAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereLIMITECOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereNOMEREPRESENTANTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereRGREPRESENTANTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconvenio whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Clienteconvenio extends Model
{
    protected $fillable = [
      'cliente_id',
      'datacontrato',
      'limitecompra',
      'diafechamento',
      'diavencimento',
      'comissao',
      'comissaodestino',
      'cpfrepresentante',
      'rgrepresentante',
      'nomerepresentante'
    ];
    
    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }
}
