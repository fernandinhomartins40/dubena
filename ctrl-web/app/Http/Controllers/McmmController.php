<?php

namespace App\Http\Controllers;

use DB;
use App\Mcmm;
use App\Empresa;
use App\Services\CarbonCustom as Carbon;
use App\Mcmmhistoricosaidas;
use Illuminate\Http\Request;
use App\Mcmmhistoricoentradas;
use Illuminate\Support\Facades\Session;

class McmmController extends Controller
{

    private $pdf;
    private $font;
    private $fontSize;
    private $widthDefaultProd;

    public function index(Mcmm $mcmm)
    {
        $this->authorize('view', $mcmm);
        $mcmm = Mcmm::where('empresa_id', Session::get('empresa_padrao')->id)->get();

        return View('mcmm.mcmm_index', compact('mcmm'));
    }

    public function create(Mcmm $mcmm)
    {
        $this->authorize('create', $mcmm);
        $empresa = Empresa::with('cidade', 'bairro', 'rua')->find(Session::get('empresa_padrao')->id);
        $cidade_uf = $empresa->cidade->descricao . '/' . $empresa->uf;
        $endereco = $empresa->rua->descricao . ', ' . $empresa->numero . ' - ' . $empresa->bairro->descricao;

        return View('mcmm.mcmm_form', compact('empresa', 'endereco', 'cidade_uf'));
    }

    public function store(Request $request, Mcmm $mcmm)
    {
        return $this->insertUpdateMcmm($request, $mcmm, 'create');
    }

    public function show($id, Mcmm $mcmm)
    {
        return $this->editOrShow($id, $mcmm, 'view');
    }

    public function edit($id, Mcmm $mcmm)
    {
        return $this->editOrShow($id, $mcmm, 'update');
    }

    private function editOrShow($id, $mcmmModel, $action)
    {
        $this->authorize($action, $mcmmModel);
        $mcmm = Mcmm::find($id);
        $this->authorize('igualdade', $mcmm);
        if (is_null($mcmm))
            return redirect('mcmm')->withMessageDanger("Mcmm não encontrada");
        $saidas = $this->toDataTableSaidas($id);
        $entradas = $this->toDataTableEntradas($id);
        $saldoAnterior = $entradas['saldoAnterior'];
        $entradas = $entradas['entradas'];
        $cidade_uf = $mcmm->cidade . '/' . $mcmm->uf;
        $mcmm->capacidadearmazenamento = number_format($mcmm->capacidadearmazenamento, 0, ',', '.') . ' Kg';
        $mcmm->datamovimento = Carbon::createFromFormat('Y-m-d H:i:s', $mcmm->datamovimento)->format('m/Y');
        $mcmm->datainiciofiltro = requestDataOracle($mcmm->datainiciofiltro, false);
        $mcmm->datafimfiltro = requestDataOracle($mcmm->datafimfiltro, false);
        $show = '';
        if ($action === 'view')
            $compact = compact('mcmm', 'cidade_uf', 'saidas', 'entradas', 'saldoAnterior', 'show');
        else
            $compact = compact('mcmm', 'cidade_uf', 'saidas', 'entradas', 'saldoAnterior');
        return View('mcmm.mcmm_form', $compact);
    }

    public function update($id, Request $request, Mcmm $mcmm)
    {
        return $this->insertUpdateMcmm($request, $mcmm, 'update', $id);
    }

    private function insertUpdateMcmm($request, $mcmm, $action, $id = null)
    {
        $this->authorize($action, $mcmm);
        $this->validate($request, [
            'responsavel'      => 'required',
            'datainiciofiltro' => 'required',
            'datafimfiltro'    => 'required',
            'datamovimento'    => 'required'
        ]);
        try {
            DB::beginTransaction();
            $data = $this->getDataRequest($request);

            if (empty($data['distribuidora']))
                throw new \Exception("O campo Distribuidora é obrigatório");

            if (empty($data['registro_anp']))
                throw new \Exception("O campo Registro ANP é obrigatório");

            if ($action === 'create') {
                $mcmm = Mcmm::create($data);
            } else {
                $mcmm = Mcmm::find($id);
                $mcmm->update($data);
            }
            $this->insertUpdateEntradas($data['entradas'], $data['entradas_original'], $mcmm);
            $this->insertUpdateSaidas($data['saidas'], $data['saidas_original'], $mcmm);
        } catch (\Exception $e) {
            DB::rollback();
            $redirect = $action === 'create' ? 'mcmm/create' : "mcmm/$id/edit";
            return redirect($redirect)->withErrors(getErrorsException($e))->withInput();
        }
        DB::commit();
        $msg = $action === 'create' ? 'cadastrada' : "atualizada";
        return redirect('mcmm')->withMessageSuccess("Mcmm $msg com sucesso!");
    }

