<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfemitidaparcela
 *
 * @property string $BAIXADO
 * @property string|null $CREATED_AT
 * @property string $DATAVENCIMENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float $MORADIARIA
 * @property int $NFEMITIDA_ID
 * @property int $NUMEROPARCELA
 * @property string $REFERENCIA
 * @property string|null $UPDATED_AT
 * @property float $VALORJUROS
 * @property float $VALORMULTA
 * @property float $VALORORIGINAL
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Nfemitida $nfEmitida
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereBAIXADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereMORADIARIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereNFEMITIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereNUMEROPARCELA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereREFERENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereVALORJUROS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereVALORMULTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidaparcela whereVALORORIGINAL($value)
 * @mixin \Eloquent
 */
class Nfemitidaparcela extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfemitida_id', 'numeroparcela',
        'referencia', 'datavencimento', 'valororiginal'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function nfEmitida()
    {
        return $this->belongsTo('App\Nfemitida');
    }

}
