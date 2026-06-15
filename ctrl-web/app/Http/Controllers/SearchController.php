<?php

namespace App\Http\Controllers;

/**
 * ApiSearchController is used for the "smart" search throughout the site.
 * it returns and array of items (with type and icon specified) so that the selectize.js plugin can render the search results properly
 * */

use App\Helpers\Utils\NfUtil;
use App\Processors\Nfe\Tributacao\NfeImpostoProcessor;
use DB;
use Illuminate\Support\Collection;
use Session;
use App\Rua;
use App\User;
use App\Banco;
use App\Setor;
use App\Conta;
use App\Bairro;
use App\Boleto;
use App\Pedido;
use App\Empresa;
use App\Produto;
use App\Cliente;
use App\Nfemitida;
use App\Nfimposto;
use App\Financeiro;
use App\Pedidoitem;
use App\Layoutbanco;
use App\Colaborador;
use App\Estoquesetor;
use App\Empresaconfig;
use App\Pedidosituacao;
use App\Contafechamento;
use App\Clientetelefone;
use App\Financeiroparcela;
use App\Condicaopagamento;
use App\Setorcolaboradores;
use App\Ocorrenciasremessas;
use Illuminate\Http\Request;
use App\Monitoramentochamadas;
use App\Chequeemitidofinanceiro;
use App\Chequerecebidofinanceiro;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use App\Services\CarbonCustom as Carbon;
// FASE 4: removido `use Yajra\Oci8\Query\OracleBuilder` (saída do Oracle).
// Era usado apenas em docblocks @param/@return, não em código executável.

class SearchController extends Controller
{

    public function appendValue($data, $type, $element)
    {
        // operate on the item passed by reference, adding the element and type
        foreach ($data as $key => &$item) {
            if (is_object($item))
                $item = (array) $item;
            $item[$element] = $type;
        }
        return $data;
    }

    public function appendURL($data, $prefix)
    {
        // operate on the item passed by reference, adding the url based on slug
        foreach ($data as $key => &$item) {
            $item['url'] = url($prefix . '/' . $item['slug']);
        }
        return $data;
    }

    public function indexcliente()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $raw = "(" . rawTranslateSpecialChars("nome") . " LIKE '%$query%' OR "
            . rawTranslateSpecialChars("fantasia") . " LIKE '%$query%' " . ")";
        $clientes = Cliente::where('empresa_id', Session::get('empresa_padrao')->id)
            ->where('ativo', true)
            ->whereRaw($raw)
            ->orderBy('nome', 'asc')
            ->take(10)
            ->get(array('id', 'nome'))->toArray();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');
        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function indexclientetodasempresas()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $clientes = Cliente::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
            ->whereRaw(rawTranslateSpecialChars("nome") . " LIKE '%$query%'")
            ->orderBy('nome', 'asc')
            ->take(8)
            ->get(array('id', 'nome'))->toArray();

        //$responsavels 	= $this->appendURL($responsavels, 'responsavels');
        // Add type of data to each item of each set of results
        $clientes = $this->appendValue($clientes, 'cliente', 'class');

        //return response()->json(array(
        //	'data'=>$responsavels
        //));
        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function indexclienteoutrasempresas()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $clientes = Cliente::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('empresa_id', '!=', Session::get('empresa_padrao')->id)
            ->whereRaw(rawTranslateSpecialChars("nome") . " LIKE '%$query%'")
            ->orderBy('nome', 'asc')
            ->take(8)
            ->get(array('id', 'nome'))->toArray();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');

        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function indexclientecompleto()
    {
        $query = e(Input::get('q', ''));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $Cliente = Cliente::where('clientes.id', $query)->leftJoin('tipopessoas as p', 'p.id', 'clientes.tipopessoa_id')->select(DB::raw("clientes.*"), 'p.tipopessoacadastro')->get()->first();
        if ($Cliente->datanascimento != null) {
            $dt = explode('-', $Cliente->datanascimento);
            $Cliente["datanascimento"] = $dt[2] . "/" . $dt[1] . "/" . $dt[0];
        }
        if ($Cliente->rgdataexpedicao != null) {
            $dt = explode('-', $Cliente->rgdataexpedicao);
            $Cliente["rgdataexpedicao"] = $dt[2] . "/" . $dt[1] . "/" . $dt[0];
        }
        $Cliente["foto"] = null;
        $Cliente["nomepai"] = '';
        $Cliente["nomemae"] = '';
        $Cliente["telefone"] = '';
        $Cliente["celular"] = '';

        foreach ($Cliente->telefones as $fone) {
            if (substr($fone->telefonetipo->descricao, 0, 7) == 'Celular') {
                $Cliente["celular"] = $fone->telefone;
            }
            if ($fone->telefonetipo->descricao == 'Fixo Residencial') {
                $Cliente["telefone"] = $fone->telefone;
            }
        }
        $Cliente["empresa"] = Empresa::find($Cliente->empresa_id)->nome_informal;
        if ($Cliente->condicaopagamento != null) {
            $Cliente["condicaopagamento_descricao"] = '';
        }

        return ($Cliente);
    }

    public function searchBanco()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $bancos = Banco::whereRaw(rawTranslateSpecialChars("codigo") . " LIKE '%$query%' OR " . rawTranslateSpecialChars("descricao") . " LIKE '%$query%'"
            . "AND (grupo_id = " . Session::get('empresa_padrao')->grupo_id . " OR grupo_id IS NULL)")
            ->orderBy('descricao', 'asc')
            ->take(8)
            ->get(array('id', 'codigo', 'descricao'))->toArray();

        for ($i = 0; $i < count($bancos); $i++) {
            $bancos[$i]["descricao"] = str_pad($bancos[$i]["codigo"], 4, "0", STR_PAD_LEFT) . ' ' . $bancos[$i]["descricao"];
        }

