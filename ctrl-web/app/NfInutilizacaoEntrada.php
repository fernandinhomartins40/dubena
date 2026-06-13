<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\NfInutilizacaoEntrada
 *
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string $NFMODELO
 * @property int $NFNUMERO
 * @property int $NFSERIE
 * @property int|null $NFSITUACAO_ID
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property string $XJUST
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereNFMODELO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereNFNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereNFSERIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereNFSITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\NfInutilizacaoEntrada whereXJUST($value)
 * @mixin \Eloquent
 */
class NfInutilizacaoEntrada extends Model
{
    protected $table = "nfinutilizacaoentradas";
}
