<?php

namespace App\Http\Controllers;

use App\Centrocusto;
use App\Cliente;
use App\Financeirorateio;
use App\Helpers\Utils\NfUtil as Util;
use App\Http\Requests\NfRequest;
use App\NfInutilizacaoEntrada;
use App\Nfoperacao;
use App\Nfrecebida;
use App\Planoconta;
use App\Processors\Nfe\Tributacao\NfeImpostoProcessor;
use App\Processors\Nfe\NfProcessor;
use App\Produto;
use App\Repository\SelectRepository;
use App\Services\CarbonCustom as Carbon;
use App\Services\CarbonCustom;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Input;
use NFePHP\NFe\Common\Standardize;

/**
 * Class NfrecebidaController
 * @package App\Http\Controllers
 * @method whereCnpj(string $maskCNPJ)
 */
class NfrecebidaController extends Controller
{

    /**
     * @param Nfrecebida $nots
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Nfrecebida $nots)
    {
        $this->authorize('view', $nots);
        $dataInicial = Input::get('dataI', Carbon::now()->subDays(2)->toDateString());
        $dataFinal = Input::get('dataF', Carbon::now()->toDateString());

        return $this->filter($dataInicial, $dataFinal);
    }

    /**
     * @param $dataInicial
     * @param $dataFinal
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws Exception
     */
    function filter($dataInicial, $dataFinal)
    {
        $cliente_id = (int) Input::get('cliente_id', 0);
        $tipoLancamento = (int) Input::get('tipolancamento', 9);

        $nfDb = Nfrecebida::where('empresa_id', Session::get('empresa_padrao')->id)
            ->whereBetween('datahoraemissao', [$dataInicial . ' 00:00', $dataFinal . ' 23:59']);

        if ($cliente_id > 0) {
            $nfDb->where('cliente_id', $cliente_id);
        }

        if ($tipoLancamento !== 9) {
            $nfDb->where('tipolancamento', $tipoLancamento);
        }

        $clientenome = "";
        if ($cliente_id > 0) {
            $cli = DB::table('clientes c')->where('id', $cliente_id)->select('nome')->get()->first();
            if (is_null($cli)) {
                Session::flash("message_info", "Cliente número " . $cliente_id . " não encontrado.");
            } else {
                $clientenome = $cli->nome;
            }
        }

        $processor = new NfProcessor();

        $results = $processor->filterIndex($nfDb);
        $currentPage = (int) Input::get('page', 1);
        $totalPages = ceil($results->total / $processor->resultsPerPage);

        $redirectUrl = '?index=' . str_replace("&", 'extPar', \Request::fullUrl());
        $fullUrl = route('nfrecebida.create') . $redirectUrl;

        $nfTable = $processor->paginate($results, $currentPage, $totalPages, $redirectUrl);
        $dataInicial = requestDataOracle($dataInicial);
        $dataFinal = requestDataOracle($dataFinal);
        $tiponf = "recebida";

        return view('nf.nfrecebida',
            compact('nfTable', 'dataInicial', 'dataFinal', 'cliente_id',
                'clientenome', 'currentPage', 'totalPages', 'fullUrl', 'tiponf'));
    }

