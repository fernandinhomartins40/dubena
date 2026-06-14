<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResources;
use App\Oauthclient;
use App\Colaboradortelefone;
use App\Colaborador;
use App\Condicaopagamento;
use App\Empresa;
use App\Empresaconfig;
use App\Nfoperacao;
use App\Produto;
use App\Cliente;
use App\Veiculo;
use App\Pedido;
use App\Pedidoitem;
use App\Financeiro;
use App\Processors\BoletoProcessor;
use App\Http\Resources\Classes\AppConfig;
use Carbon\Carbon;
use App\Pedidosituacao as Situacao;
use App\Processors\MobileAppProcessor;
use App\Repository\MobileRepository;
use DB;
use Exception;
use GuzzleHttp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Input;
use Intervention\Image\ImageManagerStatic as Image;
use InvalidArgumentException;
use stdClass;
use Storage;
use App\Http\Requests\PedidoRequest;
use App\Http\Controllers\NfemitidaController;
use App\Nfemitida;
use App\Nfemitidaitem;
use App\Segmento;
use App\Estado;
use App\Tipopessoa;
use App\Rua;
use App\Bairro;
use App\Cidade;
use App\Telefonetipo;
use App\Http\Controllers\ClienteController;
use App\Http\Requests\ClienteRequest;
use App\Clientetelefone;
use App\Financeiroparcela;
use Session;
use App\Boleto;
use App\Clienteproduto;
use App\Conta;
use App\Helpers\Utils\BoletoUtil as BoletoUtil;
use App\Helpers\Utils\NfUtil as NfUtil;
use App\Helpers\Utils\PedidoUtil as PedidoUtil;
use Config;
use Log;

class NfwebController extends Controller
{

