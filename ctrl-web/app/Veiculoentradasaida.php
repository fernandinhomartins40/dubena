<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Veiculoentradasaida
 *
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $EMPRESA_ID
 * @property string $ENTRADA
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float $KM
 * @property float $KMRODADO
 * @property string|null $OBSERVACAO
 * @property string $SAIDA
 * @property string $TELACONTROLAKM
 * @property string $TEMPORODADO
 * @property string $ULTIMADATAHORA
 * @property float $ULTIMOKM
 * @property string|null $UPDATED_AT
 * @property int $VEICULO_ID
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasgrupo
 * @property-read \App\Veiculo $veiculo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereENTRADA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereKM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereKMRODADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereSAIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereTELACONTROLAKM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereTEMPORODADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereULTIMADATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereULTIMOKM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaida whereVEICULOID($value)
 * @mixin \Eloquent
 */
class Veiculoentradasaida extends Model
{
    protected $fillable = ["veiculo_id","empresa_id","grupo_id","entrada","saida","observacao","km","ultimokm","kmrodado",
            "temporodado","ultimadatahora","datahora","telacontrolakm"];

    public function veiculo(){
        return $this->belongsTo('App\Veiculo','veiculo_id');
    }

    public function empresa(){
        return $this->belongsTo('App\Empresa','empresa_id');
    }

    public function empresasgrupo(){
        return $this->belongsTo('App\Empresasgrupo','grupo_id');
    }
}