    /**
     * @param Nfrecebida $nots
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create(Nfrecebida $nots)
    {
        $this->authorize('create', $nots);
        return $this->form(null, "create");
    }

    /**
     * @param NfRequest $request
     * @param Nfrecebida $nots
     * @return RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(NfRequest $request, Nfrecebida $nots)
    {
        $this->authorize('create', $nots);
        try {
            DB::beginTransaction();

            $data = $this->validateData($request->all());

            //nfsituacao não varia em lançamentos
            $data['nfsituacao_id'] = (int) (isset($data['nfsituacao_id']) ? $data['nfsituacao_id'] : 100);
            $nfrecebida = Nfrecebida::create($data);

            $nfrecebida->produtosjson = $data['produtos'];

            if ($data['nfsituacao_id'] === 100) {
                $this->gerarFinanceiro($data, $nfrecebida);
            }

            if ($nfrecebida->formapagamento && $nfrecebida->fretemodalidade !== "9" && $nfrecebida->vfrete > 0 && $data['nfsituacao_id'] === 100) {
                $this->gerarFinanceiro($data, $nfrecebida, true);
            }

            $processor = new NfeImpostoProcessor($nfrecebida);
            $processor->calculaImposto(true, $data["nfsituacao_id"]);


            DB::commit();
            $url = Util::getUrlPostWrite('recebida', $nfrecebida->id);

            $message = 'Documento atualizado com sucesso!';
            if ($nfrecebida->tipolancamento === "1") {
                if (! Produto::updatePGLP($nfrecebida->produtosjson, $nfrecebida->empresa_id)) {
                    $message = 'Documento salvo mas não foi possível atualizar os percentuais de GLP';
                }
            }

            Session::flash("message_success", $message);
            return Redirect::to($url);
        } catch (Exception $e) {
            DB::rollback();
            $params = str_replace('extPar', "&", Input::get('index', route("nfrecebida.index")));
            $url = "nfrecebida/create" . '?index=' . $params;
            return Redirect::to($url)->withErrors($e->getMessage())->withInput();
        }
    }

    /**
     * @param NfRequest $request
     * @param $id
     * @param Nfrecebida $nots
     * @return RedirectResponse
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(NfRequest $request, $id, Nfrecebida $nots)
    {
        $this->authorize('update', $nots);
        DB::beginTransaction();
        try {
            $nfrecebida = Nfrecebida::with("nfRecebidaItem")->find($id);
            $this->authorize('igualdade', $nfrecebida);
            $data = $this->validateData($request->all());
            Util::revisionItems($nfrecebida, $data, "produtos", "NFRecebidaItems");
            Util::generateRateioLog($nfrecebida, $data, "rateios", "NFRecebidaRateios");

            //nfsituacao não varia em lançamentos
            $data['nfsituacao_id'] = (int) (array_key_exists("nfsituacao_id", $data) ? $data['nfsituacao_id'] : 100);
            $original = clone $nfrecebida;
            $nfrecebida->update($data);
            $nfrecebida->produtosjson = $data['produtos'];

            $this->estornoNfRecebidaItem($original);

            $processor = new NfProcessor($nfrecebida);
            if ($processor->checkForChangesFinanceiro($original, $data) || $data["nfsituacao_id"] !== (int) $original->nfsituacao_id) {
                $this->estornarFinanceiro($nfrecebida);
                if ($data['nfsituacao_id'] === 100) {
                    $this->gerarFinanceiro($data, $nfrecebida);
                }
            }

            if ($processor->checkForChangesFinanceiroFrete($original, $data) || $data["nfsituacao_id"] !== (int) $original->nfsituacao_id) {
                $this->estornarFinanceiro($nfrecebida, true);
                if ($nfrecebida->formapagamento && $nfrecebida->vfrete > 0 && $data['nfsituacao_id'] === 100) {
                    $this->gerarFinanceiro($data, $nfrecebida, true);
                }
            }

            if ($data['nfsituacao_id'] === 100) {
                $processor->updateRateio($data);
            }

            $processorT = new NfeImpostoProcessor($nfrecebida);
            $processorT->calculaImposto(true, $data['nfsituacao_id']);

            $message = 'Documento atualizado com sucesso!';
            if ($nfrecebida->tipolancamento === "1") {
                if (! Produto::updatePGLP($nfrecebida->produtosjson, $nfrecebida->empresa_id)) {
                    $message = 'Documento salvo mas não foi possível atualizar os percentuais de GLP';
                }
            }

            DB::commit();
            $url = Util::getUrlPostWrite('recebida', $id);
            Session::flash("message_success", $message);
            return Redirect::to($url);
        } catch (Exception $e) {
            DB::rollback();
            return Redirect::to("/nfrecebida/{$id}/edit/")->withErrors($e->getMessage())->withInput();
        }
    }

    /**
     * @param $id
     * @param Nfrecebida $nots
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit($id, Nfrecebida $nots)
    {
        $this->authorize('update', $nots);
        return $this->form($id);
    }

    /**
     * @param $id
     * @param Nfrecebida $nots
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($id, Nfrecebida $nots)
    {
        $this->authorize('view', $nots);
        return $this->form($id, "show");
    }

    /**
     * @param $id
     * @param string $mode
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    private function form($id, $mode = "edit")
    {

        $nfrecebida = Util::getNfToForm($id, $mode, "recebida");
        $this->authorize('igualdade', $nfrecebida);
        $nfrecebidaitems = Util::getItemsToForm($id, $mode, "recebida");

        $nffinalidade = Util::getNfFinalidade();
        $presencascomprador = Util::getPrecencaComprador();
        $fretemodalidades = Util::getFreteModalidades();

        $empresa = SelectRepository::getEmpresaNfToView($nfrecebida->empresa_id);

        $operacaorecebida = SelectRepository::getOperacaoToView($empresa);
        $operacaoemitida = SelectRepository::getOperacaoToView($empresa, "emitida");

        $transportadoras = SelectRepository::getTransportadorasToView($empresa);
        $setores = SelectRepository::getSetoresToView($empresa);
        $condicaopagamentos = SelectRepository::getCondPgtoToView($empresa);

        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = collect(Planoconta::menuspermissoesAll());
        $planocontasR = $planocontas->where("pagarreceber", "R");
        $planocontasD = $planocontas->where("pagarreceber", "D");

        $nfrecebida = Util::adjustValuesToForm($nfrecebida, $nfrecebidaitems);

        $produtos = SelectRepository::getProdutoNf($empresa->id);

        $situacoes = Util::getSituacaoLancDoc();

        $fields = [
            'nffinalidade'                => $nffinalidade,
            'operacaorecebida'            => $operacaorecebida,
            'operacaoemitida'             => $operacaoemitida,
            'empresa'                     => $empresa,
            'presencascomprador'          => $presencascomprador,
            'fretemodalidades'            => $fretemodalidades,
            'transportadoras'             => $transportadoras,
            'setores'                     => $setores,
            'produtos'                    => $produtos,
            'fretecentrocusto_descricao'  => strval($nfrecebida->fretecc),
            'fretecentrocusto_id'         => $nfrecebida->fretecentrocusto_id,
            'freteplanoconta_descricao'   => strval($nfrecebida->fretepc),
            'freteplanoconta_id'          => $nfrecebida->freteplanoconta_id,
            'centrocusto_descricao'       => strval($nfrecebida->cc),
            'centrocusto_id'              => $nfrecebida->centrocusto_id,
            'planoconta_descricao'        => strval($nfrecebida->pc),
            'planoconta_id'               => $nfrecebida->planoconta_id,
            'empresa_id'                  => $nfrecebida->empresa_id,
            'condicaopagamentos'          => $condicaopagamentos,
            'planocontas'                 => $planocontas,
            'centrocustos'                => $centrocustos,
            'planocontasR'                => $planocontasR,
            'planocontasD'                => $planocontasD,
            'tiponf'                      => 'recebida',
            'situacoes'                   => $situacoes,
            'cProdAnpGLP'                 => Util::getCProdAnpGLP(),
            'cfopGLP'                     => Util::getCFOPGLP(),
            'cliente_config'              => $this->getClienteConfig()
        ];

        if ($nfrecebida->xml) {
            $fields['xmlImport'] = json_encode($this->loadXmlImport($nfrecebida->xml));
        }

        if ($mode === "show") {
            $fields['show'] = true;
        }


        if ($mode !== "create") {
            Util::setFieldsFormShowEdit($nfrecebida, $nfrecebidaitems, $fields, "recebida");
        }

        return view('nf.nfe_form', $fields);
    }

    /**
     * @param $id
     * @param Nfrecebida $nfr
     * @return string
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy($id, Nfrecebida $nfr)
    {
        $this->authorize('delete', $nfr);
        $nfrecebida = Nfrecebida::with('freteFinanceiro', 'financeiro')->find($id);
        $this->authorize('igualdade', $nfrecebida);
        DB::beginTransaction();
        try {
            if (is_null($nfrecebida)) {
                throw new Exception("Documento não encontrado");
            }

            $this->estornarFinanceiro($nfrecebida);

            $this->estornoNfRecebidaItem($nfrecebida);

            $nfrecebida->delete();
        } catch (Exception $e) {
            DB::rollback();
            return '<br />Problemas ao realizar exclusão!<br />' . $e->getMessage();
        }
        DB::commit();
        return 'OK|';
    }

    /**
     * valida e trata os dados vindos da view
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    protected function validateData($data)
    {
        if ($data['nfefinalidade'] == 4 && strlen(trim($data['chaveacessoref'])) === 0)
            throw new Exception("É preciso informar a Chave de Referência para NF de finalidade 4.");

        $data['nftipoambiente'] = 1;
        $data['condicaopagamento_id'] = isset($data['condicaopagamento_id']) ? $data['condicaopagamento_id'] : null;

        $data['nfsubserie'] = strlen($data['nfsubserie']) > 0 ? $data['nfsubserie'] : 0;
        $data['chaveacessodv'] = substr($data['chaveacesso'], -1);

        return Util::validateDataGeneral($data);
    }

    /**
     * @param $nfrecebida
     * @return bool
     * @throws Exception
     */
    private function estornoNfRecebidaItem(Nfrecebida $nfrecebida)
    {
        $processor = new NfProcessor();
        $processor->setEmpresa($nfrecebida->empresa_id);
        $processor->estornoItem($nfrecebida, $nfrecebida->nfRecebidaItem);

        $nfrecebida->nfRecebidaVolume()->delete();
        $nfrecebida->nfRecebidaItem()->delete();
        return true;
    }

