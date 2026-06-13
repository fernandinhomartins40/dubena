<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Configuracoesgerais
 *
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property string|null $KEYGOOGLEMAPS
 * @property string|null $LINKMONITORAMENTO
 * @property string|null $UPDATED_AT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereKEYGOOGLEMAPS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereLINKMONITORAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereUPDATEDAT($value)
 * @mixin \Eloquent
 * @property string|null $EMAILASSUNTO
 * @property string|null $EMAILCORPO
 * @property string|null $EMAILKEYGOOGLE
 * @property string|null $EMAILNOMEREMENTE
 * @property string|null $EMAILPORTASMTP
 * @property string|null $EMAILREMETENTE
 * @property string|null $EMAILREQUERAUTENTICACAO
 * @property string|null $EMAILREQUERCONEXAOTLS
 * @property string|null $EMAILSENHA
 * @property string|null $EMAILSERVIDORSMTP
 * @property string|null $EMAILUSUARIO
 * @property string|null $REMEMBERMAILS
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILASSUNTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILCORPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILKEYGOOGLE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILNOMEREMENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILPORTASMTP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILREMETENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILREQUERAUTENTICACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILREQUERCONEXAOTLS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILSENHA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILSERVIDORSMTP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereEMAILUSUARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereREMEMBERMAILS($value)
 * @property string|null $RTCNPJ
 * @property string|null $RTCONTATO
 * @property string|null $RTCSRT
 * @property string|null $RTEMAIL
 * @property string|null $RTIDCSRT
 * @property string|null $RTTELEFONE
 * @property string|null $SATCNPJHOMOLOG
 * @property string|null $SATCNPJPROD
 * @property string|null $SATEMITCNPJHOMOLOG
 * @property string|null $SATSIGNACHOMOLOG
 * @property string|null $SATSIGNACPROD
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereRTCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereRTCONTATO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereRTCSRT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereRTEMAIL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereRTIDCSRT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereRTTELEFONE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereSATCNPJHOMOLOG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereSATCNPJPROD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereSATEMITCNPJHOMOLOG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereSATSIGNACHOMOLOG($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Configuracoesgerais whereSATSIGNACPROD($value)
 */
class Configuracoesgerais extends Model
{
    protected $fillable = [
        'keygooglemaps', 'linkmonitoramento', "emailkeygoogle", "remembermails", "emailremetente",
        "emailnomeremente", "emailusuario", "emailsenha", "emailservidorsmtp", "emailportasmtp",
        "emailassunto", "emailcorpo", "emailrequerautenticacao", "emailrequerconexaotls",
        "satcnpjprod", "satcnpjhomolog", "satsignacprod", "satsignachomolog", "satemitcnpjhomolog",
        // Responsavel tecnico
        "rtcnpj", "rtcontato", "rtemail", "rttelefone", "rtidcsrt", "rtcsrt"
    ];
}
