<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Chequeemitido
 *
 * @property int $CHEQUESITUACAO_ID
 * @property int $CONTA_ID
 * @property int $CONTATALAO_ID
 * @property string|null $CREATED_AT
 * @property string $DATACOMPETENCIA
 * @property string $DATAEMISSAO
 * @property string|null $DATAPAGAMENTO
 * @property string $DATAVENCIMENTO
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $NUMEROCHEQUE
 * @property string|null $OBSERVACAO
 * @property string|null $UPDATED_AT
 * @property float $VALOR
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitidoencontrocontas[] $chequeEmitidoEncontroContas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Chequeemitidofinanceiro[] $chequeEmitidoFinanceiro
 * @property-read \App\Chequesituacao $chequesituacao
 * @property-read \App\Conta $conta
 * @property-read \App\Contatalao $contaTalao
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereCHEQUESITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereCONTATALAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereDATACOMPETENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereDATAEMISSAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereDATAPAGAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereDATAVENCIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereNUMEROCHEQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Chequeemitido whereVALOR($value)
 * @mixin \Eloquent
 */
class Chequeemitido extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $revisionCreationsEnabled = true;

    protected $fillable = ['grupo_id', 'empresa_id', 'conta_id', 'chequesituacao_id', 'contatalao_id',
        'numerocheque', 'dataemissao', 'datavencimento', 'datacompetencia', 'datapagamento', 
        'valor', 'observacao'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function chequesituacao()
    {
        return $this->belongsTo('App\Chequesituacao');
    }

    public function contaTalao()
    {
        return $this->belongsTo('App\Contatalao');
    }

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function chequeEmitidoFinanceiro()
    {
        return $this->hasMany('App\Chequeemitidofinanceiro');
    }
    
    public function chequeEmitidoEncontroContas()
    {
        return $this->hasMany('App\Chequeemitidoencontrocontas');
    }
}
