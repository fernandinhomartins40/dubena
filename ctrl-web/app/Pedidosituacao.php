<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Pedidosituacao
 *
 * @property string $ANDROIDUSA
 * @property string|null $ATIVO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property string $EMENTREGA
 * @property int $EMPRESA_ID
 * @property string $ENTREGACANCELADA
 * @property string $ENTREGADOROFFLINE
 * @property string $ENTREGAFINALIZADA
 * @property string $ENTREGAPENDENTE
 * @property string $ENTREGATRANFERIDA
 * @property string $FECHADOCANCELADO
 * @property string $FECHADOCONCLUIDO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $PEDIDOLIDOMOVEL
 * @property string $PEDIDORECEBIDOMOVEL
 * @property string|null $UPDATED_AT
 * @property string $VALEGAS
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Pedidosituacaohistorico[] $pedidoSituacaoHisorico
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereANDROIDUSA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereEMENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereENTREGACANCELADA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereENTREGADOROFFLINE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereENTREGAFINALIZADA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereENTREGAPENDENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereENTREGATRANFERIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereFECHADOCANCELADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereFECHADOCONCLUIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao wherePEDIDOLIDOMOVEL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao wherePEDIDORECEBIDOMOVEL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacao whereVALEGAS($value)
 * @mixin \Eloquent
 */
class Pedidosituacao extends Model
{


    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'padraotelapedido',
        'entregafinalizada', 'entregacancelada', 'entregapendente', 'androidusa',
        'fechadoconcluido', 'fechadocancelado', 'entregatranferida', 'ementrega', 
        'entregadoroffline', 'ativo', 'valegas', 'pedidorecebidomovel', 'pedidolidomovel',
        'solicitacartaoautorizacao'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function pedidoSituacaoHisorico()
    {
        return $this->hasMany('App\Pedidosituacaohistorico');
    }

}