    private function getDataRequest($request)
    {
        $empresa = $request->session()->get('empresa_padrao');
        $data = $request->except(['_token', 'entradas_html', 'saidas_html', 'id']);
        $data['datainiciofiltro'] = insertDataOracle($data['datainiciofiltro']);
        $data['datafimfiltro'] = insertDataOracle($data['datafimfiltro']);
        $data['datamovimento'] = Carbon::createFromFormat('m/Y', $data['datamovimento'])->endOfMonth();
        $data['uf'] = explode('/', $data['cidade'])[1];
        $data['cidade'] = explode('/', $data['cidade'])[0];
        $data['capacidadearmazenamento'] = str_replace([' Kg', '.', ','], '', $data['capacidadearmazenamento']);
        $data['depd'] = isset($data['depd']);
        $data['depr'] = isset($data['depr']);
        $data['prt'] = isset($data['prt']);
        $data['prr'] = isset($data['prr']);
        $data['prd'] = isset($data['prd']);
        $data['empresa_id'] = $empresa->id;
        $data['grupo_id'] = $empresa->grupo_id;
        return $data;
    }

    private function insertUpdateEntradas($entradas, $entradasOriginal, $mcmm)
    {
        $entradas = json_decode($entradas);
        $entradasOriginal = json_decode($entradasOriginal);
        $mcmm->historicoEntrada()->where('em_uso', true)->update(['em_uso' => false]);
        $this->insertHistoricoEntradas($entradas, $mcmm);
        $this->insertHistoricoEntradas($entradasOriginal, $mcmm, true);
    }

    private function insertHistoricoEntradas($array, $mcmm, $original = false)
    {
        $count = count($array);
        for ($i = 0; $i < $count; $i++) {
            $historico = new Mcmmhistoricoentradas();
            if ($array[$i]->tipo_nf == "Recebida")
                $historico->nfrecebida_id = $array[$i]->id;
            else if ($array[$i]->tipo_nf == "Emitida")
                $historico->nfemitida_id = $array[$i]->id;
            $historico->mesanterior = $array[$i]->mesanterior;
            $historico->total = $array[$i]->total;
            $historico->original = $original;
            $historico->em_uso = true;
            $historico->mcmm_id = $mcmm->id;
            $historico->qdep02 = (int) $array[$i]->qdep02;
            $historico->qdep08 = (int) $array[$i]->qdep08;
            $historico->qdep13 = (int) $array[$i]->qdep13;
            $historico->qdep20 = (int) $array[$i]->qdep20;
            $historico->qdep45 = (int) $array[$i]->qdep45;
            $historico->qdep90 = (int) $array[$i]->qdep90;
            $historico->save();
        }
    }

    private function insertUpdateSaidas($saidas, $saidasOriginal, $mcmm)
    {
        $saidas = json_decode($saidas);
        $saidasOriginal = json_decode($saidasOriginal);
        $mcmm->historicoSaida()->where('em_uso', true)->update(['em_uso' => false]);
        $this->insertHistoricoSaidas($saidas, $mcmm);
        $this->insertHistoricoSaidas($saidasOriginal, $mcmm, true);
    }

    private function insertHistoricoSaidas($array, $mcmm, $original = false)
    {
        for ($i = 0; $i < count($array); $i++) {
            $historico = new Mcmmhistoricosaidas();
            if ($i === 0)
                $historico->operacao = 1;
            else if ($i === 1)
                $historico->operacao = 2;
            else if ($i === 2)
                $historico->operacao = 3;
            else
                $historico->operacao = 0;
            $historico->somames = $array[$i]->soma;
            $historico->somaseguinte = $array[$i]->somaseguinte;
            $historico->original = $original;
            $historico->em_uso = true;
            $historico->mcmm_id = $mcmm->id;
            $historico->qdep02 = (int) $array[$i]->qdep02;
            $historico->qdep08 = (int) $array[$i]->qdep08;
            $historico->qdep13 = (int) $array[$i]->qdep13;
            $historico->qdep20 = (int) $array[$i]->qdep20;
            $historico->qdep45 = (int) $array[$i]->qdep45;
            $historico->qdep90 = (int) $array[$i]->qdep90;
            $historico->save();
        }
    }

    public function searchEntradaSaidaMcmm()
    {
        $empresa = Session::get('empresa_padrao');
        $dataInicio = Carbon::parse($_GET['dataInicio'])->startOfDay();
        $dataFim = Carbon::parse($_GET['dataFim'])->endOfDay();

        //Saldo anterior entradas
        $saldoAnterior = $this->getSaldoAnterior($empresa, $dataInicio);

        //Entradas
        $entradas = $this->getEntradas($empresa, [$dataInicio, $dataFim]);

        //saidas normais
        $saidasNormais = $this->getSaidasNormais($empresa, [$dataInicio, $dataFim]);

        //Saidas Representante PRT, PRD PRR
        $saidasRepresentante = $this->getSaidasRepresentante($empresa, [$dataInicio, $dataFim]);

        //Saidas outras operações
        $saidasOutras = $this->getSaidasOutras($empresa, [$dataInicio, $dataFim]);

        $saidas = collect([$saidasNormais->first(), $saidasRepresentante->first(), $saidasOutras->first()]);

        return response()->json(compact('saldoAnterior', 'entradas', 'saidas'));
    }

