<?php

namespace App\Repository;

use DB;
use Input;
use Session;
use Exception;
use App\Setor;
use App\Estado;
use App\Bairro;
use App\Cidade;
use App\Pedido;
use App\Cliente;
use App\Empresa;
use App\Tipopessoa;
use App\Empresaconfig;
use App\Motivonaovenda;
use App\Pedidooperacao;
use App\Pedidosituacao;
use App\Condicaopagamento;
use App\Pedidomotivoatraso;
use App\Services\CarbonCustom as Carbon;
use App\Helpers\Utils\PedidoUtil as Util;

class PedidoRepository
{

    public static function getConfig($empresa_id)
    {
        $config = Empresaconfig::where('empresa_id', $empresa_id)->get()->first();
        if (!is_null($config)) {
            unset($config['logo']);
            unset($config['logoimg']);
            unset($config['senhamestre']);
        }
        return $config;
    }

    public static function getEmpresasByUser($grupo_id, $user)
    {
        $empresas = self::getEmpresas($user, $grupo_id);
        return $empresas->pluck('nome_informal','id')->prepend('Selecione','');
    }

    public static function getCondPgto($grupo_id, $formatted = false)
    {
        $cond = Condicaopagamento::where([
                    ['ativo', 1],
                    ['grupo_id', $grupo_id]
        ]);
        if ($formatted) {
            return $cond->select(DB::raw("id || '-' || tipo as id_tipo"), 'descricao')->orderby('descricao')
                            ->get()->pluck('descricao', 'id_tipo')->prepend('Selecione', '');
        } else {
            return $cond->orderby('descricao')
                            ->get()->pluck('descricao', 'id');
        }
    }

    public static function getMotNaoVenda($grupo_id)
    {
        return Motivonaovenda::where([
                    ['grupo_id', $grupo_id],
                    ['ativo', 1]
                ])->select('descricao', 'id')->get()->pluck('descricao', 'id');
    }

