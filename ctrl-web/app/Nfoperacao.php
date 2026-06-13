<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Nfoperacao
 *
 * @property int $APARECETELA
 * @property string $ATUALIZACUSTO
 * @property string $CADASTRONF
 * @property int|null $CFOP
 * @property int|null $CFOPIE
 * @property string|null $CREATED_AT
 * @property string $DEOLHONOIMPOSTO
 * @property string $DESCRICAO
 * @property string $DESCRICAOFISCAL
 * @property int $EMPRESA_ID
 * @property int $GRUPO_ID
 * @property int $ID
 * @property string|null $INFORMACOESADICIONALFISCO
 * @property string $MOVIMENTAESTOQUE
 * @property string $MOVIMENTAFINANCEIRO
 * @property string $SPEDVENDA
 * @property int|null $TIPONF
 * @property string|null $UPDATED_AT
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfceconfigpedido[] $nfEmitida
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitidaitem[] $nfEmitidaItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfimposto[] $nfImposto
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfrecebidaitem[] $nfRecebidaItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfceconfigpedido[] $nfceconfigpedido
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereAPARECETELA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereATUALIZACUSTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereCADASTRONF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereCFOP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereCFOPIE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereDEOLHONOIMPOSTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereDESCRICAOFISCAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereINFORMACOESADICIONALFISCO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereMOVIMENTAESTOQUE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereMOVIMENTAFINANCEIRO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereSPEDVENDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereTIPONF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereUPDATEDAT($value)
 * @mixin \Eloquent
 * @property string $USASAT
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Nfoperacao whereUSASAT($value)
 */
class Nfoperacao extends Model
{
    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $fillable = [
        'grupo_id', 'empresa_id', 'descricao', 'descricaofiscal', 'cfop', 'cfopie', 'origem_icms',
        'informacoesadicionalfisco', 'modalidadebcicms', 'modalidadebcicmsst', 'movimentaestoque', 'usasat',
        'movimentafinanceiro', 'aparecetela', 'cadastronf', 'spedvenda', 'deolhonoimposto', 'tiponf', 
        'atualizacusto', 'enviaappnf'
    ];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {

        return $this->belongsTo('App\Empresa');
    }

    function nfRecebidaItem()
    {
        return $this->hasMany('App\Nfrecebidaitem');
    }

    public function nfImposto()
    {
        return $this->hasMany('App\Nfimposto');
    }

    function nfEmitidaItem()
    {
        return $this->hasMany('App\Nfemitidaitem');
    }

    public function nfEmitida()
    {
        return $this->hasMany('App\Nfceconfigpedido');
    }

    public function nfceconfigpedido()
    {
        return $this->hasMany('App\Nfceconfigpedido');
    }
    public function produtos()
    {
        return $this->hasMany('App\Nfoperacaoproduto');
    }
    public function produtoconvenios()
    {
        return $this->hasMany('App\Nfoperacaoprodutoconvenio');
    }
}
