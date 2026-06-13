<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Mcmmhistoricoentradas
 *
 * @property string|null $CREATED_AT
 * @property string $EM_USO
 * @property int $ID
 * @property int $MCMM_ID
 * @property string $MESANTERIOR
 * @property int|null $NFEMITIDA_ID
 * @property int|null $NFRECEBIDA_ID
 * @property string $ORIGINAL
 * @property int $QDEP02
 * @property int $QDEP08
 * @property int $QDEP13
 * @property int $QDEP20
 * @property int $QDEP45
 * @property int $QDEP90
 * @property string $TOTAL
 * @property string|null $UPDATED_AT
 * @property-read \App\Mcmm $mcmm
 * @property-read \App\Nfemitida $nfEmitida
 * @property-read \App\Nfrecebida $nfRecebida
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereEMUSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereMCMMID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereMESANTERIOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereNFEMITIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereNFRECEBIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereQDEP02($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereQDEP08($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereQDEP13($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereQDEP20($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereQDEP45($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereQDEP90($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereTOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricoentradas whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Mcmmhistoricoentradas extends Model
{
	protected $fillabe = ['mcmm_id', 'nfemitida_id', 'nfrecebida_id', 'qdep02', 'qdep08', 'qdep13', 'qdep20', 'qdep45', 'qdep90',
						 'original', 'total', 'mesanterior', 'em_uso'];
    
    public function mcmm()
    {
        return $this->belongsTo('App\Mcmm','mcmm_id');
    }

    public function nfEmitida()
    {
        return $this->belongsTo('App\Nfemitida','nfemitida_id')->select('nfnumero', 'nfserie', 'datahoraentradasaida', 'id');
    }

    public function nfRecebida()
    {
        return $this->belongsTo('App\Nfrecebida','nfrecebida_id')->select('nfnumero', 'nfserie', 'datahoraentradasaida', 'id');
    }
}