    private function getSaldoAnterior($empresa, $dataInicio)
    {
        $lastMcmm = Mcmm::where('empresa_id', $empresa->id)->whereRaw("datafimfiltro <= to_date('$dataInicio')")->orderBy('id', 'desc')->select(DB::raw("max('id') as id"))->max('id');
        if (is_null($lastMcmm)) {
            $saldoAnterior = (object) [];
            $saldoAnterior->qdep02 = 0;
            $saldoAnterior->qdep08 = 0;
            $saldoAnterior->qdep13 = 0;
            $saldoAnterior->qdep20 = 0;
            $saldoAnterior->qdep45 = 0;
            $saldoAnterior->qdep90 = 0;
        } else {
            $saldoAnterior = Mcmmhistoricosaidas::where('mcmm_id', $lastMcmm)->where('em_uso', true)->where('somaseguinte', true)->where('original', false)
                            ->select('qdep02', 'qdep08', 'qdep13', 'qdep20', 'qdep45', 'qdep90')->get()->first();
        }
        return $saldoAnterior;
    }

    private function getEntradas($empresa, $periodo)
    {
        //Entradas
        $operacoesEntradaRec = "1403, 1409, 1908, 1949";
        $entradasNfRec = $this->getNf("recebida", $operacoesEntradaRec, $empresa, $periodo)
                ->addSelect(DB::raw("'Recebida' as tipo_nf"))
                ->get();

        $operacoesEntradaEmit = "1415";
        $entradasNfEmit = $this->getNf("emitida", $operacoesEntradaEmit, $empresa, $periodo)
                ->addSelect(DB::raw("'Emitida' as tipo_nf"))
                ->get();
        $entradasCol = collect([]);
        $entradas = $entradasNfEmit->merge($entradasNfRec);
        $entradas = $entradas->sortBy('datahoraentradasaida', SORT_NATURAL);
        foreach ($entradas as $e)
            $entradasCol->push($e);

        return $entradasCol;
    }

    private function getNf($tipo, $operacoes, $empresa, array $periodo, $entrada_saida = 'entrada')
    {
        $where = "nf.empresa_id = $empresa->id AND prod.tipo_glp IS NOT NULL "
                . "AND (nf.cfop in ($operacoes) or item.cfop in ($operacoes))";

        $nf = DB::table("nf$tipo" . "s nf")
                ->join("nf$tipo" . "items item", "item.nf" . $tipo . "_id", 'nf.id')
                ->join('produtos prod', 'item.cprod', 'prod.id')
                ->whereBetween('nf.datahoraentradasaida', $periodo);

        if ($entrada_saida === 'entrada') {
            $select = ['nf.id', 'nfnumero', 'nf.nfserie',
                DB::raw("TO_CHAR(datahoraentradasaida, 'dd/mm/yyyy') as datahoraentradasaida"),
                DB::raw("sum(CASE WHEN tipo_glp = '1' THEN item.qcom ELSE 0 END) as qdep02"),
                DB::raw("sum(CASE WHEN tipo_glp = '2' THEN item.qcom ELSE 0 END) as qdep08"),
                DB::raw("sum(CASE WHEN tipo_glp = '3' THEN item.qcom ELSE 0 END) as qdep13"),
                DB::raw("sum(CASE WHEN tipo_glp = '4' THEN item.qcom ELSE 0 END) as qdep20"),
                DB::raw("sum(CASE WHEN tipo_glp = '5' THEN item.qcom ELSE 0 END) as qdep45"),
                DB::raw("sum(CASE WHEN tipo_glp = '6' THEN item.qcom ELSE 0 END) as qdep90")
            ];
            $nf->groupBy('nf.id', 'nfnumero', 'nf.nfserie', 'datahoraentradasaida');
        } else {
            $select = [DB::raw("sum(item.qcom) as qde"), 'prod.tipo_glp'];
            $where .= " AND nf.nfsituacao_id = 100";
            $nf->groupBy('prod.tipo_glp');
        }
        $nf->whereRaw($where);
        return $nf->select($select);
    }

    private function getSaidasNormais($empresa, $periodo)
    {
        $operacoesEntregaNormal = '5405';
        $saidasNormais = $this->getNf("emitida", $operacoesEntregaNormal, $empresa, $periodo, 'saida')->get();
        return $this->montarTableMcmm($saidasNormais, 'Entrega Normal');
    }

    private function getSaidasRepresentante($empresa, $periodo)
    {
        //Saidas Representante PRT, PRD PRR
        $operacoesEntregaRepre = '5409';
        $saidasRepresentante = $this->getNf("emitida", $operacoesEntregaRepre, $empresa, $periodo, 'saida')->groupBy('prod.tipo_glp')->get();
        return $this->montarTableMcmm($saidasRepresentante, 'Repres, PRT, PRD ou PRR');
    }

    private function getSaidasOutras($empresa, $periodo)
    {
        $operacoesEntregaOutras = "5949";
        $saidasOutras = $this->getNf("emitida", $operacoesEntregaOutras, $empresa, $periodo, 'saida')->groupBy('prod.tipo_glp')->get();
        return $this->montarTableMcmm($saidasOutras, 'Outras Saídas');
    }

