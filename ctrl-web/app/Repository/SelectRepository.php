<?php

namespace App\Repository;

use App\Empresa;
use DB;
use App\User;
use App\Setor;
use App\Nfipi;
use App\Conta;
use App\NFcest;
use App\Boleto;
use App\Produto;
use App\Cliente;
use App\Veiculo;
use App\Promocao;
use App\Segmento;
use App\Tipopessoa;
use App\Veiculotipo;
use App\Colaborador;
use App\Colaboradorcomissao;
use App\Spedtipoitem;
use App\Produtoclasse;
use App\Unidademedida;
use App\Nfgrupofiscal;
use App\Pedidooperacao;
use App\Valegassituacao;
use App\Financeiroparcela;
use App\Condicaopagamento;
use App\Setorcolaboradores;
use App\Vendaativaocorrenciatipo;

class SelectRepository
{

    private $empresa_id;
    private $grupo_id;

    public function __construct($empresa_id = null, $grupo_id = null)
    {
        $this->empresa_id = $empresa_id;
        $this->grupo_id = $grupo_id;
    }

    public function setEmpresa($empresa)
    {
        $this->empresa_id = $empresa;
    }

    public function setGrupo($grupo)
    {
        $this->grupo_id = $grupo;
    }

    public function getProdutoClasse()
    {
        return Produtoclasse::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getUnidadeMedida()
    {
        return Unidademedida::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getNfGrupoFiscal()
    {
        return Nfgrupofiscal::where(['ativo' => 1, 'empresa_id' => $this->empresa_id])->pluck('descricao', 'id');
    }

    public function getSpedTipoItem()
    {
        return Spedtipoitem::pluck('descricao', 'id');
    }

    public function getIpi()
    {
        return Nfipi::all();
    }

    public function getNfcest($cest, $ncm)
    {
        return NFcest::where(['ncm' => $ncm, 'cest' => $cest])->get();
    }

    public function getClienteJuridico()
    {
        $tipopessoa = Tipopessoa::where(['grupo_id' => $this->grupo_id, 'ativo' => 1, 'tipopessoacadastro' => 'J'])->pluck('id')->toArray();
        $cliente = Cliente::where(['empresa_id' => $this->empresa_id, 'ativo' => 1, 'cliente' => 1])
            ->whereIn('tipopessoa_id', $tipopessoa)
            ->orderBy('nome')->pluck('nome', 'id');
        return $cliente;
    }

    public function getProdutos($classe = false)
    {
        if ($classe) {
            $class = Produtoclasse::where([["tipo", "G"], ['grupo_id', $this->grupo_id]])->pluck('id')->toArray();
            return Produto::where(['ativo' => 1, 'empresa_id' => $this->empresa_id])->whereIn('produtoclasse_id', $class)->orderby('descricao')->pluck('descricao', 'id');
        }
        return Produto::where(['ativo' => 1, 'empresa_id' => $this->empresa_id])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getCondicaoPagamento()
    {
        return Condicaopagamento::where(['ativo' => 1, 'grupo_id' => $this->grupo_id])->where([['tipo', '!=', 4], ['tipo', '!=', 5]])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getSituacao($tipo)
    {
        return Valegassituacao::where(DB::raw('upper(descricao)'), mb_strtoupper($tipo))->get()->first();
    }

    public function getColaborador()
    {
        return Colaborador::where(['empresa_id' => $this->empresa_id, 'ativo' => 1])->orderby('nome')->pluck('nome', 'id');
    }

    public function getSetores()
    {
        return Setor::where(['empresa_id' => $this->empresa_id, 'ativo' => 1])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getPedidoOperacoes()
    {
        return Pedidooperacao::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getSegmentos()
    {
        return Segmento::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])->orderby('descricao')->pluck('descricao', 'id');
    }

    public function getTipoOcorrencia()
    {
        return Vendaativaocorrenciatipo::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])->pluck('descricao', 'id');
    }

    public function getVeiculos()
    {
        return Veiculo::where(['empresa_id' => $this->empresa_id, 'ativo' => 1])->orderBy('placa')->pluck('placa', 'id');
    }

    public function getTipoVeiculos()
    {
        return Veiculotipo::where(['empresa_id' => $this->empresa_id, 'ativo' => 1])->orderBy('descricao')->pluck('descricao', 'id');
    }

    public function getSetorColaborador()
    {
        $setorscolaborador = Setorcolaboradores::pluck('colaborador_id')->toArray();
        $colaborador = Colaborador::where(['empresa_id' => $this->empresa_id, 'ativo' => 1])->whereIn('id', $setorscolaborador)->orderBy('nome')->pluck('nome', 'id');
        return $colaborador;
    }

    public function getColaboradorComissaoTon()
    {
        // Nome da tabela em minúsculas: Postgres trata "COLABORADORCOMISSAOS"
        // (quoted) como identificador distinto de colaboradorcomissaos. Oracle/
        // MySQL eram case-insensitive.
        $colaborador = Colaboradorcomissao::join('colaboradors as co','colaboradorcomissaos.colaborador_id','co.id')
                                    ->where(['colaboradorcomissaos.empresa_id' => $this->empresa_id, 'colaboradorcomissaos.ativo' => 1, 'tonelagem' => 1])
                                    ->select('co.id', 'co.nome')
                                    ->pluck('nome', 'id');

        return $colaborador;
    }


    public function getEmpresasUser()
    {
        $empresas = User::find(\Auth::user()->id)->empresas()->orderBy('nome_informal')->pluck('nome_informal', 'id');
        return $empresas;
    }

    public function getPromocoes()
    {
        return Promocao::where(['empresa_id' => $this->empresa_id, 'ativo' => 1])->orderBy('descricao')->get()->pluck('descricao', 'id');
    }

    public function getConvenios()
    {
        return Cliente::where(['empresa_id' => $this->empresa_id, 'ativo' => 1, 'convenioativo' => 1])->pluck('nome', 'id');
    }

    public function getTipoPessoa()
    {
        return Tipopessoa::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])->orderBy('descricao')->pluck('descricao', 'id');
    }

