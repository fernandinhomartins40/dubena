<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Mcmmhistoricosaidas
 *
 * @property string|null $CREATED_AT
 * @property string $EM_USO
 * @property int $ID
 * @property int $MCMM_ID
 * @property int $OPERACAO
 * @property string $ORIGINAL
 * @property int $QDEP02
 * @property int $QDEP08
 * @property int $QDEP13
 * @property int $QDEP20
 * @property int $QDEP45
 * @property int $QDEP90
 * @property string $SOMAMES
 * @property string $SOMASEGUINTE
 * @property string|null $UPDATED_AT
 * @property-read \App\Mcmm $mcmm
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereEMUSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereMCMMID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereOPERACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereQDEP02($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereQDEP08($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereQDEP13($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereQDEP20($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereQDEP45($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereQDEP90($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereSOMAMES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereSOMASEGUINTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmmhistoricosaidas whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Mcmmhistoricosaidas extends Model
{
	protected $fillabe = ['mcmm_id', 'qdep02', 'qdep08', 'qdep13', 'qdep20', 'qdep45', 'qdep90',
						 'operacao', 'somames', 'somaseguinte', 'em_uso', 'original'];
    
    public function mcmm()
    {
        return $this->belongsTo('App\Mcmm','mcmm_id');
    }
}
