<?php

namespace App\Http\Controllers;

use App\Cliente;
use DB;
use Session;
use Redirect;
use Exception;
use App\Valegas;
use App\Financeiro;
use App\Valegasvenda;
use App\Financeirorateio;
use App\Condicaopagamento;
use App\Financeiroparcela;
use Illuminate\Http\Request;
use App\Condicaopagamentoparcela;
use App\Repository\SelectRepository;
use App\Http\Requests\ValeGasRequest;
use App\Processors\financeiroProcessor;
use App\Services\CarbonCustom as Carbon;

class ValegasvendaController extends Controller
{

    private $empresa_id;
    private $repositorio;
    protected $errors = [];

    protected function addError($error)
    {
        array_push($this->errors, $error);
    }

    protected function getErrors()
    {
        return implode(', ', $this->errors);
    }

    public function index(Valegasvenda $ven)
    {
        $this->authorize('view', $ven);

        $this->definition();

        $inicio = request()->get("inicio", null);
        $fim = request()->get("fim", null);

        if (!is_null($inicio)) {
            $inicio = Carbon::createFromFormat("d/m/Y", $inicio)->format("Y-m-d");
        } else {
            $inicio = Carbon::now()->format("Y-m-d");
        }

        if (!is_null($fim)) {
            $fim = Carbon::createFromFormat("d/m/Y", $fim)->format("Y-m-d");
        } else {
            $fim = Carbon::now()->format("Y-m-d");
        }

        $vendavalegas = Valegasvenda::where('empresa_id', $this->empresa_id)
            ->whereBetween("datavenda", [$inicio, $fim])
            ->get();

        return view('valegas.valegas')->with(compact('vendavalegas'));
    }

    public function create(Valegasvenda $ven)
    {
        $this->authorize('create', $ven);
        $this->definition();
        $produtos = $this->repositorio->getProdutos()->prepend('Selecione', '');
        $condicaopagamento = $this->repositorio->getCondicaoPagamento()->prepend('Selecione', '');
        $prevenda = $this->repositorio->getSituacao('Impresso Pré-Venda')->id;
        $valegaspre = Valegas::where(['valegassituacao_id' => $prevenda, 'empresa_id' => $this->empresa_id])->get();
        return view('valegas.valegas_form')->with(compact('produtos', 'condicaopagamento', 'valegaspre'));
    }

    public function store(ValeGasRequest $request, Valegasvenda $ven)
    {
        $this->authorize('create', $ven);
        $this->definition();
        $data = $request->all();
        $dadosextras = $this->dadosextras($data);
        $data = array_merge($data, $dadosextras);
        $situacao = $data["valegassituacao_id"];
        $situacaoimpresso = $this->repositorio->getSituacao('Impresso')->id;
        $isPrevenda = ! $data["valortotal_hd"];
        $isFechar = $data["valoresfechar"];
        DB::begintransaction();
        try {
            $valegasvenda = Valegasvenda::create($data);
            if ($isFechar) {
                $ids = $this->parseFecharPreVenda($data);
                $updateothers = $this->updateValegas($ids, $valegasvenda, $situacaoimpresso);
                if (!$updateothers) {
                    throw new Exception($this->getErrors());
                }
            } else {
                $prevenda = $this->salvarPreVenda($valegasvenda, $situacao, $isPrevenda);
            }
            if (isset($prevenda) && !$prevenda) {
                throw new Exception($this->getErrors());
            }

            $redirect = "vendavalegas?cliente_id=" . $valegasvenda->cliente_id .
                "&cliente=" . $valegasvenda->Cliente->nome .
                "&prevenda=" . $valegasvenda->prevenda;

            if (! $isPrevenda) {
                $bol = $this->gerarFinanceiro($data, $valegasvenda);
                if ($bol) {
                    if (isset($updateothers)) {
                        $redirect = "vendavalegas";
                    }
                } else {
                    throw new Exception($this->getErrors());
                }
            }
        } catch (Exception $ex) {
            DB::rollback();
            $error = getErrorsException($ex);
            return Redirect::to('/vendavalegas/create')
                ->withErrors($error)
                ->withInput();
        }
        DB::commit();
        return Redirect::to($redirect)->withMessageSuccess('Venda de vale gás cadastrada com sucesso!');
    }

