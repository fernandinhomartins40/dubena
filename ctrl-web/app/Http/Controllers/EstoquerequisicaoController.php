<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Setor;
use App\Produto;
use App\Planoconta;
use App\Centrocusto;
use App\Http\Requests;
use App\Estoquerequisicao;
use Illuminate\Http\Request;
use App\Estoquerequisicaoitem;
use App\Estoquesetorhistorico;
use Maatwebsite\Excel\Facades\Excel;
use App\Processors\EstoqueProcessor;
use App\Services\CarbonCustom as Carbon;
use App\Http\Requests\EstoqueRequisicoesRequest;

class EstoquerequisicaoController extends Controller
{

    function index(Estoquerequisicao $req)
    {
        // dd(Estoquerequisicao::find(496));
        $this->authorize('view',$req);
        $dataInicial = Carbon::now()->subDays(2)->startOfDay();
        $dataFinal = Carbon::now()->endOfDay();
        $dados = Estoquerequisicao::leftJoin('planocontas plano', 'estoquerequisicaos.planoconta_id', 'plano.id')
            ->leftJoin('centrocustos centro', 'estoquerequisicaos.centrocusto_id', 'centro.id')
            ->whereBetween('datahora', [$dataInicial, $dataFinal])
            ->where('estoquerequisicaos.empresa_id', Session::get('empresa_padrao')->id)
            ->select('estoquerequisicaos.id','estoquerequisicaos.datahora',
                'plano.descricao as plano', 'centro.descricao as centro',
                'estoquerequisicaos.user_id','estoquerequisicaos.cancelado')
            ->get();
        $dataInicial = requestDataOracle($dataInicial);
        $dataFinal = requestDataOracle($dataFinal);
        return view('estoque.requisicao.estoquerequisicao', compact("dados", 'dataInicial', 'dataFinal'));
    }

    function filter($dataInicial, $dataFinal)
    {
        $datainicio = Carbon::parse($dataInicial)->startOfDay();
        $datafim = Carbon::parse($dataFinal)->endOfDay();
        $dados = Estoquerequisicao::leftJoin('planocontas plano', 'estoquerequisicaos.planoconta_id', 'plano.id')
            ->leftJoin('centrocustos centro', 'estoquerequisicaos.centrocusto_id', 'centro.id')
            ->whereBetween('datahora', [$datainicio, $datafim])
            ->where('estoquerequisicaos.empresa_id', Session::get('empresa_padrao')->id)
            ->select('estoquerequisicaos.id','estoquerequisicaos.datahora',
                'plano.descricao as plano', 'centro.descricao as centro',
                'estoquerequisicaos.user_id', 'estoquerequisicaos.cancelado')
            ->get();
        $dataInicial = requestDataOracle($dataInicial, false);
        $dataFinal = requestDataOracle($dataFinal, false);
        return view('estoque.requisicao.estoquerequisicao', compact("dados", 'dataInicial', 'dataFinal'));
    }

    function create(Estoquerequisicao $req, Request $request)
    {
        $this->authorize('create',$req);
        $empresa_id = Session::get('empresa_padrao')->id;
        $produtos = Produto::where([
                ['empresa_id', $empresa_id],
                ['ativo', 1]
            ])->pluck('descricao', 'id');

        $setor = Setor::where([
                ['empresa_id', $empresa_id],
                ['ativo', 1]
            ])->pluck('descricao', 'id');

        $colaborador = \Auth::user()->name;
        $produtosEstoque = [];
        $centrocustoslnk = [];
        $centrocusto_descricao = '';
        $centrocusto_id = '';
        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = Planoconta::menuspermissoesAll();
        $planocontaslnk = [];
        $planoconta_descricao = '';
        $planoconta_id = '';

        return view('estoque.requisicao.estoquerequisicao_form', compact('produtos', 'setor', 'colaborador', 'produtosEstoque', 'planoconta_id', 'centrocusto_id', 'centrocusto_descricao', 'planoconta_descricao', 'centrocustos', 'planocontas'));
    }