    public function logComponentCrash()
    {
        try {
            $now = Carbon::now();
            $errors = "message: " . Input::get("error_message", "");
            $errors .= " | stack: " . Input::get("stack", "");
            Storage::disk("errors")
                ->append(
                    "component_crash " . $now->month . "-" . $now->year . ".log",
                    $now->toDateTimeString() . " | " . $errors
                );
            return responseSuccess();
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     * @throws GuzzleHttp\Exception\GuzzleException
     */

    public function getToken()
    {
        if (Input::get('app_key') !== sha1(env('APP_KEY'))) {
            //        if (Input::get('app_key') !== env('APP_KEY')) {
            return response()->json([
                'msg'       => 'app_key is not found: ' . sha1(env('APP_KEY')),
                'status'    => 'NOK'
            ], 404);
        } else {
            try {
                $pass = encodeSecret('oauthclient@admin');
                $client = $this->createClient($pass);

                $response = $this->sendHttpRequest($client, $pass);

                if (isset($response->access_token)) {
                    $response->client_id = $client->id;
                    $response->client_secret = $pass;
                }

                return response()->json([
                    'data'      => $response,
                    'status'    => 'OK'
                ], 200);
            } catch (Exception $e) {
                return response()->json([
                    'msg' => $e->getMessage(),
                    'status' => 'NOK'
                ], 400);
            }
        }
    }

    public function login(Request $request)
    {
        try {
            $this->throwIf(! $request->get("phone"), 'Campo "phone" não informado');

            $telefones = Colaboradortelefone::where('telefone', $request->get("phone"))->get();
            $this->throwIf(count($telefones) == 0, "Colaborador não encontrado com esse telefone", 102);

            $colaborador = Colaborador::findOrFail($telefones->first()->colaborador_id);
            $this->throwIf(! $colaborador, "Colaborador não encontrado", 102);
            $this->throwIf(! $colaborador->ativo, "Colaborador está inativo, login não permitido", 102);
            $colaborador->telefone = $request->get("phone");


            return responseSuccess($colaborador);
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage()) : responseError($e->getMessage());
        }
    }

    public function changeRegistrationId(Request $request)
    {
        try {

            $this->throwIf(! $request->get("pushregistration_id"), 'Campo "pushregistration_id" não informado');
            $this->throwIf(! $request->get("collaborator_id"), 'Campo "collaborator_id" não informado');

            $colaborador = Colaborador::findOrFail($request->get("collaborator_id"));

            $this->throwIf(! $colaborador, "Colaborador não encontrado", 101);
            $this->throwIf(! $colaborador->ativo, "Colaborador está inativo, alteração não permitida", 101);

            $colaborador->update([
                "pushregistration_id" => $request->get("pushregistration_id")
            ]);

            return responseSuccess($colaborador);
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage()) : responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     */
    public function nfwebRootRequest()
    {
        return $this->onOpenNfwebApp();
    }

    /**
     * @deprecated
     * @return JsonResponse
     */
    public function onOpenNfwebApp()
    {
        try {
            $data = new stdClass();

            $telefones = Colaboradortelefone::where('telefone', Input::get('phone'))->get();
            if (count($telefones) == 0) {
                return responseError("Colaborador não encontrado com esse telefone");
            }
            $data->colaborador = Colaborador::findOrFail($telefones->first()->colaborador_id);
            $data->colaborador->telefone = Input::get('phone');

            if (! $data->colaborador) {
                return responseError("Seu cadastro não foi encontrado, entre em contato com a revenda");
            }
            if (! $data->colaborador->ativo) {
                return responseError("Seu cadastro foi inativado, entre em contato com a revenda");
            }
            $data->pagamentos = [];
            $data->operacoes = [];
            //$data->clientes = [];
            $data->produtos = [];
            $data->veiculos = [];

            if ($data->colaborador) {
                $setor = $data->colaborador->setors->first();
                $data->veiculos = $setor->veiculos;

                $data->colaborador->veiculo_id = 0;
                foreach ($data->veiculos as $v) {

                    if ($data->colaborador->id == $v["colaborador_id"]) {
                        $data->colaborador->veiculo_id = $v->id;
                    }
                }
                $empresa = Empresa::find($data->colaborador->empresa_id, ['id', 'nome_informal']);
                $data->revenda = $empresa;
                $data->revenda->presenca_comprador = 1;
                $data->revenda->modalidade_frete = 3;
                $config = DB::table('empresaconfigs')
                    ->where('empresa_id', $empresa->id)
                    ->select('presencacomprador', 'fretemodalidade', 'transportadorpadrao_id', 'pedidooperacao_id')->get()->first();
                $data->revenda->transportador_id = $config->transportadorpadrao_id;
                $data->revenda->setor_id = $setor->id;
                $data->revenda->setor_descricao = $setor->descricao;
                unset($data->colaborador->empresa);
                $data->operacoes = Nfoperacao::where('empresa_id', $empresa->id)
                    ->whereIn('aparecetela', [1, 2])
                    ->where('enviaappnf', true)
                    ->orderBy('descricao')
                    ->select('id', 'descricao')->get();
                $data->produtos = Produto::where('empresa_id', $empresa->id)
                    ->where('ativo', true)
                    ->where('enviaappnf', true)
                    ->orderBy('descricao')
                    ->select('id', 'descricao', 'precovenda')->get();
                /*
                $data->clientes = Cliente::from("clientes as c")
                                         ->leftJoin('ruas as r', 'c.rua_id', 'r.id')
                                         ->leftJoin('bairros as b', 'c.bairro_id', 'b.id')
                                         ->leftJoin('clientes as d', 'c.convenio_id', 'd.id')
                                         ->leftJoin('tipopessoas as e', 'c.tipopessoa_id', 'e.id')
                                         ->where('c.empresa_id', $empresa->id)
                                         ->where('c.setor_id', $setor->id)
                                         ->where('c.ativo', true)
                                         ->orderBy('c.nome')
                                         ->select('c.id', 'c.nome', DB::Raw("coalesce(c.cpf, c.cnpj, '') as cnpjcpf"), 'c.complemento',
                                                  'c.observacoes', 'c.convenio', 'd.nome as convenio_nome', 'e.tipopessoacadastro as tipopessoa',
                                                  'c.nfemite',
                                                  DB::Raw("r.descricao||', '||c.numero||' - '||b.descricao as endereco"))->get();
                */
                $pagamentos = Condicaopagamento::where('empresa_id', $empresa->id)
                    ->where('ativo', true)
                    ->where('enviaappnf', true)
                    ->whereNotNull('pedidosituacaoappnf_id')
                    ->orderBy('descricao')
                    ->get();

                foreach ($pagamentos as $pagamento) {
                    $dias = $pagamento->dias_primeira;
                    if ($pagamento->condicaoPagamentoParcela != null) {
                        foreach ($pagamento->condicaoPagamentoParcela as $parcela) {
                            if ($parcela->dias > $dias) {
                                $dias = $parcela->dias;
                            }
                        }
                    }
                    array_push($data->pagamentos, [
                        "id" => $pagamento->id,
                        "descricao" => $pagamento->descricao,
                        "dias" => $dias,
                        "tipo" => $pagamento->tipo,
                        "cartao" => $pagamento->pedidosituacao->solicitacartaoautorizacao
                    ]);
                }
            }
            return responseSuccess($data);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    public function getParcelasVencidasCliente()
    {
        try {
            $this->throwIf(! Input::get("cliente_id"), 'Campo "cliente_id" não informado');
            $parcelas = Financeiro::from("financeiros f")
                ->join("financeiroparcelas as p", 'f.id', 'p.financeiro_id')
                ->where('cliente_id', Input::get('cliente_id'))
                ->where('baixado', false)
                ->where('datavencimento', '<', Carbon::now()->startOfDay())
                ->orderBy('datavencimento', 'asc')
                ->select(DB::Raw("TO_CHAR(p.datavencimento, 'DD/MM/YYYY') as datavencimento"), 'f.documento', 'f.descricao', 'p.valor')
                ->get();
            return responseSuccess($parcelas);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }


    public function changeVeiculo()
    {
        try {

            $this->throwIf(! Input::get("veiculo_id"), 'Campo "veiculo_id" não informado');
            $this->throwIf(! Input::get("colaborador_id"), 'Campo "colaborador_id" não informado');

            $colaborador = Colaborador::find(Input::get("colaborador_id"));
            $this->throwIf(! $colaborador, "Colaborador não encontrado", 101);
            $veiculo = Veiculo::find(Input::get("veiculo_id"));
            $this->throwIf(! $veiculo, "Veículo não encontrado", 101);

            $veiculos = Veiculo::where('colaborador_id', $colaborador->id);
            $veiculos->update(array('colaborador_id' => null));
            $veiculo->colaborador_id = $colaborador->id;
            $veiculo->save();

            return responseSuccess($colaborador);
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage()) : responseError($e->getMessage());
        }
    }

    public function savePedido()
    {
        try {
            $nfe_consulta = 0;
            $auth = new AuthController();
            $logged = $auth->loginFromApi([
                'email'      => env('DEFAULT_USER_SYSTEM'),
                'password'      => env('DEFAULT_PASSWORD_SYSTEM')
                //"email"     => "qtiqti",
                //"password"  => "1234"
            ]);
            $this->throwIf(!$logged, "Usuário e/ou senha incorretos!", 401);
            $data = json_decode(json_encode(Input::all()["pedido"]), FALSE);
            $informacaocomplementar = '';
            if (isset($data->informacaocomplementar)) {
                $informacaocomplementar = !$data->informacaocomplementar ? '' : $data->informacaocomplementar;
            }
            $emiteNF = $data->emiteNF == "true";
            $emiteNFC = $data->emiteNFC == "true";
            $emiteNFCNaoId = $data->emiteNFCNaoId == "true";
            $emiteBoleto = $data->emiteBoleto == "true";
            $colaborador_id = Input::all()["colaborador_id"];
            $colaborador = Colaborador::find($colaborador_id);
            $order = new stdClass();
            $config = new AppConfig();
            $empresaconfig = Empresaconfig::where('empresa_id', $colaborador->empresa_id)->first();
            $cliente = Cliente::find($data->cliente->id);
            if (isset($data->observacoes)) {
                $cliente->observacoes = $data->observacoes;
                $cliente->save();
            }
            $order->cliente = $cliente;
            $this->throwIf(
                !$order->cliente,
                "Cliente não encontrado na base de dados ID: " . $data->cliente->id,
                101
            );
            $order->cliente->phone = '';
            $telefones = $order->cliente->telefones;
            if (count($telefones) > 0) {
                $order->cliente->phone = $telefones->first()->telefone;
            }
            $order->pagamento = Condicaopagamento::find($data->pagamento->id);
            $this->throwIf(
                !$order->pagamento,
                "Condição de Pagamento não encontrada na base de dados ID: " . $data->pagamento->id,
                101
            );
            //Mudança de regra - mesmo que não informar que emite NF, emitir uma NFCe não identificada
            if (!$emiteNF && !$emiteNFC && $order->pagamento->appnfceauto) {
                $emiteNFC = true;
                $emiteNFCNaoId = true;
            }

            $this->throwIf(
                $order->pagamento->pedidosituacaoappnf_id == null,
                "Status de pedido não vinculado a esta condição de pagamento: " . $order->pagamento->descricao,
                101
            );
            $order->setor = $order->cliente->setor;
            $config->setor_id = $order->cliente->setor_id;
            $this->throwIf(
                !$order->setor,
                "Setor de entrega não encontrado na base de dados",
                101
            );
            $order->setor = $order->cliente->setor;
            $this->throwIf(
                !$order->setor,
                "Setor de entrega não encontrado na base de dados",
                101
            );
            //TRATAR OPERAÇÃO PEDIDO
            //Colocar na config
            $order->pedidooperacao_id = $empresaconfig->pedidooperacaoappnf_id;
            $order->colaborador_id = $colaborador_id;
            $config->colaborador_id = $colaborador_id;
            //TRATAR SITUACAO
            $order->pedidosituacao_id = $order->pagamento->pedidosituacaoappnf_id;
            $order->situacao = Situacao::find($order->pedidosituacao_id);
            $this->throwIf(
                !$order->situacao,
                "Situação de Pedido não encontrada na base de dados ID: " . 25,
                101
            );
            $address = new stdClass();
            $address->cidade_id = $order->cliente->cidade_id;
            $address->rua_id = $order->cliente->rua_id;
            $address->bairro_id = $order->cliente->bairro_id;
            $address->cep = $order->cliente->cep;
            $address->uf = $order->cliente->uf;
            $address->numero = $order->cliente->numero;
            $address->pontoreferencia = $order->cliente->ponto_referencia;
            $address->complemento = $order->cliente->complemento;
            //INCLUIR NUMERO, PONTO REFERENCIA, OBSERVACOES
            $order->endereco = $address;
            $order->pedido_id = -1;

            $order->items = array();
            foreach ($data->produtos as $produto) {
                $item = new stdClass();
                $item->produto_id = $produto->id;
                $item->product = $produto;
                $item->precovendaunitario = $produto->preco;
                $item->quantidade = $produto->qtde;
                array_push($order->items, $item);
            }
            $processor = new MobileAppProcessor();
            $processor->checkForConvenio($order);
            $order->valorvenda = $data->totalProdutos;
            $order->valordesconto = $data->desconto == "" ? 0 : $data->desconto;
            $order->valorvenda -= $order->valordesconto;
            $order->datahoraprevisao = Carbon::now();
            $order->cartaoautorizacao = $data->autorizacao;
            $order->nfweb = true;
            //$order->valorvenda -= (isset($order->valordesconto)?$order->valordesconto:0);
            $order->nfweb_vencimento = Carbon::createFromFormat('d/m/Y', substr($data->dataVencimento, 0, 10));
            $order = MobileRepository::createOrder($order, $config, true);
            $retorno = "Pedido nº " . $order->id . ". ";

            $erronf = false;
            $numnf = "";
            if ($emiteNFC) {
                try {
                    $dataupd = [
                        "nfcpfcnpj" => $emiteNFCNaoId ? '' : $order->cliente->cpf,
                        "nftipo" => $emiteNFCNaoId ? "0" : "1", //0 = não identificado 1 = identificado
                        "indicador_ie" => $emiteNFCNaoId ? 9 : $order->cliente->indicador_ie,
                        "transportador_id" => null
                    ];
                    $pedidoC = new PedidoController();
                    $request = new PedidoRequest();
                    $request->replace($dataupd);
                    $response = $pedidoC->updateDadosNf($request, $order->id);
                    if ($response != "OK|") {
                        $retorno = "Pedido " . $order->id . " gerado, ocorreu um erro ao atualizar a NFCe: " . $response;
                        $erronf = true;
                    } else {
                        $datanf = [
                            "indicador_ie" => $emiteNFCNaoId ? 9 : $order->cliente->indicador_ie,
                            "transportador_id" => "null",
                            "freteplaca" => "",
                            "freteplacauf" => "",
                            "fretecondicaopagamento_id" => null,
                            "formapagamento" => "0",
                            "empresa_documento" => $order->cliente->empresa_id,
                            "appinformacaocomplementar" => $informacaocomplementar
                        ];
                        $nfemitidaC = new NfemitidaController();
                        $request = new PedidoRequest();
                        $request->replace($datanf);
                        $response = $nfemitidaC->processorPedidoNf($request, $order->id);

                        $resp = $response->original;
                        //return responseError(json_encode($response));
                        if ($resp["status"] == "OK") {
                            $numnf = $resp["nfnumero"];
                            $retorno .= "NFCe nº " . $resp["nfnumero"] . ".";
                            try {
                                $myRequest = new Request();
                                $myRequest->setMethod('GET');
                                $myRequest->request->add(['id' => $resp["id"]]);
                                $ret = $nfemitidaC->transmitirnf($resp["id"]);
                                //return responseError($ret);
                                $retorno .= $ret;
                            } catch (Exception $etrans) {
                                $retorno .= " Houve um erro ao transmitir a NFCe: " . $etrans->getMessage();
                            }
                        } else {
                            $retorno = "Pedido " . $order->id . " gerado, ocorreu um erro ao gerar a NFCe: " . $resp["msg"];
                            $erronf = true;
                        }
                    }
                } catch (Exception $enf) {
                    $retorno = "Pedido " . $order->id . " gerado, ocorreu um erro ao gerar a NFCe: " . $enf->getMessage() . " " . $enf->getFile() . " " . $enf->getLine();
                    $erronf = true;
                }
            } elseif ($emiteNF) {
                try {
                    $setor = $colaborador->setors->first();
                    $veiculo = null;
                    foreach ($setor->veiculos as $v) {
                        if ($colaborador->id == $v["colaborador_id"]) {
                            $veiculo = $v;
                        }
                    }
                    $dataupd = [
                        "nfcpfcnpj" => $order->cliente->cpf,
                        "nftipo" => "1", //0 = não identificado 1 = identificado
                        "indicador_ie" => $order->cliente->indicador_ie,
                        "transportador_id" => $empresaconfig->transportadorappnf_id,
                    ];
                    $pedidoC = new PedidoController();
                    $request = new PedidoRequest();
                    $request->replace($dataupd);
                    $response = $pedidoC->updateDadosNf($request, $order->id, true);
                    if ($response != "OK|") {
                        $retorno = "Pedido " . $order->id . " gerado, ocorreu um erro ao atualizar a NF: " . $response;
                        $erronf = true;
                    } else {
                        $datanf = [
                            "indicador_ie" => $order->cliente->indicador_ie,
                            "transportador_id" => $empresaconfig->transportadorappnf_id,
                            "freteplaca" => $veiculo ? $veiculo->placa : "",
                            "freteplacauf" => $veiculo ? $veiculo->placauf : "",
                            "fretecondicaopagamento_id" => "null",
                            "formapagamento" => "0",
                            "empresa_documento" => $order->cliente->empresa_id,
                            "nfoperacao_id" => $data->operacao->id,
                            "appinformacaocomplementar" => $informacaocomplementar
                        ];
                        $nfemitidaC = new NfemitidaController();
                        $request = new PedidoRequest();
                        $request->replace($datanf);
                        $response = $nfemitidaC->processorPedidoAppnf($request, $order->id);

                        $resp = $response->original;
                        //return responseError(json_encode($resp["id"]));
                        if ($resp["status"] == "OK") {
                            $numnf = $resp["nfnumero"];
                            $retorno .= " NFe nº " . $resp["nfnumero"] . ".";
                            try {
                                $myRequest = new Request();
                                $myRequest->setMethod('GET');
                                $myRequest->request->add(['id' => $resp["id"]]);
                                $ret = $nfemitidaC->transmitirnf($resp["id"]);
                                $retorno .= $ret;
                                /*
                                $nfEmpty = new Nfemitida();
                                for($i = 0; $i<4; $i++){
                                    sleep(5);
                                    $result = $nfemitidaC->consultarnfAppNF($nfEmpty, $resp["id"]);
                                    if($result["erro"]){
                                        sleep(5);
                                    } else {
                                        break;
                                    }
                                }
                                */
                            } catch (Exception $etrans) {
                                $retorno .= " Houve um erro ao transmitir a NF: " . $etrans->getMessage();
                            }
                        } else {
                            $retorno = "Pedido " . $order->id . " gerado, ocorreu um erro ao gerar a NF: " . $resp["msg"];
                            $erronf = true;
                        }
                    }
                } catch (Exception $enf) {
                    $retorno = "Pedido " . $order->id . " gerado, ocorreu um erro ao gerar a NF: " . $enf->getMessage(); //." ".$enf->getFile()." ".$enf->getLine();
                    $erronf = true;
                }
            }
            if ($numnf != "") {
                if (PedidoUtil::allwedToCreateFinanceiro($order->condicaopagamento, $order->pedidosituacao)) {
                    $fin = $order->financeiro;
                    $fin->documento = $numnf;
                    $fin->save();
                }
            }
            if ($erronf) {
                return responseError($retorno);
            } else {
                if ($emiteBoleto) {
                    $parcelas = [];
                    foreach ($order->financeiro->financeiroparcela as $parc) {
                        $newparc = new stdClass();
                        $newparc->id = $parc->id;
                        $newparc->numero = $parc->numero;
                        $newparc->datacompetencia = Carbon::parse($parc->datacompetencia)->format('d/m/Y');
                        $newparc->datavencimento = Carbon::parse($parc->datavencimento)->format('d/m/Y');
                        $newparc->valor = requestNumeroDecimalOracle($parc->valor);
                        $newparc->juros = 0;
                        $newparc->multa = 0;
                        $newparc->valorefetivado = $parc->valorefetivado;
                        $newparc->cliente_id = $order->cliente_id;
                        array_push($parcelas, $newparc);
                    }
                    $data = [
                        'inseredescparcela' => 'true',
                        'conta_id' => $empresaconfig->contaappnf_id,
                        'parcelas' => json_encode($parcelas),
                    ];

                    $processor = new BoletoProcessor();
                    $ret =  $processor->gerarBoleto($data, true);
                    if ($ret["status"] != 'OK|') {
                        if ($numnf != "") {
                            //$this->consultarStatusNF($numnf);
                        }
                        return responseError($retorno . ". " . $ret["msg"]);
                    } else {
                        $dir = BoletoUtil::savePDF($order->empresa_id, base64_decode($ret["data"]), str_pad($order->id, 7, "0", STR_PAD_LEFT));
                        if ($numnf != "") {
                            //$this->consultarStatusNF($numnf);
                        }
                        return responseSuccess($order->id, $retorno . ". Boleto gerado com sucesso");
                    }
                } else {
                    if ($numnf != "") {
                        //$this->consultarStatusNF($numnf);
                    }
                    return responseSuccess($order->id, $retorno);
                }
            }
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getLine()) : responseError($e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
        }
    }
    public function enviarEmail()
    {
        try {
            $auth = new AuthController();
            $logged = $auth->loginFromApi([
                'email'      => env('DEFAULT_USER_SYSTEM'),
                'password'      => env('DEFAULT_PASSWORD_SYSTEM')
                //"email"     => "qtiqti",
                //"password"  => "1234"
            ]);
            if (! Input::get("pedido_id")) {
                throw new Exception('Campo "pedido_id" não informado', 101);
            }
            $pedido_id = Input::get("pedido_id");
            $pedido = Pedido::findOrFail($pedido_id);
            $email = $pedido->cliente->email;
            $temnf = false;
            if ($pedido->nfce_id != null && $pedido->nfce_id > 0) {
                //Gravar danfe para envio por email
                $nfemitidaC = new NfemitidaController();
                $nfEmpty = new Nfemitida();
                for ($i = 0; $i < 4; $i++) {
                    $result = $nfemitidaC->consultarnfAppNF($nfEmpty, intval($pedido->nfce_id));
                    if ($result["erro"]) {
                        sleep(5);
                    } else {
                        $temnf = true;
                    }
                }
            }
            $files = array();

            if ($email == null || $email == '') {
                return responseSuccess("SEM EMAIL");
            }
            $boleto = BoletoUtil::getPDFFile($pedido->empresa_id, str_pad($pedido->id, 7, "0", STR_PAD_LEFT));
            if (!is_bool($boleto)) {
                array_push($files, $boleto);
            }
            if (!$temnf && is_bool($boleto)) {
                return responseSuccess('Boleto e NF não encontrados');
            }

            $configempresa = DB::table('empresaconfigs as c')
                ->where('c.empresa_id', $pedido->empresa_id)
                ->get()->first();
            if (is_null($configempresa)) {
                throw new Exception("Não foi possível localizar as configurações da empresa");
            }
            if ($temnf) {
                $nfemitida = Nfemitida::find($pedido->nfce_id);
                if ($nfemitida->cliente_id != $configempresa->nfcecliente_id) {
                    $type = $nfemitida->nfmodelo == 55 ? 'danfe' : 'danfce';
                    $arq = (NfUtil::getPDFPath($nfemitida->chaveacesso, $nfemitida->emitcnpj, $type));
                    if (file_exists($arq)) {
                        array_push($files, $arq);
                    }
                }
            }
            try {
                if (count($files) > 0) {
                    $request = new \Illuminate\Http\Request();
                    $request->replace([
                        'empresa' => 'false',
                        'empresa_id_config' => $pedido->empresa_id,
                    ]);
                    $config = setMailConfig($request, false);
                    Config::set('mail', $config);

                    sendMail([
                        'content' => $configempresa->emailcorpo,
                        'subject' => "Arquivos " . $pedido->empresa->razao_social,
                        'to'      => [
                            $pedido->cliente->email
                        ],
                        'files'   => $files
                    ]);
                    // unlink($files->namezip);
                }
            } catch (\RuntimeException $e) {
                return responseError($e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
            } catch (Exception $e) {
                return responseError($e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
            }
            return responseSuccess("e-mail enviado com sucesso");
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage()) : responseError($e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
        }
    }

    public function pedidoConsulta()
    {
        try {
            if (! Input::get("colaborador_id")) {
                throw new Exception('Campo "colaborador_id" não informado', 101);
            }

            if (! Input::get("pedido_id")) {
                throw new Exception('Campo "pedido_id" não informado', 101);
            }

            $colaborador_id = Input::get("colaborador_id");

            $pedido_id = Input::get("pedido_id");

            if ($colaborador_id == 108) {
                //$colaborador_id = 431;
            }

            $colaborador = Colaborador::findOrFail($colaborador_id);

            $pedidos = Pedido::from('pedidos as p')
                ->join('clientes as c', 'c.id', 'p.cliente_id')
                ->leftJoin('ruas as r', 'p.entregarua_id', 'r.id')
                ->leftJoin('bairros as b', 'p.entregabairro_id', 'b.id')
                ->leftJoin('condicaopagamentos as d', 'p.condicaopagamento_id', 'd.id')
                ->leftJoin('pedidosituacaos as e', 'p.pedidosituacao_id', 'e.id')
                ->leftJoin('setors as s', 'p.entregasetor_id', 's.id')
                ->leftJoin('nfemitidas as nf', 'p.nfce_id', 'nf.id')
                ->where('p.empresa_id', $colaborador->empresa_id)
                ->where('p.colaborador_id', $colaborador->id)
                ->where('p.id', $pedido_id)
                ->orderBy('p.datahoraacao', 'DESC')
                ->selectRaw(
                    "p.id, p.cliente_id, p.condicaopagamento_id, p.datahoraacao, TO_CHAR(p.datahoraacao, 'DD/MM/YYYY HH24:MI:SS') as datahoraacao, p.valorvenda, p.valordesconto, " .
                        "p.entregapontoreferencia, p.financeiro_id, p.nfce_id, p.entregacomplemento, " .
                        "d.descricao as condicao_pagamento, e.descricao as status, s.descricao as setor, " .
                        "c.nome, coalesce(c.cpf, c.cnpj, '') as cnpjcpf, c.observacoes as cli_obs, " .
                        "r.descricao||', '||p.entreganumero||' - '||b.descricao as endereco, p.financeiro_id, " .
                        "nf.nfnumero, nf.nfmodelo, CASE WHEN nf.nfmodelo = '65' THEN 'NFCe '||nf.nfnumero WHEN nf.nfmodelo = '55' THEN 'NFe '||nf.nfnumero ELSE '' END as nfnum "
                )->get();

            if (count($pedidos) == 0) {
                /*
                try {
                    Log::info("colaborador ".$colaborador->id . PHP_EOL);
                    Log::info("empresa ".$colaborador->empresa_id . PHP_EOL);
                    Log::info("pedido ".$pedido_id . PHP_EOL);
                    Log::info("NAO ACHOU PEDIDO" . PHP_EOL);
                } catch(\Exception $e) {
                }
                */
                return responseSuccess(collect([]));
            }

            $pedidosRequest = collect([]);
            foreach ($pedidos as $pedido) {
                $items = array();
                foreach (Pedidoitem::where('pedido_id', $pedido->id)->get() as $item) {
                    $newItem = new stdClass();
                    $newItem->produto_id = $item->produto_id;
                    $newItem->produto_descricao = $item->produto->descricao;
                    $newItem->quantidade = $item->quantidade;
                    $newItem->precounitario = $item->precovendaunitario;
                    $newItem->valortotal = $item->precovendatotal;
                    array_push($items, $newItem);
                }
                $pedido->financeiroparcela_id = null;
                $pedido->boleto_id = null;
                $fin = Financeiro::find($pedido->financeiro_id);
                if ($fin) {
                    $parcela = $fin->financeiroparcela->first();
                    $pedido->financeiroparcela_id = $parcela->id;
                    if ($parcela && $parcela->boleto) {
                        $pedido->boleto_id = $parcela->boleto->id;
                    }
                }

                $pedido->items = $items;
                $pedidosRequest->push($pedido);
            }
            return responseSuccess($pedidosRequest);
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage()) : responseError($e->getMessage());
        }
    }

    public function nfeConsulta()
    {
        try {
            if (! Input::get("colaborador_id")) {
                throw new Exception('Campo "colaborador_id" não informado', 101);
            }

            if (! Input::get("nfce_id")) {
                throw new Exception('Campo "nfce_id" não informado', 101);
            }

            $colaborador_id = Input::get("colaborador_id");
            $nfce_id = Input::get("nfce_id");

            $colaborador = Colaborador::findOrFail($colaborador_id);

            $nf = Nfemitida::from('nfemitidas as p')
                ->where('p.empresa_id', $colaborador->empresa_id)
                ->where('p.id', $nfce_id)
                ->orderBy('p.id', 'DESC')
                ->selectRaw(
                    "	ID , NFOPERACAO_ID, CHAVEACESSO , CHAVEACESSODV , CFOP , DESCRICAOOPERACAO , FORMAPAGAMENTO, NFMODELO , NFSERIE , NFNUMERO ," .
                        " DATAHORAEMISSAO ,DATAHORAENTRADASAIDA ,TIPO ,NFTIPOIMPRESSAO ,NFTIPOEMISSAO ,NFTIPOAMBIENTE ,NFEFINALIDADE ,NFPROCESSOEMISSAO ,NFVERSAOPROCESSAMENTO ,EMITCNPJ ,EMITCPF ,EMITRAZAOSOCIAL ,EMITNOMEFANTASIA ," .
                        " EMITENDERECO||', '||EMITNUMERO AS EMITENDERECO, EMITNUMERO, EMITCOMPLEMENTO ,EMITBAIRRO," .
                        " EMITCIDADE_ID ,EMITCIDADENOME ,EMITCIDADECODIGOIBGE ,EMITUF ,EMITUFCODIGOIBGE ,EMITCEP ,EMITPAISNOME ,EMITPAISCODIGOIBGE ,EMITIE ,EMITINSCRICAOMUNICIPAL,EMITCNAE," .
                        " COALESCE(DESTCNPJ, DESTCPF) AS DESTCPF, DESTRAZAOSOCIAL ," .
                        " DESTENDERECO||', '||DESTNUMERO AS DESTENDERECO, DESTNUMERO ,COALESCE(DESTCOMPLEMENTO, '') AS DESTCOMPLEMENTO,DESTBAIRRO ,DESTCIDADE_ID ,DESTCIDADENOME ,DESTCIDADECODIGOIBGE ,DESTUF ,DESTCEP ,DESTPAISCODIGOIBGE ,DESTPAISNOME ,DESTINDICADORIE ,DESTEMAIL,FRETEMODALIDADE ,FRETECPF ,FRETECNPJ ,FRETERAZAOSOCIAL ,FRETEENDERECOCOMPL ,FRETECIDADENOME ,FRETUF ,FRETIE ,FRETEPLACA,FRETEPLACAUF, " .
                        " COALESCE(INFORMACAOCOMPLEMENTAR, '') AS INFORMACAOCOMPLEMENTAR, COALESCE(INFORMACAOADICIONALFISCO, '') AS INFORMACAOADICIONALFISCO, CLIENTE_ID ,CODCNF ,CODCDV ,CODCRT ,NITEM ,VBC ,VICMS ,VBCST ,VST ,VPROD ,VFRETE ,VSEG ,VDESC ,VII ,VIPI ,VPIS ,VCOFINS ,VOUTRO ,VNF ,NFSITUACAO_ID ,FINANCEIRO_ID ,FRETEFINANCEIRO_ID ,PLANOCONTA_ID ,CENTROCUSTO_ID ,FRETEPLANOCONTA_ID ,FRETECENTROCUSTO_ID ,USER_ID ,FRETECLIENTE_ID ,CONDICAOPAGAMENTO_ID ,VBCFUNRURAL ,VPFUNRURAL ,VFUNRURAL ,EMISSAO ,DESCRICAOFINANCEIRO,DATAHORAAUTORIZACAO,EMITTELEFONE ,DESTTELEFONE ,NUMERORECIBOENVIO ,PLANOCONTADESCRICAO,NATUREZASPED ,COALESCE(DESTIE, '') AS DESTIE , VFCPSTRET,VFCPST,VICMSUFREMET,VICMSUFDEST,VFCPUFDEST,VICMSDESON,VFCP,DESCRICAOSITUACAO,NFC_TPAG,IDDEST ,INDFINAL, XMLRETORNO, PROTOCOLO, XMLRETORNOCOMPLETO "
                )->get();

            if (count($nf) == 0) {
                return responseSuccess(collect([]));
            }
            $auth = new AuthController();
            $logged = $auth->loginFromApi([
                'email'      => env('DEFAULT_USER_SYSTEM'),
                'password'      => env('DEFAULT_PASSWORD_SYSTEM')
                //"email"     => "qtiqti",
                //"password"  => "1234"
            ]);

            $nfemitidaC = new NfemitidaController();
            $nf1 = new Nfemitida();
            $nfRequest = collect([]);
            foreach ($nf as $n) {
                $result = $nfemitidaC->consultarnfAppNF($nf1, intval($nfce_id));
                if ($result["erro"]) {
                    return responseError($result["msg"]);
                }
                if ($result["cStat"] != 100) {
                    return responseError('Nota Fiscal não autorizada: ' . $result["cStat"]);
                }
                if ($n->nfsituacao_id != 100) {
                    $n = Nfemitida::from('nfemitidas as p')
                        ->where('p.empresa_id', $colaborador->empresa_id)
                        ->where('p.id', $nfce_id)
                        ->orderBy('p.id', 'DESC')
                        ->selectRaw(
                            "	ID , NFOPERACAO_ID, CHAVEACESSO , CHAVEACESSODV , CFOP , DESCRICAOOPERACAO , FORMAPAGAMENTO, NFMODELO , NFSERIE , NFNUMERO ," .
                                " DATAHORAEMISSAO ,DATAHORAENTRADASAIDA ,TIPO ,NFTIPOIMPRESSAO ,NFTIPOEMISSAO ,NFTIPOAMBIENTE ,NFEFINALIDADE ,NFPROCESSOEMISSAO ,NFVERSAOPROCESSAMENTO ,EMITCNPJ ,EMITCPF ,EMITRAZAOSOCIAL ,EMITNOMEFANTASIA ," .
                                " EMITENDERECO||', '||EMITNUMERO AS EMITENDERECO, EMITNUMERO, EMITCOMPLEMENTO ,EMITBAIRRO," .
                                " EMITCIDADE_ID ,EMITCIDADENOME ,EMITCIDADECODIGOIBGE ,EMITUF ,EMITUFCODIGOIBGE ,EMITCEP ,EMITPAISNOME ,EMITPAISCODIGOIBGE ,EMITIE ,EMITINSCRICAOMUNICIPAL,EMITCNAE," .
                                " COALESCE(DESTCNPJ, DESTCPF) AS DESTCPF, DESTRAZAOSOCIAL ," .
                                " DESTENDERECO||', '||DESTNUMERO AS DESTENDERECO, DESTNUMERO ,COALESCE(DESTCOMPLEMENTO, '') AS DESTCOMPLEMENTO,DESTBAIRRO ,DESTCIDADE_ID ,DESTCIDADENOME ,DESTCIDADECODIGOIBGE ,DESTUF ,DESTCEP ,DESTPAISCODIGOIBGE ,DESTPAISNOME ,DESTINDICADORIE ,DESTEMAIL,FRETEMODALIDADE ,FRETECPF ,FRETECNPJ ,FRETERAZAOSOCIAL ,FRETEENDERECOCOMPL ,FRETECIDADENOME ,FRETUF ,FRETIE ,FRETEPLACA,FRETEPLACAUF, " .
                                " COALESCE(INFORMACAOCOMPLEMENTAR, '') AS INFORMACAOCOMPLEMENTAR, COALESCE(INFORMACAOADICIONALFISCO, '') AS INFORMACAOADICIONALFISCO, CLIENTE_ID ,CODCNF ,CODCDV ,CODCRT ,NITEM ,VBC ,VICMS ,VBCST ,VST ,VPROD ,VFRETE ,VSEG ,VDESC ,VII ,VIPI ,VPIS ,VCOFINS ,VOUTRO ,VNF ,NFSITUACAO_ID ,FINANCEIRO_ID ,FRETEFINANCEIRO_ID ,PLANOCONTA_ID ,CENTROCUSTO_ID ,FRETEPLANOCONTA_ID ,FRETECENTROCUSTO_ID ,USER_ID ,FRETECLIENTE_ID ,CONDICAOPAGAMENTO_ID ,VBCFUNRURAL ,VPFUNRURAL ,VFUNRURAL ,EMISSAO ,DESCRICAOFINANCEIRO,DATAHORAAUTORIZACAO,EMITTELEFONE ,DESTTELEFONE ,NUMERORECIBOENVIO ,PLANOCONTADESCRICAO,NATUREZASPED ,COALESCE(DESTIE, '') AS DESTIE , VFCPSTRET,VFCPST,VICMSUFREMET,VICMSUFDEST,VFCPUFDEST,VICMSDESON,VFCP,DESCRICAOSITUACAO,NFC_TPAG,IDDEST ,INDFINAL, XMLRETORNO, PROTOCOLO, XMLRETORNOCOMPLETO "
                        )->first();
                }
                $items = array();
                foreach (Nfemitidaitem::where('nfemitida_id', $n->id)->get() as $item) {
                    array_push($items, $item);
                }
                $n->items = $items;
                $parcelas = array();

                $financeiro = Pedido::where('nfce_id', $n->id)->first()->financeiro;
                $financeiros = $financeiro->financeiroparcela;
                $n->condicao_pagamento = $financeiro->condicaopagamento->descricao;
                foreach ($financeiros as $fin) {
                    array_push($parcelas, $fin);
                }
                $n->parcelas = $parcelas;
                $xmlret = $n->xmlretornocompleto;
                if (strpos($xmlret, '<dhRecbto>') > 0) {
                    $n->datahora_autorizacao = substr(str_replace("T", " ", explode('</dhRecbto>', explode('<dhRecbto>', $xmlret)[1])[0]), 0, 19);
                } else {
                    return responseError('A Receita Estadual ainda não autorizou a NF. Tente novamente daqui a alguns segundos.');
                }
                if (strpos($xmlret, '<vTotTrib>') > 0) {
                    $n->vTotTrib = explode('</vTotTrib>', explode('<vTotTrib>', $xmlret)[1])[0];
                } else {
                    $n->vTotTrib = "0";
                }
                if (strpos($xmlret, '<infCpl>') > 0) {
                    $n->infCpl = explode('</infCpl>', explode('<infCpl>', $xmlret)[1])[0];
                } else {
                    $n->infCpl = "";
                }
                if (strpos($xmlret, '<qrCode>') > 0) {
                    $n->qrCode = explode('</qrCode>', explode('<qrCode>', $xmlret)[1])[0];
                } else {
                    $n->qrCode = 'teste';
                }
                unset($n->xmlretornocompleto);
                $nfRequest->push($n);
            }
            return responseSuccess($nfRequest);
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getLine()) : responseError($e->getMessage() . " " . $e->getLine());
        }
    }

    public function pedidoDuplicata()
    {
        try {
            if (! Input::get("colaborador_id")) {
                throw new Exception('Campo "colaborador_id" não informado', 101);
            }

            if (! Input::get("pedido_id")) {
                throw new Exception('Campo "pedido_id" não informado', 101);
            }

            $colaborador_id = Input::get("colaborador_id");
            $pedido_id = Input::get("pedido_id");

            $colaborador = Colaborador::findOrFail($colaborador_id);


            $pedidos = Pedido::from('pedidos as p')
                ->join('clientes as c', 'c.id', 'p.cliente_id')
                ->join('empresas as e', 'e.id', 'p.empresa_id')
                ->leftJoin('ruas as re', 'e.rua_id', 're.id')
                ->leftJoin('bairros as be', 'e.bairro_id', 'be.id')
                ->leftJoin('cidades as ce', 'e.cidade_id', 'ce.id')
                ->leftJoin('ruas as r', 'p.entregarua_id', 'r.id')
                ->leftJoin('bairros as b', 'p.entregabairro_id', 'b.id')
                ->leftJoin('cidades as ci', 'p.entregacidade_id', 'ci.id')
                ->leftJoin('condicaopagamentos as d', 'p.condicaopagamento_id', 'd.id')
                ->leftJoin('pedidosituacaos as e', 'p.pedidosituacao_id', 'e.id')
                ->leftJoin('setors as s', 'p.entregasetor_id', 's.id')
                ->where('p.empresa_id', $colaborador->empresa_id)
                ->where('p.colaborador_id', $colaborador->id)
                ->where('p.id', $pedido_id)
                ->orderBy('p.datahoraacao', 'DESC')
                ->selectRaw(
                    "p.id as ID, d.descricao as FORMAPAGAMENTO, p.datahoraacao as DATAHORAEMISSAO, p.datahoraacao as DATAHORAENTRADASAIDA, " .
                        "e.cnpj as EMITCNPJ, e.razao_social AS EMITRAZAOSOCIAL, e.nome_fantasia AS EMITNOMEFANTASIA, " .
                        "re.descricao||', '||e.numero||' - '||be.descricao as EMITENDERECO, e.numero AS EMITNUMERO, e.complemento AS EMITCOMPLEMENTO, " .
                        "be.descricao as EMITBAIRRO, e.cidade_id as EMITCIDADE_ID, ce.descricao as EMITCIDADENOME, e.uf as EMITUF, e.cep AS EMITCEP, " .
                        "e.inscricao_estadual as EMITIE, e.inscricao_municipal AS EMITINSCRICAOMUNICIPAL, " .
                        "coalesce(c.cpf, c.cnpj, ' ') as DESTCPF, c.nome AS DESTRAZAOSOCIAL, r.descricao||', '||p.entreganumero||' - '||b.descricao as DESTENDERECO, " .
                        "p.entreganumero AS DESTNUMERO, coalesce(p.entregacomplemento, '') as DESTCOMPLEMENTO, b.descricao AS DESTBAIRRO, " .
                        "p.entregacidade_id AS DESTCIDADE_ID, ci.descricao AS DESTCIDADENOME, ci.uf AS DESTUF, p.entregacep AS DESTCEP, c.email AS DESTEMAIL," .
                        "p.cliente_id AS CLIENTE_ID, p.condicaopagamento_id, p.valorvenda AS VNF, p.valordesconto AS VDESC, " .
                        "p.entregapontoreferencia, p.financeiro_id, p.nfce_id, COALESCE(c.inscricao_estadual, '') AS DESTIE, e.telefone1 AS EMITTELEFONE,  " .
                        "(select string_agg(tel.telefone, ', ' order by tel.telefone) as telefone " .
                        "from clientetelefones tel where cliente_id = c.id limit 1) as desttelefone"
                )->get();

            if (count($pedidos) == 0) {
                return responseSuccess(collect([]));
            }

            $pedidosRequest = collect([]);
            foreach ($pedidos as $pedido) {
                $items = array();
                $vprod = 0;
                foreach (Pedidoitem::where('pedido_id', $pedido->id)->get() as $item) {
                    $newItem = new stdClass();
                    $newItem->cprod = $item->produto_id;
                    $newItem->xprod = $item->produto->descricao;
                    $newItem->ucom = $item->produto->unidademedida->sigla;
                    $newItem->qcom = $item->quantidade;
                    $newItem->vuncom = $item->precovendaunitario;
                    $newItem->vprod = $item->precovendatotal;
                    $vprod += $item->precovendatotal;
                    array_push($items, $newItem);
                }
                $pedido->financeiroparcela_id = null;
                $pedido->boleto_id = null;
                $parcelas = array();
                $financeiro = Financeiro::find($pedido->financeiro_id);
                $financeiros = $financeiro->financeiroparcela;
                $pedido->condicao_pagamento = $financeiro->condicaopagamento->descricao;
                foreach ($financeiros as $fin) {
                    array_push($parcelas, $fin);
                }
                $pedido->items = $items;
                $pedido->parcelas = $parcelas;
                $pedido->vprod = $vprod;
                $pedidosRequest->push($pedido);
            }
            return responseSuccess($pedidosRequest);
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getLine()) : responseError($e->getMessage() . " " . $e->getLine());
        }
    }

    public function visualizarDanfe()
    {
        try {
            $auth = new AuthController();
            $logged = $auth->loginFromApi([
                'email'      => env('DEFAULT_USER_SYSTEM'),
                'password'      => env('DEFAULT_PASSWORD_SYSTEM')
                //"email"     => "qtiqti",
                //"password"  => "1234"
            ]);
            if (! Input::get("id")) {
                throw new Exception('Campo "id" não informado', 101);
            }
            $request = new \Illuminate\Http\Request();
            $request->setMethod('GET');
            $request->request->add(['id' => Input::get("id")]);
            $nfemitidaC = new NfemitidaController();
            $nf = new Nfemitida();
            $result = $nfemitidaC->consultarnfAppNF($nf);
            if ($result["erro"]) {
                return responseError($result["msg"]);
            } else {
                return (responseSuccess(base64_encode($result["data"])));
            }
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getFile() . " " . $e->getLine) : responseError($e->getMessage());
        }
    }

    public function visualizarBoleto()
    {
        try {
            $auth = new AuthController();
            $logged = $auth->loginFromApi([
                'email'      => env('DEFAULT_USER_SYSTEM'),
                'password'      => env('DEFAULT_PASSWORD_SYSTEM')
                //"email"     => "qtiqti",
                //"password"  => "1234"
            ]);
            if (!Input::get("id")) {
                throw new Exception('Campo "id" não informado', 101);
            }
            if (!Input::get("tipo")) {
                throw new Exception('Campo "tipo" não informado', 101);
            }
            $tipo = Input::get("tipo");
            $parcelas = [];
            $order = Pedido::findOrFail(Input::get("id"));
            foreach ($order->financeiro->financeiroparcela as $parc) {
                $newparc = new stdClass();
                $newparc->id = $parc->id;
                $newparc->numero = $parc->numero;
                $newparc->datacompetencia = Carbon::parse($parc->datacompetencia)->format('d/m/Y');
                $newparc->datavencimento = Carbon::parse($parc->datavencimento)->format('d/m/Y');
                $newparc->valor = $parc->valor;
                $newparc->juros = 0;
                $newparc->multa = 0;
                $newparc->valorefetivado = $parc->valorefetivado;
                $newparc->cliente_id = $order->cliente_id;
                $newparc->nossonumero = $parc->boleto->nossonumero;
                $newparc->dv = $parc->boleto->dv;
                array_push($parcelas, $newparc);
            }
            $empresaconfig = Empresaconfig::where('empresa_id', $order->empresa_id)->first();
            $data = [
                'inseredescparcela' => 'true',
                'conta_id' => $empresaconfig->contaappnf_id,
                'parcelas' => json_encode($parcelas),
            ];

            $processor = new BoletoProcessor();
            $ret =  $processor->gerarBoleto($data, $tipo == "3" ? false : true);
            if ($tipo == "3") {
                return $ret;
            }
            if ($ret["status"] != 'OK|') {
                return responseError($ret["msg"]);
            } else {
                if ($tipo == "1") {
                    return (responseSuccess($ret["data"]));
                } else {
                    $bol = $processor->boletos[0]->getProperties();
                    $pars = [
                        'dataVencimento'         => Carbon::parse($bol->dataVencimento)->format('d/m/Y'),
                        'valor'                  => $bol->valor,
                        'multa'                  => $bol->multa,
                        'juros'                  => $bol->juros,
                        'numero'                 => $parcelas[0]->nossonumero,
                        'numeroDV'               => $parcelas[0]->dv,
                        'numeroDocumento'        => $bol->numeroDocumento,
                        'pagador'                => $bol->pagador,
                        'beneficiario'           => $bol->beneficiario,
                        'agencia'                => $bol->agencia,
                        'conta'                  => $bol->conta,
                        'contaDv'                => $bol->contaDv,
                        'carteira'               => $bol->carteira,
                        'instrucoes'             => $bol->instrucoes[0],
                        'aceite'                 => $bol->aceite,
                        'especieDoc'             => $bol->especieDoc,
                        'desconto'               => "",
                        'jurosApos'              => false,
                        'dataDocumento'          => Carbon::parse($bol->dataDocumento)->format('d/m/Y'),
                        'localPagamento'         => $bol->localPagamento,
                        'cedente'                => $bol->cedente,
                        'banco'                  => $bol->codigoBanco,
                        'bancoDv'                => '7',
                        'codigoBarras'           => $bol->campoCodigoBarras,
                        'linhaDigitavel'         => $bol->campoLinhaDigitavel,
                        'codigoCliente'          => '',
                    ];
                    return responseSuccess($pars);
                }
            }
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getFile() . " " . $e->getLine()) : responseError($e->getMessage());
        }
    }
    public function pedidosReport()
    {
        try {

            if (! Input::get("colaborador_id")) {
                throw new Exception('Campo "colaborador_id" não informado', 101);
            }

            if (! Input::get("initial_date")) {
                throw new Exception('Campo "initial_date" não informado', 101);
            }

            if (! Input::get("final_date")) {
                throw new Exception('Campo "final_date" não informado', 101);
            }

            $initialDate = Carbon::parse(Input::get("initial_date"));
            $finalDate = Carbon::parse(Input::get("final_date"));
            $colaborador_id = Input::get("colaborador_id");
            if ($colaborador_id == 108) {
                $colaborador_id = 431;
            }

            $colaborador = Colaborador::findOrFail($colaborador_id);

            $pedidos = Pedido::from('pedidos as p')
                ->join('clientes as c', 'c.id', 'p.cliente_id')
                ->leftJoin('ruas as r', 'p.entregarua_id', 'r.id')
                ->leftJoin('bairros as b', 'p.entregabairro_id', 'b.id')
                ->leftJoin('condicaopagamentos as d', 'p.condicaopagamento_id', 'd.id')
                ->leftJoin('pedidosituacaos as e', 'p.pedidosituacao_id', 'e.id')
                ->leftJoin('setors as s', 'p.entregasetor_id', 's.id')
                ->leftJoin('nfemitidas as nf', 'p.nfce_id', 'nf.id')
                ->where('p.empresa_id', $colaborador->empresa_id)
                ->where('p.colaborador_id', $colaborador->id)
                ->where(function ($query) {
                    $query->where('e.entregafinalizada', '=', 1)
                        ->orWhere('e.fechadoconcluido', '=', 1);
                })
                ->whereBetween(
                    'datahoraacao',
                    [$initialDate->startOfDay(), $finalDate->endOfDay()]
                )
                ->orderBy('p.datahoraacao', 'DESC')
                ->selectRaw(
                    "p.id, p.cliente_id, p.condicaopagamento_id, p.datahoraacao, TO_CHAR(p.datahoraacao, 'DD/MM/YYYY HH24:MI:SS') as datahoraacao, p.valorvenda, p.valordesconto, " .
                        "p.entregapontoreferencia, p.financeiro_id, p.nfce_id, p.entregacomplemento, " .
                        "d.descricao as condicao_pagamento, e.descricao as status, s.descricao as setor, " .
                        "c.nome, coalesce(c.cpf, c.cnpj, '') as cnpjcpf, c.observacoes as cli_obs, " .
                        "r.descricao||', '||p.entreganumero||' - '||b.descricao as endereco, " .
                        "nf.nfnumero, nf.nfmodelo, CASE WHEN nf.nfmodelo = '55' THEN 'NFCe '||nf.nfnumero WHEN nf.nfmodelo = '65' THEN 'NFe '||nf.nfnumero ELSE '' END as nfnum "
                )->get();

            if (count($pedidos) == 0) {
                return responseSuccess(collect([]));
            }

            $pedidosRequest = collect([]);
            foreach ($pedidos as $pedido) {
                $items = array();
                foreach (Pedidoitem::where('pedido_id', $pedido->id)->get() as $item) {
                    $newItem = new stdClass();
                    $newItem->produto_id = $item->produto_id;
                    $newItem->produto_descricao = $item->produto->descricao;
                    $newItem->quantidade = $item->quantidade;
                    $newItem->precounitario = $item->precovendaunitario;
                    $newItem->valortotal = $item->precovendatotal;
                    array_push($items, $newItem);
                }
                $pedido->items = $items;
                $pedidosRequest->push($pedido);
            }
            return responseSuccess($pedidosRequest);
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage()) : responseError($e->getMessage());
        }
    }




