<?php

namespace App\Http\Controllers;

use DB;
use Spreadsheet;
use Xlsx;
use Redirect;
use Exception;
use App\Pedido;
use App\Cliente;
use App\Services\CarbonCustom as Carbon;
use App\Financeiro;
use App\Pedidoitem;
use App\Http\Requests;
use App\Pedidosituacao;
use App\Clienteconvenio;
use App\Financeirorateio;
use App\Financeiroparcela;
use App\Condicaopagamento;
use App\Conveniofechamento;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;
use App\Processors\financeiroProcessor;
use Illuminate\Support\Facades\Session;
use App\Conveniofechamentopedido as Conveniopedido;
use App\Empresaconfig;
use App\Helpers\Utils\BoletoUtil;
use App\Processors\BoletoProcessor;
use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpSpreadsheetSpreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use stdClass;

class FechamentoconvenioController extends Controller
{

    private $grupo_id;
    private $empresa_id;
    private $empresa_config;
    private $repository;

    function __construct(SelectRepository $repository)
    {
        $this->repository = $repository;
    }

    function index(Conveniofechamento $conv)
    {
        $this->authorize('view', $conv);
        $this->definition();
        $clientes = $this->repository->getConvenios()->prepend('Selecione', '');
        $conveniosfechados = Conveniofechamento::whereBetween('dataemissao', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()])
            ->where('empresa_id', $this->empresa_id)->take(1000)->get();
        return view('convenios.fechamentoconvenio')->with(compact('clientes', 'conveniosfechados'));
    }

    function create(Conveniofechamento $conv)
    {
        $this->authorize('create', $conv);
        $this->definition();
        $clientes = $this->repository->getConvenios()->prepend('Selecione', '');
        $condicao = Condicaopagamento::where([
            'grupo_id'  => $this->grupo_id,
            'tipo'      => 0,
            'ativo'     => 1
        ])->select('id')->first();
        if (!$condicao) {
            Session::flash('message_danger', 'Por favor, cadastre uma condição de pagamento a vista para continuar!');
        } else {
            $condicao = $condicao->id;
        }
        return view('convenios.fechamentoconvenio_form')->with(compact('clientes', 'condicao'));
    }

    function store(Request $request, Conveniofechamento $conv)
    {
        $this->authorize('create', $conv);
        $this->definition();
        $data = $request->all();
        $data = $this->dadosExtras($data);
        // $fechado = $this->checarPeriodo($data);
        $pedidos = json_decode($data["tblconveniopedidos_hd"]);
        DB::beginTransaction();
        try {
            $fechamento = Conveniofechamento::create($data);
            $salvarpedidos = $this->salvarPedidos($fechamento, $pedidos);
            if ($salvarpedidos == true) {
                $financeiro_id = $this->processoFinanceiroFechamento($fechamento, $pedidos);
            } else {
                throw new exception($salvarpedidos);
            }
        } catch (Exception $ex) {
            DB::rollback();
            return responseError($ex->getMessage());
        }
        if ($financeiro_id) {
            DB::commit();
            return responseSuccess($fechamento->id);
            //return Redirect::to('fechamentoconvenio?cod=' . $fechamento->id)->withMessageSuccess('Fechamento de convênio salvo com sucesso!');
        } else {
            return responseError($financeiro_id);
            //return Redirect::to('fechamentoconvenio/create')
            //    ->withErrors($financeiro_id)
            //    ->withInput();
        }
    }

    function show($id, Conveniofechamento $conv)
    {
        $this->authorize('view', $conv);
        return $this->showEdit($id, 'show');
    }

    function edit($id, Conveniofechamento $conv)
    {
        $this->authorize('update', $conv);
        return $this->showEdit($id, 'edit');
    }

    function update(Request $request, $id, Conveniofechamento $conv)
    {
        $this->authorize('update', $conv);
        $fechamento = Conveniofechamento::find($id);
        $this->authorize('igualdade', $fechamento);
        $this->definition();
        $data = $request->all();
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);
        $pedidos = json_decode($data["tblconveniopedidos_hd"]);
        DB::beginTransaction();
        try {
            $financeirocancelado = $this->cancelarFinanceiro($fechamento);
            if ($financeirocancelado) {
                $fechamento->convenioFechamentoPedido()->delete();
                $salvarpedidos = $this->salvarPedidos($fechamento, $pedidos);
                $fechamento->update($data);
                if ($salvarpedidos == true) {
                    $financeiro_id = $this->processoFinanceiroFechamento($fechamento, $pedidos);
                } else {
                    throw new exception($salvarpedidos);
                }
            } else {
                throw new exception($financeirocancelado);
            }
        } catch (Exception $ex) {
            DB::rollback();
            $error = getErrorsException($ex);
            return responseError($error);
            //return Redirect::to("fechamentoconvenio/$id/edit")
            //    ->withErrors($error)
            //    ->withInput();
        }
        if ($financeiro_id) {
            DB::commit();
            return responseSuccess($fechamento->id);
            //return Redirect::to('fechamentoconvenio?cod=' . $fechamento->id)->withMessageSuccess('Fechamento de convênio atualizado com sucesso!');
        } else {
            return responseError($financeiro_id);
            //return Redirect::to('fechamentoconvenio/create')
            //    ->withErrors($financeiro_id)
            //    ->withInput();
        }
    }

    public function gerarPDF($id, Conveniofechamento $conv)
    {
        $this->authorize('view', $conv);
        $fe = Conveniofechamento::with('cliente')->where('id', $id)->get()->first();
        $this->authorize('igualdade', $fe);
        $comissao = Clienteconvenio::where('cliente_id', $fe->cliente_id)->get()->first();
        $titulo = "FECHAMENTO CONVÊNIO";
        $fechamentopedidos = DB::table('conveniofechamentopedidos as fechamentoped')
            ->where('fechamentoped.conveniofechamento_id', $id)
            ->join('clientes', 'fechamentoped.cliente_id', 'clientes.id')
            ->join('pedidoitems as items', 'fechamentoped.pedido_id', 'items.pedido_id')
            ->select(
                'clientes.nome',
                'fechamentoped.pedidovalor as precovendatotal',
                //'items.precovendaunitario',
                'clientes.id as cliente_id',
                'fechamentoped.pedidodata',
                DB::raw('SUM(items.quantidade) as quantidade')
            )
            ->groupBy('clientes.nome')
            ->groupBy('fechamentoped.pedidovalor')
            ->groupBy('clientes.id')
            ->groupBy('fechamentoped.pedidodata')
            ->orderBy('clientes.nome')
            ->orderBy('fechamentoped.pedidodata')->get();
        $quantidade = 0;
        $pedidos = collect([]);
        $colectionclientes = collect([]);
        foreach ($fechamentopedidos as $fechamento) {
            $objcliente = (object) array();
            $objpedido = (object) array();
            $objcliente->cliente_id = $fechamento->cliente_id;
            $objcliente->cliente = $fechamento->nome;
            $cli = $fechamentopedidos->where('cliente_id', $fechamento->cliente_id);
            $objcliente->totalcliente = $cli->sum('precovendatotal');
            $objcliente->totalquantidade = $cli->sum('quantidade');
            $objpedido->cliente_id = $objcliente->cliente_id;
            $objpedido->data = requestDataOracle($fechamento->pedidodata, false);
            $objpedido->precovenda = requestNumeroDecimalOracle($fechamento->precovendatotal);
            $objpedido->quantidade = $fechamento->quantidade;
            $colectionclientes->push($objcliente);
            $pedidos->push($objpedido);
        }
        $clientes = $colectionclientes->unique('cliente_id');
        $total = $clientes->sum('totalcliente');
        if ($comissao->comissaodestino == 2) {
            $total = $total - ($total * ($comissao->comissao / 100));
        }
        //return view('convenios.fechamentoconvenio_pdf')->with(compact('titulo','clientes','pedidos',
        //'desconto','total','fe'));
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('convenios.fechamentoconvenio_pdf', compact(
            'titulo',
            'clientes',
            'pedidos',
            'total',
            'fe'
        ));
        return $pdf->stream();
    }

    public function gerarXLS($id, Conveniofechamento $conv)
    {
        $this->authorize('view', $conv);
        $fe = Conveniofechamento::with('cliente')->where('id', $id)->get()->first();
        $this->authorize('igualdade', $fe);
        $comissao = Clienteconvenio::where('cliente_id', $fe->cliente_id)->get()->first();
        $titulo = "FECHAMENTO CONVÊNIO";

        $filtro = 'Empresa: ' . Session::get('empresa_padrao')->nome_informal;

        $fechamentopedidos = DB::table('conveniofechamentopedidos as fechamentoped')
            ->where('fechamentoped.conveniofechamento_id', $id)
            ->join('clientes', 'fechamentoped.cliente_id', 'clientes.id')
            ->join('pedidoitems as items', 'fechamentoped.pedido_id', 'items.pedido_id')
            ->select(
                'clientes.nome',
                'fechamentoped.pedidovalor as precovendatotal',
                //'items.precovendaunitario',
                'clientes.id as cliente_id',
                'fechamentoped.pedidodata',
                'clientes.codigo_convenio as codigo_convenio',
                'clientes.cpf',
                DB::raw('SUM(items.quantidade) as quantidade')
            )
            ->groupBy('clientes.nome')
            ->groupBy('fechamentoped.pedidovalor')
            ->groupBy('clientes.id')
            ->groupBy('fechamentoped.pedidodata')
            ->groupBy('clientes.codigo_convenio')
            ->groupBy('clientes.cpf')
            ->orderBy('clientes.nome')
            ->orderBy('fechamentoped.pedidodata')->get();
        $quantidade = 0;
        $pedidos = collect([]);
        $colectionclientes = collect([]);
        foreach ($fechamentopedidos as $fechamento) {
            $objcliente = (object) array();
            $objpedido = (object) array();
            $objcliente->cliente_id = $fechamento->cliente_id;
            $objcliente->cliente = $fechamento->nome;
            $objcliente->cpf = $fechamento->cpf;
            $objcliente->codigo_convenio = $fechamento->codigo_convenio;
            $cli = $fechamentopedidos->where('cliente_id', $fechamento->cliente_id);
            $objcliente->totalcliente = $cli->sum('precovendatotal');
            $objcliente->totalquantidade = $cli->sum('quantidade');
            $objpedido->cliente_id = $objcliente->cliente_id;
            $objpedido->data = requestDataOracle($fechamento->pedidodata, false);
            $objpedido->precovenda = $fechamento->precovendatotal;
            $objpedido->quantidade = $fechamento->quantidade;
            $colectionclientes->push($objcliente);
            $pedidos->push($objpedido);
        }
        $clientes = $colectionclientes->unique('cliente_id');
        $total = $clientes->sum('totalcliente');
        $totalquantidade = $clientes->sum('totalquantidade');
        if ($comissao->comissaodestino == 2) {
            $total = $total - ($total * ($comissao->comissao / 100));
        }
        $xls = array();
        array_push($xls, ['FECHAMENTO DE CONVÊNIO']);
        array_push($xls, ['']);
        array_push($xls, ['Dia Entrega: ' . $fe->cliente->clienteConvenio->diafechamento . ' - Dia Recebimento: ' . $fe->cliente->clienteConvenio->diavencimento]);
        array_push($xls, [$fe->cliente->nome]);
        array_push($xls, [$fe->cliente->rua->descricao . ", " . $fe->cliente->numero . " - " . $fe->cliente->bairro->descricao]);
        array_push($xls, ['Nome', 'CPF', 'Código', 'Pedido', 'Qtde', 'Total']);

        foreach ($clientes as $cliente) {
            array_push($xls, [$cliente->cliente_id . " - " . $cliente->cliente, $cliente->cpf, $cliente->codigo_convenio, '', $cliente->totalquantidade, $cliente->totalcliente]);
            foreach ($pedidos as $pedido) {
                if ($pedido->cliente_id == $cliente->cliente_id) {
                    array_push($xls, ['', '', '', $pedido->data, $pedido->quantidade, $pedido->precovenda]);
                }
            }
        }
        array_push($xls, ['Total de Vendas ao Convênio', '', '', '', $totalquantidade, $total]);
        $spreadsheet = new PhpSpreadsheetSpreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($xls, NULL, 'A1');

        //Tamanho colunas
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(14);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(14);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(12);
        //Cabeçalho negrito
        $spreadsheet->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A3')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A4')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A5')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('A6')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('B6')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('C6')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('D6')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('E6')->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('F6')->getFont()->setBold(true);
        //Formatar valor
        $spreadsheet->getActiveSheet()->getStyle('F7:F99999')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        //Bordas
        $styleThickBrownBorderOutline = [
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
                'left' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
                'right' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        for ($i = 6; $i <= count($xls); $i++) {
            for ($j = 1; $j <= 6; $j++) {
                $coord = Coordinate::stringFromColumnIndex($j) . ($i);
                $spreadsheet->getActiveSheet()->getStyle($coord)->applyFromArray($styleThickBrownBorderOutline);
            }
        }
        //Última Linha
        $spreadsheet->getActiveSheet()->getStyle('A' . (count($xls)))->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('B' . (count($xls)))->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('C' . (count($xls)))->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('D' . (count($xls)))->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('E' . (count($xls)))->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->getStyle('F' . (count($xls)))->getFont()->setBold(true);
        $spreadsheet->getActiveSheet()->mergeCells('A' . (count($xls)) . ':D' . (count($xls)));

        if (Session::get('empresa_padrao')->logo != null) {
            //dd(base64_decode(Session::get('empresa_padrao')->logo));
            $image = imagecreatefromstring(base64_decode(Session::get('empresa_padrao')->logo));
            imagesavealpha($image, true);
            $drawing = new MemoryDrawing();
            $drawing->setName('teste');
            $drawing->setImageResource($image);
            $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
            $drawing->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT);
            $drawing->setWidthAndHeight(148, 70);
            $drawing->setResizeProportional(true);
            $drawing->setCoordinates('F1');
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
        }



        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="fechamento_convenio_' . $fe->id . '.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer->save('php://output');
    }

    public function filtroConsultas(Conveniofechamento $conv)
    {
        $this->authorize('view', $conv);
        $this->definition();
        $clientes = $this->repository->getConvenios()->prepend('Selecione', '');
        $get = $_GET;
        $cliente_id = (int) \Input::get("cliente", 0);
        $datainicio = Carbon::parse($get["datainicio"])->startOfDay();
        $datafim = Carbon::parse($get["datafim"])->endOfDay();
        if ($cliente_id > 0) {
            $conveniosfechados = Conveniofechamento::whereBetween('dataemissao', [$datainicio, $datafim])
                ->where('cliente_id', $cliente_id)->take(1000)->get();
        } else {
            $conveniosfechados = Conveniofechamento::whereBetween('dataemissao', [$datainicio, $datafim])
                ->where('empresa_id', $this->empresa_id)->take(1000)->get();
        }
        return view('convenios.fechamentoconvenio')->with(compact('clientes', 'conveniosfechados'));
    }

    //Private Functions
    private function showEdit($id, $mostrar)
    {
        $fechamento = Conveniofechamento::find($id);
        $this->authorize('igualdade', $fechamento);
        $this->definition();
        $fechamentoconvenio = $this->correcoesFechamento($fechamento);
        $pedidos = $this->getPedidos($fechamentoconvenio);
        $clientes = $this->repository->getConvenios()->prepend('Selecione', '');
        $condicao = $fechamento->condicaopagamento_id;
        $edit = 1;
        $show = 1;
        $mostrar = $mostrar == 'show';
        if ($mostrar) {
            return view('convenios.fechamentoconvenio_form')->with(compact('clientes', 'condicao', 'fechamentoconvenio', 'show', 'pedidos'));
        } else {
            return view('convenios.fechamentoconvenio_form')->with(compact('clientes', 'condicao', 'fechamentoconvenio', 'pedidos', 'edit'));
        }
    }

    private function dadosExtras($data)
    {
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["dataemissao"] = insertDataOracle($data["dataemissao"]);
        $data["datavencimento"] = insertDataOracle($data["datavencimento"]);
        $data["valor"] = insertNumeroDecimalOracle($data["valor"]);
        return $data;
    }

    private function salvarPedidos($fechamento, $pedidos)
    {
        try {
            foreach ($pedidos as $pedido) {
                $fechamentopedidos = new Conveniopedido();
                $fechamentopedidos->conveniofechamento_id = $fechamento->id;
                $fechamentopedidos->pedido_id = $pedido[0];
                $fechamentopedidos->cliente_id = $pedido[1];
                $fechamentopedidos->pedidodata = insertDataOracle($pedido[3]);
                $fechamentopedidos->pedidovalor = insertNumeroDecimalOracle($pedido[4]);
                $fechamentopedidos->save();
            }
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
        return true;
    }

    private function processoFinanceiroFechamento($fechamentoconvenio, $pedidos)
    {
        $fechamento = Conveniofechamento::find($fechamentoconvenio->id);
        $condicaopagamento = Condicaopagamento::find($fechamento->condicaopagamento_id);
        $processor = new FinanceiroProcessor();
        $raton = new Financeirorateio();
        $dataemissao = $fechamento->dataemissao;
        $dataemissao = explode(' ', $dataemissao);
        $dataemissao = explode('-', $dataemissao[0]);
        $data = Carbon::create($dataemissao[0], $dataemissao[1], $dataemissao[2]);
        $valortotal = $fechamento->valor;
        $parcelasnovo = calculoParcelas($condicaopagamento->id, $data, $valortotal);
        $parcelas = [];
        foreach ($pedidos as $pedido) {
            $ped = Pedido::find($pedido[0]);
            $financeiro_id = $ped->financeiro_id;
            if (is_null($financeiro_id) && !is_null($ped->nfce_id)) {
                $financeiro_id = $ped->nfEmitida->financeiro_id;
            }
            $financeiroparcelas = Financeiroparcela::where('financeiro_id', $financeiro_id)->pluck('id')->first();
            array_push($parcelas, $financeiroparcelas);
        }
        try {
            $financeiro = new Financeiro();
            $financeiro->empresa_id = $fechamento->empresa_id;
            $financeiro->grupo_id = $fechamento->grupo_id;
            $financeiro->cliente_id = $fechamento->cliente_id;
            $financeiro->pagarreceber = "R";
            $financeiro->documento = $fechamento->id;
            $financeiro->descricao = ("Fechamento de convênio do cliente: " . $fechamento->cliente->nome);
            $financeiro->cartaonsu = null;
            $financeiro->cartaoautorizacao = null;
            $financeiro->valor = $fechamento->valor;
            $financeiro->condicaopagamento_id = $fechamento->condicaopagamento_id;
            $financeiro->datacompetencia = $fechamento->dataemissao;
            $financeiro->dataemissao = $fechamento->dataemissao;
            $processor->setFinanceiro($financeiro);

            $rato = [];
            $raton["centrocusto_id"] = $this->empresa_config->centrocusto_id;
            $raton["planoconta_id"] = $this->empresa_config->planoconta_id;
            $raton["valor"] = $fechamento->valor;
            $raton["financeiro_id"] = $processor->getFinanceiro()->id;
            $raton["percentual"] = 1;
            array_push($rato, $raton);
            $processor->setRateios($rato);

            $parc = [];
            for ($i = 0; $i < count($parcelasnovo); $i++) {
                $financeiroparcela = new Financeiroparcela();
                $financeiroparcela["datavencimento"] = $fechamento->datavencimento;
                $financeiroparcela["valor"] = $parcelasnovo[$i]["valor"];
                $financeiroparcela["empresa_id"] = $this->empresa_id;
                $financeiroparcela["grupo_id"] = $this->grupo_id;
                $financeiroparcela["financeiro_id"] = $processor->getFinanceiro()->id;
                $financeiroparcela["multa"] = 0;
                $financeiroparcela["juros"] = 0;
                $financeiroparcela["desconto"] = 0;
                $financeiroparcela["valorefetivado"] = $parcelasnovo[$i]["valor"];
                $financeiroparcela["numero"] = count($parcelasnovo);
                $financeiroparcela["baixado"] = false;
                $financeiroparcela["datacompetencia"] = $fechamento->dataemissao;
                $financeiroparcela["pagarreceber"] = $processor->getFinanceiro()->pagarreceber;
                array_push($parc, $financeiroparcela);
            }
            $processor->setAgrupar(true);
            $processor->setParcelasOrigem($parcelas);
            $processor->setParcelas($parc);
            $gravar = $processor->gravar();
            if (!$gravar) {
                $erro = $processor->getErrors();
                throw new exception($erro);
            }
            $idfinanceiro = $processor->getFinanceiro()->id;
            $fechamento->update(["financeiro_id" => $idfinanceiro]);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
        return true;
    }

    private function checarPeriodo($data)
    {
        $dataemissao = Carbon::parse($data["dataemissao"])->startOfDay();
        $datavencimento = Carbon::parse($data["datavencimento"])->endOfDay();
        $cliente_id = $data["cliente_id"];
        $fechamentos = Conveniofechamento::where('cliente_id', $cliente_id)
            ->whereBetween('datavencimento', [$dataemissao, $datavencimento])
            ->get();
        if (count($fechamentos) > 0) {
            return false;
        } else {
            return true;
        }
    }

    private function getPedidos($fechamento)
    {
        $pedidos = DB::table('conveniofechamentopedidos as convenioped')
            ->join('pedidos', 'pedidos.id', 'convenioped.pedido_id')
            ->join('clientes', 'convenioped.cliente_id', 'clientes.id')
            ->where([
                ['conveniofechamento_id', $fechamento->id]
            ])->select(
                'pedidos.id',
                'clientes.nome as cliente',
                'convenioped.cliente_id',
                'pedidos.datahoraprevisaoentrega',
                'pedidos.valorvenda'
            )
            ->orderBy('cliente')->orderBy('pedidos.datahoraprevisaoentrega')->get();
        return $pedidos;
    }

    private function correcoesFechamento($fechamento)
    {
        $fechamento->dataemissao = requestDataOracle($fechamento->dataemissao, false, true);
        $fechamento->datavencimento = requestDataOracle($fechamento->datavencimento, false, true);
        $fechamento->valor = requestNumeroDecimalOracle($fechamento->valor);
        return $fechamento;
    }

    private function cancelarFinanceiro($fechamento)
    {
        $motivo = "EDICAO DO FECHAMENTO DE CONVENIO COD.: " . $fechamento->id;
        $parcelas_origem = $this->parcelasOrigem($fechamento->id);
        $financeiro = Financeiro::find($fechamento->financeiro_id);
        $processor = new FinanceiroProcessor();
        $processor->setFinanceiro($financeiro);
        $processor->setParcelasOrigem($parcelas_origem);
        $cancelado = $processor->desagruparParcelas($motivo);
        return $cancelado;
    }

    public function parcelasOrigem($id)
    {
        $pedidos = Conveniopedido::where('conveniofechamento_id', $id)->get()->pluck('pedido_id');
        $parcelas = [];
        foreach ($pedidos as $pedido) {
            $ped = Pedido::find($pedido);
            $financeiro_id = $ped->financeiro_id;
            if (is_null($financeiro_id) && !is_null($ped->nfce_id)) {
                $financeiro_id = $ped->nfEmitida->financeiro_id;
            }
            if (!is_null($financeiro_id)) {
                $financeiroparcelas = Financeiroparcela::where('financeiro_id', $financeiro_id)->pluck('id')->first();
                array_push($parcelas, $financeiroparcelas);
            }
        }
        return $parcelas;
    }

    //Ajax
    public function filtroPedidos($cliente, $datast, $dataen, $edit, $id)
    {
        $this->definitionIds();
        $comissao = Clienteconvenio::where('cliente_id', $cliente)->get()->first();
        $cliente_id = $cliente;
        $datainicio = Carbon::parse($datast)->startOfDay();
        $datafim = Carbon::parse($dataen)->endOfDay();
        $cond = Condicaopagamento::where([['grupo_id', $this->grupo_id], ['tipo', 4]])->pluck('id')->toArray();
        $pedidosituacao = Pedidosituacao::where([['grupo_id', $this->grupo_id], ['fechadoconcluido', 1]])->pluck('id')->toArray();
        if ($edit == "1") {
            $pedidos = Pedido::join('clientes', 'pedidos.cliente_id', 'clientes.id')
                ->leftJoin('conveniofechamentopedidos', 'pedidos.id', 'conveniofechamentopedidos.pedido_id')
                ->whereBetween('pedidos.datahoraprevisaoentrega', [$datainicio, $datafim])
                ->whereIn('pedidos.pedidosituacao_id', $pedidosituacao)
                ->whereIn('pedidos.condicaopagamento_id', $cond)
                ->where([
                    ['clientes.empresa_id', $this->empresa_id],
                    ['clientes.convenio_id', $cliente_id]
                ])
                ->whereRaw('(conveniofechamentopedidos.id IS NULL or conveniofechamentopedidos.conveniofechamento_id = ' . $id . ')')
                ->select(
                    'pedidos.id',
                    'pedidos.cliente_id',
                    'pedidos.valorvenda',
                    'pedidos.datahoraprevisaoentrega',
                    'clientes.nome'
                )
                ->orderBy('clientes.nome')->orderBy('pedidos.datahoraprevisaoentrega')->get();
        } else {
            $pedidos = Pedido::join('clientes', 'pedidos.cliente_id', 'clientes.id')
                ->leftJoin('conveniofechamentopedidos', 'pedidos.id', 'conveniofechamentopedidos.pedido_id')
                ->whereBetween('pedidos.datahoraprevisaoentrega', [$datainicio, $datafim])
                ->whereIn('pedidos.pedidosituacao_id', $pedidosituacao)
                ->whereIn('pedidos.condicaopagamento_id', $cond)
                ->where([
                    ['clientes.empresa_id', $this->empresa_id],
                    ['clientes.convenio_id', $cliente_id]
                ])
                ->whereNull('conveniofechamentopedidos.id')
                ->select(
                    'pedidos.id',
                    'pedidos.cliente_id',
                    'pedidos.valorvenda',
                    'pedidos.datahoraprevisaoentrega',
                    'clientes.nome'
                )
                ->orderBy('clientes.nome')->orderBy('pedidos.datahoraprevisaoentrega')->get();
        }
        $totalpedidos = $pedidos->sum('valorvenda');
        if ($comissao->comissaodestino == 2) {
            $totalpedidos = $totalpedidos - ($totalpedidos * ($comissao->comissao / 100));
        }
        $totalpedidos = requestNumeroDecimalOracle($totalpedidos);
        return compact('pedidos', 'totalpedidos');
    }

    private function definitionIds()
    {
        //busca empresa padrao
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
    }

    private function definition()
    {
        //busca empresa padrao
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //busca config da empresa
        $this->empresa_config = Session::get('empresa_config');
        //set grupo repository
        $this->repository->setGrupo($this->grupo_id);
        //set empresa repository
        $this->repository->setEmpresa($this->empresa_id);
    }

    public function emitirNF(Request $request){
        try {
            $id = $request->all()['conveniofechamento_id'];
            $fechamento = Conveniofechamento::find($id);
            throwIf(!$fechamento, 'Fechamendo de convênio não encontrado para emissão de NF');
            $empresaconfig = Empresaconfig::where('empresa_id', $fechamento->empresa_id)->first();
            throwIf(!$empresaconfig, 'Não existe configuração para essa empresa');
            $nfemitidaC = new NfemitidaController();
            $response = $nfemitidaC->processorFechamentoConvenionf($fechamento);
            $resp = $response->original;
            if ($resp["status"] == "OK") {
                $retorno = '';
                $retorno .= $resp["msg"] . ".";
                try {
                    $myRequest = new Request();
                    $myRequest->setMethod('GET');
                    $myRequest->request->add(['id' => $resp["id"]]);
                    $ret = $nfemitidaC->transmitirnf($resp["id"]);
                    $retorno .= $ret;
                } catch (Exception $etrans) {
                    $retorno .= " Houve um erro ao transmitir a NF: " . $etrans->getMessage();
                }
                return responseSuccess(['mensagem'=>$retorno, 'id'=>$resp["id"]]); 
            } else {
                return responseError($resp['msg']); 
            }
        } catch(\Exception $e){
            return responseError($e->getMessage());
        }
    }

    public function emitirBoleto(Request $request){
        try {
            $id = $request->all()['conveniofechamento_id'];
            $fechamento = Conveniofechamento::find($id);
            throwIf(!$fechamento, 'Fechamendo de convênio não encontrado para emissão de boleto');
            throwIf(!$fechamento->financeiro, 'Financeiro referente a esse fechamendo de convênio não encontrado para emissão de boleto');
            throwIf(count($fechamento->financeiro->boleto)>0, 'Já foi emitido boleto para esse fechamento');
            $empresaconfig = Empresaconfig::where('empresa_id', $fechamento->empresa_id)->first();
            throwIf(!$empresaconfig, 'Não existe configuração para essa empresa');

            $parcelas = [];
            foreach ($fechamento->financeiro->financeiroparcela as $parc) {
                throwIf($parc->baixado, 'Não é possível gerar o boleto, já existem parcelas baixadas no financeiro');
                throwIf(Carbon::parse($parc->datavencimento)->format('Ymd') < Carbon::now()->format('Ymd'), 'Não é possível gerar o boleto, existem parcelas vencidas');
                
                $newparc = new stdClass();
                $newparc->id = $parc->id;
                $newparc->numero = $parc->numero;
                $newparc->datacompetencia = Carbon::parse($parc->datacompetencia)->format('d/m/Y');
                $newparc->datavencimento = Carbon::parse($parc->datavencimento)->format('d/m/Y');
                $newparc->valor = requestNumeroDecimalOracle($parc->valor);
                $newparc->juros = 0;
                $newparc->multa = 0;
                $newparc->valorefetivado = $parc->valorefetivado;
                $newparc->cliente_id = $fechamento->cliente_id;
                array_push($parcelas, $newparc);
            }
            $data = [
                'inseredescparcela' => 'true',
                'conta_id' => $empresaconfig->contaconvenionf_id,
                'parcelas' => json_encode($parcelas),
            ];
            DB::beginTransaction();
            $financeiro = Financeiro::find($fechamento->financeiro_id);
            if($financeiro){
                if($empresaconfig->condicaopagamentoconvenio_id && $empresaconfig->condicaopagamentoconvenio_id != $financeiro->condicaopagamento_id){
                    $financeiro->condicaopagamento_id = $empresaconfig->condicaopagamentoconvenio_id;
                }
                if($fechamento->nfemitida){
                    $financeiro->documento = 'NF '.$fechamento->nfemitida->nfnumero;
                }
                $financeiro->save();
            }
            $processor = new BoletoProcessor();
            $ret =  $processor->gerarBoleto($data, true);
            DB::commit();
            if ($ret["status"] != 'OK|') {
                return responseError($ret["msg"]);
            } else {
                return responseSuccess(['mensagem'=>"Boleto gerado com sucesso", 'boleto'=>$ret["data"], 'id'=>$fechamento->id]);
            }

        } catch(\Exception $e){
            DB::rollback();
            return responseError($e->getMessage());
        }
    }
}
