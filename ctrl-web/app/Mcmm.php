<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Mcmm
 *
 * @property int|null $CAPACIDADEARMAZENAMENTO
 * @property string $CIDADE
 * @property string $CNPJ
 * @property int $CODIGO_IBGE
 * @property string|null $CREATED_AT
 * @property string $DATAFIMFILTRO
 * @property string $DATAINICIOFILTRO
 * @property string $DATAMOVIMENTO
 * @property string $DEPD
 * @property string $DEPR
 * @property string $DISTRIBUIDORA
 * @property int $EMPRESA_ID
 * @property string $ENDERECO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $OBSERVACOES
 * @property string $PRD
 * @property string $PRR
 * @property string $PRT
 * @property string $RAZAO_SOCIAL
 * @property string $REGISTRO_ANP
 * @property string $RESPONSAVEL
 * @property string $UF
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Mcmmhistoricoentradas[] $historicoEntrada
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Mcmmhistoricosaidas[] $historicoSaida
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereCAPACIDADEARMAZENAMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereCIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereCNPJ($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereCODIGOIBGE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereDATAFIMFILTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereDATAINICIOFILTRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereDATAMOVIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereDEPD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereDEPR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereDISTRIBUIDORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereENDERECO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereOBSERVACOES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm wherePRD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm wherePRR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm wherePRT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereRAZAOSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereREGISTROANP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereRESPONSAVEL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Mcmm whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Mcmm extends Model
{
    protected $fillable = ['empresa_id', 'grupo_id', 'datainiciofiltro', 'datafimfiltro', 'datamovimento', 'razao_social', 
    						 	'distribuidora', 'registro_anp', 'depd', 'depr', 'prt', 'prr', 'prd', 'capacidadearmazenamento', 
    						 	'endereco', 'cidade', 'observacoes', 'uf', 'cnpj', 'codigo_ibge', 'responsavel'
    						];


    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo','grupo_id');
    }		

    public function empresa()
    {
        return $this->belongsTo('App\Empresa','empresa_id');
    }

    public function historicoEntrada()
    {
        return $this->hasMany('App\Mcmmhistoricoentradas','mcmm_id');
    }

    public function historicoSaida()
    {
        return $this->hasMany('App\Mcmmhistoricosaidas','mcmm_id');
    }
}
