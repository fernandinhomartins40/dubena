<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Setor;
use App\Produto;
use App\Services\CarbonCustom as Carbon;
use App\Estoquetransferencia;
use App\Estoquesetorhistorico;
use App\Estoquetransferenciaitem;
use App\Processors\EstoqueProcessor;
use App\Http\Requests\EstoqueTransferenciasRequest;

class EstoqueTransferenciasController extends Controller
{

    private $rowTotal;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Estoquetransferencia $trans)
    {
        $this->authorize('view',$trans);
        $estoquetransferencias = Estoquetransferencia::where([['empresa_id', Session::get('empresa_padrao')->id], [DB::raw("TO_CHAR(datahora, 'YYYY-MM-DD')"), Carbon::now()->toDateString()]])->get();

        if(count($_GET) > 0 && isset($_GET['dataInicio']) && isset($_GET['dataFim']) && strlen($_GET['dataInicio']) > 0 && strlen($_GET['dataFim']) > 0)
            return $this->getFiltros($_GET);

        return view('estoque.transferencias.estoquetransferencias', compact('estoquetransferencias'));
    }

    private function getFiltros($get)
    {
        $dataInicio = insertDataOracle($get['dataInicio']) . ' 00:00:00';
        $dataFim = insertDataOracle($get['dataFim']) . ' 23:59:59';
        $estoquetransferencias = Estoquetransferencia::where([['empresa_id', Session::get('empresa_padrao')->id]])->whereBetween('datahora', [$dataInicio, $dataFim])->get();
        return view('estoque.transferencias.estoquetransferencias', compact('estoquetransferencias', 'dataInicio', 'dataFim'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Estoquetransferencia $trans)
    {
        $this->authorize('create',$trans);
        $produtos = Produto::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
            ])->pluck('descricao', 'id');
        $setorOrigem = Setor::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
            ])->pluck('descricao', 'id');
        $setorDestino = Setor::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
            ])->pluck('descricao', 'id');
        $colaborador = \Auth::user()->name;
        $produtosEstoque = [];
        return view('estoque.transferencias.estoquetransferencias_form', compact('produtos', 'setorOrigem', 'setorDestino', 'colaborador', 'produtosEstoque'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(EstoqueTransferenciasRequest $request, Estoquetransferencia $trans)
    {
        $this->authorize('create',$trans);
        $data = $request->all();
        $data['datahoracompetencia'] = Carbon::now();
        $data['datahora'] = insertDataOracle($data['datahora']);
        $data['user_id'] = \Auth::user()->id;
        $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
        $data['empresa_id'] = Session::get('empresa_padrao')->id;

        DB::beginTransaction();
        try {

            $estoqueTransferencia = Estoquetransferencia::create($data);
            $insertProdutos = $this->insertUpdateProdutos($data, $estoqueTransferencia);
            if (is_bool($insertProdutos)) {
                DB::commit();
                return \Redirect::to('/estoquetransferencias')
                ->withMessageSuccess('Transferência realizada com sucesso!');
            } else {
                DB::rollback();
                return \Redirect::to('/estoquetransferencias/create')
                ->withErrors($insertProdutos)
                ->withInput();
            }
        } catch (ValidationException $e) {
            DB::rollback();
            return \Redirect::to('/estoquetransferencias/create')
            ->withErrors($e->getMessage())
            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/estoquetransferencias/create')
            ->withErrors($e->getMessage())
            ->withInput();
        }
    }

    private function insertUpdateProdutos($data, $estoqueTransferencia)
    {
        if ($data["produtos"] != null) {
            $produtos = json_decode($data["produtos"]);
            if (count($produtos) === 0) {
                return 'Ao menos um produto deve ser adicionado!';
            }
            foreach ($produtos as $produto) {
                try {
                    $estoqueTransferenciaItem = new Estoquetransferenciaitem();
                    $estoqueTransferenciaItem->estoquetransferencia_id = $estoqueTransferencia->id;
                    $estoqueTransferenciaItem->produto_id = $produto[0];
                    $estoqueTransferenciaItem->quantidade = $produto[2];
                    $estoqueTransferenciaItem->save();
                } catch (\Exception $e) {
                    return $e->getMessage();
                }
                $estoquesetorhistoricos = [];
                $estoquesetorhistorico = new Estoquesetorhistorico();
                $estoquesetorhistorico->user_id = \Auth::user()->id;
                $estoquesetorhistorico->setor_id = $data['origemsetor_id'];
                $estoquesetorhistorico->produto_id = $produto[0];
                $estoquesetorhistorico->movimentacao = 'SAIDA';
                $estoquesetorhistorico->quantidade = $produto[2];
                $estoquesetorhistorico->motivo = "Transferencia de estoque: " . $data['observacoes'];
                $estoquesetorhistorico->datahora = $data['datahoracompetencia'];
                $estoquesetorhistorico->datahoracompetencia = $data['datahora'];
                $estoquesetorhistorico->entidade = 'Estoquetransferencia';
                $estoquesetorhistorico->entidade_id = $estoqueTransferencia->id;
                $estoquesetorhistorico->grupo_id = Session::get('empresa_padrao')->grupo_id;
                $estoquesetorhistorico->empresa_id = Session::get('empresa_padrao')->id;
                array_push($estoquesetorhistoricos, $estoquesetorhistorico);

                $estoquesetorhistorico = new Estoquesetorhistorico();
                $estoquesetorhistorico->user_id = \Auth::user()->id;
                $estoquesetorhistorico->setor_id = $data['destinosetor_id'];
                $estoquesetorhistorico->produto_id = $produto[0];
                $estoquesetorhistorico->movimentacao = 'ENTRADA';
                $estoquesetorhistorico->quantidade = $produto[2];
                $estoquesetorhistorico->motivo = "Transferencia de estoque: " . $data['observacoes'];
                $estoquesetorhistorico->datahora = $data['datahoracompetencia'];
                $estoquesetorhistorico->datahoracompetencia = $data['datahora'];
                $estoquesetorhistorico->entidade = 'Estoquetransferencia';
                $estoquesetorhistorico->entidade_id = $estoqueTransferencia->id;
                $estoquesetorhistorico->grupo_id = Session::get('empresa_padrao')->grupo_id;
                $estoquesetorhistorico->empresa_id = Session::get('empresa_padrao')->id;
                array_push($estoquesetorhistoricos, $estoquesetorhistorico);

                $estoqueProcessor = new EstoqueProcessor();
                $transferirEstoque = $estoqueProcessor->movimentarEstoque($estoquesetorhistoricos);
                if (!$transferirEstoque) {
                    return implode(' ', $estoqueProcessor->getErrors());
                }
            }
            return true;
        } else {
            return 'Ao menos um produto deve ser adicionado!';
        }
        return true;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Estoquetransferencia $trans)
    {
        $this->authorize('view',$trans);
        $estoqueTransferencia = Estoquetransferencia::findOrFail($id);
        $this->authorize('igualdade',$estoqueTransferencia);
        $colaborador = $estoqueTransferencia->user->name;
        $produtosEstoque = Produto::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
            ])->pluck('descricao', 'id');
        $setorOrigem = Setor::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
            ])->pluck('descricao', 'id');
        $setorDestino = Setor::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
            ])->pluck('descricao', 'id');

        $show = true;

        return View('estoque.transferencias.estoquetransferencias_form', compact('estoqueTransferencia', 'show', 'colaborador', 'setorOrigem', 'setorDestino', 'produtosEstoque'));
    }

    function gerarPDF($id, Estoquetransferencia $trans)
    {
        $this->authorize('view',$trans);
        $estoqueTransferencia = Estoquetransferencia::findOrFail($id);
        $this->authorize('igualdade',$estoqueTransferencia);
        $estoquetransferenciaitems = Estoquetransferenciaitem::where('estoquetransferencia_id', $id)->get();
        $totalItems = Estoquetransferenciaitem::where('estoquetransferencia_id', $id)->sum('quantidade');
        $codigo = $estoqueTransferencia->id;
        $dataTransferencia = requestDataOracle($estoqueTransferencia->datahora);
        $setorOrigem = $estoqueTransferencia->origemSetor->descricao;
        $setorDestino = $estoqueTransferencia->destinoSetor->descricao;
        $user = $estoqueTransferencia->user->name;
        $titulo = 'RELATÓRIO DE TRANSFERÊNCIA';
        $filtro = '';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('reports.report_estoquetransferencia', compact('estoquetransferenciaitems', 'titulo', 'filtro', 'codigo', 'dataTransferencia', 'setorOrigem', 'setorDestino', 'user', 'totalItems'));
//        return View('reports.report_estoquetransferencia', compact('estoquetransferenciaitems', 'titulo', 'filtro', 'codigo',
//                'dataTransferencia', 'setorOrigem', 'setorDestino', 'user', 'totalItems'));
        return $pdf->stream();
    }

    // function exportarXLS($id)
    // {
    //     $estoqueTransferencia = Estoquetransferencia::findOrFail($id);
    //     $estoquetransferenciaitems = Estoquetransferenciaitem::where('estoquetransferencia_id', $id)->get();
    //     $totalItems = Estoquetransferenciaitem::where('estoquetransferencia_id', $id)->sum('quantidade');
    //     $codigo = $estoqueTransferencia->id;

    //     $this->rowTotal = 15 + Estoquetransferenciaitem::where('estoquetransferencia_id', $id)->count('quantidade');
    //     $dataTransferencia = requestDataOracle($estoqueTransferencia->datahora);
    //     $setorOrigem = $estoqueTransferencia->origemSetor->descricao;
    //     $setorDestino = $estoqueTransferencia->destinoSetor->descricao;
    //     $user = $estoqueTransferencia->user->name;

    //     $xls = Array();
    //     array_push($xls, ['', '', 'RELATÓRIO DE TRANSFERÊNCIAS', '', 'Emitido em ' . Carbon::now()->format('d/m/Y H:i:s')]);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['Cód Transferência: ' . $codigo]);
    //     array_push($xls, ['Data Transferência: ' . $dataTransferencia]);
    //     array_push($xls, ['Setor Origem: ' . $setorOrigem]);
    //     array_push($xls, ['Setor Destino: ' . $setorDestino]);
    //     array_push($xls, ['Autorizado por: ' . $user]);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);

    //     array_push($xls, ['', 'Cód Produto', 'Produto', 'Quantidade', '']);

    //     foreach ($estoquetransferenciaitems as $estoquetransferenciaitem) {
    //         array_push($xls, ['', $estoquetransferenciaitem->produto_id, $estoquetransferenciaitem->produto->descricao, $estoquetransferenciaitem->quantidade, '']);
    //     }
    //     array_push($xls, ['', 'TOTAL', '', $totalItems]);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '', 'RETORNO']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P13 Cheio: ________', '', 'P13 Vazio: _______', '', 'P13 Troca: _______']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P20 Cheio: ________', '', 'P20 Vazio: _______', '', 'P20 Troca: _______']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P45 Cheio: ________', '', 'P45 Vazio: _______', '', 'P45 Troca: _______']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '', 'À CARREGAR']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P13 Cheio: ________']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P20 Cheio: ________']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P45 Cheio: ________']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['OBS.:', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['', '', 'ESTOQUE FINAL']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P13 Cheio: ________', '', 'P13 Vazio: _______', '', 'P13 Troca: _______']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P20 Cheio: ________', '', 'P20 Vazio: _______', '', 'P20 Troca: _______']);
    //     array_push($xls, ['', '']);
    //     array_push($xls, ['P45 Cheio: ________', '', 'P45 Vazio: _______', '', 'P45 Troca: _______']);
    //     array_push($xls, ['']);

    //     Excel::create('Transferencia de estoque - ' . $dataTransferencia, function($excel) use ($xls) {
    //         $excel->sheet('Transferencia de estoque', function($sheet) use($xls) {
    //             $sheet->setAutoSize(false);
    //             $sheet->setPageMargin(0.5);
    //             $sheet->fromArray($xls, null, 'A1', true, false);

    //             $sheet->cells('C1:E1', function($cells) {
    //                 $cells->setFontWeight('bold');
    //                 $cells->setFontSize(12);
    //             });
    //             $sheet->setHeight(1, 40);
    //             $sheet->setHeight(2, 7);
    //             $sheet->cells("B14:D14", function ($cells) {
    //                 $cells->setBorder('medium', 'medium', 'medium', 'medium');
    //                 $cells->setFontWeight('bold');
    //             });
    //             $sheet->cells("A3:E3", function ($cells) {
    //                 $cells->setBorder('none', 'none', 'medium', 'none');
    //             });
    //             $sheet->cells('B' . $this->rowTotal . ':D' . $this->rowTotal, function ($cells) {
    //                 $cells->setBorder('medium', 'medium', 'medium', 'medium');
    //             });
    //             for ($i = 14; $i < $this->rowTotal + 1; $i++) {
    //                 $sheet->cells('B' . ($i), function ($cells) {
    //                     $cells->setBorder('thin', 'thin', 'thin', 'thin');
    //                 });
    //                 $sheet->cells('C' . ($i), function ($cells) {
    //                     $cells->setBorder('thin', 'thin', 'thin', 'thin');
    //                 });
    //                 $sheet->cells('D' . ($i), function ($cells) {
    //                     $cells->setBorder('thin', 'thin', 'thin', 'thin');
    //                 });
    //             }

    //             $sheet->setWidth('A', 18);
    //             $sheet->setWidth('B', 20);
    //             $sheet->setWidth('C', 40);
    //             $sheet->setWidth('D', 20);
    //             $sheet->setWidth('E', 18);

    //             $sheet->cells('B' . $this->rowTotal . ':D' . $this->rowTotal, function ($cells) {
    //                 $cells->setBackground('#C0C0C0');
    //             });
    //             for ($i = 1; $i < 10; $i++) {
    //                 $sheet->mergeCells('A' . $i . ':e' . $i);
    //             }
    //             for ($i = 0; $i < count($xls); $i++) {
    //                 $sheet->cells("A" . ($i) . ":E" . ($i), function($cells) {
    //                     $cells->setAlignment('center');
    //                 });
    //                 if ($i > $this->rowTotal + 1) {
    //                     $sheet->cells('A' . $i . ':E' . $i, function($cells) {
    //                         $cells->setAlignment('right');
    //                     });
    //                     $sheet->cells('C' . $i, function($cells) {
    //                         $cells->setAlignment('center');
    //                     });
    //                 }
    //                 $sheet->cells("A3:A7", function($cells) {
    //                     $cells->setAlignment('left');
    //                 });
    //                 if ($i === $this->rowTotal + 4 or $i === $this->rowTotal + 13 or $i === $this->rowTotal + 25) {
    //                     $sheet->cells("A" . $i . ':E' . $i, function($cells) {
    //                         $cells->setAlignment('center');
    //                         $cells->setBackground('#C0C0C0');
    //                         $cells->setBorder('thin', 'thin', 'thin', 'thin');
    //                     });
    //                 }
    //                 if ($i === $this->rowTotal + 22) {
    //                     $sheet->cells("A" . ($i + 1) . ':E' . ($i + 1), function($cells) {
    //                         $cells->setBorder('thin', 'none', 'none', 'none');
    //                     });
    //                     $sheet->cells("B" . $i . ':E' . $i, function($cells) {
    //                         $cells->setBorder('thin', 'none', 'none', 'none');
    //                     });
    //                 }

    //                 if ($xls[$i][0] == "Nome") {
    //                     $sheet->cells('A' . ($i) . ':D' . ($i), function($cells) {
    //                         $cells->setFontWeight('bold');
    //                         $cells->setFontSize(13);
    //                     });
    //                     $sheet->cells('A' . ($i + 1) . ':D' . ($i + 1), function($cells) {
    //                         $cells->setFontWeight('bold');
    //                         $cells->setFontSize(13);
    //                     });
    //                 }
    //             }
    //             $sheet->cells("E1", function ($cells) {
    //                 $cells->setAlignment('right');
    //             });
    //             if (Session::get('empresa_padrao')->logo != null) {
    //                 $image = imagecreatefromstring(Session::get('empresa_padrao')->logo);
    //                 imagesavealpha($image, true);
    //                 $drawing = new PHPExcel_Worksheet_MemoryDrawing();
    //                 $drawing->setName('teste');
    //                 $drawing->setImageResource($image);
    //                 $drawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_PNG);
    //                 $drawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
    //                 $drawing->setWidthAndHeight(148, 70);
    //                 $drawing->setResizeProportional(true);
    //                 $drawing->setCoordinates('A1');
    //                 $drawing->setOffsetX(5);
    //                 $drawing->setOffsetY(5);
    //                 $drawing->setWorksheet($sheet);
    //             }
    //         });
    //     })->export('xls');
    // }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

    }

}