    function store(EstoqueRequisicoesRequest $request, Estoquerequisicao $req)
    {
        $this->authorize('create',$req);
        $data = $request->all();
        $url = isset($data["filtro_url"]) ? $data["filtro_url"] : null;
        $data['datahora'] = insertDataOracle($data['datahora']);
        $data['user_id'] = \Auth::user()->id;
        $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
        $data['empresa_id'] = Session::get('empresa_padrao')->id;
        DB::beginTransaction();
        try {
            $estoqueRequisicao = Estoquerequisicao::create($data);

            $insertProdutos = $this->insertUpdateProdutos($data, $estoqueRequisicao);
            if (! is_bool($insertProdutos)) {
                throw new \Exception($insertProdutos);
            }

        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/estoquerequisicao/create')
                ->withErrors($e->getMessage())
                ->withInput();
        }
        $redirect = '/estoquerequisicao';
        if ($url) {
            $redirect .= substr($url, 0, 1) == "/" ? $url : "/$url";
        }
        DB::commit();
        return \Redirect::to($redirect)->withMessageSuccess('Requisição realizada com sucesso!');
    }

    public function show($id, Estoquerequisicao $req)
    {
        $this->authorize('view',$req);
        return $this->returnViewShowEdit($id, 'show');
    }

    private function insertUpdateProdutos($data, $estoqueRequisicao)
    {
        if ($data["produtos"] != null) {
            $produtos = json_decode($data["produtos"]);
            if (count($produtos) === 0) {
                return 'Ao menos um produto deve ser adicionado!';
            }
            foreach ($produtos as $produto) {
                try {
                    $estoqueRequisicaoItem = new Estoquerequisicaoitem();
                    $estoqueRequisicaoItem->estoquerequisicao_id = $estoqueRequisicao->id;
                    $estoqueRequisicaoItem->produto_id = $produto[0];
                    $estoqueRequisicaoItem->setor_id = $produto[3];
                    $estoqueRequisicaoItem->quantidade = insertNumeroDecimalOracle($produto[4]);
                    $estoqueRequisicaoItem->customedio = $produto[5] !== '' ? insertNumeroDecimalOracle($produto[5]) : 0;
                    $estoqueRequisicaoItem->entradasaida = 'S';
                    $estoqueRequisicaoItem->save();
                } catch (\Exception $e) {
                    return $e->getMessage();
                }
                $estoquesetorhistoricos = [];
                $estoquesetorhistorico = new Estoquesetorhistorico();
                $estoquesetorhistorico->user_id = \Auth::user()->id;
                $estoquesetorhistorico->setor_id = $produto[3];
                $estoquesetorhistorico->produto_id = $produto[0];
                $estoquesetorhistorico->movimentacao = 'SAIDA';
                $estoquesetorhistorico->quantidade = insertNumeroDecimalOracle($produto[4]);
                $estoquesetorhistorico->motivo = "Requisicao de estoque: " . $data['observacoes'];
                $estoquesetorhistorico->datahora = $data['datahora'];
                $estoquesetorhistorico->datahoracompetencia = $data['datahora'];
                $estoquesetorhistorico->entidade = 'Estoquerequisicao';
                $estoquesetorhistorico->entidade_id = $estoqueRequisicao->id;
                $estoquesetorhistorico->grupo_id = Session::get('empresa_padrao')->grupo_id;
                $estoquesetorhistorico->empresa_id = Session::get('empresa_padrao')->id;
                array_push($estoquesetorhistoricos, $estoquesetorhistorico);

                $estoqueProcessor = new EstoqueProcessor();
                $transferirEstoque = $estoqueProcessor->movimentarEstoque($estoquesetorhistoricos);
                if (!$transferirEstoque) {
                    return $estoqueProcessor->getErrors();
                }
            }
            return true;
        } else {
            return 'Ao menos um produto deve ser adicionado!';
        }
        return true;
    }

