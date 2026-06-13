<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
use App\Veiculo;
use App\Colaborador;

/**
 * App\Veiculoabastecimento
 *
 * @property int $COLABORADOR_ID
 * @property string|null $CREATED_AT
 * @property string $DATA
 * @property int $EMPRESA_ID
 * @property int $ID
 * @property float $KMANTERIOR
 * @property float $KMATUAL
 * @property float $KMRODADO
 * @property float $MEDIACONSUMO
 * @property string $TELACONTROLAKM
 * @property float $TOTALLITROS
 * @property string|null $UPDATED_AT
 * @property int $VEICULO_ID
 * @property-read \App\Colaborador $colaborador
 * @property-read \App\Empresa $empresa
 * @property-read \App\Veiculo $veiculo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereCOLABORADORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereDATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereKMANTERIOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereKMATUAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereKMRODADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereMEDIACONSUMO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereTELACONTROLAKM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereTOTALLITROS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoabastecimento whereVEICULOID($value)
 * @mixin \Eloquent
 */
class Veiculoabastecimento extends Model {

    protected $fillable = ['veiculo_id', 'colaborador_id', 'empresa_id', 'data', 'mediaconsumo', 'kmatual',
        'kmanterior', 'kmrodado', 'totallitros','telacontrolakm'];

    public function empresa() {
        return $this->belongsTo('App\Empresa');
    }

    public function colaborador() {
        return $this->belongsTo('App\Colaborador');
    }

    public function veiculo() {
        return $this->belongsTo('App\Veiculo');
    }

}
