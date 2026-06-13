<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\CupomFiscalParcela
 *
 * @property mixed $numeroparcela
 * @property mixed $referencia
 * @property mixed $datavencimento
 * @property mixed $baixado
 * @property mixed $cmp
 * @property mixed $vmp
 * @property mixed $vtroco
 * @property mixed $cupomfiscal_id
 * @property mixed $grupo_id
 * @property mixed $empresa_id
 * @mixin \Eloquent
 * @property string $BAIXADO
 * @property string $CMP
 * @property string|null $CREATED_AT
 * @property int $CUPOMFISCAL_ID
 * @property string $DATAVENCIMENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $NUMEROPARCELA
 * @property string $REFERENCIA
 * @property string|null $UPDATED_AT
 * @property float $VMP
 * @property float $VTROCO
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereBAIXADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereCMP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereCUPOMFISCALID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereNUMEROPARCELA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereREFERENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereVMP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\CupomFiscalParcela whereVTROCO($value)
 */
class CupomFiscalParcela extends Model
{
    protected $table = "cupomfiscalparcela";

    protected $fillable = [
        "numeroparcela", "referencia", "datavencimento", "baixado", "cmp", "vmp", "vtroco",
        "cupomfiscal_id", "grupo_id", "empresa_id"
    ];
}
