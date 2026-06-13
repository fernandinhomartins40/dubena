<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfrecebidaparcela
 *
 * @property string $BAIXADO
 * @property string|null $CREATED_AT
 * @property string $DATAVENCIMENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float $MORADIARIA
 * @property int $NFRECEBIDA_ID
 * @property int $NUMEROPARCELA
 * @property string $REFERENCIA
 * @property string|null $UPDATED_AT
 * @property float $VALORJUROS
 * @property float $VALORMULTA
 * @property float $VALORORIGINAL
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Nfrecebida $nfRecebida
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereBAIXADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereMORADIARIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereNFRECEBIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereNUMEROPARCELA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereREFERENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereVALORJUROS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereVALORMULTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfrecebidaparcela whereVALORORIGINAL($value)
 * @mixin \Eloquent
 */
class Nfrecebidaparcela extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfrecebida_id', 'numeroparcela', 'referencia',
        'datavencimento', 'valororiginal'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function nfRecebida()
    {
        return $this->belongsTo('App\Nfrecebida');
    }

}