        return response()->json(array(
            'data' => $bancos
        ));
    }

    /**
     * buscando as movimentações do caixa
     * @return string or \Illuminate\Http\Response
     */
    public function indexmovimentocontaatual()
    {
        $query = e(Input::get('q', ''));
        $fechamento = $this->getFechamentoToMovtos($query);

        if ($query == '' || is_null($fechamento))
            return $this->viewMovtos();

        $conta = DB::table('contas')->where('id', $query)->get()->first();

        $movimentosDB = $this->getMovimentosDB($query, $fechamento);
        if (e(Input::get('issetFechamento', '')) === "true") {
            $date = Carbon::parse($fechamento->datahorafechamento)->format("Y-m-d H:i");
        } else {
            $date = e(Input::get('datafinal', ''));

            if (strlen($date) === 0)
                $date = Carbon::now()->format("Y-m-d H:i");
            else
                $date = Carbon::createFromFormat("d/m/Y H:i", $date)->format("Y-m-d H:i");
        }
        $countTotal = $this->getCountSearchMovtos($movimentosDB, $date);

        if ($countTotal > 20000)
            return "O número de registros é muito grande e pode causar uma sobrecarga. Por favor, utilize o filtro para trazer um número menor resultados.";

        return $this->validateMovtosCaixa($movimentosDB, $fechamento, $conta, $countTotal, $date);
    }

    /**
     * calculando a quantidade de registros
     * @param \Eloquent|OracleBuilder $movimentosDB
     * @param $date
     * @return int
     */
    private function getCountSearchMovtos($movimentosDB, $date)
    {
        $countTotal = clone $movimentosDB;
        $countTotal = $countTotal->selectRaw('count(*) as count')
            ->where('mov.datahorabaixa', '<=', $date . ':59')
            ->get()->first();

        if (is_null($countTotal))
            return 0;
        else
            return (int) $countTotal->count;
    }

    /**
     * Pega o fechamento correspondente à conta
     * @param int $query
     * @return Collection
     */
    private function getFechamentoToMovtos($query)
    {
        $contafechamento_id = e(Input::get('c', ''));

        $fechamento = DB::table("contafechamentos")->where('conta_id', $query);
        if ($contafechamento_id != -1)
            $fechamento = $fechamento->where('id', $contafechamento_id)->get()->first();
        else
            $fechamento = $fechamento->where('fechado', 0)->get()->first();
        return $fechamento;
    }

    /**
     * faz a consulta no banco
     * @param int $conta_id
     * @param $fechamento
     * @return OracleBuilder
     */
    private function getMovimentosDB($conta_id, $fechamento)
    {
        return DB::table('contamovimentos as mov')
            ->leftJoin('contatransferencias as tra', 'mov.contatransferencia_id', 'tra.id')
            ->leftJoin('contamovimentotipos as tipos', 'mov.contamovimentotipo_id', 'tipos.id')
            ->leftJoin('financeiroparcelas as par', function ($join) {
                $join->on('mov.financeiroparcela_id', 'par.id')->where('agrupamento_status', '<=', "1");
            })
            ->leftJoin('financeiros as fin', 'par.financeiro_id', 'fin.id')
            ->leftJoin('clientes as cli', 'fin.cliente_id', 'cli.id')
            ->where('mov.conta_id', $conta_id)
            ->where('mov.contafechamento_id', $fechamento->id);
    }

    /**
     * Faz as validações das movimentações e retorna a view
     * @param OracleBuilder $movimentosDB
     * @param Collection $fechamento
     * @param Collection $conta
     * @param int $countTotal
     * @return $this->viewMovtos()
     */
    private function validateMovtosCaixa($movimentosDB, $fechamento, $conta, $countTotal, $date)
    {
        $resultsPerPage = 100;

        $permissao = \Auth::user()->contas()->with('conta')->where('conta_id', '=', $conta->id)->get()->first();
        $estorno = !is_null($permissao) ? $permissao->estornar == 1 : false;

        $btnBase = '<button class="btn btn-nw-registro btn-xs" id="btnEstornar" onclick="parent.abrirTelaEstornar(:pars)">Estornar</button>';

        $movs = clone $movimentosDB;
        $movs = $movs->selectRaw($this->getSelectSearchMovtos())
            ->where('mov.datahorabaixa', '<=', $date . ':59')
            ->orderBy('mov.datahorabaixa', 'asc')->get();
        foreach ($movs as $mov) {
            $hasTransf = !is_null($mov->contatransferencia_id);
            $hasParc = !is_null($mov->financeiroparcela_id);
            if ($hasTransf || $hasParc) {
                $botoes = str_replace(":pars", "'" . $mov->id . "', '" . $mov->origem . "'", $btnBase);
                $estornoAllowed = ($hasParc || ($hasTransf && $mov->pagarreceber === 'S')) && $estorno && $mov->origem !== "EST";
                $mov->botoes = $estornoAllowed ? $botoes : "";
            }
        }

        $extraTotalPages = $countTotal % $resultsPerPage;
        $totalPages = (int) ($countTotal / $resultsPerPage) + ($extraTotalPages > 0 ? 1 : 0);

        //-1 pra pegar a index do array;
        $page = (int) e(Input::get('page', 0)) - 1;

        if ($page < 0)
            $page = 0;

        if ($totalPages <= 0)
            $totalPages = 1;

        $movs = collect(paginate($movs, $page, $resultsPerPage));

        $init = $this->getExtraValuesMovtos($fechamento, $conta);
        $movs->prepend($init);

        if ($page !== 0)
            $movs->shift();

        $selectRaw = "SUM(round(CASE WHEN mov.PAGARRECEBER = 'R' THEN mov.valorefetivado * 1 ELSE mov.valorefetivado * -1 end, 2)) as valorefetivado";
        $total = requestNumeroDecimalOracle(insertNumeroDecimalOracle($init->valorefetivado) + $movimentosDB->whereRaw('(FINANCEIROPARCELA_ID IS NOT NULL OR CONTATRANSFERENCIA_ID IS NOT NULL)')
            ->selectRaw($selectRaw)->first()->valorefetivado);

        $last = $this->getExtraValuesMovtos($fechamento, $conta, 'last');

        $valorCheques = requestNumeroDecimalOracle(DB::table('chequerecebidos')
            ->where('depositoconta_id', $conta->id)->whereIn("chequesituacao_id", [5, 7])
            ->selectRaw('SUM(valor) AS valor')->sum('valor'));

        $valorEfetivado = $last->valorefetivado;
        $last->valorefetivado = $total;
        if ($page + 1 === $totalPages)
            $movs->push($last);

        $msg = '';
        if ($valorEfetivado !== $total)
            $msg = "Pode haver uma inconsistencia no banco de dados, contate o suporte.";

        return $this->viewMovtos($movs, $fechamento, $conta, $valorCheques, $page + 1, $totalPages, $msg);
    }

    /**
     * retorna a view com os valores passados por parâmetro
     * @param Array $data
     * @param Array $fechamento
     * @param Array $conta
     * @param double $valorCheques
     * @param int $page
     * @param int $totalPages
     * @return \Illuminate\Http\Response
     */
    private function viewMovtos($data = [], $fechamento = [], $conta = [], $valorCheques = 0, $page = 1, $totalPages = 1, $msg = "")
    {
        $data = collect((object) [
            'data'         => $data,
            'fechamento'   => $fechamento,
            'conta'        => $conta,
            'valorcheques' => $valorCheques,
            'atualPage'    => $page,
            'totalPages'   => $totalPages,
            'msg'          => $msg
        ])->toJson();
        return View("financeiro.partials.iframe_table", compact('data'));
    }

    /**
     * retorna os arrays extras (saldo inicial e final) da tela de movimentação do caixa
     * @param Collection $fechamento
     * @param Collection $conta
     * @param string $type
     * @return array
     */
    private function getExtraValuesMovtos($fechamento, $conta, $type = 'first')
    {
        $issetFechamento = e(Input::get('issetFechamento', '')) === 'true';
        if ($type === 'first') {
            $arr = [
                'id'                 => '',
                'datahoraabertura'   => Carbon::parse($fechamento->datahoraabertura)->format('d/m/Y H:i'),
                'descricao'          => 'Saldo Inicial (Abertura)',
                'cliente'            => '',
                'valorefetivado'     => requestNumeroDecimalOracle($fechamento->saldoinicial),
                'pagarreceber'       => '',
                'datavencimento'     => '',
                'contamovimentotipo' => '',
                'botoes'             => '',
                'origem'             => '',
            ];
        } else {
            $saldo = requestNumeroDecimalOracle($issetFechamento ? $fechamento->saldofinal : $conta->saldoatual);
            $arr = [
                'id'                 => '',
                'datahoraabertura'   => Carbon::parse($fechamento->datahorafechamento)->format('d/m/Y H:i'),
                'descricao'          => 'Saldo Atual',
                'cliente'            => '',
                'valorefetivado'     => $saldo,
                'pagarreceber'       => '',
                'datavencimento'     => '',
                'contamovimentotipo' => '',
                'botoes'             => '',
                'origem'             => '',
            ];
        }
        return (object) $arr;
    }

    /**
     * retorna a string usada no select de movimentos
     * @return string
     */
    private function getSelectSearchMovtos()
    {
        return "mov.id, TO_CHAR(mov.datahorabaixa, 'DD/MM/YYYY HH24:MI') AS datahoraabertura, "
            . "mov.descricao, cli.nome AS cliente, "
            . "'R$ ' || TO_CHAR(mov.valorefetivado, '9G999G999G999G999G999G999G990D09', 'NLS_NUMERIC_CHARACTERS = '',.''') AS valorefetivado, "
            . "CASE WHEN mov.pagarreceber = 'R' THEN 'E' ELSE 'S' END as pagarreceber, "
            . "TO_CHAR(par.datavencimento, 'DD/MM/YYYY') AS datavencimento, "
            . "tipos.descricao as contamovimentotipo, mov.origem, mov.contatransferencia_id, mov.financeiroparcela_id";
    }

    public function indexclientes()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));
        if (!$query && $query == '') {
            return response()->json(array(), 400);
        }

        $raw = "(" . rawTranslateSpecialChars("nome") . " LIKE '%$query%'"
            . " OR " . rawTranslateSpecialChars("fantasia") . " LIKE '%$query%'" . ")" .
            " AND cliente = 1" .
            " AND empresa_id = " . Session::get('empresa_padrao')->id;

        $clientes = Cliente::whereRaw($raw)
            ->orderBy('nome', 'asc')
            ->take(8)
            ->get(array('id', 'nome'))->toArray();

        return response()->json(array(
            'data' => $this->appendValue($clientes, 'cliente', 'class')
        ));
    }

    public function indexfornecedores()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $clientes = Cliente::where('empresa_id', Session::get('empresa_padrao')->id)
            ->whereRaw(rawTranslateSpecialChars("nome") . " LIKE '%$query%' AND (FORNECEDOR = 1 OR TRANSPORTADOR = 1)")
            ->orderBy('nome', 'asc')
            ->take(10)
            ->get(array('id', 'nome'))->toArray();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');

        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function clientespf()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $clientes = Cliente::where('empresa_id', Session::get('empresa_padrao')->id)
            ->where([
                ['clientes.ativo', true],
                ['clientes.cliente', 1]
            ])
            ->where('tipopessoas.tipopessoacadastro', 'F')
            ->whereRaw(rawTranslateSpecialChars("nome") . " LIKE '%$query%'")
            ->orderBy('clientes.nome', 'asc')
            ->join('tipopessoas', 'clientes.tipopessoa_id', '=', 'tipopessoas.id')
            ->take(8)
            ->get(array('clientes.id', 'clientes.nome', 'clientes.cpf'))->toArray();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');
        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function clientespj()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $clientes = Cliente::where('empresa_id', Session::get('empresa_padrao')->id)
            ->where([
                ['clientes.ativo', true],
                ['clientes.cliente', 1]
            ])
            ->where('tipopessoas.tipopessoacadastro', 'J')
            ->whereRaw(rawTranslateSpecialChars("nome") . " LIKE '%$query%'")
            ->orderBy('clientes.nome', 'asc')
            ->join('tipopessoas', 'clientes.tipopessoa_id', '=', 'tipopessoas.id')
            ->take(8)
            ->get(array('clientes.id', 'clientes.nome', 'clientes.cnpj'))->toArray();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');
        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function fornecedorespj()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $clientes = Cliente::where('empresa_id', Session::get('empresa_padrao')->id)
            ->where([
                ['clientes.ativo', true],
                ['clientes.fornecedor', 1]
            ])
            ->whereRaw(rawTranslateSpecialChars("nome") . " LIKE '%$query%'")
            ->orderBy('clientes.nome', 'asc')
            ->take(8)
            ->get(array('clientes.id', 'clientes.nome', 'clientes.cnpj'))->toArray();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');
        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function indexcolaboradores()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $colaboradors = Colaborador::where('empresa_id', Session::get('empresa_padrao')->id)
            ->whereRaw(rawTranslateSpecialChars("colaboradors.nome") . " LIKE '%$query%'")
            ->orderBy('nome', 'asc')
            ->take(8)
            ->get(array('id', 'nome'))->toArray();

        // Add type of data to each item of each set of results
        $colaboradors = $this->appendValue($colaboradors, 'colaborador', 'class');

        return response()->json(array(
            'data' => $colaboradors
        ));
    }

    public function searchCondicaoPagamento()
    {
        $query = (int) e(Input::get('q', ''));

        if ($query === 0)
            return response()->json(array(), 400);

        $Condicao = Condicaopagamento::with('condicaoPagamentoParcela')->find($query);
        return ($Condicao);
    }

    public function searchRua($cidade_id)
    {
        $query = str_encode_to_query(e(Input::get('q', '')));
        if (!$query && $query == '')
            return \response()->json(array(), 400);
        DB::raw("alter session set nls_comp='linguistic'");
        DB::raw("alter session set nls_sort='binary_ai'");
        $raw = rawTranslateSpecialChars("descricao") . " LIKE '%$query%'" .
            " AND grupo_id = " . Session::get("empresa_padrao")->grupo_id .
            " AND ativo = 1";
        $ruas = Rua::where('cidade_id', $cidade_id)
            ->whereRaw($raw)
            ->take(10)
            ->get(array('id', 'descricao'))->toArray();

        return response()->json(array(
            'data' => $ruas
        ));
    }

    public function searchColaboradorProduto($setor_id)
    {
        $colaboradores = Setorcolaboradores::where([
            ['setors.ativo', 1],
            ['setors.id', $setor_id]
        ])
            ->join('setors', 'setors.id', '=', 'setorcolaboradores.setor_id')
            ->join('colaboradors', 'colaboradors.id', '=', 'setorcolaboradores.colaborador_id')
            ->orderby('colaboradors.nome')
            ->get(['colaboradors.id', 'colaboradors.nome']);

        $produtos = Estoquesetor::join('produtos', 'estoquesetors.produto_id', '=', 'produtos.id')
            ->join('setors', 'estoquesetors.setor_id', '=', 'setors.id')
            ->where([
                ['estoquesetors.setor_id', $setor_id],
                ['setors.ativo', 1],
                ['produtos.ativo', 1]
            ])->orderby('produtos.descricao')
            ->select(['produtos.descricao', 'produtos.id', 'produtos.precovenda', 'produtos.precovendaminimo'])
            ->get();
        return ['colaboradores' => $colaboradores, 'produtos' => $produtos];
    }

    public function searchDadosPedidoEmpresa($empresa_id, $controller = false)
    {
        $config = Empresaconfig::where('empresa_id', $empresa_id)->first();
        if (isset($config->senhamestre) and $config->senhamestre != null)
            unset($config->senhamestre);
        if ($controller) {
            return !is_null($config) ? $config : false;
        }
        if (!is_null($config)) {
            $condicoesPagamento = Condicaopagamento::where([
                ['ativo', 1],
                ['grupo_id', $config->grupo_id]
            ])->orderby('descricao')->get(['num_parcelas', 'id', 'tipo', 'descricao']);
            $condicaoPagamentoConfig = [];
            if (isset($config->maximoparcelas)) {
                for ($i = 0; $i < count($condicoesPagamento); $i++) {
                    if ($condicoesPagamento[$i]->num_parcelas <= $config->maximoparcelas) {
                        $id = $condicoesPagamento[$i]->id;
                        $condicaoPagamentoConfig[]['id_tipo'] = $id . '-' . $condicoesPagamento[$i]->tipo;
                        $condicaoPagamentoConfig[]['descricao'] = $condicoesPagamento[$i]->descricao;
                    }
                }
            } else {
                foreach ($condicoesPagamento as $condicaoPgto) {
                    $id = $condicaoPgto->id;
                    $condicaoPagamentoConfig[]['id_tipo'] = $id . '-' . $condicaoPgto->tipo;
                    $condicaoPagamentoConfig[]['descricao'] = $condicaoPgto->descricao;
                }
            }
            $cep = Empresa::find($config->empresa_id)->cep;

            $setores = Setor::where([
                ['ativo', 1],
                ['empresa_id', $config->empresa_id]
            ])->orderBy('descricao')->get(['id', 'descricao']);

            $produtos = Produto::where([
                ['ativo', 1],
                ['empresa_id', $config->empresa_id]
            ])->orderBy('descricao')->get(['id', 'descricao']);

            $statusPadrao = isset($config->pedidostatuspadrao) ? $config->pedidostatuspadrao : '';
            $operacaoPadrao = isset($config->operacaodisk) ? $config->operacaodisk : '';
            return [
                'config'             => $config,
                'condicao_pagamento' => $condicaoPagamentoConfig,
                'setores'            => $setores,
                'cep'                => $cep,
                'status_padrao'      => $statusPadrao,
                'operacao_padrao'    => $operacaoPadrao,
                'produtos'           => $produtos
            ];
        } else {
            return 'As configurações da empresa não foram encontradas!';
        }
    }

    public function searchClientePedido()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $raw = "(" . rawTranslateSpecialChars("nome") . " LIKE '%$query%' OR ";
        $raw .= rawTranslateSpecialChars("fantasia") . " LIKE '%$query%'" . ")";
        $raw .= " AND ativo = 1 AND empresa_id IN (SELECT empresa_id FROM empresa_user where user_id = " . \Auth::user()->id . ")";
        $clientes = Cliente::with('rua', 'bairro', 'cidade')
            ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)
            ->whereRaw($raw)
            ->orderBy('nome', 'asc')
            ->select('nome', 'id', 'rua_id', 'bairro_id', 'cidade_id', 'numero')
            ->take(8)
            ->get();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');

        return response()->json(array(
            'data' => $clientes->toArray()
        ));
    }

    public function searchClientePedidoConvenio()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        // $raw = "(" . rawTranslateSpecialChars("nome") . " LIKE '%$query%' OR ";
        // $raw .= rawTranslateSpecialChars("fantasia") . " LIKE '%$query%'" . ")";
        $raw = " ativo = 1 AND empresa_id IN (SELECT empresa_id FROM empresa_user where user_id = " . \Auth::user()->id . ")";
        $clientes = Cliente::with('rua', 'bairro', 'cidade')
            ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)
            ->whereRaw($raw)
            ->where(DB::raw("REGEXP_REPLACE(cpf, '[^0-9]', '')"), 'LIKE', "{$query}%")
            ->orderBy('nome', 'asc')
            ->select('nome', 'id', 'cpf', 'rua_id', 'bairro_id', 'cidade_id', 'numero')
            ->take(8)
            ->get();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');

        return response()->json(array(
            'data' => $clientes->toArray()
        ));
    }

    /**
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function searchClienteNF()
    {
        $mode = Input::get('mode', "E");
        $rawSelect = "nome || '||' || fantasia as nomecompleto, "
            . " CASE WHEN fantasia IS NOT NULL "
            . "     THEN nome || ' - ' || fantasia"
            . "     ELSE nome"
            . " END as nome, "
            . " id";
        if ($mode == "C") {
            // rownum=1 removido: a busca é por PK (c.id/r.id/b.id), já retorna 1 linha (PG).
            $rawSelect .= ", cnpj, (SELECT descricao FROM cidades c WHERE cidade_id = c.id) as cidade";
            $rawSelect .= ", cpf, (SELECT descricao FROM ruas r WHERE rua_id = r.id) as rua";
            $rawSelect .= ", complemento, (SELECT descricao FROM bairros b WHERE bairro_id = b.id) as bairro";
            $rawSelect .= ", numero, uf ";
        }
        $query = str_encode_to_query(e(Input::get('q', '')));

        $getBy = Input::get("get_by", "name");
        if ($getBy == "id") {
            return response()->json([
                'data' => Cliente::selectRaw($rawSelect)->where("id", $query)->get()
            ]);
        } else {

            if (!$query && $query == '')
                return response()->json(array(), 400);

            $clientefornecedor = e(Input::get('clientefornecedor', ''));
            $empresa_id = (int) Input::get('empresa_id', 0);
            if ($mode === "R" || $mode === "C") {
                $empresa_id = Session::get("empresa_padrao")->id;
            }

            if ($empresa_id === 0)
                return "Não foi possível encontrar a empresa";

            $rawTipoCliente = "1 = 1";
            if ($clientefornecedor == 'C') {
                $rawTipoCliente = "cliente = 1";
            } else if ($clientefornecedor == 'F') {
                $rawTipoCliente = "fornecedor = 1";
            }
            $raw = "(" . rawTranslateSpecialChars("nome") . " LIKE '%$query%' " . " "
                . "OR " . rawTranslateSpecialChars("fantasia") . " LIKE '%$query%') "
                . " AND " . $rawTipoCliente . " AND nfemite = 1 AND ativo = 1 AND empresa_id = " . $empresa_id;

            $clientes = DB::table('clientes')->orderBy('nome', 'asc')
                ->whereRaw($raw)->selectRaw($rawSelect)->take(8)->get()->toArray();
            return response()->json([
                'data' => $this->appendValue($clientes, 'cliente', 'class')
            ]);
        }
    }

    public function searchEmpresaNF()
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $empresas = Empresa::with('rua', 'bairro', 'cidade')
            ->whereRaw(rawTranslateSpecialChars("razao_social") . " LIKE '%$query%' "
                . " AND (nfceemite = 1 OR nfemite = 1)")
            ->orderBy('razao_social', 'asc')
            ->take(8)
            ->get();
        $empresas = $this->appendValue($empresas, 'empresa', 'class');
        return response()->json(array(
            'data' => $empresas
        ));
    }

    public function searchTelefonesMonitoramento()
    {
        try {

            $user = \Auth::user();
            $query = "select ep.id " .
                "from menuusers mu " .
                "inner join empresa_user eu on eu.user_id = mu.user_id and eu.empresa_id = mu.empresa_id " .
                "inner join menus me on mu.menu_id = me.id " .
                "inner join empresas ep on mu.empresa_id = ep.id and eu.empresa_id = ep.id " .
                "where mu.user_id = $user->id and me.descricao like 'pedido.index' and mu.visualizar = 1 " .
                "order by nome_informal";

            $empresas = collect(DB::select($query))->pluck('id')->toArray();
            $calls = Monitoramentochamadas::whereIn('empresa_id', $empresas)->get()->pluck('telefone')->toArray();
            $countCalls = count($calls);
            if (!$countCalls) {
                return $calls;
            }
            if ($countCalls > 100) {
                throw new \Exception("Há muitas chamadas em espera no servidor e isso pode gerar uma sobrecarga, " .
                    "entre em contato com o suporte para que as chamadas muito antigas sejam apagadas.");
            }
            $empresas = implode(', ', $empresas);
            $tels = "'";
            $i = 0;
            foreach ($calls as $c) {
                $i++;
                $tels .= $c . "'";
                if ($i < $countCalls) {
                    $tels .= ", '";
                }
            }
            $raw = '(m.empresa_id IN (' . $empresas . ')) AND (c.telefone IN (' . $tels . ') OR c.telefone IS NULL)';
            $rawSel = 'm.telefone, e.nome_informal, '
                . ' CASE WHEN c.empresa_id IS NOT NULL THEN c.empresa_id ELSE m.empresa_id END'
                . ' AS empresa_id, m.id';
            $clienteTel = DB::table('monitoramentochamadas as m')
                ->leftJoin('clientetelefones as c', 'c.telefone', 'm.telefone')
                ->leftJoin('empresas as e', 'e.id', 'm.empresa_id')
                ->whereRaw($raw)
                ->selectRaw($rawSel)
                ->orderBy('m.id')
                ->take(10)
                ->get();

            return $clienteTel;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function searchContasReceber($cliente_id)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        if (is_null(Cliente::where('id', $cliente_id)->where([['cliente', 1], ['fornecedor', 1], ['empresa_id', $empresa_id]])->get()->first()))
            return '404';

        $encontrocontas = DB::table('chequeemitidoencontrocontas')
            ->join('financeiroparcelas', 'chequeemitidoencontrocontas.financeiroparcela_id', 'financeiroparcelas.id')
            ->join('financeiros', 'financeiroparcelas.financeiro_id', 'financeiros.id')
            ->where('financeiros.cliente_id', $cliente_id)->select('financeiroparcelas.id')->pluck('id', 'id')->toArray();

        $chequefinanceiroparcelas_id = Chequerecebidofinanceiro::join('financeiroparcelas', 'chequerecebidofinanceiros.financeiroparcela_id', 'financeiroparcelas.id')
            ->join('financeiros', 'financeiroparcelas.financeiro_id', 'financeiros.id')
            ->where([
                ['financeiros.cliente_id', $cliente_id],
                ['financeiroparcelas.pagarreceber', "R"],
                ['financeiroparcelas.empresa_id', $empresa_id],
                ['financeiroparcelas.baixado', 0],
            ])->select('financeiroparcelas.id')->distinct()
            ->pluck('financeiroparcelas.id')
            ->toArray();

        $financeiro = Financeiroparcela::join('financeiros', 'financeiroparcelas.financeiro_id', 'financeiros.id')
            ->whereNotIn('financeiroparcelas.id', $encontrocontas)
            ->where([
                ['financeiros.cliente_id', $cliente_id],
                ['financeiroparcelas.pagarreceber', "R"],
                ['financeiros.empresa_id', $empresa_id],
                ['financeiroparcelas.baixado', 0],
                ['financeiroparcelas.agrupamento_status', '!=', 3]
            ])->whereNotIn('financeiroparcelas.id', $chequefinanceiroparcelas_id)
            ->select('financeiros.cliente_id', 'financeiroparcelas.pagarreceber', 'financeiroparcelas.valor', 'financeiroparcelas.id as parcela_id', 'financeiros.dataemissao', 'financeiroparcelas.datavencimento')->get();

        return $financeiro->toArray();
    }

    public function searchContasPagar($cliente_id)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        if (is_null(Cliente::where('id', $cliente_id)->where([['cliente', 1], ['fornecedor', 1], ['empresa_id', $empresa_id]])->get()->first()))
            return '404';

        $encontrocontas = DB::table('chequerecebidoencontrocontas')
            ->join('financeiroparcelas', 'chequerecebidoencontrocontas.financeiroparcela_id', 'financeiroparcelas.id')
            ->join('financeiros', 'financeiroparcelas.financeiro_id', 'financeiros.id')
            ->where('financeiros.cliente_id', $cliente_id)->select('financeiroparcelas.id')->pluck('id', 'id')->toArray();

        $chequefinanceiroparcelas_id = Chequeemitidofinanceiro::join('financeiroparcelas', 'chequeemitidofinanceiros.financeiroparcela_id', 'financeiroparcelas.id')
            ->join('financeiros', 'financeiroparcelas.financeiro_id', 'financeiros.id')
            ->where([
                ['financeiros.cliente_id', $cliente_id],
                ['financeiroparcelas.pagarreceber', "P"],
                ['financeiroparcelas.empresa_id', $empresa_id],
                ['financeiroparcelas.baixado', 0],
            ])->select('financeiroparcelas.id')->distinct()
            ->pluck('financeiroparcelas.id')
            ->toArray();

        $financeiro = Financeiroparcela::join('financeiros', 'financeiroparcelas.financeiro_id', 'financeiros.id')
            ->whereNotIn('financeiroparcelas.id', $encontrocontas)
            ->where([
                ['financeiros.cliente_id', $cliente_id],
                ['financeiroparcelas.pagarreceber', "P"],
                ['financeiros.empresa_id', $empresa_id],
                ['financeiroparcelas.baixado', 0],
                ['financeiroparcelas.agrupamento_status', '!=', 3]
            ])->whereNotIn('financeiroparcelas.id', $chequefinanceiroparcelas_id)
            ->select('financeiros.cliente_id', 'financeiroparcelas.pagarreceber', 'financeiroparcelas.valor', 'financeiroparcelas.id as parcela_id', 'financeiros.dataemissao', 'financeiroparcelas.datavencimento')->get();

        return $financeiro->toArray();
    }

    public function searchClientesComodato($tipo)
    {
        $clientes = DB::table('clientes')->rightJoin('comodatos', 'clientes.id', 'comodatos.cliente_id')
            ->join('tipopessoas', 'tipopessoas.id', 'clientes.tipopessoa_id')
            ->where([
                ['clientes.ativo', 1],
                ['comodatos.ativo', 1],
                ['clientes.empresa_id', Session::get('empresa_padrao')->id],
                ['comodatos.tipo', $tipo]
            ]);

        if ($tipo < 2)
            $clientes = $clientes->where('tipopessoas.tipopessoacadastro', $tipo == 0 ? "J" : "F");
        else
            $clientes = $clientes->where('clientes.fornecedor', 1);

        $clientes = $clientes->select('clientes.id', 'clientes.nome')->groupBy('clientes.id', 'clientes.nome')->orderby('clientes.nome')->get();
        return $clientes;
    }

    public function searchFornecedoresEmpresaUser($empresas_id)
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $empresas_id = $empresas_id == 0 ? User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray() : explode(',', $empresas_id);
        $clientes = DB::table('clientes')
            ->join('empresas', 'empresas.id', 'clientes.empresa_id')
            ->where([
                ['clientes.ativo', 1],
                ['fornecedor', 1]
            ])
            ->whereRaw(rawTranslateSpecialChars("clientes.nome") . " LIKE '%$query%'")
            ->whereIn('empresa_id', $empresas_id)
            ->select('clientes.id as id', 'clientes.nome as nome', 'empresas.nome_informal')
            ->orderBy('clientes.nome', 'asc')
            ->take(8)
            ->get();
        // return $clientes;
        // $clientes = $this->appendValue($clientes, 'cliente', 'class');
        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function searchClientesEmpresaUser($empresas_id)
    {
        $query = str_encode_to_query(e(Input::get('q', '')));

        if (!$query && $query == '')
            return response()->json(array(), 400);
        $empresas_id = $empresas_id == 0 ? User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray() : explode(',', $empresas_id);
        $clientes = DB::table('clientes')
            ->join('empresas', 'empresas.id', 'clientes.empresa_id')
            ->where([
                ['clientes.ativo', 1],
                ['cliente', 1]
            ])
            ->whereRaw(rawTranslateSpecialChars("nome") . " LIKE '%$query%'")
            ->whereIn('empresa_id', $empresas_id)
            ->select('clientes.id as id', 'clientes.nome as nome', 'empresas.nome_informal')
            ->orderBy('nome', 'asc')
            ->take(8)
            ->get();
        // return $clientes;
        // $clientes = $this->appendValue($clientes, 'cliente', 'class');
        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function contasByEmpresa($empresa_id)
    {
        return \Auth::user()->contas()
            ->where('visualizar', '=', 1)
            ->where('empresa_id', $empresa_id)
            ->join('contas as conta', 'conta.id', 'contausers.conta_id')->select('conta.descricao', 'conta.id')->get()->toArray();
    }

    public function getSetor()
    {
        $filtro = $_GET;
        $empresa_id = $filtro["empresa"];
        $tipo = $filtro["tipo"];
        $setores = Setor::where(['ativo' => 1, 'empresa_id' => $empresa_id])->select('descricao', 'id')->orderBy('descricao')->get();
        return $setores;
    }

    public function searchPedidosMapaEntregas()
    {
        $datainicio = Carbon::createFromFormat('d/m/Y', $_GET['datainicio'])->startOfDay();
        $datafim = Carbon::createFromFormat('d/m/Y', $_GET['datafim'])->endOfDay();
        $setor_id = $_GET['setor_id'];
        $pedidos = DB::table('pedidos as pedido')
            ->join('clientes as cliente', 'cliente.id', 'pedido.cliente_id')
            ->join('bairros', 'bairros.id', 'pedido.entregabairro_id')
            ->join('ruas', 'ruas.id', 'pedido.entregarua_id')
            ->whereRaw("pedidosituacao_id IN (SELECT id FROM pedidosituacaos WHERE grupo_id = " . Session::get('empresa_padrao')->grupo_id . " AND (fechadoconcluido = 1 or entregafinalizada = 1) )")
            ->whereBetween("datahoraenvioentregador", [$datainicio, $datafim])
            ->where('pedido.empresa_id', Session::get('empresa_padrao')->id)
            ->selectRaw(
                "pedido.id, pedido.latitude as entregalatitude, " .
                    "pedido.longitude as entregalongitude, cliente.nome, " .
                    "pedido.latitude || pedido.longitude as latlng, (ruas.descricao || ', ' || pedido.entreganumero) as endereco, " .
                    "round((entregadatahora - datahoraenvioentregador) * 24 * 60) as tempoentrega, " .
                    "TO_CHAR(entregadatahora, 'dd/mm/yyyy hh24:mi:ss') as entrega, " .
                    "TO_CHAR(datahoraenvioentregador, 'dd/mm/yyyy hh24:mi:ss') as previsao"
            )->orderby('latlng');

        if ($setor_id > 0)
            $pedidos->where('entregasetor_id', $setor_id);

        return $pedidos->get()->toArray();
    }

    public function searchPedidosPendentesMapaEntregas()
    {
        $pedidos = DB::table('pedidos as pedido')
            ->join('clientes as cliente', 'cliente.id', 'pedido.cliente_id')
            ->join('bairros', 'bairros.id', 'pedido.entregabairro_id')
            ->join('ruas', 'ruas.id', 'pedido.entregarua_id')
            ->join('setors as setor', 'setor.id', 'pedido.entregasetor_id')
            ->join('colaboradors as colaborador', 'colaborador.id', 'pedido.colaborador_id')
            ->whereRaw("pedidosituacao_id IN "
                . " (SELECT id FROM pedidosituacaos WHERE fechadoconcluido <> 1 and fechadocancelado <> 1 and entregafinalizada <> 1 and entregacancelada <> 1 and"
                . " grupo_id = " . Session::get('empresa_padrao')->grupo_id . ")")
            ->where('pedido.empresa_id', Session::get('empresa_padrao')->id)
            ->where('datahoraenvioentregador', '<>', null)
            ->select(
                'pedido.id',
                'pedido.latitude',
                'pedido.longitude',
                'entregaurgente',
                'cliente.nome',
                DB::raw("pedido.latitude || pedido.longitude as latlng"),
                DB::raw("setor.descricao || ' - ' || colaborador.nome as setorcolaborador"),
                DB::raw("(ruas.descricao || ', ' || pedido.entreganumero || ' - ' || bairros.descricao) as endereco"),
                DB::raw("TO_CHAR(datahoraenvioentregador, 'dd/mm/yyyy hh24:mi:ss') as previsao")
            )->orderby('latlng')->get()->toArray();
        $pedidosNew = [];
        foreach ($pedidos as $pedido) {
            $config = Session::get('empresa_config');
            $pedido->tempo = Carbon::now()->diffInSeconds(Carbon::createFromFormat('d/m/Y H:i:s', $pedido->previsao));
            $tempoEntregaConfig = $pedido->entregaurgente == '1' ? $config->tempourgente * 60 : $config->tempoentrega * 60;
            if ($pedido->tempo <= $tempoEntregaConfig - ($tempoEntregaConfig * 0.2))
                $pedido->atraso = 0;
            else if ($pedido->tempo >= $tempoEntregaConfig + ($tempoEntregaConfig * 0.2))
                $pedido->atraso = 2;
            else
                $pedido->atraso = 1;
            $pedido->tempo = round($pedido->tempo / 60) . ' min';
            $pedido->entregaurgente = $pedido->entregaurgente ? "Sim" : "Não";
            array_push($pedidosNew, $pedido);
        }
        return $pedidosNew;
    }

    public function searchPedidosMapaEntregasCoordenadas()
    {
        $data = Carbon::createFromFormat('d/m/Y', $_GET['data']);
        $pedidos = DB::table('pedidos as pedido')
            ->join('clientes as cliente', 'cliente.id', 'pedido.cliente_id')
            ->join('bairros', 'bairros.id', 'pedido.entregabairro_id')
            ->join('ruas', 'ruas.id', 'pedido.entregarua_id')
            ->whereBetween('pedido.entregadatahora', [$data->copy()->startOfDay()->toDateTimeString(), $data->copy()->endOfDay()->toDateTimeString()])
            ->whereRaw("(pedidosituacao_id IN (SELECT id FROM "
                . "pedidosituacaos WHERE grupo_id = "
                . Session::get('empresa_padrao')->grupo_id . " AND (fechadoconcluido = 1 or entregafinalizada = 1)))")
            ->where('pedido.empresa_id', Session::get('empresa_padrao')->id)
            ->selectRaw(
                "pedido.id, pedido.latitude, pedido.longitude, entregaurgente, "
                    . " cliente.nome, (case when entregalatitude = 0 and entregalongitude = 0 then null else entregalatitude end) as entregalatitude, "
                    . " (case when entregalatitude = 0 and entregalongitude = 0 then null else entregalongitude end) as entregalongitude, "
                    . " (case when entregalatitude = 0 and entregalongitude = 0 then null else pedido.entregalatitude || pedido.entregalongitude end) as latlng, "
                    . " entregadatahora as entrega, "
                    . " (ruas.descricao || ', ' || pedido.entreganumero || ' - ' || bairros.descricao) as endereco, "
                    . " TO_CHAR(datahoraenvioentregador, 'dd/mm/yyyy hh24:mi:ss') as previsao"
            )->orderby('latlng')->get()->toArray();
        foreach ($pedidos as $pedido) {
            $pedido->distancia = distanciaCoord($pedido->latitude, $pedido->longitude, $pedido->entregalatitude, $pedido->entregalongitude);
        }

        return $pedidos;
    }

    public function searchPedidosMapaEntregasAtrasadas()
    {
        $data = Carbon::createFromFormat('d/m/Y', $_GET['data']);
        $pedidos = DB::table('pedidos as pedido')
            ->join('clientes as cliente', 'cliente.id', 'pedido.cliente_id')
            ->join('bairros', 'bairros.id', 'pedido.entregabairro_id')
            ->join('ruas', 'ruas.id', 'pedido.entregarua_id')
            ->leftJoin('pedidomotivoatrasos as atraso', 'atraso.id', 'pedido.pedidomotivoatraso_id')
            ->whereRaw("pedidosituacao_id IN (SELECT id FROM pedidosituacaos WHERE grupo_id = " . Session::get('empresa_padrao')->grupo_id . " AND (fechadoconcluido = 1 OR entregafinalizada = 1))")
            ->whereBetween('pedido.entregadatahora', [$data->startOfDay()->toDateTimeString(), $data->copy()->endOfDay()->toDateTimeString()])
            ->where('pedido.empresa_id', Session::get('empresa_padrao')->id)
            ->selectRaw(
                "pedido.id, pedido.latitude as entregalatitude, entregaurgente, " .
                    "pedido.longitude as entregalongitude, cliente.nome, " .
                    "CASE WHEN atraso.id IS NOT NULL THEN atraso.id ELSE -1 END AS atraso_id, " .
                    "atraso.descricao as atraso, pedido.latitude || pedido.longitude as latlng, " .
                    "(ruas.descricao || ', ' || pedido.entreganumero || ' - ' || bairros.descricao) as endereco, " .
                    "round((entregadatahora - datahoraenvioentregador) * 24 * 60) as tempoentrega, " .
                    "TO_CHAR(entregadatahora, 'dd/mm/yyyy hh24:mi:ss') as entrega, " .
                    "TO_CHAR(datahoraenvioentregador, 'dd/mm/yyyy hh24:mi:ss') as previsao"
            );

        if ((int) Input::get('somenteatrasadas', 0)) {
            $pedidos = $pedidos->whereRaw('atraso.id is not null');
        }

        $pedidos = $pedidos->orderby('latlng')->get();

        $pedidoAnt = null;
        $pedidosAtrasosDiff = collect([]);

        $pedidosU = $pedidos->unique('atraso_id')->sortBy('atraso_id');
        foreach ($pedidosU as $pedido) {
            $pedidosAtrasosDiff->push($pedido);
        }

        return [
            'mapa'               => $pedidos->toArray(),
            'chart'              => $pedidos->sortBy('atraso_id'),
            'pedidosAtrasosDiff' => $pedidosAtrasosDiff->toArray()
        ];
    }

    public function searchPedidosTempoEntregas()
    {
        $year = $this->getPedidosTempoEntregas($_GET);
        $month = $this->getPedidosTempoEntregas($_GET, "M");
        return compact('year', 'month');
    }

    private function getPedidosTempoEntregas($pars, $type = 'Y')
    {

        $year = $pars['year'];
        $month = Carbon::today()->month;

        if (strlen($month) == 1)
            $month = "0$month";

        $empresa = Session::get('empresa_padrao');
        $selPedsit = "SELECT id FROM pedidosituacaos WHERE (fechadoconcluido = 1 or entregafinalizada = 1) AND grupo_id = " . $empresa->grupo_id;

        $rawTempoEntrega = "round((entregadatahora - datahoraenvioentregador) * 24 * 60)";

        $select = "SELECT PEDIDO.ID, setor.id as setor_id, SETOR.DESCRICAO as SETOR, "
            . "  CASE WHEN $rawTempoEntrega < 15 "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END AS tempo1, "
            . "  CASE WHEN $rawTempoEntrega < 30 AND $rawTempoEntrega >= 15 "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END AS tempo2, "
            . "  CASE WHEN $rawTempoEntrega < 45 AND $rawTempoEntrega >= 30 "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END AS tempo3, "
            . "  CASE WHEN $rawTempoEntrega < 60 AND $rawTempoEntrega >= 45 "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END AS tempo4, "
            . "  CASE WHEN $rawTempoEntrega >= 60 OR $rawTempoEntrega IS NULL "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END AS tempo5 "
            . "  from PEDIDOS pedido inner join SETORS setor on SETOR.ID = PEDIDO.ENTREGASETOR_ID "
            . "  inner join PEDIDOITEMS item on ITEM.PEDIDO_ID = PEDIDO.ID "
            . "  inner join PRODUTOS prod on PROD.ID = ITEM.PRODUTO_ID "
            . "  where pedidosituacao_id IN ($selPedsit) AND entregadatahora IS NOT NULL AND datahoraenvioentregador IS NOT NULL"
            . "  and PEDIDO.EMPRESA_ID = " . $empresa->id;

        if ((isset($pars['prod']) > 0 && (int) $pars['prod'] > 0))
            $select .= " and PROD.TIPO_GLP = " . (int) $pars['prod'];

        if ($type === "M")
            $select .= " AND TO_CHAR(entregadatahora, 'mm-yyyy') = '$month-$year'";
        else if ($type === 'Y')
            $select .= " AND TO_CHAR(date_trunc('year', entregadatahora), 'yyyy') = '$year'";

        $select .= " GROUP BY PEDIDO.ID, setor.id, SETOR.DESCRICAO,  CASE WHEN $rawTempoEntrega < 15 "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END, "
            . "  CASE WHEN $rawTempoEntrega < 30 AND $rawTempoEntrega >= 15 "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END, "
            . "  CASE WHEN $rawTempoEntrega < 45 AND $rawTempoEntrega >= 30 "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END, "
            . "  CASE WHEN $rawTempoEntrega < 60 AND $rawTempoEntrega >= 45 "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END, "
            . "  CASE WHEN $rawTempoEntrega >= 60 OR $rawTempoEntrega IS NULL "
            . "         THEN 1 "
            . "         ELSE 0 "
            . "  END ORDER BY SETOR.DESCRICAO asc ";
        $select = "SELECT sum(tempo1) as tempo1, sum(tempo2) as tempo2, sum(tempo3) as tempo3, sum(tempo4) as tempo4, sum(tempo5) as tempo5, "
            . " count(setor_id) as qde, setor_id, setor FROM ($select) GROUP BY setor, setor_id";
        $pedidos = collect(DB::select($select))->sortBy('setor', SORT_NATURAL);

        $i = 0;
        foreach ($pedidos as $p) {
            $p->order = $i++;
        }
        return $pedidos->keyBy('order');
    }

    public function nfimpostoByGfOperacao($grupofiscal_id, $nfoperacao_id)
    {
        return count(Nfimposto::where('nfgrupofiscal_id', $grupofiscal_id)->where('nfoperacao_id', $nfoperacao_id)->take(1)->get()) > 0 ? 'OK|' : 'NOK';
    }

    public function getLayoutBanco($id)
    {
        return response()->json(Layoutbanco::find($id));
    }

    public function checkCaixaIsFechado()
    {
        $data_to_date = "TO_DATE('" . Carbon::createFromFormat('d/m/Y H:i:s', $_GET['data']) . "', 'yyyy-mm-dd hh24:mi:ss')";
        $conta_id = $_GET['conta_id'];
        $raw = "conta_id = $conta_id and ("
            . " (datahoraabertura <= $data_to_date and datahorafechamento >= $data_to_date) or (datahoraabertura <= $data_to_date and datahorafechamento is null))";
        $fechamento = Contafechamento::whereRaw($raw)->get()->first();
        if (is_null($fechamento))
            return 'NOK';
        else if ($fechamento->fechado)
            return "FEC";
        else
            return 'OK|';
    }

    public function getCodMovRemessaByBanco($id, $tipo)
    {
        $conta = Boleto::with('conta')->find($id)->conta;
        $codigoBanco = $conta->banco->codigo;
        $ocorrencias = Ocorrenciasremessas::where('codigo_banco', $codigoBanco)
            ->where('tipo', $tipo)->where('allowed_user', true)
            ->select('id', 'codigo', 'descricao')
            ->orderby('codigo')->orderby('descricao')
            ->get();
        return response()->json($ocorrencias);
    }

    public function getCodMovRemessaByConta($id, $tipo)
    {
        $conta = Conta::find($id);
        if (is_null($conta)) {
            return response()->json("Conta não encontrada");
        }
        if (is_null($conta->banco)) {
            return response()->json("Banco não encontrado");
        }

        $codigoBanco = $conta->banco->codigo;
        $ocorrencias = Ocorrenciasremessas::where('codigo_banco', $codigoBanco)
            ->where('tipo', $tipo)->where('allowed_user', true)
            ->select('id', 'codigo', 'descricao')
            ->orderby('codigo')->orderby('descricao')
            ->get();
        return response()->json($ocorrencias);
    }

    public function getContaById($id)
    {
        $conta = Conta::find($id);
        $conta->boletojuros = sprintf('%0.3f', $conta->boletojuros);
        $conta->boletomulta = sprintf('%0.3f', $conta->boletomulta);
        return response()->json($conta);
    }

    public function searchParcelasByIds($parcelas)
    {
        $parcelas = explode(',', $parcelas);
        $parcelas = Financeiroparcela::where('financeiroparcelas.empresa_id', Session::get('empresa_padrao')->id)
            ->whereIn('financeiroparcelas.id', $parcelas)
            ->where('baixado', 0)
            ->join('financeiros', 'financeiros.id', '=', 'financeiroparcelas.financeiro_id')
            ->join('clientes', 'clientes.id', '=', 'financeiros.cliente_id')
            ->leftJoin('boletos as b', 'b.financeiroparcela_id', 'financeiroparcelas.id')
            ->leftJoin('chequerecebidofinanceiros as cheque', 'cheque.financeiroparcela_id', 'financeiroparcelas.id')
            ->leftJoin('chequeemitidoencontrocontas as encontrocontas', 'encontrocontas.financeiroparcela_id', 'financeiroparcelas.id')
            ->leftJoin('chequerecebidofinanceiros as encontrocontascheque', 'encontrocontascheque.id', 'encontrocontas.chequeemitido_id')
            ->select('financeiroparcelas.id', 'financeiros.documento', 'financeiroparcelas.numero', 'dataemissao', 'datavencimento', 'datahorabaixa', 'financeiroparcelas.valor', 'financeiroparcelas.multa', 'financeiroparcelas.juros', 'financeiroparcelas.desconto', 'financeiroparcelas.valorefetivado', 'clientes.nome', 'financeiros.descricao', 'financeiroparcelas.baixado', 'financeiros.cliente_id', 'financeiroparcelas.agrupamento_status', 'financeiros.cartaoautorizacao', 'cheque.numerocheque as numcheque', 'encontrocontascheque.numerocheque as encontrocontasnumcheque', 'b.nossonumero as nossonumeroboleto')
            ->get();
        $financeiroController = new FinanceiroController();
        return $financeiroController->formatParcelasToView($parcelas);
    }

    public function getBairrosRuasByCidade($cidade_id)
    {
        $ruas = Rua::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('ativo', true)->orderBy('descricao')->select('id', 'descricao');
        $bairros = Bairro::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->select('id', 'descricao');
        if (str_contains($cidade_id, ",")) {
            $bairros = $bairros->whereRaw('cidade_id in (' . $cidade_id . ")")->get();
            $ruas = $ruas->whereRaw('cidade_id in (' . $cidade_id . ")")->get();
        } else {
            $bairros = $bairros->where('cidade_id', $cidade_id)->get();
            $ruas = $ruas->where('cidade_id', $cidade_id)->get();
        }
        return ['bairros' => $bairros, 'ruas' => $ruas];
    }

    function buscaClienteTelefone($telefone)
    {
        $cliente_id = intval(Input::get('cliente_id', 0));
        $validateExists = (bool) Input::get('validateExists', false);
        $telefone = str_replace('--', ' ', $telefone);

        // rownum<=1 → ->limit(1); telefone parametrizado (era interpolado → SQLi).
        $raw = "t.telefone = ?"
            . " AND t.grupo_id = ?"
            . " AND t.empresa_id in (SELECT empresa_id FROM empresa_user WHERE user_id = ?)";
        $clientetelefone = DB::table('clientetelefones as t')
            ->join('clientes as cli', 'cli.id', 't.cliente_id')
            ->whereRaw($raw, [$telefone, Session::get('empresa_padrao')->grupo_id, \Auth::user()->id])
            ->select('cliente_id', 'nome')->limit(1)->get();

        if ($validateExists) {
            if (count($clientetelefone) === 0) {
                return "OK|";
            } else if (count($clientetelefone->where('cliente_id', $cliente_id)) > 0) {
                return "OPS";
            } else {
                return $clientetelefone->first()->nome;
            }
        } else {
            $clientetelefone = $clientetelefone->first();
            if (!is_null($clientetelefone)) {
                return $this->clienteToPedidos($clientetelefone->cliente_id);
            } else {
                return "OPS";
            }
        }
    }

    function buscaClienteById($id)
    {
        return $this->clienteToPedidos($id);
    }

    private function clienteToPedidos($cliente_id)
    {
        $cliente = $this->buscaClienteByIdToPedidos($cliente_id);
        if (isset($cliente["convenioempresa"])) {
            if (!is_null($cliente["convenioempresa"]) && ($cliente["convenioempresa"]["convenioativo"] != 1 || $cliente["convenioempresa"]["ativo"] != 1)) {
                $cliente["convenio_id"] = null;
                $cliente["convenioempresa"] = null;
            }
        }
        if (is_string($cliente))
            return $cliente;
        $historico = $this->historicoCliente($cliente_id, null);

        if (is_string($historico)) {
            return $historico;
        }
        if (!isset($cliente['cliente_produto'])) {
            return "Erro ao buscar cliente";
        }
        if (isset($cliente['convenioempresa']) && isset($cliente['convenioempresa']['produtoconvenio'])) {
            $produtosConvenio = collect($cliente['convenioempresa']['produtoconvenio'])->toJson();
        } else {
            $produtosConvenio = [];
        }
        return [
            'cliente'          => $cliente,
            'historico'        => $historico,
            'cliente_produto'  => $cliente['cliente_produto'],
            'produtosconvenio' => $produtosConvenio
        ];
    }

    function buscaClienteByIdToPedidos($id)
    {
        $select = [
            'rua.descricao as rua_descricao',
            'bairro.descricao as bairro_descricao',
            'cidade.descricao as cidade_descricao',
            'clientes.empresa_id',
            'clientes.uf',
            'clientes.cidade_id',
            'clientes.bairro_id',
            'clientes.id',
            'clientes.id as cliente_id',
            'clientes.rua_id',
            'clientes.numero',
            'clientes.complemento',
            'clientes.ponto_referencia',
            'clientes.cep',
            'clientes.observacoes',
            'clientes.setor_id',
            'clientes.tipopessoa_id',
            'clientes.nome',
            'tipo.tipopessoacadastro',
            'clientes.convenio_id',
            'clientes.cliente',
            'clientes.ativo',
            'tel.telefone',
            'clientes.gasdopovo'
        ];

        $cliente = Cliente::with('clienteConvenio', 'condicaoPagamento', 'clienteProduto', 'clienteProduto.produto', 'convenioempresa.clienteConvenio', 'convenioempresa.produtoconvenio', 'convenioempresa.produtoconvenio.produto')
            ->join('tipopessoas as tipo', 'tipo.id', 'clientes.tipopessoa_id')
            ->join('ruas as rua', 'rua.id', 'clientes.rua_id')
            ->join('bairros as bairro', 'bairro.id', 'clientes.bairro_id')
            ->join('cidades as cidade', 'cidade.id', 'clientes.cidade_id')
            // Oracle usava rownum<=1 no join p/ pegar 1 telefone; Postgres: subquery
            // DISTINCT ON (cliente_id) garante no máximo uma linha de telefone por cliente.
            ->leftJoin(DB::raw('(SELECT DISTINCT ON (cliente_id) cliente_id, telefone FROM clientetelefones ORDER BY cliente_id, id) as tel'), 'tel.cliente_id', 'clientes.id')
            ->where('clientes.id', $id)
            ->select($select)
            ->get()->first();

        if (is_null($cliente)) return 'ERRO! Cliente não encontrado!';

        if (!is_null($cliente->convenioempresa)) {
            if ($cliente->convenioempresa->convenioativo == 1 || $cliente->convenioempresa->ativo != 1) {
                foreach ($cliente->convenioempresa->produtoconvenio as $prodconv) {
                    if ($prodconv->preco == 0) {
                        $prodconv->preco = $prodconv->produto->precovenda;
                    }
                }
            } else {
                $cliente->convenio_id = null;
                $cliente->convenioempresa = null;
            }
        }

        return makeObs($cliente);
    }

    function historicoCliente($cliente_id, $pedido_id = null, $cliente = null)
    {
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $select = [
            'item.quantidade',
            'pedidos.id',
            'precovendatotal',
            'datahoraprevisaoentrega',
            'pedidosituacao_id',
            'entregafinalizada',
            'entregacancelada',
            'entregapendente',
            'produto_id',
            'fechadoconcluido',
            'fechadocancelado',
            'entregatranferida',
            'ementrega',
            'pedidorecebidomovel',
            'pedidolidomovel',
            'valorvenda',
            'entregataxa',
            'entregaurgente',
            'entregatelefone',
            'situacao.descricao as situacao_descricao',
            'produto.descricao as produto',
            'pagamento.descricao as condicaopagamento',
            'condicaopagamento_id',
            'pagamento.tipo as condicaopagamento_tipo',
            'pedidos.financeiro_id'
        ];
        $pedidos = Pedido::with('financeiro.financeiroparcela')
            ->where([
                ['pedidos.cliente_id', $cliente_id],
                ['pedidos.grupo_id', $grupo_id],
                ['pedidos.id', '!=', $pedido_id]
            ])->join('pedidoitems as item', 'item.pedido_id', 'pedidos.id')
            ->join('pedidosituacaos as situacao', 'situacao.id', 'pedidos.pedidosituacao_id')
            ->join('produtos as produto', 'produto.id', 'item.produto_id')
            ->join('condicaopagamentos as pagamento', 'pagamento.id', 'pedidos.condicaopagamento_id')
            ->select($select)
            ->orderBy('pedidos.id', 'desc')
            ->take(20)
            ->get();
        if (!$cliente) {
            $cliente = Cliente::with('convenioempresa.clienteConvenio')->find($cliente_id);
            if (isset($cliente->convenioempresa)) {
                if ($cliente->convenioempresa->convenioativo != 1 || $cliente->convenioempresa->ativo != 1) {
                    $cliente->convenio_id = null;
                    $cliente->convenioempresa = null;
                }
            }
        }
        $historico = [];

        foreach ($pedidos as $pedido) {
            array_push($historico, $this->checaItens($pedido));
        }

        array_push($historico, $this->checaConvenioEPromocao($cliente, $cliente_id, $pedido_id));

        return $historico;
    }

    private function checaItens($pedido)
    {
        $promocao_id = null;
        $vencimentoParcelaAtual = null;
        $data = requestDataOracle($pedido->datahoraprevisaoentrega, false);
        $atrasado = false;
        $vencimento = null;
        $status = 0;
        if (isset($pedido->financeiro)) {
            $count = 0;
            $countT = count($pedido->financeiro->financeiroparcela);
            foreach ($pedido->financeiro->financeiroparcela as $parcela) {
                $vencimentoParcelaAtual = $parcela->datavencimento;
                $count++;
                //Status agrupado
                if ($parcela->agrupamento_status == 2) {
                    $reparcela = $this->checaReparcelamento($parcela->agrupador_financeiro_id, 1);
                    $status = $reparcela["status"];
                    $vencimentoParcelaAtual = $reparcela["vencimento"];
                    $cancelado = $status == '3';
                    $atrasado = $status == '1';
                    $pendente = $status == '0';
                } else {
                    //status cancelado
                    $cancelado = $parcela->agrupamento_status == '3';

                    //status baixado
                    if ($parcela->baixado == '1')
                        $status = 2;

                    if ($status != 2 && !$cancelado && strtotime($vencimentoParcelaAtual) < strtotime(Carbon::now()->startOfDay()->toDateTimeString())) {
                        //a primeira parcela atrasada será registrada
                        if (!$atrasado)
                            $vencimento = $vencimentoParcelaAtual;
                        //status atrasado
                        $atrasado = true;
                    }
                    //status pendente
                    $pendente = !$atrasado && $parcela->baixado == '0' && !$cancelado ? true : false;
                }
                if ($count === $countT) {
                    if ($atrasado)
                        $status = 1; //status atrasado
                    else if ($pendente)
                        $status = 0; //status pendente
                    else if ($cancelado)
                        $status = 3; //status cancelado
                }
                //caso não haja nenhuma parcela atrasada, pegará o valor da primeira
                $vencimento = !isset($vencimento) ? $vencimentoParcelaAtual : $vencimento;
            }
        } else {
            $status = 2;
        }

        if ($pedido->fechadocancelado)
            $status = 3;

        return [
            'data'                        => $data,
            'descricao_produto'           => $pedido->produto,
            'produto_id'                  => $pedido->produto_id,
            'precovendatotal'             => requestNumeroDecimalOracle($pedido->precovendatotal),
            'quantidade'                  => $pedido->quantidade,
            'vencimento'                  => requestDataOracle($vencimento),
            'condicaopagamento_descricao' => $pedido->condicaopagamento,
            'condicaopagamento_id'        => $pedido->condicaopagamento_id,
            'condicaopagamento_tipo'      => $pedido->condicaopagamento_tipo,
            'pedidosituacao'              => $pedido->situacao_descricao,
            'id'                          => $pedido->id,
            'status'                      => $status,
        ];
    }
    private function checaReparcelamento($financeiro_id, $nested)
    {
        $vencimentoParcelaAtual = null;
        $atrasado = false;
        $vencimento = null;
        $status = 0;
        if (isset($financeiro_id) && $nested < 20) {
            $count = 0;
            $financeiro = Financeiro::find($financeiro_id);
            $countT = count($financeiro->financeiroparcela);

            foreach ($financeiro->financeiroparcela as $parcela) {
                $vencimentoParcelaAtual = $parcela->datavencimento;
                $count++;
                //Status agrupado
                if ($parcela->agrupamento_status == 2 && $parcela->financeiro_id != $parcela->agrupador_financeiro_id) {
                    $reparcela = $this->checaReparcelamento($parcela->agrupador_financeiro_id, $nested + 1);
                    $status = $reparcela["status"];
                    $vencimentoParcelaAtual = $reparcela["vencimento"];
                    $cancelado = $status == '3';
                    $atrasado = $status == '1';
                    $pendente = $status == '0';
                } else {
                    if ($parcela->agrupamento_status != 2) {
                        //status cancelado
                        $cancelado = $parcela->agrupamento_status == '3';

                        //status baixado
                        if ($parcela->baixado == '1')
                            $status = 2;

                        if (strtotime($vencimentoParcelaAtual) < strtotime(Carbon::now()->startOfDay()->toDateTimeString()) && !$cancelado && $parcela->baixado == '0') {
                            //a primeira parcela atrasada será registrada
                            if (!$atrasado)
                                $vencimento = $vencimentoParcelaAtual;
                            //status atrasado
                            $atrasado = true;
                        }
                        if ($cancelado)
                            $vencimentoParcelaAtual = null;
                        //status pendente
                        $pendente = !$atrasado && $parcela->baixado == '0' && !$cancelado;
                    }
                }

                if ($count === $countT) {
                    if ($atrasado)
                        $status = 1; //status atrasado
                    else if ($pendente)
                        $status = 0; //status pendente
                    else if ($cancelado)
                        $status = 3; //status cancelado
                }
                //caso não haja nenhuma parcela atrasada, pegará o valor da primeira
                $vencimento = !isset($vencimento) && $vencimentoParcelaAtual != null ? $vencimentoParcelaAtual : $vencimento;
            }
        } else {
            if ($nested < 20)
                $status = 2;
            else {
                //dd($financeiro_id);
                $status = 2;
            }
        }

        return [
            'vencimento'                  => $vencimento,
            'status'                      => $status,
        ];
    }
    //checa os dados de convenio e promoção
    private function checaConvenioEPromocao($cliente, $cliente_id, $pedido_id)
    {
        $id = false;
        $convenioPara = false;
        $comissao = 0;
        $convenioDisponivel = false;
        $limiteDisponivel = 0;
        $comprasProdutoPromocao = 0;
        $quantidadePedidos = 0;
        $produtoPremio = 0;
        $produtoPremioDescricao = '';
        $produtoPromocao_id = 0;
        $quantidadePremios = 0;
        $ganhouProdutoPremio = 0;
        $promo_descricao = '';
        $participaPromocao = 0;

        $convenio = $cliente->convenioempresa && $cliente->convenioempresa->clienteConvenio ? $cliente->convenioempresa->clienteConvenio : null;

        //checa se há convenio e as compras que foram feitas com o convenio
        if ($cliente->convenio_id && $convenio) {
            $fechamento = getProximoVencimento($convenio->diafechamento);
            $fechamentoAnterior = Carbon::parse($fechamento)->subMonth()->toDateTimeString();

            $totalcomprasConvenio =  intVal(DB::table('pedidos as p')->join('pedidosituacaos as s', 's.id', 'p.pedidosituacao_id')
                ->join('condicaopagamentos as cond', function ($join) {
                    $join->on('cond.id', 'p.condicaopagamento_id')->where('tipo', '4');
                })
                ->join('pedidoitems as it', function ($join) {
                    $join->on('it.pedido_id', 'p.id')->whereRaw("fechadocancelado <> 1 AND entregacancelada <> 1");
                })
                ->where([
                    ['cliente_id', $cliente_id],
                    ['p.id', '!=', $pedido_id]
                ])->whereBetween('datahoraprevisaoentrega', [$fechamentoAnterior, $fechamento])
                ->selectRaw("sum(quantidade) as quantidade")->get()->first()->quantidade);

            if ($cliente->convenio && $totalcomprasConvenio < $convenio->limitecompra) {
                if ($convenio->limitecompra < $cliente->conveniolimite) {
                    $limiteDisponivel = $cliente->conveniolimite - $totalcomprasConvenio;
                } else {
                    $limiteDisponivel = $convenio->limitecompra - $totalcomprasConvenio;
                }
                $convenioPara = $convenio->comissaodestino;
                $comissao = $convenio->comissao;
                $convenioDisponivel = true;
            } else {
                if ($totalcomprasConvenio < $cliente->conveniolimite) {
                    $convenioDisponivel = true;
                    $limiteDisponivel = $cliente->conveniolimite - $totalcomprasConvenio;
                    $convenioPara = $convenio->comissaodestino;
                    $comissao = $convenio->comissao;
                }
            }
        }
        //se o usuário participa de uma promoção, verifica as a quantidade de vezes que o usuario ganhou o brinde e outros dados para validações na tela de pedidos
        if (is_null($pedido_id))
            $data = Carbon::now()->toDateTimeString();
        else
            $data = Pedido::find($pedido_id)->datahora;
        $clientePromocao = $cliente->clientePromocao()->where([['datainicio', '<=', $data], ['datafim', '>=', $data]])->get()->first();
        $hasProdutoPromocao = isset($clientePromocao->promocao->produto_id) && !is_null($clientePromocao->promocao->produto_id);
        if (!is_null($clientePromocao) && $hasProdutoPromocao) {
            $produtoPremio = $clientePromocao->promocao->premioproduto_id;
            $quantidadePedidos = $clientePromocao->promocao->quantidadepedidos;
            $quantidadePremios = $clientePromocao->promocao->quantidadepremios;
            $produtoPromocao_id = $clientePromocao->promocao->produto_id;
            $produtoPremioDescricao = isset($clientePromocao->promocao->premio->descricao) ? $clientePromocao->promocao->premio->descricao : '';
            $pedidoitem = Pedidoitem::where('pedidos.cliente_id', $cliente_id)
                ->whereIn('pedidoitems.produto_id', [$produtoPromocao_id, $produtoPremio])
                ->whereRaw("(sit.fechadoconcluido = 1 OR sit.entregafinalizada = 1)")
                ->whereBetween('pedidos.datahoraprevisaoentrega', [$clientePromocao->datainicio, $clientePromocao->datafim])
                ->join('pedidos', 'pedidoitems.pedido_id', '=', 'pedidos.id')
                ->join('pedidosituacaos as sit', 'sit.id', '=', 'pedidos.pedidosituacao_id')
                ->select('pedidoitems.quantidade', 'pedidoitems.precovendaunitario', 'pedidoitems.produto_id', 'pedidos.datahoraprevisaoentrega')
                ->get();

            $comprasProdutoPromocao = $pedidoitem->where('produto_id', $produtoPromocao_id)->sum('quantidade');
            $ganhouProdutoPremio = $pedidoitem->where('produto_id', $produtoPremio)->sum('quantidade');
            $promo_descricao = $clientePromocao->promocao()->first()->descricao;
            $participaPromocao = 1;
        }
        $conv_descricao = !isset($cliente->convenioempresa->nome) ? '' : $cliente->convenioempresa->nome;
        $dados = [
            'id'                     => $id,
            'conveniodisponivel'     => $convenioDisponivel,
            'qdelimitedisponivel'    => $limiteDisponivel,
            'conveniopara'           => $convenioPara,
            'comissao'               => $comissao,
            'premioproduto_id'       => $produtoPremio,
            'comprasprodutopromocao' => $comprasProdutoPromocao,
            'quantidadepedidos'      => $quantidadePedidos,
            'quantidadepremios'      => $quantidadePremios,
            'produtopromocao_id'     => $produtoPromocao_id,
            'produtopremiodescricao' => $produtoPremioDescricao,
            'ganhouprodutopremio'    => $ganhouProdutoPremio,
            'promo_descricao'        => $promo_descricao,
            'participaPromocao'      => $participaPromocao,
            'convenio'               => $cliente->convenio,
            'conv_descricao'         => $conv_descricao,
        ];
        return $dados;
    }

    public function getEmpresasByRegional()
    {
        $get = $_GET;
        $grupo = isset($get["grupo"]) && $get["grupo"] != "" ? $get["grupo"] : null;
        $regional = isset($get["regional"]) && $get["regional"] != "" ? $get["regional"] : null;
        $empresas_id = \Auth::user()->empresas()->pluck('id')->toArray();
        $empresas = Empresa::whereIn('id', $empresas_id);
        if (is_null($grupo)) {
            $empresas = $empresas->where('regiao_id', $regional);
        } else if (is_null($regional)) {
            $empresas = $empresas->where('grupo_id', $grupo);
        } else {
            $empresas = $empresas->where([
                ['grupo_id', $grupo],
                ['regiao_id', $regional]
            ]);
        }
        $empresas = $empresas->orderBy('nome_informal')->pluck('nome_informal', 'id');

        return $empresas;
    }

    public function getEmpresasByGrupo()
    {
        $grupo = $_GET["grupo"];
        $empresa = Empresa::where('grupo_id', $grupo)->select('id', 'nome_informal')->get();
        return $empresa;
    }

    public function getCentroByPai()
    {
        $centro = $_GET["centro_id"];
        $empresa = Session::get('empresa_padrao')->id;
        // Oracle START WITH id=$centro CONNECT BY PRIOR id = paicentrocusto_id:
        // desce a árvore de centros de custo (do $centro p/ os descendentes).
        // Postgres: WITH RECURSIVE descendente por paicentrocusto_id.
        $query = "WITH RECURSIVE arv AS ( " .
            "  SELECT id, descricao, empresa_id, finalizador FROM centrocustos WHERE id = $centro " .
            "  UNION ALL " .
            "  SELECT c.id, c.descricao, c.empresa_id, c.finalizador FROM centrocustos c " .
            "    JOIN arv ON c.paicentrocusto_id = arv.id " .
            ") " .
            "SELECT id, descricao FROM arv WHERE empresa_id = $empresa AND finalizador = 1";
        $centro = collect(DB::select($query));

        return $centro->pluck('descricao', 'id');
    }

    public function getInfoClienteByPedido($pedido_id)
    {
        $pedido = Pedido::with('cliente.tipopessoa', 'financeiro.condicaoPagamento')->find($pedido_id);

        if (is_null($pedido))
            return "Não foi possível encontrar os dados do pedido";

        $isDup = is_null($pedido->financeiro) ? false : $pedido->financeiro->condicaoPagamento->nfc_tpag == 14;
        $config = DB::table('empresaconfigs')
            ->where('empresa_id', $pedido->empresa_id)
            ->select('presencacomprador', 'fretemodalidade', 'transportadorpadrao_id', 'pedidooperacao_id')->get()->first();

        if (is_null($config)) {
            return "Configurações da empresa ainda não definidas";
        }

        $operacao = DB::table('nfoperacaos')->where('id', $config->pedidooperacao_id)->get()->first();

        if (is_null($operacao)) {
            return "Operação das configurações da empresa que define se o Pedido gera financeiro ainda não foi cadastrada";
        }
        $pedidogerafinanceiro = $operacao->movimentafinanceiro ? true : false;

        $cliente = $pedido->cliente;

        if (is_null($cliente)) {
            return "Não foi possível encontrar os dados do cliente";
        }

        $tipopessoa = $cliente->tipopessoa->tipopessoacadastro;
        $indicador_ie = $cliente->indicador_ie;
        $vfrete = requestNumeroDecimalOracle($pedido->entregataxa);

        if ($tipopessoa == 'F') {
            $cpfcnpj = $cliente->cpf;
        } else {
            $cpfcnpj = $cliente->cnpj;
        }

        $transportadoras = DB::table('clientes as c')
            ->join('cidades as cid', 'cid.id', 'c.cidade_id')
            ->join('bairros as bairro', 'bairro.id', 'c.bairro_id')
            ->join('ruas as rua', 'rua.id', 'c.rua_id')
            ->where('transportador', true)->where('c.empresa_id', $pedido->empresa_id)
            ->select('c.id', 'c.nome', 'c.inscricao_estadual', 'c.cnpj', 'c.cpf', 'c.indicador_ie', 'c.uf', 'cid.cod_ibge as codigoibge', 'cid.descricao as cidadedesc', 'rua.descricao as ruadesc', 'bairro.descricao as bairrodesc', 'c.bairro_id', 'c.rua_id', 'c.cidade_id', 'c.numero', DB::raw('(select telefone from clientetelefones where cliente_id = c.id limit 1) as telefone'), 'email')->get();

        $raw = " cond.ativo = 1 AND "
            . " condicaopagamento_id in (select condicaopagamento_id from cliente_condicaopagamento "
            . " where cliente_id in (" . implode(', ', $transportadoras->pluck('id')->toArray()) . "))";

        $condicoesPgto = DB::table('condicaopagamentos as cond')
            ->join('cliente_condicaopagamento as cc', 'cc.condicaopagamento_id', 'cond.id')
            ->whereRaw($raw)
            ->select('descricao', 'cond.id', 'cliente_id')->get();

        $condicoesPgtoPadrao = Condicaopagamento::where([
            ['ativo', 1],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
        ])->orderby('descricao')->orderBy('descricao')->get();

        return response()->json(compact('cpfcnpj', 'tipopessoa', 'indicador_ie', 'config', 'transportadoras', 'vfrete', 'condicoesPgto', 'condicoesPgtoPadrao', 'pedidogerafinanceiro', 'isDup'));
    }

    public function getTipoPgtoParcela($parcela_id)
    {
        $fin = DB::table('financeiroparcelas as p')
            ->join('financeiros as f', 'f.id', 'p.financeiro_id')
            ->join('condicaopagamentos as c', 'c.id', 'f.condicaopagamento_id')
            //->whereRaw("c.tipo in (2,3) and p.id = $parcela_id")
            ->whereRaw("p.id = $parcela_id")
            ->select('c.tipo', 'f.cartaonsu', 'f.cartaoautorizacao', 'f.descricao', 'f.documento', 'p.datavencimento', 'c.tipo')->get()->first();
        $fin->vencimento = requestDataOracle($fin->datavencimento, false);
        return is_null($fin) ? 'OK|' : response()->json($fin);
    }

    public function clienteReportNf()
    {
        $filtro = $_GET;
        $query = $filtro["query"];

        if (!$query && $query == '')
            return response()->json(array(), 400);

        $clientes = Cliente::where('empresa_id', Session::get('empresa_padrao')->id)
            ->where([
                ['clientes.ativo', true]
            ])
            ->whereRaw(rawTranslateSpecialChars("nome") . " LIKE '%$query%'")
            ->select('clientes.id', 'clientes.nome', 'clientes.cpf')
            ->orderBy('clientes.nome', 'asc')
            ->take(8)
            ->get()
            ->toArray();

        $clientes = $this->appendValue($clientes, 'cliente', 'class');
        return response()->json(array(
            'data' => $clientes
        ));
    }

    public function getUserByEmpresa()
    {
        $empresa_id = $_GET["empresa"];
        $support = isset($_GET["support"]) && $_GET["support"] == 1;

        $user = User::join('empresa_user as emp', 'emp.user_id', 'users.id')
            ->where(['emp.empresa_id' => $empresa_id, 'ativo' => 1]);

        if (!$support) {
            $user = $user->where('support', 0);
        }

        $user = $user->select('name', 'users.id')
            ->get();

        return $user;
    }

    function getNfBySerieNum()
    {
        $numero = isset($_GET['nfnumero']) ? trim($_GET['nfnumero']) : "";
        $serie = isset($_GET['nfserie']) ? trim($_GET['nfserie']) : "";

        if (empty($numero) || empty($serie))
            return "Por favor, informe o número e a série da NFe.";

        $empresa_id = Session::get('empresa_padrao')->id;

        $nf = Nfemitida::with('nfEmitidaItem')
            ->where('empresa_id', $empresa_id)
            ->where('nfnumero', $numero)
            ->where('nfserie', $serie)
            ->where('nfmodelo', '55')
            ->select('chaveacesso', 'id')->get()->first();

        if (is_null($nf))
            return "NFe não encontrada";

        $data = [
            'items'       => $nf->nfEmitidaItem->toArray(),
            'chaveAcesso' => $nf->chaveacesso
        ];

        return response()->json($data);
    }

    /**
     * Pega os dados da request, valida o emitente e destinatário
     * chama o metodo que retorna os calculos
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function calcularImpostoProduto(Request $request)
    {
        try {
            $pars = $request->all();
            $processor = new NfeImpostoProcessor(null, $pars);
            return response()->json($processor->calculaImposto(false));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function issetImpostoProdutoAjax()
    {
        $nfoperacao_id = (int) Input::get('nfoperacao_id', 0);
        $empresa_id = (int) Input::get('empresa_id', 0);
        $nfgrupofiscal_id = (int) Input::get('nfgrupofiscal_id', 0);

        $tipoNf = Input::get("emitida", "emitida");
        if ((int) Input::get("tipolancamento", 0) || $tipoNf === "emitida" || $tipoNf === "sat") {
            $emituf = Input::get('emituf', 0);
            $destuf = Input::get('destuf', 0);
        } else {
            $emituf = Input::get('destuf', 0);
            $destuf = Input::get('emituf', 0);
        }

        $imposto = Nfimposto::where([
            ['nfoperacao_id', $nfoperacao_id],
            ['empresa_id', $empresa_id],
            ['nfgrupofiscal_id', $nfgrupofiscal_id]
        ])->with('nfimpostoestado')->get()->first();

        if ((int) Input::get("iddest") === 2) {
            $status = isset($imposto->nfimpostoestado) && !is_null($imposto->nfimpostoestado->where('origem_uf', $emituf)->where('destino_uf', $destuf)->first());
        } else {
            $status = !is_null($imposto);
        }

        return response()->json([
            'status' => $status
        ]);
    }

    public function getPcCcById()
    {
        try {

            $pc_id = (int) Input::get('pc_id', 0);
            $cc_id = (int) Input::get('cc_id', 0);

            if (!$pc_id) {
                throw new \Exception("Id do Plano de Contas Inválido", 404);
            }

            if (!$cc_id) {
                throw new \Exception("Id do Centro de Custos Inválido", 404);
            }

            $pc = DB::table("planocontas")->where('id', $pc_id)->select('codigo')->get()->first();
            if (is_null($pc)) {
                throw new \Exception("Plano de Contas não encontrado.", 404);
            }
            $cc = DB::table("centrocustos")->where('id', $cc_id)->select('codigo')->get()->first();
            if (is_null($cc)) {
                throw new \Exception("Centro de Custos não encontrado.", 404);
            }
            $status = 200;
            $data = [
                'planoconta'  => $pc,
                'centrocusto' => $cc
            ];
        } catch (\Exception $ex) {
            $status = $ex->getCode();
            $data = $ex->getMessage();
        }
        return response()->json([
            'status' => $status,
            'data'   => $data
        ]);
    }

    public function getFornecedorByCNPJ($cnpj)
    {
        try {
            $cnpj = mask($cnpj, "##.###.###/####-##");
            $forn = DB::table('clientes as c')
                ->where('fornecedor', 1)
                ->where('empresa_id', Session::get('empresa_padrao')->id)
                ->where('cnpj', $cnpj)
                ->select(["nome", "id"])
                ->get()
                ->first();
            if (!$forn) {
                return response()->json([
                    'status'    => "NO",
                    'msg'       => $forn->nome,
                    'id'        => $forn->id
                ]);
            } else {
                return response()->json([
                    'status'    => "OK",
                    'msg'       => $forn->nome,
                    'id'        => $forn->id
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status'    => "NO",
                'msg'       => $e->getMessage(),
                'id'        => 0
            ]);
        }
    }
}