    function gerarPDF($id, Estoquerequisicao $req)
    {
        $this->authorize('view',$req);
        $estoqueRequisicao = Estoquerequisicao::findOrFail($id);
        $this->authorize('igualdade',$estoqueRequisicao);
//
        $estoquerequisicaoitems = Estoquerequisicaoitem::where('estoquerequisicao_id', $id)->get();
        $totalItems = Estoquerequisicaoitem::where('estoquerequisicao_id', $id)->sum('quantidade');
        $codigo = $estoqueRequisicao->id;
        $dataRequisicao = requestDataOracle($estoqueRequisicao->datahora);
        $user = $estoqueRequisicao->user->name;
        $observacoes = $estoqueRequisicao->observacoes;
        $titulo = 'AUTORIZAÇÃO DE ESTOQUE';
        $filtro = '';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('reports.report_estoquerequisicao', compact('estoquerequisicaoitems', 'titulo', 'codigo', 'dataRequisicao', 'user', 'observacoes'));
//        return View('reports.report_estoquerequisicao', compact('estoquerequisicaoitems', 'titulo', 'codigo', 'dataRequisicao', 'setor', 'user', 'observacoes'));
        return $pdf->stream();
    }

//     function exportarXLS($id)
//     {
//         $estoqueRequisicao = Estoquerequisicao::findOrFail($id);
//         $estoquerequisicaoitems = Estoquerequisicaoitem::where('estoquerequisicao_id', $id)->get();
//         $totalItems = Estoquerequisicaoitem::where('estoquerequisicao_id', $id)->sum('quantidade');
//         $codigo = $estoqueRequisicao->id;
//         $observacoes = $estoqueRequisicao->observacoes;

//         $this->rowTotal = 15 + Estoquerequisicaoitem::where('estoquerequisicao_id', $id)->count('quantidade');
//         $dataRequisicao = requestDataOracle($estoqueRequisicao->datahora);
//         $user = $estoqueRequisicao->user->name;

//         $xls = Array();
//         array_push($xls, ['', '', '', 'AUTORIZAÇÃO DE ESTOQUE', '', '', 'Emitido em ' . Carbon::now()->format('d/m/Y H:i:s')]);
//         array_push($xls, ['', '']);
//         array_push($xls, ['', '']);
//         array_push($xls, ['', '']);
//         array_push($xls, ['', '']);
//         array_push($xls, ['Cód Requisição: ' . $codigo]);
//         array_push($xls, ['Data Requisição: ' . $dataRequisicao]);
//         array_push($xls, ['Autorizado por: ' . $user]);
//         array_push($xls, ['Observações: ' . $observacoes]);
//         array_push($xls, ['', '']);
//         array_push($xls, ['', '']);
//         array_push($xls, ['', '']);
//         array_push($xls, ['', '']);

//         array_push($xls, ['', 'Cód Produto', 'Produto', 'Setor Saída', 'Valor', 'Qde']);

//         foreach ($estoquerequisicaoitems as $estoquerequisicaoitem) {
//             array_push($xls, ['', $estoquerequisicaoitem->produto_id, $estoquerequisicaoitem->produto->descricao,
//                 $estoquerequisicaoitem->setor->descricao, requestNumeroDecimalOracle($estoquerequisicaoitem->customedio), $estoquerequisicaoitem->quantidade]);
//         }

//         array_push($xls, ['']);
//         array_push($xls, ['']);
//         array_push($xls, ['']);
//         array_push($xls, ['']);
//         array_push($xls, ['', '', 'Ass. Resp. Solicitante', '', 'Ass. Resp. Estoque']);
//         array_push($xls, ['']);

//         Excel::create('Requisicao de estoque - ' . $dataRequisicao, function($excel) use ($xls) {
//             $excel->sheet('Requisicao de estoque', function($sheet) use($xls) {
//                 $sheet->setAutoSize(false);
//                 $sheet->setPageMargin(0.5);
//                 $sheet->fromArray($xls, null, 'A1', true, false);

//                 $sheet->cells('B1:G1', function($cells) {
//                     $cells->setFontWeight('bold');
//                     $cells->setFontSize(12);
//                 });
//                 $sheet->cells("B14:F14", function ($cells) {
//                     $cells->setBorder('medium', 'medium', 'medium', 'medium');
//                     $cells->setFontWeight('bold');
//                 });
//                 $sheet->cells("A3:G3", function ($cells) {
//                     $cells->setBorder('none', 'none', 'medium', 'none');
//                 });
//                 $sheet->setHeight(1, 40);
//                 $sheet->setHeight(2, 7);
//                 for ($i = 14; $i < $this->rowTotal; $i++) {
//                     $sheet->cells('B' . ($i), function ($cells) {
//                         $cells->setBorder('thin', 'thin', 'thin', 'thin');
//                     });
//                     $sheet->cells('C' . ($i), function ($cells) {
//                         $cells->setBorder('thin', 'thin', 'thin', 'thin');
//                     });
//                     $sheet->cells('D' . ($i), function ($cells) {
//                         $cells->setBorder('thin', 'thin', 'thin', 'thin');
//                     });
//                     $sheet->cells('E' . ($i), function ($cells) {
//                         $cells->setBorder('thin', 'thin', 'thin', 'thin');
//                     });
//                     $sheet->cells('F' . ($i), function ($cells) {
//                         $cells->setBorder('thin', 'thin', 'thin', 'thin');
//                     });
//                 }

//                 $sheet->setWidth('A', 5);
//                 $sheet->setWidth('B', 15);
//                 $sheet->setWidth('C', 30);
//                 $sheet->setWidth('D', 40);
//                 $sheet->setWidth('E', 30);
//                 $sheet->setWidth('F', 15);
//                 $sheet->setWidth('G', 5);
// //                $sheet->mergeCells('C1:D1');
//                 for ($i = 1; $i < 10; $i++) {
//                     $sheet->mergeCells('A' . $i . ':e' . $i);
//                 }
//                 for ($i = 0; $i < count($xls); $i++) {
//                     $sheet->cells("A" . ($i) . ":F" . ($i), function($cells) {
//                         $cells->setAlignment('center');
//                     });
//                     if ($i > $this->rowTotal + 1) {
//                         $sheet->cells('A' . $i . ':F' . $i, function($cells) {
//                             $cells->setAlignment('right');
//                         });
//                         $sheet->cells('C' . $i, function($cells) {
//                             $cells->setAlignment('center');
//                         });
//                     }
//                     $sheet->cells("A3:A7", function($cells) {
//                         $cells->setAlignment('left');
//                     });

//                     if ($i === $this->rowTotal + 2) {
//                         $sheet->setHeight($i, 30);
//                         $sheet->cells("C" . $i, function($cells) {
//                             $cells->setBorder('none', 'none', 'dashed', 'none');
//                         });
//                         $sheet->cells("E" . $i, function($cells) {
//                             $cells->setBorder('none', 'none', 'dashed', 'none');
//                         });
//                     }
//                     if ($i === $this->rowTotal + 3) {
//                         $sheet->setHeight($i, 2);
//                     }
//                     if ($i === $this->rowTotal + 4) {
//                         $sheet->cells("B" . $i . ':G' . $i, function($cells) {
//                             $cells->setAlignment('center');
//                         });
//                     }

//                     if ($xls[$i][0] == "Nome") {
//                         $sheet->cells('A' . ($i) . ':F' . ($i), function($cells) {
//                             $cells->setFontWeight('bold');
//                             $cells->setFontSize(13);
//                         });
//                         $sheet->cells('A' . ($i + 1) . ':F' . ($i + 1), function($cells) {
//                             $cells->setFontWeight('bold');
                            // $cells->setFontSize(13);
//                         });
//                     }
//                 }
//                 $sheet->cells("G1", function ($cells) {
//                     $cells->setAlignment('right');
//                 });
//                 if (Session::get('empresa_padrao')->logo != null) {
//                     $image = imagecreatefromstring(Session::get('empresa_padrao')->logo);
//                     imagesavealpha($image, true);
//                     $drawing = new PHPExcel_Worksheet_MemoryDrawing();
//                     $drawing->setName('');
//                     $drawing->setImageResource($image);
//                     $drawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_PNG);
//                     $drawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
//                     $drawing->setWidthAndHeight(148, 70);
//                     $drawing->setResizeProportional(true);
//                     $drawing->setCoordinates('A1');
//                     $drawing->setOffsetX(5);
//                     $drawing->setOffsetY(5);
//                     $drawing->setWorksheet($sheet);
//                 }
//             });
//         })->download('xls');
//     }