    public function update()
    {
        // encape02@hotmail.com
    }

    public function show($id, Valegasvenda $ven)
    {
        $this->authorize('view', $ven);
        $vendavalegas = Valegasvenda::find($id);
        $this->authorize('igualdade', $vendavalegas);
        $this->definition();
        $parcelas = Financeiroparcela::where('financeiro_id', $vendavalegas->financeiro_id)->get();
        $parcelasfinanceiro = $this->validarParcelas($parcelas);
        $valegas = Valegas::where('valegasvenda_id', $id)->get();
        $produtos = $this->repositorio->getProdutos()->prepend('Selecione', '');
        $clientes = $this->repositorio->getClienteJuridico()->prepend('Selecione', '');
        $condicaopagamento = $this->repositorio->getCondicaoPagamento()->prepend('Selecione', '');
        $clientej = $clientes;
        $show = 1;
        $vendavalegas->valortotal = requestNumeroDecimalOracle($vendavalegas->valortotal);
        $vendavalegas->valorunitario = requestNumeroDecimalOracle($vendavalegas->valorunitario);
        $vendavalegas->datavenda = requestDataOracleSemHora($vendavalegas->datavenda);
        return view('valegas.valegas_form')->with(compact(
            'vendavalegas',
            'valegas',
            'produtos',
            'clientes',
            'clientej',
            'condicaopagamento',
            'parcelasfinanceiro',
            'show'
        ));
    }

    public function destroy($id, Valegasvenda $ven)
    {
        $this->authorize('delete', $ven);
        $venda = Valegasvenda::findOrFail($id);
        $this->authorize('igualdade', $venda);
        $this->definition();
        $dataatual = Carbon::now()->toDateTimeString();
        $cancelado = $this->repositorio->getSituacao('Cancelado')->id;
        $baixado = $this->repositorio->getSituacao('Baixado')->id;
        $presituacao = Valegas::where('valegasvenda_id', $id)
            ->select('valegassituacao_id', 'id')
            ->get();
        $validado = true;
        DB::beginTransaction();
        try {
            if ($venda->cancelado != "1") {
                if ($venda->prevenda != "1") {
                    $financeirocan = $this->cancelamentoParcelas($venda);
                    if ($financeirocan) {
                        foreach ($presituacao as $valegas) {
                            $valgas = Valegas::findOrFail($valegas->id);
                            if ($valgas->valegassituacao_id != $baixado) {
                                $valgas->update(["valegassituacao_id" => $cancelado, "databaixa" => $dataatual]);
                            } else {
                                throw new Exception('Venda não pode ser cancelada. Existe vale gás baixado.');
                            }
                        }
                        $venda->update(["cancelado" => 1]);
                    } else {
                        throw new Exception('Uma ou mais parcelas ja foi baixada, então venda não pode ser cancelada.');
                    }
                } else {
                    foreach ($presituacao as $valegas) {
                        $valgas = Valegas::where('id', $valegas->id)->get()->first();
                        if ($valgas->valegassituacao_id != $baixado) {
                            $valgas->update(["valegassituacao_id" => $cancelado, "databaixa" => $dataatual]);
                        } else {
                            throw new Exception('Venda não pode ser cancelada. Existe vale gás baixado.');
                        }
                    }
                    $venda->update(["cancelado" => 1]);
                }
            } else {
                throw new Exception('Não pode cancelar uma venda ja cancelada.');
            }
        } catch (Exception $ex) {
            DB::rollback();
            return Redirect::to('/vendavalegas')
                ->withErrors($ex->getMessage() . ': ' . $ex->getLine())
                ->withInput();
        }
        DB::commit();
        return Redirect::to('vendavalegas')->withMessageSuccess('A venda foi cancelada!');
    }