    /**
     * Return array of cities group by UF
     *
     * @param  int   $empresa_id
     * @return array $cities
     */
    public function getCities()
    {
        $query = "select id, descricao, uf ".
            "from cidades ".
            // UNIQUE é sintaxe Oracle; Postgres/ANSI usa DISTINCT.
            "where id in ( select distinct cidade_id from clientes where empresa_id = ".$this->empresa_id." ) ".
            "order by UF, descricao";

        $cidades = collect(DB::select($query));
        $anterior = null;
        $cities = [];
        $city = [];
        $cities[""] = "Selecione";
        foreach ($cidades as $cidade) {
            if (!is_null($anterior) && $anterior != $cidade->uf) {
                $cities[$anterior] = $city;
                $city = [];
            }
            $city[$cidade->id] = $cidade->descricao;
            $anterior = $cidade->uf;
        }
        $cities[$anterior] = $city;
        return $cities;
    }

    public static function getProdutoNf($empresa_id)
    {
        return Produto::where([['ativo', 1], ['nfepermite', 1]])
            ->where('empresa_id', $empresa_id)
            ->orderBy('produtos.descricao')
            ->select([
                'descricao', 'id', 'customedio', 'produtos.pesoliquido',
                'produtos.pesobruto', 'produtos.precovenda', 'produtos.precovendaminimo',
                'produtos.pgni', 'produtos.pgnn', 'produtos.pglp', 'produtos.tipo_glp',
                'produtos.nfecprodanp as cprodanp'
            ])->get();
    }

    public static function searchCliente($where = null, $select = null, $joins = null, $whereRaw = null, $whereIn = null, $with = null)
    {
        $select = is_null($select) ? ['*'] : $select;
        return self::selectGeneral(Cliente::select($select), 'clientes', $where, $joins, $whereRaw, $whereIn, $with);
    }