    public static function getPedidoOperacao($grupo_id)
    {
        return Pedidooperacao::where([
                            ['ativo', 1],
                            ['grupo_id', $grupo_id]
                        ])
                        ->orderBy('descricao')->select('descricao', 'id')
                        ->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public static function getPedidoSituacao($grupo_id)
    {
        return Pedidosituacao::where([
                            ['ativo', 1],
                            ['grupo_id', $grupo_id]
                        ])->orderBy('descricao')->select('descricao', 'id')
                        ->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public static function getBairros($cidade_id, $grupo_id, $user = null)
    {
        $bairros = Bairro::where('cidade_id', $cidade_id)
                ->orderBy('descricao')
                ->select('descricao', 'id');

        if (!is_null($user)) {
            $bairros->whereIn('grupo_id', $user->empresas()->select('grupo_id')->pluck('grupo_id'));
        } else {
            $bairros->where('grupo_id', $grupo_id);
        }
        return $bairros->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public static function getCidadesByUf($uf)
    {
        return Cidade::where('uf', $uf)->select('descricao', 'id')
                        ->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public static function getClientById($id)
    {
        return Cliente::where('id', $id)
                        ->with('rua', 'bairro', 'cidade', 'clienteProduto', 'condicaoPagamento', 'convenioempresa.produtoconvenio')
                        ->get()->first();
    }

    public static function getSetorByEmpresa($empresa_id)
    {
        return Setor::where('empresa_id', $empresa_id)->where('ativo', 1)
                        ->select('id', 'descricao')->get()
                        ->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public static function getMotAtraso($grupo_id)
    {
        return Pedidomotivoatraso::where([
                            ['grupo_id', $grupo_id],
                            ['ativo', 1]
                        ])->orderBy('descricao')
                        ->select('descricao', 'id')
                        ->get()->pluck('descricao', 'id');
    }

    public static function getUf()
    {
        return Estado::all()->pluck('uf', 'uf');
    }

    private static function getQueryCases($get)
    {
        $data = Carbon::now()->toDateTimeString();
        $caseUrgente = "CASE WHEN ENTREGAURGENTE = 1 "
                . "THEN config.tempourgente "
                . "ELSE config.tempoentrega "
                . "END";
        $caseAtrasado = "CASE WHEN ("
                . " (TO_DATE('$data', 'YYYY-MM-DD HH24:MI:SS') - DATAHORAPREVISAOENTREGA) "
                . " * 1440) > $caseUrgente "
                . "  THEN 'atrasado' "
                . "  ELSE (CASE WHEN apipedido_id IS NOT NULL THEN 'pedido-app' ELSE ':status' END) "
                . "END";

        $caseSituacao = "(CASE WHEN ENTREGAFINALIZADA = 1 OR FECHADOCONCLUIDO = 1 "
                . "     THEN 'concluido' "
                . " WHEN ENTREGACANCELADA = 1 OR FECHADOCANCELADO = 1 "
                . "     THEN 'cancelado' "
                . " WHEN ENTREGAPENDENTE = 1 "
                . "     THEN " . str_replace(':status', 'pendente', $caseAtrasado)
                . " WHEN ENTREGATRANFERIDA = 1"
                . "     THEN 'transferir'"
                . " WHEN EMENTREGA = 1 OR PEDIDORECEBIDOMOVEL = 1 OR PEDIDOLIDOMOVEL = 1 "
                . "     THEN " . str_replace(':status', 'emEntrega', $caseAtrasado)
                . "     ELSE ''"
                . " END)";
        if ($get == 'caseSituacao') {
            return $caseSituacao;
        }
    }

    private static function getDBColumnCorresp($viewColumn)
    {
        switch ($viewColumn) {
            case 'datahora':
                return 'pedido.datahoraprevisaoentrega';
            case 'cliente':
                return 'cliente.nome';
            case 'setorcolaborador':
                return 'setor.descricao';
            case 'status':
                return 'situacao.descricao';
            case 'empresa':
                return 'empresa.nome_informal';
            case 'endereco':
                return 'rua.descricao';
            case 'pagamento':
                return 'pagamento.descricao';
            case 'telefone':
                return 'pedido.entregatelefone';
            case 'valor':
                return 'pedido.valorvenda';
            case 'entregataxa':
                return 'pedido.entregataxa';
            case 'urgente':
                return 'pedido.entregaurgente';
            case 'datahoraenvioentregador':
                return 'pedido.datahoraenvioentregador';
            default:
                return 'pedido.id';
        }
    }

    private static function selectPedidosToMonitoramento($user)
    {
        $dataInicio = Input::get('datainicio', null);
        $dataFinal = Input::get('datafinal', null);
        $setor_id = (int) Input::get('setor_id', 0);
        $status_id = (int) Input::get('status_id', 0);
        $empresa_id = (int) Input::get('empresa_id', 0);
        $colaborador_id = (int) Input::get('colaborador_id', 0);

        $pedidos = DB::table('pedidos as pedido')->join("pedidosituacaos as situacao", 'pedido.pedidosituacao_id', 'situacao.id')
                ->leftJoin('empresaconfigs as config', 'pedido.empresa_id', 'config.empresa_id')
                ->whereRaw('pedido.grupo_id = ' . Session::get('empresa_padrao')->grupo_id);

        if ($setor_id > 0) {
            $pedidos->where('entregasetor_id', $setor_id);
        }
        if ($status_id > 0) {
            $pedidos->where('pedidosituacao_id', $status_id);
        }
        if ($colaborador_id != 0) {
            $pedidos->where('colaborador_id', $colaborador_id);
        }

        if ($empresa_id != 0) {
            $pedidos->where('pedido.empresa_id', $empresa_id);
        } else {
            $empresas = self::getEmpresas($user);
            $pedidos->whereIn('pedido.empresa_id', $empresas->pluck('id'));
        }

        if ($dataInicio && $dataFinal) {
            $raw = "datahoraprevisaoentrega "
                    . " BETWEEN TO_DATE('$dataInicio 00:00:00', 'yyyy-mm-dd HH24:MI:SS') "
                    . " AND TO_DATE('$dataFinal 23:59:59', 'yyyy-mm-dd HH24:MI:SS')";
            $pedidos->whereRaw($raw);
        } elseif ($dataInicio) {
            $pedidos->where('datahoraprevisaoentrega', '>=', $dataInicio . ' 00:00:00');
        } elseif ($dataFinal) {
            $pedidos->where('datahoraprevisaoentrega', '<=', $dataFinal . ' 23:59:59');
        }

        return $pedidos;
    }

    public static function getPedidosMonitoramento($user)
    {
        $pedidos = static::selectPedidosToMonitoramento($user)
                ->leftJoin('pedidoitems as item', 'pedido.id', 'item.pedido_id')
                ->join("clientes as cliente", 'cliente.id', 'pedido.cliente_id')
                ->join("colaboradors as colaborador", 'colaborador.id', 'pedido.colaborador_id')
                ->join("setors as setor", 'setor.id', 'pedido.entregasetor_id')
                ->join("empresas as empresa", 'empresa.id', 'pedido.empresa_id')
                ->join('bairros as bairro', 'bairro.id', 'pedido.entregabairro_id')
                ->join('ruas as rua', 'rua.id', 'pedido.entregarua_id')
                ->join('cidades as cidade', 'cidade.id', 'pedido.entregacidade_id')
                ->join("condicaopagamentos as pagamento", 'pagamento.id', 'pedido.condicaopagamento_id');

        $caseSituacao = static::getQueryCases('caseSituacao');

        $countPedidos = $pedidos->selectRaw('count(*) as count')->get()->first();

        if (!is_null($countPedidos) && $countPedidos->count > 20000) {
            $msg = "O número de registros é muito grande e pode causar uma sobrecarga. "
                    . "Por favor, utilize os filtros para trazer um número menor resultados.";
            throw new Exception($msg);
        }

        $select = [
            DB::raw("sum(quantidade) as quantidade"), DB::raw("$caseSituacao as situacao"),
            'datahoraenvioentregador', 'pedidosituacao_id', 'pedido.id', 'valorvenda',
            'entregataxa', 'entregaurgente', 'entregatelefone', 'datahoraprevisaoentrega',
            'cliente.nome', 'situacao.descricao as situacao_descricao', 'setor.descricao as setor',
            'colaborador.nome as colaborador', 'empresa.nome_informal',
            'pagamento.descricao as condicaopagamento', 'rua.descricao as rua',
            'pedido.entreganumero', 'bairro.descricao as bairro', 'cidade.descricao as cidade',
            'pedido.nfcegerou', 'pedido.nfce_id', 'pedido.apipedido_id'
        ];

        $groupBy = [
            DB::raw($caseSituacao), 'datahoraenvioentregador', 'pedidosituacao_id', 'pedido.id',
            'valorvenda', 'entregataxa', 'entregaurgente', 'entregatelefone',
            'datahoraprevisaoentrega', 'cliente.nome', 'situacao.descricao', 'setor.descricao',
            'colaborador.nome', 'empresa.nome_informal', 'pagamento.descricao', 'rua.descricao',
            'pedido.entreganumero', 'bairro.descricao', 'cidade.descricao', 'pedido.nfcegerou',
            'pedido.nfce_id', 'pedido.apipedido_id'
        ];

        $orderBy = static::getDBColumnCorresp(Input::get('sortBy', 'pedido.id'));

        return $pedidos->select($select)->groupBy($groupBy)
                        ->orderBy($orderBy, Input::get('order', 'DESC'))->get();
    }

    public static function getPedidoForm($id)
    {
        $pedido = Pedido::where('id', $id)
                        ->with('pedidoitem', 'entregacidade', 'condicaopagamento', 'empresa', 'pedidosituacao')
                        ->get()->first();
        if ($pedido->empresa) {
            unset($pedido->empresa->logo);
            unset($pedido->empresa->logoimg);
        }
        return $pedido;
    }

    public static function getPedidosByCartao($grupo_id, $cartaoMascarado, $date, $except)
    {
        return Pedido::where([
                    ['pedidos.grupo_id', $grupo_id],
                    ['pedidos.datahoraprevisaoentrega', '>=', $date],
                    ['pedidos.numerocartao', $cartaoMascarado],
                    ['pedidosituacaos.fechadocancelado', 0],
                    ['pedidosituacaos.entregacancelada', 0],
                    ['pedidos.id', '<>', $except]
                ])->join('pedidosituacaos', 'pedidosituacaos.id', '=', 'pedidos.pedidosituacao_id')->get();
    }

    public static function selectTipoPessoas($grupo_id)
    {
        $tipopessoasdados = Tipopessoa::where('ativo', 1)
                        ->where('grupo_id', $grupo_id)
                        ->orderBy('descricao')->get();
        $tipopessoas = ['' => 'Selecione'];
        foreach ($tipopessoasdados as $tipopessoa) {
            $tipopessoas[$tipopessoa->id . $tipopessoa->tipopessoacadastro] = $tipopessoa->descricao;
        }
        return $tipopessoasdados;
    }

    public static function findOrFail($id, $strModel)
    {
        $path = 'App\\' . $strModel;
        if (class_exists($path)) {
            $model = $path::find($id);
        } else {
            throw new Exception("Class " . $path . " not found.");
        }

        if (is_null($model)) {
            $msg = "Não foi possível encontrar a "
                    . Util::getDescriptionOfModel($strModel)
                    . " com o código " . $id;
            throw new Exception($msg);
        }

        return $model;
    }

    public static function getAllSetoresAllowedUser($grupo_id, $empresas)
    {
        // $empresas é uma coleção keyed por id de empresa, mas inclui a chave ''
        // do placeholder "Selecione". O Postgres rejeita '' num IN de integer
        // ("invalid input syntax for type integer"); MySQL/Oracle aceitavam.
        // Filtra apenas chaves numéricas válidas.
        $ids = $empresas->keys()->filter(function ($k) {
            return is_numeric($k);
        })->values();

        return DB::table('setors as s')->where('s.grupo_id', $grupo_id)
                        ->whereIn('s.empresa_id', $ids)
                        ->where('ativo', true)
                        ->orderBy('s.descricao')
                        ->selectRaw('s.descricao, s.empresa_id, s.id')
                        ->get();
    }

    public static function getColaboradoresBySetores($setores)
    {
        return DB::table('setorcolaboradores as sc')
                        ->join('colaboradors as c', 'c.id', '=', 'sc.colaborador_id')
                        ->whereIn('sc.setor_id', $setores->pluck('id'))
                        ->orderby('c.nome')
                        ->selectRaw('c.id, c.nome, sc.setor_id')->get();
    }

    /**
     * Tras somente as empresas que o usuario tem acesso a tela de pedidos
     *
     * @var     \App\User                       $user
     * @var     int                             $grupo_id
     * @return \Illuminate\Support\Collection   $empresas
     */
    public static function getEmpresas($user = null, $grupo_id = null)
    {
        if (is_null($user)) {
            $user = \Auth::user();
        }
        if (! is_null($grupo_id)) {
            $grupo_id = "and ep.grupo_id = $grupo_id ";
        }

        $query = "select ep.id, ep.nome_informal ".
            "from menuusers mu ".
            "inner join empresa_user eu on eu.user_id = mu.user_id and eu.empresa_id = mu.empresa_id ".
            "inner join menus me on mu.menu_id = me.id ".
            "inner join empresas ep on mu.empresa_id = ep.id and eu.empresa_id = ep.id ".
            "where mu.user_id = $user->id and me.descricao like 'pedido.index' and mu.visualizar = 1 $grupo_id".
            "order by nome_informal";

        return collect(DB::select($query));
    }

    public static function getConfigs($empresas, $grupo_id)
    {
        $configs = DB::table("empresaconfigs as c")->whereIn('empresa_id', $empresas)
            ->join("empresas as e", "e.id", "c.empresa_id")
            ->select([
                'e.id', 'empresa_id', 'tempoentrega', 'tempourgente', 'pedidostatuspadrao', 'operacaodisk',
                'androidutiliza', 'quant_padrao', 'impressaoautomatica', 'pedidoemitenfce', 'cep', 'cidade_id',
                'maximoparcelas', 'tempourgente', 'pedidocontrolatempoligacoes', 'validagasbolso', 'pedidovalidacartao',
                'validaatraso', 'validapixentrega', 'produtogp_id', 'condicaopagamentogp_id'
            ])->get();

        $condicoesPagamentoOriginal = DB::table('condicaopagamentos as c')->where([
            ['ativo', 1],
            ['grupo_id', $grupo_id]
        ])->orderby('descricao')->select('num_parcelas', 'id', 'tipo', 'descricao')->get();

        $raw = "empresa_id IN (" . implode(", ", $empresas) . ") AND ativo = 1";
        $setores = DB::table("setors as s")->whereRaw($raw)->orderBy("descricao")
            ->select(['id', 'descricao', 'empresa_id'])->get();

        $colaboradores = static::getColaboradoresBySetores($setores);
        foreach ($setores as &$setor) {
            //filtrando colaboradores dos setores
            $colaboradoresSetor = $colaboradores->where('setor_id', $setor->id);
            $colaboradores = $colaboradores->where('setor_id', "!=", $setor->id);
            $setor->colaboradores = Util::reindexArray($colaboradoresSetor, ['id', 'nome']);
        }

        foreach ($configs as &$config) {

            //filtrando setores da empresa
            $setoresEmpresa = $setores->where('empresa_id', $config->empresa_id);
            $setores = $setores->where('empresa_id', "!=", $config->empresa_id);
            $config->setores = Util::reindexArray($setoresEmpresa, ['descricao', 'id', 'colaboradores']);

            if ($config->maximoparcelas) {
                $filtered = $condicoesPagamentoOriginal->where('num_parcelas', '<=', $config->maximoparcelas);
                $config->condicao_pagamento = Util::reindexArray($filtered, ['tipo', 'descricao', 'id']);;
            } else {
                $config->condicao_pagamento = $condicoesPagamentoOriginal;
            }

        }

        return $configs;
    }

    public static function getDataByGroupToFormPedido($grupo_id, $user) {

        $empresas = static::getEmpresasByUser($grupo_id, $user);
        $allConfigs = static::getConfigs($empresas->keys()->filter(function ($i) {return !empty($i);})->toArray(), $grupo_id);
        $motivonaovendas = static::getMotNaoVenda($grupo_id);
        $operacoes = static::getPedidoOperacao($grupo_id);
        $status = static::getPedidoSituacao($grupo_id);
        $uf = static::getUf();
        $motivosatrasos = static::getMotAtraso($grupo_id);
        return compact('empresas', 'allConfigs', 'motivonaovendas', 'operacoes', 'status', 'uf', 'motivosatrasos');
    }
}
