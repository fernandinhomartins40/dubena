<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Boletohistorico
 *
 * @property string $ALTERACAODATAHORA
 * @property int $ALTERACAOUSER_ID
 * @property string $ALTEROUCANCELOU
 * @property string $ALTEROUVENCIMENTO
 * @property int $BOLETO_ID
 * @property int|null $BOLETOREMESSA_ID
 * @property string $CANCELADO
 * @property int $CONTA_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property string $DV
 * @property int $EMPRESA_ID
 * @property int|null $FINANCEIRO_ID
 * @property int|null $FINANCEIROPARCELA_ID
 * @property string $GEROUBOLETO
 * @property string $GEROUREMESSA
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $INFO_CANCELAMENTO
 * @property string $NOSSONUMERO
 * @property int $NUMEROSEQUENCIA
 * @property int|null $OCORRENCIA_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Financeiroparcela $financeiroparcela
 * @property-read \App\Ocorrenciasremessas $ocorrencia
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereALTERACAODATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereALTERACAOUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereALTEROUCANCELOU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereALTEROUVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereBOLETOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereBOLETOREMESSAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereCANCELADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereDV($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereGEROUBOLETO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereGEROUREMESSA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereINFOCANCELAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereNOSSONUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereNUMEROSEQUENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereOCORRENCIAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boletohistorico whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Boletohistorico extends Model
{
    protected $fillable = [	
                    'grupo_id', 'empresa_id', 'boleto_id', 'financeiro_id', 'financeiroparcela_id', 'conta_id', 'datahora',
                    'numerosequencia', 'dv', 'nossonumero', 'nossonumero', 'alteracaodatahora', 'alteracaouser_id',
                    'cancelado', 'gerouboleto', 'gerouremessa', 'alterouvencimento', 'alteroucancelou', 'info_cancelamento',
                    'ocorrencia_id', 'boletoremessa_id'
                ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }
    public function financeiro()
    {
        return $this->belongsTo('App\Financeiro');
    }
    public function financeiroparcela()
    {
        return $this->belongsTo('App\Financeiroparcela');
    }
    public function ocorrencia()
    {
        return $this->belongsTo('App\Ocorrenciasremessas', 'ocorrencia_id');
    }
}