    public function gerarPDF($id, Valegasvenda $ven)
    {
        $this->authorize('view', $ven);
        $valegasvenda = Valegasvenda::find($id);
        $this->authorize('igualdade', $valegasvenda);
        $this->definition();
        $pdf = \App::make('dompdf.wrapper');
        if ($valegasvenda->valortotal !== null) {
            $titulo = 'DUPLICATA';
            $empresa = $valegasvenda->empresa;
            $parcelas = Financeiroparcela::where('financeiro_id', $valegasvenda->financeiro_id)->get();
            $condi = $this->validarParcelas($parcelas);
            $pdf->loadView('valegas.valegas_duplicata', compact('titulo', 'valegasvenda', 'empresa', 'condi'));
            return $pdf->stream();
        } else {
            return Redirect::to('/vendavalegas')->withMessageInfo('Não é possivel gerar duplicata de pré-venda.');
        }
    }

    ////// Impressão e Vale Gás
    public function imprimirIndex(Valegas $vl)
    {
        $this->authorize('viewImpressao', $vl);
        $this->definition();
        $clientes = $this->repositorio
            ->getClientesValeGasNaoImpresso()
            ->prepend("Selecione", "");
        return view('valegas.imprimir')->with(compact('clientes'));
    }

    public function imprimirGas(Request $request)
    {
        $this->definition();
        $data = $request->all();
        $valegas = $this->buscarCliProd($data);
        if ($valegas !== null) {
            $id = $valegas[0]->valegasvenda_id;
            $idvalegas = $valegas[0]->id;
            $titulo = "Etiquetas N°:" . $id;
            $apartir = isset($data["apartir"]) ? $data["apartir"] : 0;
            $anterior = [];
            if ($apartir >= 1 && $apartir <= 30) {
                for ($i = 1; $i < $apartir; $i++) {
                    $dados = array("cliente_id" => "", "codigo" => "", "prevendasequencia" => "", "produto_id" => "");
                    array_push($anterior, $dados);
                }
            }
            $valgas = [];
            for ($i = 0; $i < count($valegas); $i++) {
                if (strlen($valegas[$i]->cliente_nome) >= 28) {
                    $valegas[$i]->cliente_nome = substr($valegas[$i]->cliente_nome, 0, 28) . '...';
                }
                $dados = array(
                    "id"                => $valegas[$i]->id,
                    "cliente_id"        => $valegas[$i]->cliente_nome,
                    "prevendasequencia" => $valegas[$i]->prevendasequencia,
                    "produto_id"        => $valegas[$i]->produto_nome,
                    "codigo"            => $valegas[$i]->codigo
                );
                array_push($valgas, $dados);
            }
            // dd($valegas);
            $dadosgerais = array_merge($anterior, $valgas);
            $quant = floor(count($dadosgerais) / 10);
            if ((count($dadosgerais) % 10) > 0) {
                $quant++;
            }
            $setvg = [];
            $vg10 = [];
            //guarda etiquetas de 10 em 10
            for ($i = 0; $i < count($dadosgerais); $i++) {
                array_push($vg10, $dadosgerais[$i]);
                if ((($i + 1) % 10) == 0) {
                    array_push($setvg, $vg10);
                    $vg10 = [];
                }
            }
            if (count($vg10) > 0) {
                array_push($setvg, $vg10);
            }
            //guarda agora de 3 em 3
            $valegas = [];
            $valegas3 = [];
            for ($i = 0; $i < count($setvg); $i++) {
                array_push($valegas3, $setvg[$i]);
                if ((($i + 1) % 3) == 0) {
                    array_push($valegas, $valegas3);
                    $valegas3 = [];
                }
            }
            if (count($valegas3) > 0) {
                array_push($valegas, $valegas3);
            }
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('valegas.etiquetas', compact('titulo', 'valegas'))->setPaper('letter');
            $updated = $this->updateImpresso($dadosgerais, $data, $apartir);
            if ($updated === true) {
                return $pdf->stream('etiquetas venda n°' . $id . '.pdf');
            } else {
                return $updated;
            }
        } else {
            return Redirect::to('vendavalegas.imprimir')->withMessageInfo('Não há vale gás a ser impresso.');
        }
    }

