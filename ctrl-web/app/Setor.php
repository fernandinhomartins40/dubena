<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Setor
 *
 * @property string|null $ATIVO
 * @property int $BAIRRO_ID
 * @property string $CEP
 * @property int $CIDADE_ID
 * @property string|null $COMPLEMENTO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property string $ESTOQUEPROPRIO
 * @property int $GRUPO_ID
 * @property int $ID
 * @property float|null $LATITUDE
 * @property float|null $LONGITUDE
 * @property int|null $MONITORAMENTOCERCA_ID
 * @property string $NUMERO
 * @property int|null $RUA_ID
 * @property string|null $UF
 * @property string|null $UPDATED_AT
 * @property string|null $USARASTREAMENTO
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Android[] $android
 * @property-read \App\Bairro $bairro
 * @property-read \App\Cidade $cidade
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Colaborador[] $colaborador
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Colaboradorcomissao[] $colaboradorcomissao
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Estado $estado
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquefechamentosetor[] $estoqueFechamentoSetor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquefisicosetor[] $estoqueFisicoSetor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquerequisicaoitem[] $estoqueRequisicaoItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquesetor[] $estoqueSetor
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquerequisicaoitem[] $estoqueSetorHistorico
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Estoquetransferencia[] $estoqueTransferencia
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Metavenda[] $metaVenda
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitida[] $nfEmitida
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfemitidaitem[] $nfEmitidaItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Nfrecebidaitem[] $nfRecebidaItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Posvendapesquisa[] $posVendaPesquisa
 * @property-read \App\Rua $rua
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Setorcolaboradores[] $setorcolaboradores
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereBAIRROID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereCEP($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereCIDADEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereCOMPLEMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereESTOQUEPROPRIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereLATITUDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereLONGITUDE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereMONITORAMENTOCERCAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereNUMERO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereRUAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereUF($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Setor whereUSARASTREAMENTO($value)
 * @mixin \Eloquent
 */
class Setor extends Model
{

    protected $fillable = ['grupo_id', 'empresa_id', 'monitoramentocerca_id',
        'descricao', 'rua_id', 'numero', 'complemento', 'latitude', 'longitude',
        'cidade_id', 'cep', 'bairro_id', 'ativo', 'estoqueproprio', 'uf', 'usarastreamento',
         'pedidooperacao_id', 'qtderesidencias'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function bairro()
    {
        return $this->belongsTo('App\Bairro');
    }

    public function cidade()
    {
        return $this->belongsTo('App\Cidade');
    }

    public function estado()
    {
        return $this->belongsTo('App\Estado');
    }

    public function rua()
    {
        return $this->belongsTo('App\Rua', 'rua_id');
    }

    public function android()
    {
        return $this->hasMany('App\Android');
    }

    public function colaborador()
    {
        return $this->hasMany('App\Colaborador', 'setor_id', 'id');
    }

    public function colaboradorcomissao()
    {
        return $this->hasMany('App\Colaboradorcomissao');
    }

    public function estoqueFechamentoSetor()
    {
        return $this->hasMany('App\Estoquefechamentosetor');
    }

    public function estoqueFisicoSetor()
    {
        return $this->hasMany('App\Estoquefisicosetor');
    }

    public function estoqueRequisicaoItem()
    {
        return $this->hasMany('App\Estoquerequisicaoitem');
    }

    public function estoqueSetorHistorico()
    {
        return $this->hasMany('App\Estoquerequisicaoitem');
    }

    public function estoqueSetor()
    {
        return $this->hasMany('App\Estoquesetor');
    }

    public function estoqueTransferencia()
    {
        return $this->hasMany('App\Estoquetransferencia');
    }

    public function metaVenda()
    {
        return $this->hasMany('App\Metavenda');
    }

    public function posVendaPesquisa()
    {
        return $this->hasMany('App\Posvendapesquisa');
    }

    function nfRecebidaItem()
    {
        return $this->hasMany('App\Nfrecebidaitem');
    }

    function nfEmitidaItem()
    {
        return $this->hasMany('App\Nfemitidaitem');
    }

    public function nfEmitida()
    {
        return $this->hasMany('App\Nfemitida');
    }

    public function setorcolaboradores()
    {
        return $this->hasMany('App\Setorcolaboradores');
    }

    public function selectSetor($empresa)
    {
        return $this->where('empresa_id',$empresa)->pluck('descricao','id');
    }
    public function veiculos()
    {
        return $this->belongsToMany('App\Veiculo', 'setor_veiculo', 'setor_id', 'veiculo_id');
    }
    
    public function pedidoOp()
    {
        return $this->belongsTo('App\Nfoperacao', 'pedidooperacao_id');
    }
}
