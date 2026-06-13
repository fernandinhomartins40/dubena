<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfemitidacartacorrecao
 *
 * @property string $CHNFE
 * @property string $CNPJ
 * @property int $CORGAO
 * @property string|null $CREATED_AT
 * @property string|null $DATAHORAEVENTO
 * @property string $DESCEVENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property string|null $hash
 * @property int $ID
 * @property int $IDLOTE
 * @property int $NFEMITIDA_ID
 * @property int $NFNUMERO
 * @property int $NSEQEVENTO
 * @property string|null $PROTOCOLORETORNOEVENTO
 * @property int $TPAMB
 * @property int $TPEVENTO
 * @property string|null $UPDATED_AT
 * @property string $VEREVENTO
 * @property string $XCONDUSO
 * @property string $XCORRECAO
 * @property string|null $XMLRETORNOEVENTO
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Nfemitida $nfEmitida
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereCHNFE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereCORGAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereDATAHORAEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereDESCEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereIDLOTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereNFEMITIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereNFNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereNSEQEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao wherePROTOCOLORETORNOEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereTPAMB($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereTPEVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereVEREVENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereXCONDUSO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereXCORRECAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfemitidacartacorrecao whereXMLRETORNOEVENTO($value)
 * @mixin \Eloquent
 */
class Nfemitidacartacorrecao extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'nfemitida_id', 'nfnumero',
        'idlote', 'corgao', 'tpamb', 'cnpj', 'chnfe', 'tpevento', 'nseqevento',
        'verevento', 'descevento', 'xcorrecao', 'xconduso', 'datahoraevento',
        'xmlretornoevento', 'protocoloretornoevento', 'hash'];

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
