<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Veiculopneu
 *
 * @property string|null $ALERTAANTES
 * @property string|null $CREATED_AT
 * @property string $DATA
 * @property int $EMPRESA_ID
 * @property int $ID
 * @property float $KM
 * @property float|null $KMALERTAANTES
 * @property string $MEDIDAPNEUS
 * @property int $QUANTIDADE
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property int $VEICULO_ID
 * @property float $VIDAUTILKM
 * @property-read \App\Veiculo $veiculo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereALERTAANTES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereDATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereKM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereKMALERTAANTES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereMEDIDAPNEUS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereVEICULOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculopneu whereVIDAUTILKM($value)
 * @mixin \Eloquent
 */
class Veiculopneu extends Model
{
    protected $fillable = ['veiculo_id','data','km','vidautilkm','valor','quantidade',
        'medidapneus','alertaantes','kmalertaantes','empresa_id'];
    
    public function veiculo(){
        return $this->belongsTo('App\Veiculo');
    }
    
}
