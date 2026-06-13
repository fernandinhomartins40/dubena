<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Boleto
 *
 * @property string $ALTEROU
 * @property string $ALTEROUCANCELOU
 * @property string $ALTEROUVENCIMENTO
 * @property string $CANCELADO
 * @property int $CONTA_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property string $DV
 * @property int $EMPRESA_ID
 * @property int|null $FINANCEIRO_ID
 * @property int|null $FINANCEIROPARCELA_ID
 * @property string $GEROUREMESSA
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $IMPRIMIU
 * @property string $NOSSONUMERO
 * @property int $NUMEROSEQUENCIA
 * @property string $PENDENCIA
 * @property int|null $PROTESTO_DEVOLUCAO
 * @property int|null $ULTIMAOCORRENCIA_ID
 * @property string|null $UPDATED_AT
 * @property float|null $VALOR_ABATIMENTO
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Boletohistorico[] $boletohistorico
 * @property-read \App\Conta $conta
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Financeiro $financeiro
 * @property-read \App\Financeiroparcela $financeiroparcela
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereALTEROU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereALTEROUCANCELOU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereALTEROUVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereCANCELADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereDV($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereFINANCEIROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereFINANCEIROPARCELAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereGEROUREMESSA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereIMPRIMIU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereNOSSONUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereNUMEROSEQUENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto wherePENDENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto wherePROTESTODEVOLUCAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereULTIMAOCORRENCIAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Boleto whereVALORABATIMENTO($value)
 * @mixin \Eloquent
 */
class Boleto extends Model
{

    protected $fillable = [
        'empresa_id',
        'grupo_id',
        'financeiroparcela_id',
        'financeiro_id',
        'conta_id',
        'datahora',
        'numerosequencia',
        'nossonumero',
        'cancelado',
        'gerouremessa',
        'alterouvencimento',
        'alteroucancelou',
        'dv',
        'protesto_devolucao',
        'v',
        'alterou', 
        'valor_abatimento',
        'pendencia',
        'ultimaocorrencia_id',
        'imprimiu'
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

    public function boletohistorico()
    {
        return $this->hasMany('App\Boletohistorico');
    }

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

}