    private function montarTableMcmm($values, $operacao = '')
    {
        $data = collect((object) []);
        if (count($values) > 0) {
            foreach ($values as $value) {
                $value->qdep02 = $value->tipo_glp == '1' ? $value->qde : 0;
                $value->qdep08 = $value->tipo_glp == '2' ? $value->qde : 0;
                $value->qdep13 = $value->tipo_glp == '3' ? $value->qde : 0;
                $value->qdep20 = $value->tipo_glp == '4' ? $value->qde : 0;
                $value->qdep45 = $value->tipo_glp == '5' ? $value->qde : 0;
                $value->qdep90 = $value->tipo_glp == '6' ? $value->qde : 0;
                unset($value->qde);
                unset($value->tipo_glp);
                if (!empty($operacao))
                    $value->operacao = $operacao;
                if (count($data) === 0)
                    $data->push($value);
                else
                    $data = $this->combineArray($data->first(), $value);
            }
        } else {
            $value = (object) [];
            $value->qdep02 = 0;
            $value->qdep08 = 0;
            $value->qdep13 = 0;
            $value->qdep20 = 0;
            $value->qdep45 = 0;
            $value->qdep90 = 0;
            $value->operacao = $operacao;
            $data->push($value);
        }
        return $data;
    }

    private function combineArray($array1, $array2)
    {
        $array1->qdep02 = $array1->qdep02 + $array2->qdep02;
        $array1->qdep08 = $array1->qdep08 + $array2->qdep08;
        $array1->qdep13 = $array1->qdep13 + $array2->qdep13;
        $array1->qdep20 = $array1->qdep20 + $array2->qdep20;
        $array1->qdep45 = $array1->qdep45 + $array2->qdep45;
        $array1->qdep90 = $array1->qdep90 + $array2->qdep90;

        return collect([$array1]);
    }

    private function toDataTableSaidas($mcmm_id, $whereRaw = "operacao <> 0")
    {
        $saidas = Mcmmhistoricosaidas::where('em_uso', true)->where('mcmm_id', $mcmm_id)->where('original', false)
                ->whereRaw($whereRaw)->orderBy('operacao')
                ->select('qdep02', 'qdep08', 'qdep13', 'qdep20', 'qdep45', 'qdep90', 'operacao', 'somames', 'id')
                ->get();

        foreach ($saidas as $saida) {
            if ($saida->operacao === '1')
                $saida->operacao = 'Entrega Normal';
            else if ($saida->operacao === '2')
                $saida->operacao = 'Repres, PRT, PRD ou PRR';
            else if ($saida->operacao === '3')
                $saida->operacao = 'Outras Saídas';
            else if ($saida->somames === '1')
                $saida->operacao = 'Venda do Mês';
        }
        return $saidas->toJson();
    }

    private function toDataTableEntradas($id)
    {

        $entradas = Mcmmhistoricoentradas::with('nfEmitida', 'nfRecebida')->where('em_uso', true)->where('total', false)->where('mcmm_id', $id)->where('original', false)
                ->select('mcmm_id', 'mesanterior', 'qdep02', 'qdep08', 'qdep13', 'qdep20', 'total', 'qdep45', 'qdep90', 'nfemitida_id', 'nfrecebida_id')
                ->get();
        $saldoAnterior = $entradas->where('mesanterior', true)->first();
        $saldoAnterior = !is_null($saldoAnterior) ? $saldoAnterior->toJson() : null;
        $entr = collect([]);
        foreach ($entradas as $e) {
            if ($e->mesanterior == '0') {
                if (!is_null($e->nfemitida_id) && is_null($e->nfrecebida_id)) {
                    $e->nfserie = $e->nfEmitida->nfserie;
                    $e->nfnumero = $e->nfEmitida->nfnumero;
                    $e->datahoraentradasaida = requestDataOracle($e->nfEmitida->datahoraentradasaida, false);
                    $e->tipo_nf = 'Emitida';
                    $e->id = $e->nfemitida_id;
                } else if (!is_null($e->nfrecebida_id) && is_null($e->nfemitida_id)) {
                    $e->nfserie = $e->nfRecebida->nfserie;
                    $e->nfnumero = $e->nfRecebida->nfnumero;
                    $e->datahoraentradasaida = requestDataOracle($e->nfRecebida->datahoraentradasaida, false);
                    $e->tipo_nf = 'Recebida';
                    $e->id = $e->nfrecebida_id;
                }
                unset($e->nfemitida_id);
                unset($e->nfrecebida_id);
                unset($e->nfEmitida);
                unset($e->nfRecebida);
                unset($e->mcmm_id);
                $entr->push($e);
            }
        }
        $entradas = $entr->toJson();
        return compact('saldoAnterior', 'entradas');
    }