    /**
     * @return JsonResponse
     */
    public function getNotifications()
    {
        try {
            return responseSuccess(
                NotificationRepository::whereCompanyId($this->userAuth->company_id)->whereRead(false)->get()
            );
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function readNotification($id)
    {
        try {
            $notification = NotificationRepository::findOrFail($id, "Notificação");
            $notification->update(["read" => true]);

            return responseSuccess(
                $notification
            );
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     */
    public function indicateCompany()
    {
        try {
            CompanyIndication::create(request()->only(["client_name", "company_phone", "company_name"]));

            return responseSuccess();
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param $pass
     * @return OauthClient
     */
    private function createClient($pass)
    {
        $client = new Oauthclient();
        $client->user_id = null;
        $client->name = 'app client';
        $client->secret = $pass;
        $client->password_client = 1;
        $client->personal_access_client = 1;
        $client->redirect = 'null';
        $client->revoked = 0;
        $client->save();

        return $client;
    }

    /**
     * @param $client
     * @param $pass
     * @return mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    private function sendHttpRequest($client, $pass)
    {
        $request = request();
        $url = env('SECRET_URL', "http://127.0.0.1/ctrl2/public");

        $api = new ApiResources($url, null, "POST");

        $uri = str_replace("api/getToken", "", $request->server("REDIRECT_URL")) . "oauth/token";

        $formParams = [
            'username'      => env('DEFAULT_USER_SYSTEM'),
            'password'      => env('DEFAULT_PASSWORD_SYSTEM'),
            'client_secret' => $pass,
            'scope'         => '',
            'grant_type'    => 'password',
            'client_id'     => $client->id
        ];

        return $api->request($formParams, $uri, false);
    }

    public function testToken()
    {
        return responseSuccess([]);
    }

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function rootRequest()
    {
        return responseError('rootRequest');
        try {

            DB::beginTransaction();

            $data = new stdClass();

            $address = new AddressController();
            $data->address = $address->get(true);
            $data->allAddress = $address->getAll(true);

            $order = new OrderController();
            $data->order = $order->track(true);

            try {
                $client = new ClientController();
                $data->novodispositivo = $client->get(true);
            } catch (Exception $e) {
                return responseReject("Registro não encotnrado(a) no banco de dados", "NOT_FOUND");
            }

            DB::commit();
            return responseSuccess($data);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }


    /**
     * @return JsonResponse
     */
    public function marker()
    {
        $name = Input::get("name", "default");
        if ($name === "utilitario_000") {
            $name = "truck";
        }
        $s = DIRECTORY_SEPARATOR;

        $file = base_path() . $s . 'storage' . $s . $s . 'img' . $s . 'markers' . $s . $name . ".png";
        if (! file_exists($file)) {
            $file = str_replace($name, "default", $file);
        }

        return Image::make($file)->resize(100, 100)->response();
    }

    /**
     * @return JsonResponse|mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function testTokenERP()
    {
        try {
            $params = Input::all();
            if (! isset($params["authorization"]) || (isset($params["authorization"]) && ! $params["authorization"])) {
                throw new InvalidArgumentException("Token não informado");
            }

            try {
                $response = $this->testTokenAPI($params["authorization"], $params["url"]);
            } catch (Exception $e) {
                if ($e->getCode() === 404) {
                    return responseError("URL inválida");
                } else if ($e->getCode() === 401) {
                    return responseError("Token inválido");
                }
                return responseError($e->getMessage());
            }
            return responseSuccess($response);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @param $token
     * @param $url
     * @return mixed
     * @throws GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     */
    public function testTokenAPI($token, $url)
    {
        //        $user = User::findOrFail($user_id);
        $user = null;

        $api = new ApiResources($url . "api/", $user);

        $api->setAuthorizationCode($token);

        return $api->request([], "testTokenAPI");
    }

    public function destroy()
    {
        try {
            $client = Oauthclient::find(Input::get('id', 0));

            if (! $client) {
                throw new Exception("API Client não encontrado.");
            }
            $client->delete();

            return response()->json([
                'data'  => 'OK',
                'msg'   => 'Sucesso!',
                'status' => 'OK'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage()
            ], 400);
        }
    }
    public function getCadastros()
    {
        try {
            $data = new stdClass();

            $telefones = Colaboradortelefone::where('telefone', Input::get('phone'))->get();
            if (count($telefones) == 0) {
                return responseError("Colaborador não encontrado com esse telefone");
            }
            $colaborador = Colaborador::findOrFail($telefones->first()->colaborador_id);

            if (! $colaborador) {
                return responseError("Seu cadastro não foi encontrado, entre em contato com a revenda");
            }
            if (! $colaborador->ativo) {
                return responseError("Seu cadastro foi inativado, entre em contato com a revenda");
            }
            $data->clientes = [];

            if ($colaborador) {
                $setor = $colaborador->setors->first();
                $data->clientes = Cliente::from("clientes as c")
                    ->leftJoin('ruas as r', 'c.rua_id', 'r.id')
                    ->leftJoin('bairros as b', 'c.bairro_id', 'b.id')
                    ->leftJoin('clientes as d', 'c.convenio_id', 'd.id')
                    ->leftJoin('tipopessoas as e', 'c.tipopessoa_id', 'e.id')
                    ->where('c.empresa_id', $colaborador->empresa_id)
                    ->where('c.setor_id', $setor->id)
                    ->where('c.ativo', true)
                    ->orderBy('c.nome')
                    //->where('e.tipopessoacadastro', 'J')
                    //->take(10)
                    ->select(
                        'c.id',
                        'c.nome',
                        DB::Raw("coalesce(c.cpf, c.cnpj, '') as cnpjcpf"),
                        'c.complemento',
                        'c.observacoes',
                        'c.convenio',
                        'd.nome as convenio_nome',
                        'e.tipopessoacadastro as tipopessoa',
                        'c.nfemite',
                        'c.rua_id',
                        'c.email',
                        'c.numero',
                        DB::Raw("r.descricao||', '||c.numero||' - '||b.descricao as endereco")
                    )->get();


                foreach ($data->clientes as $cliente) {
                    $cliente->produtos = Clienteproduto::where('cliente_id', $cliente->id)
                        ->get();
                }

                $data->segmentos = Segmento::where('grupo_id', $colaborador->grupo_id)
                    ->where('ativo', true)
                    ->orderBy('descricao')
                    ->select('id', 'descricao')->get();


                $data->tipopessoas = Tipopessoa::where('grupo_id', $colaborador->grupo_id)
                    ->where('ativo', true)
                    ->orderBy('descricao')
                    ->select('id', 'descricao', 'tipopessoacadastro')->get();

                $data->estados = Estado::orderBy('descricao')
                    ->select('uf', 'descricao')->get();

                $data->cidades = Cidade::orderBy('descricao')
                    ->select('id', 'descricao', 'uf')->get();

                $data->bairros = Bairro::where('grupo_id', $colaborador->grupo_id)
                    ->orderBy('descricao')
                    ->select('id', 'descricao', 'cidade_id')->get();

                $data->ruas = Rua::where('grupo_id', $colaborador->grupo_id)->where('ativo', true)
                    ->orderBy('descricao')
                    ->select('id', 'descricao', 'bairro_id', 'cidade_id', 'cep')->get();

                $data->telefonetipos = Telefonetipo::where('grupo_id', $colaborador->grupo_id)
                    ->where('ativo', true)
                    ->orderBy('descricao')
                    ->select('id', 'descricao')->get();
            }
            return responseSuccess($data);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }
    public function saveCliente()
    {
        try {
            $auth = new AuthController();
            $logged = $auth->loginFromApi([
                'email'      => env('DEFAULT_USER_SYSTEM'),
                'password'      => env('DEFAULT_PASSWORD_SYSTEM')
            ]);
            $this->throwIf(!$logged, "Usuário e/ou senha incorretos!", 401);
            $client = json_decode(json_encode(Input::all()["cliente"]), FALSE);
            $colaborador_id = Input::all()["colaborador_id"];
            $colaborador = Colaborador::find($colaborador_id);

            $sexo = $client->sexo->id == 1 ? 'M' : ($client->sexo->id == 2 ? 'F' : null);
            $nascimento = $client->data_nascimento;

            $fones = [];
            foreach ($client->telefones as $telefone) {
                array_push($fones, $telefone->telefone);
            }
            $telExists = Clientetelefone::where('empresa_id', $colaborador->empresa_id)
                ->whereIn('telefone', $fones)
                ->count();
            if ($telExists > 0) {
                return responseError("Telefone informado já existe em outro cliente");
            }

            $data = [];
            $emiteNF = false;
            if (isset($client->emiteNF)) {
                $emiteNF = $client->emiteNF;
                if ($emiteNF) {
                    $data["nfemite"] = 1;
                }
            }

            $data["nome"] = $client->nome;
            $data["segmento_id"] = $client->segmento->id;
            if ($client->tipopessoa->tipopessoacadastro == 'F') {
                $data["cpf"] = $client->cpf;
                $data["rg"] = $client->rg;
                $data["sexo"] = $sexo;
                $data["datanascimento"] = $nascimento;
                $data["cnpj"] = '';
                $data["inscricao_estadual"] = '';
                $data["tipopessoa_id"] = $client->tipopessoa->id . $client->tipopessoa->tipopessoacadastro;
            } elseif ($client->tipopessoa->tipopessoacadastro == 'J') {
                $data["cnpj"] = $client->cnpj;
                $data["inscricao_estadual"] = $client->ie;
                $data["cpf"] = '';
                $data["rg"] = '';
                $data["tipopessoa_id"] = $client->tipopessoa->id . $client->tipopessoa->tipopessoacadastro;
            } else {
                return responseError('Tipo de pessoa (F/J) não encontrado!');
            }
            //return responseError("A".$data["nfemite"]."X");
            if ($emiteNF) {
                $data["consumidor_final"] = $client->consumidorFinal ? 1 : 0;
                $data["indicador_ie"] = $client->indicadorie->id;
            } else {
                $data["consumidor_final"] = false;
                $data["indicador_ie"] = null;
            }
            $data["cliente"] = 1;
            $data["ativo"] = 1;
            $data["cep"] = $client->cep;
            $data["uf"] = $client->uf->uf;
            $data["cidade_id"] = $client->cidade->id;
            $data["bairro_id"] = $client->bairro->id;
            $data["rua_id"] = $client->rua->id;
            $data["numero"] = $client->numero;
            $data["complemento"] = $client->complemento;
            $data["ponto_referencia"] = $client->pontoreferencia;
            if (isset($client->observacoes))
                $data["observacoes"] = $client->observacoes;
            $data["setor_id"] = $colaborador->setors->first()->id;
            $data["email"] = $client->email;
            //$data["tipopessoa_id"] = $client->tipopessoa->id;

            $req = new ClienteRequest();
            $req->replace($data);

            $cont = new ClienteController();
            $response = $cont->store($req, (new Cliente()));
            if ($response["status"] === "OK") {
                try {
                    $cliente_id = $response["data"]->id;
                    foreach ($client->telefones as $telefone) {
                        $fone = new Clientetelefone();
                        $fone["grupo_id"] = $colaborador->grupo_id;
                        $fone["empresa_id"] = $colaborador->empresa_id;
                        $fone["telefonetipo_id"] = $telefone->id;
                        $fone["telefone"] = $telefone->telefone;
                        $fone["whatsapp"] = 0;
                        $fone["cliente_id"] = $cliente_id;
                        $fone->save();
                    }

                    $cliente = Cliente::from("clientes as c")
                        ->leftJoin('ruas as r', 'c.rua_id', 'r.id')
                        ->leftJoin('bairros as b', 'c.bairro_id', 'b.id')
                        ->leftJoin('clientes as d', 'c.convenio_id', 'd.id')
                        ->leftJoin('tipopessoas as e', 'c.tipopessoa_id', 'e.id')
                        ->where('c.id', $cliente_id)
                        ->orderBy('c.nome')
                        ->select(
                            'c.id',
                            'c.nome',
                            DB::Raw("coalesce(c.cpf, c.cnpj, '') as cnpjcpf"),
                            'c.complemento',
                            'c.observacoes',
                            'c.convenio',
                            'd.nome as convenio_nome',
                            'e.tipopessoacadastro as tipopessoa',
                            'c.nfemite',
                            'c.rua_id',
                            'c.email',
                            DB::Raw("r.descricao||', '||c.numero||' - '||b.descricao as endereco")
                        )->get();

                    return responseSuccess($cliente);
                } catch (Exception $e) {
                    return "Cliente cadastrado com sucesso. Erro ao inserir telefone: " . $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getLine()) : responseError($e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
                }
            } else {
                $msg = $response["msg"];
                return responseError($msg);
            }
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getLine()) : responseError($e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
        }
    }
    public function saveClienteObs()
    {
        try {
            $auth = new AuthController();
            $logged = $auth->loginFromApi([
                'email'      => env('DEFAULT_USER_SYSTEM'),
                'password'      => env('DEFAULT_PASSWORD_SYSTEM')
            ]);
            $this->throwIf(!$logged, "Usuário e/ou senha incorretos!", 401);
            $client = json_decode(json_encode(Input::all()["cliente"]), FALSE);
            $colaborador_id = Input::all()["colaborador_id"];
            $colaborador = Colaborador::find($colaborador_id);
            $clienteEdit = Cliente::find($client->id);
            if (!$clienteEdit) throw new \Exception("Cliente " . $client->id . " não encontrado.");
            $clienteEdit->observacoes = $client->observacoes;
            $clienteEdit->save();
            $cliente = Cliente::from("clientes as c")
                ->leftJoin('ruas as r', 'c.rua_id', 'r.id')
                ->leftJoin('bairros as b', 'c.bairro_id', 'b.id')
                ->leftJoin('clientes as d', 'c.convenio_id', 'd.id')
                ->leftJoin('tipopessoas as e', 'c.tipopessoa_id', 'e.id')
                ->where('c.id', $client->id)
                ->orderBy('c.nome')
                ->select(
                    'c.id',
                    'c.nome',
                    DB::Raw("coalesce(c.cpf, c.cnpj, '') as cnpjcpf"),
                    'c.complemento',
                    'c.observacoes',
                    'c.convenio',
                    'd.nome as convenio_nome',
                    'e.tipopessoacadastro as tipopessoa',
                    'c.nfemite',
                    'c.rua_id',
                    'c.email',
                    DB::Raw("r.descricao||', '||c.numero||' - '||b.descricao as endereco")
                )->get();

            return responseSuccess($cliente);
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getLine()) : responseError($e->getMessage() . " " . $e->getFile() . " " . $e->getLine());
        }
    }
    public function getCliente()
    {
        try {
            $this->throwIf(! Input::get("cliente_id"), 'Campo "cliente_id" não informado');
            $cliente_id = Input::get('cliente_id');
            $parcelas = Financeiro::from("financeiros f")
                ->join("financeiroparcelas as p", 'f.id', 'p.financeiro_id')
                ->where('cliente_id', Input::get('cliente_id'))
                ->where('baixado', false)
                ->where('datavencimento', '<', Carbon::now()->startOfDay())
                ->orderBy('datavencimento', 'asc')
                ->select(DB::Raw("TO_CHAR(p.datavencimento, 'DD/MM/YYYY') as datavencimento"), 'f.documento', 'f.descricao', 'p.valor')
                ->get();

            $produtos = Clienteproduto::where('cliente_id', Input::get('cliente_id'))
                ->get();

            $clientes = Cliente::from("clientes as c")
                ->leftJoin('ruas as r', 'c.rua_id', 'r.id')
                ->leftJoin('bairros as b', 'c.bairro_id', 'b.id')
                ->leftJoin('clientes as d', 'c.convenio_id', 'd.id')
                ->leftJoin('tipopessoas as e', 'c.tipopessoa_id', 'e.id')
                ->leftJoin('segmentos as f', 'c.segmento_id', 'f.id')
                ->where('c.id', $cliente_id)
                ->select(
                    'c.id',
                    'c.nome',
                    DB::Raw("coalesce(c.cpf, c.cnpj, '') as cnpjcpf"),
                    DB::Raw("coalesce(c.rg, c.inscricao_estadual, '') as rgie"),
                    'c.complemento',
                    'c.observacoes',
                    'c.convenio',
                    'd.nome as convenio_nome',
                    'e.tipopessoacadastro as tipopessoa',
                    'c.email',
                    'c.nfemite',
                    'c.rua_id',
                    'c.fantasia',
                    'c.ponto_referencia',
                    'f.descricao as segmento_descricao',
                    DB::Raw("r.descricao||case when c.numero='' or c.numero is null then '' else ', '||c.numero end ||case when b.descricao='' or b.descricao is null then '' else ' - '||b.descricao end ||case when c.complemento='' or c.complemento is null then '' else ' - '||c.complemento end as endereco")
                )->first();

            $telefonesc = Cliente::find($cliente_id)->telefones;
            $telefones = [];
            foreach ($telefonesc as $tel) {
                array_push($telefones, ['tipo' => $tel->telefonetipo->descricao, 'telefone' => $tel->telefone]);
            }
            $pedidoController = new PedidoController();
            $historico = $pedidoController->historicoToCliente($cliente_id, 5);
            $response = ['parcelas' => $parcelas, 'cliente' => $clientes, 'telefones' => $telefones, 'historico' => $historico, 'produtos' => $produtos];
            return responseSuccess($response);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    public function baixarDanfe()
    {
        try {
            $auth = new AuthController();
            $logged = $auth->loginFromApi([
                'email'      => env('DEFAULT_USER_SYSTEM'),
                'password'      => env('DEFAULT_PASSWORD_SYSTEM')
                //"email"     => "qtiqti",
                //"password"  => "1234"
            ]);
            if (! Input::get("id")) {
                throw new Exception('Campo "id" não informado', 101);
            }
            $request = new \Illuminate\Http\Request();
            $request->setMethod('GET');
            $request->request->add(['id' => Input::get("id")]);
            $request->request->add(['fromPedidos' => true]);
            $nfemitidaC = new NfemitidaController();
            $nf = new Nfemitida();
            return $nfemitidaC->consultarnfAppNF($nf, -1, true);
            //return $result;
        } catch (Exception $e) {
            return $e->getCode() === 101 ? responseReject($e->getMessage() . " " . $e->getFile() . " " . $e->getLine) : responseError($e->getMessage());
        }
    }
}