    function edit($id, Estoquerequisicao $req)
    {
        $this->authorize('update',$req);
        return $this->returnViewShowEdit($id);
    }

    private function returnViewShowEdit($id, $acao = 'edit')
    {

        $estoqueRequisicao = Estoquerequisicao::findOrFail($id);
        $this->authorize('igualdade',$estoqueRequisicao);
        $estoquerequisicaoitems = $this->corrigirData(Estoquerequisicaoitem::where('estoquerequisicao_id', $id)->get());
        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = Planoconta::menuspermissoesAll();
        $estoqueRequisicao->datahora = requestDataOracle($estoqueRequisicao->datahora);
        $observacoes = $estoqueRequisicao->observacoes;
        $colaborador = $estoqueRequisicao->user->name;
        $centrocusto_id = $estoqueRequisicao->centrocusto_id;
        $centrocusto_descricao = $centrocusto_id ? $estoqueRequisicao->centrocusto->descricao : null;
        $planoconta_id = $estoqueRequisicao->planoconta_id;
        $planoconta_descricao = $planoconta_id ? $estoqueRequisicao->planoconta->descricao : null;
        $show = true;
        if($acao == 'show')
            return view('estoque.requisicao.estoquerequisicao_edit', compact('estoqueRequisicao', 'observacoes', 'colaborador', 'planoconta_descricao', 'planoconta_id', 'centrocusto_descricao', 'centrocusto_id', 'centrocustos', 'planocontas', 'estoquerequisicaoitems', 'show'));
        return view('estoque.requisicao.estoquerequisicao_edit', compact('estoqueRequisicao', 'observacoes', 'colaborador', 'planoconta_descricao', 'planoconta_id', 'centrocusto_descricao', 'centrocusto_id', 'centrocustos', 'planocontas', 'estoquerequisicaoitems'));
    }