    /// Funções Privadas Venda de Vale Gás
    private function salvarPreVenda($venda, $situacao, $isPrevenda)
    {
        if ($isPrevenda)
            $sequencia = Valegas::max('prevendasequencia');
        $vales = collect([]);
        $valegas = new Valegas();
        $quant = $venda->quantidade;
        DB::begintransaction();
        try {
            for ($i = 0; $i < $quant; $i++) {
                $valation = new Valegas();
                $valation->grupo_id = $this->grupo_id;
                $valation->empresa_id = $this->empresa_id;
                $valation->cliente_id = $venda->cliente_id;
                $valation->produto_id = $venda->produto_id;
                $valation->pedido_id  = $venda->pedido_id;
                $valation->valegasvenda_id = $venda->id;
                $valation->valegassituacao_id = $situacao;
                $valation->codigo = $this->gerarCodigo();
                $valation->created_at = new \DateTime();
                $valation->updated_at = new \DateTime();
                if ($isPrevenda) {
                    $sequencia++;
                    $valation->prevendasequencia = $sequencia;
                }
                $vales->push($valation);
            }
            $valegas->insert($vales->toArray());
        } catch (Exception $ex) {
            DB::rollback();
            $error = getErrorsException($ex);
            $this->addError($error);
            return false;
        }
        DB::commit();
        return true;
    }

    private function dadosextras($data)
    {
        $vendido = $this->repositorio->getSituacao('Vendido')->id;
        $prevenda = $this->repositorio->getSituacao('Pré-Venda')->id;
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["datavenda"] = insertDataOracle($data["datavenda"]);
        $data["produto_id"] = $data["produto_id_hd"];
        $data["quantidade"] = $data["quantidade_hd"];
        if (!isset($data["prevenda"])) {
            $data["valegassituacao_id"] = $vendido;
            $data["valorunitario"] = insertNumeroDecimalOracle($data["valorunitario"]);
            $data["valortotal"] = $data["valortotal_hd"];
            $data["prevenda"] = 0;
        } else {
            $data["valegassituacao_id"] = $prevenda;
        }
        return $data;
    }

