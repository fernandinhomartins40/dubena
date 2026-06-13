<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Veiculotrocaoleo
 *
 * @property string|null $ALERTAANTES
 * @property int $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property string $DATA
 * @property int $EMPRESA_ID
 * @property int $ID
 * @property float|null $KMALERTAANTES
 * @property float $KMTROCAOLEO
 * @property float $KMULTIMATROCAOLEO
 * @property float $OLEOPROXIMATROCA
 * @property float $OLEORENDIMENTO
 * @property string|null $UPDATED_AT
 * @property int $VEICULO_ID
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Veiculo $veiculo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereALERTAANTES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereDATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereKMALERTAANTES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereKMTROCAOLEO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereKMULTIMATROCAOLEO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereOLEOPROXIMATROCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereOLEORENDIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculotrocaoleo whereVEICULOID($value)
 * @mixin \Eloquent
 */
class Veiculotrocaoleo extends Model
{
    protected $fillable = ['veiculo_id', 'colaborador_id','data','kmultimatrocaoleo','empresa_id',
        'kmtrocaoleo','oleorendimento','oleoproximatroca','kmalertaantes','alertaantes'];
    
    public function veiculo(){
        return $this->belongsTo('App\Veiculo');
    }
    
    public function colaborador(){
        return $this->belongsTo('App\Colaborador');
    }
    
    public function colaboradorNome($empresa_id) {
        return static::rightJoin('colaboradors', 'colaborador_id', '=', 'colaboradors.id')
                        ->select('colaboradors.id', 'colaboradors.nome')
                        ->where('colaboradors.empresa_id', $empresa_id)
                        ->where('ativo', true)
                ->pluck('nome', 'id');
    }
}