    /**
     *
     * @param array $data
     * @param Nfrecebida $nfrecebida
     * @param bool $frete
     * @throws Exception
     */
    private function gerarFinanceiro($data, $nfrecebida, $frete = false)
    {
        $nfoperacao = Nfoperacao::find($data['nfoperacao_id']);
        $nfrecebidadb = NFRecebida::find($nfrecebida->id);
        // 0-Nada 1-Pagar 2-Receber
        if ($nfoperacao->movimentafinanceiro == 0 && !$frete) {
            return;
        } else {
            $processor = new NfProcessor();
            $processor->setEmpresa($nfrecebidadb->empresa_id);
            $processor->setNf($nfrecebidadb);
            $dataparsed = Carbon::parse($nfrecebidadb->datahoraemissao);

            if ($frete) {
                $total = $nfrecebidadb->vfrete;
                $desconto = 0;
                $cliente_id = $data["fretecliente_id"];
                $descricao_lanc = "Frete de Lançamento de Documento N°:" . $nfrecebida->nfnumero . ", id:" . $nfrecebida->id . ". " . $nfrecebida->descricaofinanceiro;
                $condicaopagamento_id = $data["fretecondicaopagamento_id"];

                $centrocusto_id = $nfrecebida->fretecentrocusto_id;
                $planoconta_id = $nfrecebida->freteplanoconta_id;

                if (! $centrocusto_id || ! $planoconta_id) {
                    $msg = "Informe um plano de contas e/ou centro de custos para o frete.";
                    throw new Exception($msg);
                }

                if ($nfrecebida->formapagamento == "1") {
                    $pagarreceber = "P";
                } elseif ($nfrecebida->formapagamento == "2") {
                    $pagarreceber = "R";
                } else {
                    //Não gera financeiro
                    return;
                }
            } else {
                $total = $nfrecebidadb->vnf - $nfrecebidadb->vfrete;
                $desconto = $nfrecebidadb->vdesc;
                $cliente_id = $data["cliente_id"];
                $descricao_lanc = "Lançamento de Documento N°:" . $nfrecebida->nfnumero . ", id:" . $nfrecebida->id . ". " . $nfrecebida->descricaofinanceiro;
                $condicaopagamento_id = $data["condicaopagamento_id"];
                $centrocusto_id = $nfrecebida->centrocusto_id;
                $planoconta_id = $nfrecebida->planoconta_id;
                // 0-Nada 1-Pagar 2-Receber
                $pagarreceber = $nfoperacao->movimentafinanceiro == 1 ? "P" : "R";
            }

            $processor->gerarFinanceiro([
                'total'                => $total,
                'desconto'             => $desconto,
                'parcelas'             => calculoParcelas($condicaopagamento_id, $dataparsed, $total),
                'data'                 => $data,
                'cliente_id'           => $cliente_id,
                'descricao_lanc'       => $descricao_lanc,
                'condicaopagamento_id' => $condicaopagamento_id,
                'centrocusto_id'       => $centrocusto_id,
                'planoconta_id'        => $planoconta_id,
                'pagarreceber'         => $pagarreceber,
                'nf'                   => $nfrecebida,
                'nfbd'                 => $nfrecebidadb,
                'frete'                => $frete
            ]);
            return;
        }
    }