    private function gerarFinanceiro($data, $venda)
    {
        $vendavenda = Valegasvenda::find($venda->id);
        $datave = explode(' ', $vendavenda->datavenda);
        $datavenda = explode('-', $datave[0]);
        $dataparsed = Carbon::create($datavenda[0], $datavenda[1], $datavenda[2]);
        $total = $vendavenda->valortotal;
        $parcelas = calculoParcelas($vendavenda->condicaopagamento_id, $dataparsed, $total);
        $processor = new financeiroProcessor();
        $raton = new Financeirorateio();
        $parc = [];
        $empresaconfig = Session::get('empresa_config');
        if (! $empresaconfig["ccvalegas_id"] || ! $empresaconfig["pcvalegas_id"]) {
            $error = "Não foi encontrado Plano de Contas ou Centro de Custos para o vale gás, favor cadastra-los na config.";
            $this->addError($error);
            return false;
        }
        try {
            $financeiro = new Financeiro();
            $financeiro->empresa_id = $this->empresa_id;
            $financeiro->grupo_id = $this->grupo_id;
            $financeiro->cliente_id = $data["cliente_id"];
            $financeiro->pagarreceber = "R";
            $financeiro->documento = $venda->id;
            $financeiro->descricao = ("Vale Gás N°:" . $venda->id);
            $financeiro->cartaonsu = null;
            $financeiro->cartaoautorizacao = null;
            $financeiro->valor = $data["valortotal"];
            $financeiro->condicaopagamento_id = $data["condicaopagamento_id"];
            $financeiro->datacompetencia = $data["datavenda"];
            $financeiro->dataemissao = $data["datavenda"];
            $processor->setFinanceiro($financeiro);

            $rato = [];
            $raton["centrocusto_id"] = $empresaconfig->ccvalegas_id;
            $raton["planoconta_id"] = $empresaconfig->pcvalegas_id;
            $raton["valor"] = $data["valortotal"];
            $raton["financeiro_id"] = $processor->getFinanceiro()->id;
            $raton["percentual"] = 1;
            array_push($rato, $raton);
            $processor->setRateios($rato);

            for ($i = 0; $i < count($parcelas); $i++) {
                $financeiroparcela = new Financeiroparcela();
                $financeiroparcela["datavencimento"] = insertDataOracle($parcelas[$i]["datavenc"]);
                $financeiroparcela["valor"] = $parcelas[$i]["valor"];
                $financeiroparcela["empresa_id"] = $this->empresa_id;
                $financeiroparcela["grupo_id"] = $this->grupo_id;
                $financeiroparcela["financeiro_id"] = $processor->getFinanceiro()->id;
                $financeiroparcela["multa"] = 0;
                $financeiroparcela["juros"] = 0;
                $financeiroparcela["desconto"] = 0;
                $financeiroparcela["valorefetivado"] = $parcelas[$i]["valor"];
                $financeiroparcela["numero"] = $i + 1;
                $financeiroparcela["baixado"] = false;
                $financeiroparcela["datacompetencia"] = $data["datavenda"];
                $financeiroparcela["pagarreceber"] = $processor->getFinanceiro()->pagarreceber;
                array_push($parc, $financeiroparcela);
            }
            $processor->setParcelas($parc);
            $gravar = $processor->gravar();
            if (! $gravar) {
                $error = $processor->getErrors();
                throw new Exception($error);
            }
            $idfinanceiro = $processor->getFinanceiro()->id;
            $vendavenda->update(["financeiro_id" => $idfinanceiro]);
        } catch (Exception $ex) {
            $error = getErrorsException($ex);
            $this->addError($error);
            return false;
        }
        return true;
    }

    private function parseFecharPreVenda($data)
    {
        $fechar = json_decode($data["valoresfechar"]);
        $ids = [];
        for ($i = 0; $i < count($fechar); $i++) {
            array_push($ids, $fechar[$i]->id);
        }
        return $ids;
    }

    private function gerarCodigo()
    {
        $numero = random_int(100000000, 999999999);

        // Se já existe, regenera e RETORNA o novo (antes descartava o retorno da
        // recursão e devolvia o número duplicado).
        if ($this->checkCodigo($numero))
            return $this->gerarCodigo();

        return $numero;
    }

    private function checkCodigo($numero)
    {
        // O código é gravado na coluna `codigo` (ver $valation->codigo), não em
        // prevendasequencia — a unicidade tem de ser checada nessa coluna.
        return Valegas::where('codigo', $numero)->exists();
    }

    private function validarParcelas($parcelas)
    {
        foreach ($parcelas as $par) {
            $par->valorparcela = requestNumeroDecimalOracle($par->valorefetivado);
            $par->datavencimento = requestDataOracle($par->datavencimento, false);
        }
        return $parcelas;
    }

    private function cancelamentoParcelas($venda)
    {
        $financeiro_id = $venda->financeiro_id;
        $descricao = 'CANCELAMENTO DE PARCELA DE FINANCEiRO COD.' . $financeiro_id . '. MOTIVO: CANCELAMENTO DO VALE GAS N°:' . $venda->id;
        $processor = new financeiroProcessor();
        $fin = Financeiro::find($financeiro_id);
        $processor->setFinanceiro($fin);
        if (!$processor->validarCancelamentoFinanceiro()) {
            return false;
        }
        if (!$processor->cancelarFinanceiro($descricao)) {
            return false;
        } else {
            return true;
        }
    }