    public static function searchParcelas($where = null, $select = null, $joins = null, $whereRaw = null, $whereIn = null, $with = null)
    {
        $select = is_null($select) ? ['*'] : $select;
        return self::selectGeneral(Financeiroparcela::select($select), 'financeiroparcelas', $where, $joins, $whereRaw, $whereIn, $with);
    }

    public static function searchBoletos($where = null, $select = null, $joins = null, $whereRaw = null, $whereIn = null, $with = null)
    {
        $select = is_null($select) ? ['*'] : $select;
        return self::selectGeneral(Boleto::select($select), 'boletos', $where, $joins, $whereRaw, $whereIn, $with);
    }

    public static function searchConta($where = null, $select = null, $joins = null, $whereRaw = null, $whereIn = null, $with = null)
    {
        $select = is_null($select) ? ['*'] : $select;
        return self::selectGeneral(Conta::select($select), 'contas', $where, $joins, $whereRaw, $whereIn, $with);
    }

    private static function selectGeneral($model, $tableName, $where, $joins, $whereRaw, $whereIn, $with)
    {

        $where = is_null($where) ? [] : $where;
        $joins = is_null($joins) ? [] : $joins;
        $whereIn = is_null($whereIn) ? [] : $whereIn;
        $with = is_null($with) ? [] : $with;

        if (count($where) > 0) {
            $model->where($where);
        }

        foreach ($joins as $key => $join) {
            $exp = explode('.', $key);
            $type = 'inner';
            if (isset($exp[1])) {
                $type = $exp[0];
                $key = $exp[1];
            }
            switch ($type) {
                case 'inner':
                    $model->join("$join $key", "$key.id", "$tableName.$key" . "_id");
                    break;
                case 'left':
                    $model->leftJoin("$join $key", "$key.id", "$tableName.$key" . "_id");
                    break;
                default:
                    $model->rightJoin("$join $key", "$key.id", "$tableName.$key" . "_id");
                    break;
            }
        }

        foreach ($with as $w) {
            $model->with($w);
        }

        foreach ($whereIn as $key => $w) {
            $model->whereIn($key, $w);
        }

        if (!is_null($whereRaw))
            $model->whereRaw($whereRaw);

        return $model;
    }

    /**
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getSituacaoToView()
    {
        return DB::table('nfsituacaos')->where('ativo', 1)->orderBy('id', 'asc')->get();
    }

    /**
     * @param $empresaPadrao
     * @return \Illuminate\Support\Collection
     */
    public static function getCondPgtoToView($empresaPadrao, $pluck = true)
    {
        $conds =  DB::table('condicaopagamentos')->where([
            ['grupo_id', $empresaPadrao->grupo_id],
            ['ativo', 1]
        ])->whereNotIn('tipo', [4, 5])->orderBy('descricao', 'asc')->select('descricao', 'id', 'nfc_tpag');
        return $pluck ? $conds->pluck('descricao', 'id')->prepend('Selecione', '') : $conds;
    }

    /**
     * @param $empresaPadrao
     * @param string $tipoNf
     * @return \Illuminate\Support\Collection
     */
    public static function getOperacaoToView($empresaPadrao, $tipoNf = "recebida")
    {
        if ($tipoNf === "recebida")
            $aparecetela = array(0, 2);
        else
            $aparecetela = array(1, 2);

        $query = DB::table('nfoperacaos')->where('empresa_id', $empresaPadrao->id)->whereIn('aparecetela', $aparecetela);
        if ($tipoNf == "sat") {
            $query->where('usasat', true);
        }
        return $query->orderBy('cfop', 'asc')->get();
    }

    /**
     * @param $empresaPadrao
     * @return \Illuminate\Support\Collection
     */
    public static function getTransportadorasToView($empresaPadrao)
    {
        return DB::table('clientes')->where([
            ['empresa_id', $empresaPadrao->id],
            ['ativo', 1],
            ['transportador', 1]
        ])->select('nome', 'id')->pluck('nome', 'id')->prepend('Selecione', '');
    }