    /**
     * @param Nfrecebida $nfrecebida
     * @param bool $frete
     * @throws Exception
     */
    private function estornarFinanceiro($nfrecebida, $frete = false)
    {
        $processor = new NfProcessor();

        if ($frete) {
            $financeiroFreteModel = $nfrecebida->freteFinanceiro;
            $nfrecebida->update(['fretefinanceiro_id' => null]);
            if (!is_null($financeiroFreteModel)) {
                $processor->estornarFinanceiroModel($financeiroFreteModel, $nfrecebida);
            }
        } else {
            $financeiroModel = $nfrecebida->financeiro;
            $nfrecebida->update(['financeiro_id' => null]);

            if (!is_null($financeiroModel)) {
                $processor->estornarFinanceiroModel($financeiroModel, $nfrecebida);
            }

            $nfrecebida->nfRecebidaParcela()->delete();
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function importXml()
    {
        try {
            $file = Input::file('file-upload');

            if (! $file) {
                throw new Exception("Arquivo não encontrado!");
            }

            $content = file_get_contents($file->getRealPath());

            $nfe = $this->loadXmlImport($content);

            $emit = $this->getEmitImport($nfe);

            $transp = $this->getTranspImport($nfe);

            return response()->json([
                "data" => [
                    "nfe"               => $nfe,
                    "cliente_id"        => $emit->id,
                    "fretecliente_id"   => $transp ? $transp->id : "",
                    "xml"               => $content
                ],
                "status" => "OK|",
                "msg" => "OK"
            ]);
        } catch (Exception $e) {
            return response()->json(["data" => [], "status" => "NOK", "msg" => $e->getMessage()]);
        }
    }

    /**
     * @return string
     */
    public function inutilizar()
    {
        try {
            $data = Input::only(["nfmodelo", "nfserie", "nini", "nfim", "xjust"]);
            $msg = "O campo :replace é obrigatório.";
            if (! (isset($data["nfmodelo"]) && $data["nfmodelo"])) {
                throw new Exception(str_replace(":replace", "Modelo", $msg));
            } elseif (! (isset($data["nfserie"]) && $data["nfserie"])) {
                throw new Exception(str_replace(":replace", "Série", $msg));
            } elseif (! (isset($data["nini"]) && $data["nini"])) {
                throw new Exception(str_replace(":replace", "Num Inicial", $msg));
            } elseif (! (isset($data["nfim"]) && $data["nfim"])) {
                throw new Exception(str_replace(":replace", "Num Final", $msg));
            } elseif (! (isset($data["xjust"]) && $data["xjust"])) {
                throw new Exception(str_replace(":replace", "Motivo", $msg));
            }
            if ($data["nfim"] < $data["nini"]) {
                throw new Exception("O Num Final não pode ser menor que o Num Inicial");
            }
            $empresa = Session::get("empresa_padrao");
            $user = \Auth::user();
            $now = CarbonCustom::now();
            $allRecords = collect([]);
            $resultsDb = NfInutilizacaoEntrada::where("empresa_id", $empresa->id)
                ->where("nfmodelo", $data["nfmodelo"])
                ->where("nfserie", $data["nfserie"])
                ->where("nfnumero", ">=", $data["nini"])
                ->where("nfnumero", "<=", $data["nfim"])
                ->get()->implode("nfnumero", ", ");

            if ($resultsDb) {
                throw new Exception("As seguintes numerações já foram inutilizadas para este modelo, série e empresa: " . $resultsDb);
            }

            for ($i = $data["nini"]; $i <= $data["nfim"]; $i++) {
                $inut = [];
                $inut["nfsituacao_id"] = 102;
                $inut["grupo_id"] = $empresa->grupo_id;
                $inut["empresa_id"] = $empresa->id;
                $inut["user_id"] = $user->id;
                $inut["datahora"] = $now;
                $inut["xjust"] = $data["xjust"];
                $inut["nfnumero"] = $i;
                $inut["nfmodelo"] = $data["nfmodelo"];
                $inut["nfserie"] = $data["nfserie"];
                $allRecords->push($inut);
            }
            $inut = new NfInutilizacaoEntrada();
            $inut->insert($allRecords->toArray());
            return "OK|";
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $nfe
     * @return mixed
     * @throws Exception
     */
    public function getEmitImport($nfe)
    {
        $emit = Cliente::whereEmpresaId(Session::get("empresa_padrao")->id);

        if (isset($nfe->emit->CNPJ)) {
            $emit->whereCnpj(maskCNPJ($nfe->emit->CNPJ));
        } else {
            $emit->whereCpf(maskCPF($nfe->emit->CPF));
        }

        $emit = $emit->select("id", "nfemite", "nome", "fornecedor")->get()->first();

        if ($emit === null) {
            $msg = "Não foi possível localizar nenhum Cliente/Fornecedor com o CNPJ/CPF informado no xml. " .
                "Cadastre-o na tela de Clientes e tente novamente!";
            throw new Exception($msg);
        }

        if (! $emit->nfemite) {
            $msg = "Campo \"Emite NFE\" precisa estar habilitado para o Cliente/Fornecedor \"" . $emit->nome . "\". " .
                "Altere-o na tela de Clientes e tente novamente!";
            throw new Exception($msg);
        }

        if (! $emit->fornecedor) {
            $msg = "Campo \"Fornecedor\" precisa estar habilitado para o Cliente/Fornecedor \"" . $emit->nome . "\". " .
                "Altere-o na tela de Clientes e tente novamente!";
            throw new Exception($msg);
        }

        return $emit;
    }

    /**
     * @param $nfe
     * @return mixed
     * @throws Exception
     */
    public function getTranspImport($nfe)
    {
        if (! isset($nfe->transp->transporta->xNome)) {
            return null;
        }

        $transp = Cliente::whereEmpresaId(Session::get("empresa_padrao")->id);

        if (isset($nfe->transp->transporta->CNPJ)) {
            $transp->whereCnpj(maskCNPJ($nfe->transp->transporta->CNPJ));
        } else {
            $transp->whereCpf(maskCPF($nfe->transp->transporta->CPF));
        }

        $transp = $transp->select("id", "nfemite", "nome", "transportador")->get()->first();

        if ($transp === null) {
            $msg = "Não foi possível localizar nenhum Cliente/Transportador com o CNPJ/CPF informado no xml. " .
                "Cadastre-o na tela de Clientes e tente novamente!";
            throw new Exception($msg);
        }

        if (! $transp->transportador) {
            $msg = "Campo \"Transportador\" precisa estar habilitado para o Cliente/Transportador \"" . $transp->nome . "\". " .
                "Altere-o na tela de Clientes e tente novamente!";
            throw new Exception($msg);
        }

        return $transp;
    }

    /**
     * @param $xmlContent
     * @return mixed
     * @throws Exception
     */
    public function loadXmlImport($xmlContent)
    {
        $std = new Standardize();

        $stdXml = $std->toStd($xmlContent);
        if (! isset($stdXml->infNFe)) {
            if (! isset($stdXml->NFe)) {
                throw new Exception("O arquivo selecionado não é um XML de NF-e/NFC-e válido!");
            }
        }

        $nfe = isset($stdXml->NFe) ? $stdXml->NFe->infNFe : $stdXml->infNFe;

        if (isset($nfe->transp->veicTransp)) {
            $nfe->transp->veicTransp->placa = mask($nfe->transp->veicTransp->placa, "###-####");
        }

        $nfe->ide->dhEmi = Carbon::parse($nfe->ide->dhEmi)->format("d/m/Y h:i");
        $nfe->ide->dhSaiEnt = Carbon::parse($nfe->ide->dhSaiEnt)->format("d/m/Y h:i");
        return $nfe;
    }

    /**
     * @return mixed
     */
    private function getClienteConfig()
    {
        $clienteConfig = Session::get("empresa_config")->nfcecliente_id;
        if (! $clienteConfig) {
            Session::flash("message_info", "Não foi possível localizar o cliente Consumidor Final padrão das configurações da empresa.");
        }
        return $clienteConfig;
    }
}