    //Funções privadas Impressão de Vale Gas
    private function buscarCliProd($data)
    {
        $this->definition();
        $prevenda = $this->repositorio->getSituacao('Pré-Venda')->id;
        $vendido = $this->repositorio->getSituacao('Vendido')->id;
        $cliente_id = $data["cliente_id"];
        $data["checkprevenda"] = isset($data["checkprevenda"]) ? $data["checkprevenda"] : 0;
        if ($data["checkprevenda"] == "1") {
            $valegas = Valegas::where('cliente_id', $cliente_id)->where("valegassituacao_id", $prevenda)->get();
        } else {
            $valegas = Valegas::where('cliente_id', $cliente_id)->where("valegassituacao_id", $vendido)->get();
        }
        if (count($valegas) > 0) {
            foreach ($valegas as $pre) {
                $pre->produto_nome = $pre->produto->descricao;
                $pre->cliente_nome = $pre->cliente->nome;
            }
            return $valegas;
        }
    }

    private function updateImpresso($dados, $data, $apartir)
    {
        $data["prevenda"] = isset($data["checkprevenda"]) ? $data["checkprevenda"] : 0;
        $impresso = $this->repositorio->getSituacao('Impresso')->id;
        $impressoprevenda = $this->repositorio->getSituacao('Impresso Pré-Venda')->id;
        DB::begintransaction();
        $apartir = $apartir === "" ? 0 : $apartir;
        try {
            if ($data["prevenda"] == "1") {
                $count = count($dados);
                for ($i = $apartir - 1; $i < $count; $i++) {
                    if (!empty($dados[$i]["cliente_id"])) {
                        $valegas = Valegas::find($dados[$i]["id"]);
                        $valegas->update(['valegassituacao_id' => $impressoprevenda]);
                    }
                }
            } else {
                $count = count($dados);
                for ($i = $apartir - 1; $i < $count; $i++) {
                    if (!empty($dados[$i]["cliente_id"])) {
                        $valegas = Valegas::find($dados[$i]["id"]);
                        $valegas->update(['valegassituacao_id' => $impresso]);
                    }
                }
            }
        } catch (Exception $ex) {
            DB::rollback();
            return Redirect::to('vendavalegas.imprimir')
                ->withErrors($ex->getMessage() . ': ' . $ex->getLine())
                ->withInput();
        }
        DB::commit();
        return true;
    }

    private function updateValegas($valegas_id, $valegasvenda, $situacao)
    {
        $this->definition();
        DB::begintransaction();
        try {
            $update = [
                "cliente_id"         => $valegasvenda->cliente_id,
                "valegasvenda_id"    => $valegasvenda->id,
                "valegassituacao_id" => $situacao
            ];
            Valegas::whereIn('id', $valegas_id)->update($update);
        } catch (Exception $ex) {
            DB::rollback();
            $error = getErrorsException($ex);
            $this->addError($error);
            return false;
        }
        DB::commit();
        return true;
    }

    //Ajax
    public function buscarParcelasAjax($cond_id)
    {
        $condicaoparcelas = Condicaopagamentoparcela::where('condicaopagamento_id', $cond_id)->get()->toArray();
        $condicao = Condicaopagamento::where('id', $cond_id)->get()->toArray();
        if (count($condicaoparcelas) > 0 || count($condicao) > 0) {
            return compact('condicaoparcelas', 'condicao');
        } else {
            return 'ERROR';
        }
    }

    public function buscarCliPre($id)
    {
        $this->definition();
        $pres = Valegas::where(['valegassituacao_id' => $this->repositorio->getSituacao('impresso pré-venda')->id, 'cliente_id' => $id])
            ->orderBy('prevendasequencia')
            ->select('id', 'prevendasequencia', 'codigo', 'produto_id', 'cliente_id')
            ->get();
        if (count($pres) > 0) {
            foreach ($pres as $pre) {
                $pre->produto_nome = $pre->produto->descricao;
                $pre->cliente_nome = $pre->cliente->nome;
            }
            return $pres;
        }
    }

    private function definition()
    {
        //busca id de empresa
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //Set Empresa ID
        $this->repositorio = new SelectRepository($this->empresa_id, $this->grupo_id);
    }
}