    public function destroy($id, Mcmm $mcmm)
    {
        $this->authorize('delete', $mcmm);
        try {
            $mcmm = Mcmm::find($id);
            $mcmm->historicoSaida()->delete();
            $mcmm->historicoEntrada()->delete();
            $mcmm->delete();
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        return "OK|";
    }

    public function printDoc()
    {
        if (isset($_GET['id']))
            $id = $_GET['id'];
        else
            return redirect('/home')->withErrors('Mcmm não encontrada');

        $mcmm = Mcmm::find($id);
        $this->authorize('igualdade', $mcmm);
        try {
            return $this->newPdf($mcmm);
        } catch (\Exception $e) {
            echo getErrorsException($e);
        }
    }

    private function newPdf($mcmm)
    {
        $mcmm->datamovimento = requestDataOracle($mcmm->datamovimento, false);
        $empresa = Session::get('empresa_padrao');
        $pdf = $this->instaceNewPdf();

        $this->widthDefaultProd = 65;
        $smallFont = 8.5;
        $normalFont = 10;
        $bigFont = 12;
        $height = 8;
        $width = 8;

        //montando o pdf
        $pdf = $this->firstTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width);
        $pdf = $this->secondTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width);
        $pdf = $this->thirdTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width);
        $pdf = $this->fourthTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width);
        $pdf = $this->fifthTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width);
        $pdf = $this->sixthTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width);
        $pdf = $this->footer($pdf, $width, $height, $normalFont);
        $pdf = $this->horizontalLinesPdf($pdf, $normalFont);
        $pdf = $this->checkboxesPdf($pdf, $mcmm);
        $pdf = $this->continuacaoEntradasPdf($pdf, $mcmm, $width, $height, $smallFont, $normalFont);

        // Print
        return $pdf->Output();
    }

    private function firstTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width)
    {
        // PRIMEIRA LINHA
        $pdf->setTitle("MCMM " . $mcmm->datamovimento);
        $pdf->Cell($width, $height, '01', 'LT ', 0, "C");
        $pdf->Cell(150, $height, 'MAPA DE CONTROLE DE MOVIMENTO MENSAL', 'LT', 0, "C");
        $pdf->SetFontSize($smallFont);
        $pdf->MultiCell(20, $height / 2, "NÚMERO \n$mcmm->id", 'LTR', "L");

        // horizontal
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetFontSize($normalFont);
        $pdf->Cell($width, $height * 6, '', 1, 0, "C");

        // SEGUNDA LINHA
        $pdf->SetXY($x + $height, $y);
        $pdf->SetFont("Arial", "b", $smallFont);
        $pdf->Cell(25, $height, "RAZÃO SOCIAL:", 'TB', 0, "L");
        $pdf->SetFont("Arial", "", $normalFont);
        $pdf->Cell(110, $height, $mcmm->razao_social, 'TB', 0, "L");
        $pdf->SetFontSize($smallFont);
        $pdf->MultiCell(35, $height / 2, "REGISTRO CNP:\n" . $mcmm->registro_anp, 'LTR', 'L');

        // TERCEIRA LINHA
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height, $y);
        $pdf->SetFont("Arial", "b", $smallFont);
        $pdf->Cell(37, $height, "TIPO DE INSTALAÇÕES:", '', 0, "L");
        $pdf->SetFont("Arial", "", $normalFont);
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetFontSize($smallFont);
        $pdf->SetXY($x + $height, $y);
        $pdf->Cell(90, -$height, '', '', 0, "L");
        $pdf->SetFontSize($smallFont);
        $pdf->MultiCell(35, $height, "CAPAC. ARMAZEN.:\n" . number_format($mcmm->capacidadearmazenamento, 0, '', '.') . ' Kg', 'LTR', 'L');

        // QUARTA LINHA
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height, $y - $height);
        $pdf->SetFont("Arial", "b", $smallFont);
        $pdf->Cell(27, $height, "DISTRIBUIDORA:", 'T', 0, "L");
        $pdf->SetFont("Arial", "", $normalFont);
        $pdf->Cell(108, $height, $mcmm->distribuidora, 'T', 1, "L");

        // QUINTA LINHA
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height, $y);
        $pdf->SetFont("Arial", "b", $smallFont);
        $pdf->Cell(20, $height, "ENDEREÇO:", 'T', 0, "L");
        $pdf->SetFont("Arial", "", $normalFont);
        $pdf->Cell(150, $height, $mcmm->endereco, 'TR', 1, "L");

        // SEXTA LINHA
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height, $y);
        $pdf->SetFont("Arial", "b", $smallFont);
        $pdf->Cell(14, $height, "CIDADE:", 'T', 0, "L");
        $pdf->SetFont("Arial", "", $normalFont);
        $pdf->Cell(71, $height, $mcmm->cidade, 'TR', 0, "L");
        $pdf->SetFont("Arial", "", $smallFont);
        $pdf->MultiCell(25, $height / 2, "CÓDIGO:\n" . $mcmm->codigo_ibge, 'TR', 'L');
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height + 110, $y - $height);
        $pdf->SetFont("Arial", "", $smallFont);
        $pdf->MultiCell(25, $height / 2, "UF:\n" . $mcmm->uf, 'TR', 'L');
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height + 135, $y - $height);
        $pdf->SetFont("Arial", "", $smallFont);
        $pdf->MultiCell(35, $height / 2, "CNPJ:\n" . $mcmm->cnpj, 'TR', 'L');

        // SÉTIMA LINHA
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height, $y);
        $pdf->SetFont("Arial", "b", $smallFont);
        $pdf->Cell(37, $height, "MOVIMENTO MÊS/ANO:", 'TB', 0, "L");
        $pdf->SetFont("Arial", "", $normalFont);
        $pdf->Cell(48, $height, $mcmm->datamovimento, 'TRB', 'L');
        $pdf->SetFontSize($smallFont);
        $pdf->MultiCell(85, $height / 2, "CNPJ:\n" . $mcmm->cnpj, 'TRB', '');

        return $this->spaceLine($pdf, $height);
    }

    public function secondTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width)
    {
        // PRIMEIRA LINHA
        $pdf->SetFont("Arial", "", $normalFont);
        $pdf->Cell($width, $height, '02', 1, 0, "C");
        $pdf->SetFont("Arial", "", $smallFont);
        $pdf->Cell($this->widthDefaultProd, $height, "ESPÉCIES DE RECIPIENTES", 'TBR', 0, "C");
        $pdf->SetFont("Arial", "", $normalFont);

        $pdf = $this->spacesGlp($pdf, $width, 'TRB', ['P02', 'P08', 'P13', 'P20', 'P45', 'P90', '']);

        return $this->spaceLine($pdf, $height);
    }

    public function thirdTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width)
    {
        // PRIMEIRA LINHA
        $pdf->SetFont("Arial", "", $normalFont);
        $pdf->Cell($width, $height, '03', 1, 0, "C");
        $pdf->SetFont("Arial", "", $smallFont);
        $pdf->Cell($this->widthDefaultProd, $height, "SALDO DO MÊS ANTERIOR", 'TBR', 0, "C");
        $pdf->SetFont("Arial", "", $normalFont);
        $mesAnterior = $mcmm->historicoEntrada()->where('em_uso', true)->where('original', false)->where('total', false)->where('mesanterior', true)->get()->first();
        if (is_null($mesAnterior))
            throw new \Exception("Informações do mês anterior não encontradas");

        $glps = [$mesAnterior->qdep02, $mesAnterior->qdep08, $mesAnterior->qdep13, $mesAnterior->qdep20, $mesAnterior->qdep45, $mesAnterior->qdep90];
        $pdf = $this->spacesGlp($pdf, $width, 'TRB', $glps);

        return $this->spaceLine($pdf, $height);
    }

    public function fourthTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width)
    {
        // PRIMEIRA LINHA
        $pdf->SetFont("Arial", "", $normalFont);
        $pdf->Cell($width, $height, '04', 1, 0, "C");
        $pdf->SetFont("Arial", "", $smallFont);
        $pdf->MultiCell($this->widthDefaultProd, $height / 2, "DOCUMENTO FISCAL \n ESPÉCIE, Nº, SÉRIE E DATA", 'TBR', "C");
        $pdf->SetFont("Arial", "", $normalFont);
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height + $this->widthDefaultProd, $y - $height);
        $pdf = $this->spacesGlp($pdf, $width, 'TRB');

        $entradas = $mcmm->historicoEntrada()->with('nfEmitida', 'nfRecebida')->where('em_uso', true)->where('original', false)->where('total', false)->where('mesanterior', false)->get();

        // horizontal
        $pdf->SetFontSize($normalFont);
        $pdf->Cell($width, $height * 10, '', "LB", 0, "C");
        $pdf->SetFontSize($smallFont);
        //linhas de items
        for ($i = 0; $i < 9; $i++) {
            if (isset($entradas[$i])) {
                if (!is_null($entradas[$i]->nfemitida_id))
                    $nf = $entradas[$i]->nfEmitida;
                else if (!is_null($entradas[$i]->nfrecebida_id))
                    $nf = $entradas[$i]->nfRecebida;
                $glps = [$entradas[$i]->qdep02, $entradas[$i]->qdep08, $entradas[$i]->qdep13, $entradas[$i]->qdep20, $entradas[$i]->qdep45, $entradas[$i]->qdep90];
                $descCell = "NF Núm " . $nf->nfnumero . ", série " . $nf->nfserie . " de " . requestDataOracle($nf->datahoraentradasaida, false);
            } else {
                $descCell = "";
                $glps = ['', '', '', '', '', '', ''];
            }
            $pdf->Cell($this->widthDefaultProd, $height, $descCell, 'BRL', 0, "C");
            $pdf = $this->spacesGlp($pdf, $width, 'BR', $glps);
            $y = $pdf->y;
            $x = $pdf->x;
            $pdf->SetXY($x + $height, $y);
        }
        $pdf->Cell($this->widthDefaultProd, $height, "TOTAL OU A TRANSPORTAR", 'BRL', 0, "C");

        $entradasTotal = $mcmm->historicoEntrada()->with('nfEmitida', 'nfRecebida')->where('em_uso', true)->where('original', false)->where('total', true)->where('mesanterior', false)->get();
        if ($entradas->count() < 9)
            $glps = [$entradasTotal->sum('qdep02'), $entradasTotal->sum('qdep08'), $entradasTotal->sum('qdep13'), $entradasTotal->sum('qdep20'), $entradasTotal->sum('qdep45'), $entradasTotal->sum('qdep90')];
        else
            $glps = ['', '', '', '', '', '', ''];

        $pdf = $this->spacesGlp($pdf, $width, 'BRL', $glps);

        return $this->spaceLine($pdf, $height);
    }

    private function fifthTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width)
    {
        $normais = collect(json_decode($this->toDataTableSaidas($mcmm->id, 'operacao = 1')))->first();
        if ($normais == null)
            throw new \Exception("Erro ao buscar as saídas");

        $representante = collect(json_decode($this->toDataTableSaidas($mcmm->id, 'operacao = 2')))->first();
        if ($representante == null)
            throw new \Exception("Erro ao buscar as saídas");

        $outras = collect(json_decode($this->toDataTableSaidas($mcmm->id, 'operacao = 3')))->first();
        if ($outras == null)
            throw new \Exception("Erro ao buscar as saídas");

        $somaMes = collect(json_decode($this->toDataTableSaidas($mcmm->id, 'somames = 1')))->first();
        if ($somaMes == null)
            throw new \Exception("Erro ao buscar as saídas");

        $saidas = collect([])->push($normais)->push($representante)->push($outras)->push($somaMes);


        // PRIMEIRA LINHA
        $pdf->SetFontSize($normalFont);
        $pdf->Cell($width, $height, '05', 1, 0, "C");

        $pdf->SetFontSize($smallFont);
        for ($i = 0; $i < count($saidas); $i++) {
            $border = count($saidas) - 1 > $i ? 'TR' : 'TRB';
            $pdf->Cell($this->widthDefaultProd, $height, $saidas[$i]->operacao, $border, 0, "C");
            $glps = [$saidas[$i]->qdep02, $saidas[$i]->qdep08, $saidas[$i]->qdep13, $saidas[$i]->qdep20, $saidas[$i]->qdep45, $saidas[$i]->qdep90];
            $pdf = $this->spacesGlp($pdf, $width, $border, $glps);
            if (count($saidas) - 1 > $i)
                $pdf->Cell($width, $height, '', count($saidas) - 2 > $i ? "LR" : "LRB", 0, "C");
        }

        return $this->spaceLine($pdf, $height);
    }

    private function sixthTablePdf($pdf, $mcmm, $smallFont, $normalFont, $bigFont, $height, $width)
    {
        $soma = collect(json_decode($this->toDataTableSaidas($mcmm->id, 'somaseguinte = 1')))->first();
        if (is_null($soma))
            throw new \Exception("Erro ao buscar saldo para mês seguinte");

        // PRIMEIRA LINHA
        $pdf->SetFontSize($normalFont);
        $pdf->Cell($width, $height, '06', 'LBT', 0, "C");

        $pdf->SetFontSize($smallFont);
        $pdf->Cell($this->widthDefaultProd, $height, 'Saldo Para Mês Seguinte', 1, 0, "C");
        $glps = [$soma->qdep02, $soma->qdep08, $soma->qdep13, $soma->qdep20, $soma->qdep45, $soma->qdep90];
        $pdf = $this->spacesGlp($pdf, $width, "RBT", $glps);

        return $this->spaceLine($pdf, $height);
    }

    private function spacesGlp($pdf, $height, $border = 'RB', $glps = array('', '', '', '', '', '', ''))
    {
        $widthGlp = 15;
        $pdf->Cell($widthGlp, $height, $glps[0], $border, 0, 'C');
        $pdf->Cell($widthGlp, $height, $glps[1], $border, 0, 'C');
        $pdf->Cell($widthGlp, $height, $glps[2], $border, 0, 'C');
        $pdf->Cell($widthGlp, $height, $glps[3], $border, 0, 'C');
        $pdf->Cell($widthGlp, $height, $glps[4], $border, 0, 'C');
        $pdf->Cell($widthGlp, $height, $glps[5], $border, 0, 'C');
        $pdf->Cell($widthGlp, $height, !isset($glps[6]) ? "0" : $glps[6], $border, 1, 'C');
        return $pdf;
    }

    private function checkboxesPdf($pdf, $mcmm)
    {
        $css = ".checkbox{"
                . "padding-top: -216.3mm;"
                . "padding-left: 47mm;"
                . "}";
        $pdf->WriteHTML($css, 1);
        // CHECKBOXES
        $instalacoes = '<div class="checkbox">';

        $checked = $mcmm->depd ? ' checked="checked" ' : '';
        $instalacoes .= "DEPD <input type=\"checkbox\">";

        $checked = $mcmm->depr ? ' checked="checked" ' : '';
        $instalacoes .= " DEPR <input type=\"checkbox\" $checked>";

        $checked = $mcmm->prt ? ' checked="checked" ' : '';
        $instalacoes .= " PRT <input type=\"checkbox\" $checked>";

        $checked = $mcmm->prr ? ' checked="checked" ' : '';
        $instalacoes .= " PRR <input type=\"checkbox\" $checked>";

        $checked = $mcmm->prd ? ' checked="checked" ' : '';
        $instalacoes .= " PRD <input type=\"checkbox\" $checked></div>";
        $pdf->WriteHTML($instalacoes);

        return $pdf;
    }

    private function horizontalLinesPdf($pdf, $normalFont)
    {
        $css = ".rotate{"
                . "text-rotate: 90;"
                . "padding-left:8px;"
                . "}"
                . ".identificacao{"
                . "padding-top:-405mm;"
                . "}";
        $pdf->WriteHTML($css, 1);
        $pdf->WriteHTML('<table><tr><td class="rotate identificacao">IDENTIFICAÇÃO</td></tr></table>');
        $css = ".entradas{"
                . "padding-top:-220mm;"
                . "}";
        $pdf->WriteHTML($css, 1);
        $pdf->WriteHTML('<table><tr><td class="rotate entradas">ENTRADAS</td></tr></table>');
        $css = ".saidas{"
                . "padding-top:-87mm;"
                . "}";
        $pdf->WriteHTML($css, 1);
        $pdf->WriteHTML('<table><tr><td class="rotate saidas">SAÍDAS</td></tr></table>');
        return $pdf;
    }

    private function footer($pdf, $width, $height, $normalFont)
    {
        $pdf = $this->spaceLine($pdf, $height);
        $pdf->SetFontSize($normalFont);
        $y = $pdf->y;
        $x = $pdf->x;
        $pdf->SetXY($x + $height, $y);
        $pdf->Cell(75, $height - ($height / 1.5), 'Local: ___________________________________________', '', 0, 'R');
        $pdf->Cell(40, $height - ($height / 1.5), 'Data: ___/___/20___ ', '', 0, 'C');
        $pdf->Cell(65, $height - ($height / 1.5), '_________________________________', '', 1, 'L');
        $pdf->Cell(75, $height, '', '', 0, 'C');
        $pdf->Cell(40, $height, '', '', 0, 'C');
        $pdf->Cell(65, $height, '        Visto do Agente', '', 1, 'C');
        $pdf->Cell(180, $height, 'Anexo: _____________________________________________________________________________________________________', '', 0, 'C');
        return $this->spaceLine($pdf, $height);
    }

    private function continuacaoEntradasPdf($pdf, $mcmm, $width, $height, $smallFont, $normalFont)
    {

        $entradas = $mcmm->historicoEntrada()->with('nfEmitida', 'nfRecebida')->where('em_uso', true)->where('original', false)->where('total', false)->where('mesanterior', false)->get();
        $countEntradas = $entradas->count();
        if ($countEntradas > 9) {
            $pdf->AddPage();
            $pdf->Cell($width, $height, '', 'LTB ', 0, "C");
            $pdf->Cell(148 - $width, $height, 'FOLHA DE CONTINUAÇÃO DO QUADRO 04', 'BT', 0, "C");
            $pdf->SetFontSize($smallFont - 1);
            $pdf->MultiCell(15, $height / 2, "Nº MAPA \n$mcmm->id", 'LTRB', "C");
            $y = $pdf->y;
            $x = $pdf->x;
            $pdf->SetXY($x + 163, $y - $height);
            $pdf->MultiCell(15, $height / 2, "Nº FOLHA $pdf->page", 'TRB', "C");
            $y = $pdf->y;
            $x = $pdf->x;
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, $height, '', "L", 0, "C");
            $pdf->SetFontSize($normalFont);
            $pdf->Cell($this->widthDefaultProd, $height, "ESPÉCIES DE RECIPIENTES", 'LTBR', 0, "C");
            $pdf = $this->spacesGlp($pdf, $width, 'TRB', ['P02', 'P08', 'P13', 'P20', 'P45', 'P90', '']);
            $pdf->Cell($width, $height, '', "L", 0, "C");
            $pdf->Cell($this->widthDefaultProd, $height, "TRANSPORTE", 'LBR', 0, "C");
            $pdf = $this->spacesGlp($pdf, $width, 'RB', ['', '', '', '', '', '', '']);

            $pdf->SetFontSize($smallFont);
            //linhas de items
            for ($i = 9; $i < $countEntradas; $i++) {
                $pdf->Cell($width, $height, '', "L", 0, "C");
                if (!is_null($entradas[$i]->nfemitida_id))
                    $nf = $entradas[$i]->nfEmitida;
                else if (!is_null($entradas[$i]->nfrecebida_id))
                    $nf = $entradas[$i]->nfRecebida;
                $glps = [$entradas[$i]->qdep02, $entradas[$i]->qdep08, $entradas[$i]->qdep13, $entradas[$i]->qdep20, $entradas[$i]->qdep45, $entradas[$i]->qdep90];
                $descCell = "NF Núm " . $nf->nfnumero . ", série " . $nf->nfserie . " de " . requestDataOracle($nf->datahoraentradasaida, false);
                $pdf->Cell($this->widthDefaultProd, $height, $descCell, 'BRL', 0, "C");
                $pdf = $this->spacesGlp($pdf, $width, 'BR', $glps);
            }
            $pdf->Cell($width, $height, '', "LBT", 0, "C");
            $pdf->Cell($this->widthDefaultProd, $height, "TOTAL OU A TRANSPORTAR", 'BRL', 0, "C");
            $total = $mcmm->historicoEntrada()->where('em_uso', true)->where('original', false)->where('total', true)->where("mesanterior", false)->get()->first();

            if (is_null($total))
                throw new \Exception("Erro ao buscar o total de entradas");
            $glps = [$total->qdep02, $total->qdep08, $total->qdep13, $total->qdep20, $total->qdep45, $total->qdep90];
            $pdf = $this->spacesGlp($pdf, $width, 'BLR', $glps);
        }
        return $pdf;
    }

    private function spaceLine($pdf, $height)
    {
        $pdf->Cell(0, $height - ($height / 1.5), '', '', 1);
        return $pdf;
    }

    private function instaceNewPdf()
    {
        $pdf = new \Mpdf\Mpdf();

        $pdf->cMarginB = 1.5;
        $pdf->cMarginT = 1.5;
        $pdf->cMarginL = 1.5;
        $pdf->cMarginR = 1.5;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetAutoPageBreak(true);
        $pdf->SetLineWidth(0.1);
        return $pdf;
    }

}
