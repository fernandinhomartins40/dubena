<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static $this whereEmpresaId($id)
 * @method static $this whereCnpj($value)
 * @method static $this whereCpf($value)
 */
class Cliente extends Model
{
    use \App\Services\RevisionsTraitService;

    /**
     * Entidade da tabela no qual ser quer manter como revisão principal
     * Somente uma string com o nome, ela deve existir na tabela
     */
    protected $identity = "empresa_id";

    /**
     * Para entidades do objeto que é mantido registro somente da data
     * Sem hora colocar aqui
     */
    protected $treatDate = ["datanascimento"];

    /**
     * campo consumidor final removido para ser colocado na tela de NF-e
     * @var array
     */
    protected $fillable = [
        'grupo_id', 'empresa_id', 'nome', 'datanascimento', 'rg', 'rgorgao',
        'rguf', 'rgdataexpedicao', 'cpf', 'sexo', 'endereco', 'numero', 'complemento', 'email',
        'cidade_id', 'cep', 'bairro_id', 'ativo', 'uf', 'foto', 'user_id', 'segmento_id',
        'tipopessoa_id', 'setor_id', 'fantasia', 'cnpj', 'inscricao_estadual', 'observacoes',
        'simples', 'indicador_ie', 'ponto_referencia', 'cliente', 'fornecedor',
        'transportador', 'estadocivil_id', 'nfemite', 'convenio', 'convenio_id', 'conveniolimite',
        'convenioativo', 'latitude', 'longitude', 'locationtype', 'rua_id', 'suframa', 'api_id',
        'endereco_app', 'nome_app', 'latitude_app', 'longitude_app', 'codigo_convenio', 'consisa_id',
        'gasdopovo'
    ];

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

    public function rua()
    {
        return $this->belongsTo('App\Rua');
    }

    public function rguf()
    {
        return $this->belongsTo('App\Estado', 'rguf');
    }

    public function uf()
    {
        return $this->belongsTo('App\Estado', 'uf');
    }

    public function telefones()
    {
        return $this->hasMany('App\Clientetelefone');
    }

    public function contatos()
    {
        return $this->hasMany('App\Clientecontato');
    }

    public function dataultimopedidoconcluido()
    {
        $pedidosituacao = Pedidosituacao::where('fechadoconcluido',1)->pluck('id')->toArray();
        return $this->hasOne('App\Pedido')->whereIn('pedidosituacao_id',$pedidosituacao)->orderBy('datahoraprevisaoentrega', "!=", null, 'desc')->get(['datahoraprevisaoentrega'])->pluck('datahoraprevisaoentrega')->first();
    }

    public function dataultimopedido()
    {
        return $this->hasOne('App\Pedido')->where('datahoraprevisaoentrega', "!=", null)->orderBy('datahoraprevisaoentrega', "!=", null, 'desc')->get(['datahoraprevisaoentrega'])->pluck('datahoraprevisaoentrega')->first();
    }

    public function clienteConvenio()
    {
        return $this->hasOne('App\Clienteconvenio');
    }

    public function clienteConvenioDependente()
    {
        return $this->hasMany('App\Clienteconveniodependente');
    }

    public function clienteConvenioDependete()
    {
        return $this->hasMany('App\Clienteconveniodependente');
    }

    public function clienteProduto()
    {
        return $this->hasMany('App\Clienteproduto');
    }

    public function clientePromocao()
    {
        return $this->hasMany('App\Clientepromocao');
    }
    public function condicaoPagamento()
    {
        return $this->belongsToMany('App\Condicaopagamento')->orderBy('descricao')->withTimestamps();
    }
    public function tipopessoa()
    {
        return $this->belongsTo('App\Tipopessoa')->orderBy('descricao');
    }

    public function convenioempresa()
    {
        return $this->belongsTo('App\Cliente', 'convenio_id');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor','setor_id');
    }

    public function segmento()
    {
        return $this->belongsTo('App\Segmento','segmento_id');
    }

    public function pedido()
    {
        return $this->hasMany('App\Pedido');
    }

    public function produtoconvenio()
    {
        return $this->hasMany('App\Clienteprodutosconvenio');
    }
}