    /**
     * @param $empresaPadrao
     * @return \Illuminate\Support\Collection
     */
    public static function getClientesToView($empresaPadrao)
    {
        return DB::table('clientes')->where([
            ['empresa_id', $empresaPadrao->id],
            ['ativo', 1],
            ['nfemite', 1]
        ])->select('nome', 'id')->pluck('nome', 'id')->prepend('Selecione', '');
    }

    /**
     * @param $empresaPadrao
     * @return \Illuminate\Support\Collection
     */
    public static function getSetoresToView($empresaPadrao)
    {
        return DB::table('setors')->where([
            ['empresa_id', $empresaPadrao->id],
            ['ativo', 1]
        ])->orderBy('descricao', 'asc')->select('descricao', 'id')->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function getEmpresaNfToView($id)
    {
        return DB::table("empresas as e")->select([
            'e.razao_social', 'e.nome_fantasia', 'e.inscricao_estadual', 'e.cnpj',
            'e.inscricao_municipal', 'e.cnae', 'e.nfecrt', 'e.codigoibgepais', 'e.uf',
            'e.cidade_id', 'e.numero', 'e.cep', 'e.complemento', 'e.nfcetipoemissao', 'e.nfetipoemissao',
            'e.telefone1', 'e.id', 'e.grupo_id', 'e.cidade_id', 'e.bairro_id', 'c.descricao as cidade_descricao',
            'b.descricao as bairro_descricao', 'r.descricao as rua_descricao', 'c.cod_ibge as cidade_cod_ibge',
            'e.nfetipoambiente', 'e.nfcetipoambiente', 'est.cod_ibge as estado_cod_ibge', 'e.contingenciaemissao'
        ])
            ->join("cidades as c", 'c.id', '=', 'e.cidade_id')
            ->join("bairros as b", 'b.id', '=', 'e.bairro_id')
            ->join("estados as est", 'est.uf', '=', 'e.uf')
            ->leftJoin("ruas as r", 'r.id', '=', 'e.rua_id')
            ->where('e.id', $id)->get()->first();
    }

    public static function getFinanceiroAllowedCanc($financeiro_id)
    {
        $raw = 'p.id as par, b.id as bol, cheque.id as cheque, '
            . 'ec.id as ec, ecc.id as eccheque, chequee.id as chequee, ecr.id as ecr';
        $tableCheque = 'chequerecebidofinanceiros';
        $tableChequeEm = 'chequeemitidofinanceiros';
        $tableEnc = 'chequeemitidoencontrocontas';
        $tableEncR = 'chequeemitidoencontrocontas';
        return DB::table('financeiroparcelas as p')
            ->where('p.financeiro_id', $financeiro_id)
            ->leftJoin($tableCheque . ' cheque', 'cheque.financeiroparcela_id', 'p.id')
            ->leftJoin($tableChequeEm . ' chequee', 'chequee.financeiroparcela_id', 'p.id')
            ->leftJoin($tableEnc . ' ec', 'ec.financeiroparcela_id', 'p.id')
            ->leftJoin($tableEncR . ' ecr', 'ecr.financeiroparcela_id', 'p.id')
            ->leftJoin('boletos as b', 'b.financeiroparcela_id', 'p.id')
            ->leftJoin($tableCheque . ' ecc', 'ecc.id', 'ec.chequeemitido_id')
            ->selectRaw($raw)
            ->get()->first();
    }

    public function getClientesValeGasNaoImpresso()
    {

        $clientes = Cliente::join("valegasvendas as ven", "ven.cliente_id", "clientes.id")
            ->join("valegas as val", "val.valegasvenda_id", "ven.id")
            ->whereIn("val.valegassituacao_id", [25, 26])
            ->where("clientes.empresa_id", $this->empresa_id)
            ->select("clientes.id", "clientes.nome")
            ->groupBy("clientes.id", "clientes.nome")
            ->orderBy("clientes.nome")
            ->pluck("nome", "id")->prepend("Selecione", "");

        return $clientes;
    }
}