    function update(Request $request, $id, Estoquerequisicao $req)
    {
        $this->authorize('update',$req);
        $estoqueRequisicao = Estoquerequisicao::find($id);
        $this->authorize('igualdade',$estoqueRequisicao);
        $data = $request->only('datahora', 'centrocusto_id', 'planoconta_id', 'observacoes', 'filtro_url');
        $data['datahora'] = insertDataOracle($data['datahora']);
        $url = isset($data["filtro_url"]) ? $data["filtro_url"] : null;
        DB::beginTransaction();
        try {
            $estoqueRequisicao->update($data);
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect('estoquerequisicao')
                ->withErrors( getErrorsException($ex) )
                ->withInput();
        }
        $redirect = "estoquerequisicao";
        if ($url) {
            $redirect .= substr($url, 0, 1) == "/" ? $url : "/$url";
        }
        DB::commit();
        return redirect($redirect)->withMessageSuccess('Atualizado com sucesso!');
    }

    function destroy($id, Estoquerequisicao $req)
    {
        $this->authorize('delete',$req);
        $requisicao = Estoquerequisicao::find($id);
        $this->authorize('igualdade',$requisicao);
        db::beginTransaction();
        try {
            if ($requisicao->cancelado == 1) {
                throw new \Exception("Requisição já está cancelada.");
            }

            Estoquerequisicao::where('id',$id)->update(['cancelado'=>'1']);
            $estoqueRequisicaoItems = Estoquerequisicaoitem::where('estoquerequisicao_id', $id)->get();
            $estoquesetorhistoricos = [];
            foreach ($estoqueRequisicaoItems as $estoqueRequisicaoItem) {
                $estoquesetorhistorico = new Estoquesetorhistorico();
                $estoquesetorhistorico->user_id = \Auth::user()->id;
                $estoquesetorhistorico->setor_id = $estoqueRequisicaoItem->setor_id;
                $estoquesetorhistorico->produto_id = $estoqueRequisicaoItem->produto_id;
                $estoquesetorhistorico->movimentacao = 'ENTRADA';
                $estoquesetorhistorico->quantidade = $estoqueRequisicaoItem->quantidade;
                $estoquesetorhistorico->motivo = 'Cancelamento de requisição de Estoque - ENTRADA';
                $estoquesetorhistorico->datahora = $requisicao->datahora;
                $estoquesetorhistorico->datahoracompetencia = $requisicao->datahora;
                $estoquesetorhistorico->entidade = 'Estoquerequisicao';
                $estoquesetorhistorico->entidade_id = $id;
                $estoquesetorhistorico->grupo_id = Session::get('empresa_padrao')->grupo_id;
                $estoquesetorhistorico->empresa_id = Session::get('empresa_padrao')->id;
                array_push($estoquesetorhistoricos, $estoquesetorhistorico);
            }
            $estoqueProcessor = new EstoqueProcessor();
            $transferirEstoque = $estoqueProcessor->movimentarEstoque($estoquesetorhistoricos);
            if (!$transferirEstoque) {
                DB::rollback();
                return implode(" ", $estoqueProcessor->getErrors());
            }
            DB::commit();
            return "OK|";
        } catch (\Exception $e) {
            DB::rollback();
            echo "<br /> " . $e->getMessage() . "<br /> erro desconhecido";
        }
    }

    private function corrigirData($produtos)
    {
        foreach ($produtos as $prod) {
            $prod->quantidade = number_format($prod->quantidade, 2, ',', '.');
        }
        return $produtos;
    }
}
