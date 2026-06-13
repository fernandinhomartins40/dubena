<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Setor;
use App\Services\CarbonCustom as Carbon;
use App\Estoquesetor;
use App\Estoquefisico;
use App\Estoquefisicosetor;
use Illuminate\Http\Request;
use App\Estoquesetorhistorico;
use App\Processors\EstoqueProcessor;

class EstoquefisicoController extends Controller
{

    public function index(Estoquefisico $fis)
    {
        $this->authorize('view', $fis);
        $empresa_id = Session::get('empresa_padrao')->id;

        $estoquefisico = Estoquefisico::where('empresa_id', $empresa_id)->get();

        return View('estoque.fisico.estoquefisico', compact('estoquefisico'));
    }

    // Old **
    // $estoquefechamentosOld = Estoquefechamento::where('empresa_id', $empresa_id)->where('reaberto', 0)
    // 										->select('datahorafechamento', 'id')->get()
    // 										->sortByDesc('datahorafechamento')->pluck('datahorafechamento', 'id');
    // $estoquefechamentos = [];
    // foreach ($estoquefechamentosOld as $key => $value) {
    // 	$estoquefechamentos[$key] = requestDataOracle($value, false);
    // }
    public function create(Estoquefisico $fis)
    {
        $this->authorize('create', $fis);
        $empresa_id = Session::get('empresa_padrao')->id;
        $setores = Setor::where([['ativo', 1], ['empresa_id', $empresa_id]])->orderBy('descricao')->get()->pluck('descricao', 'id')->prepend('Selecione', '');

        return View('estoque.fisico.estoquefisico_form', compact('setores'));
    }

    public function edit($id, Estoquefisico $fis)
    {
        $this->authorize('update', $fis);
        $estoquefisico = Estoquefisico::find($id);
        $this->authorize('igualdade', $estoquefisico);
        $empresa_id = Session::get('empresa_padrao')->id;
        if ($estoquefisico->efetivado)
            return Redirect::to('estoquefisico/' . $id)->withMessageInfo("Este estoque já foi efetivado, portanto não é possível edita-lo!");

        $estoquefisicosetor = Estoquefisicosetor::where('estoquefisico_id', $id)->get();
        $estoquefisicosetor = $this->getEstoqueSetores($estoquefisicosetor);
        $setores = Setor::where('empresa_id', $empresa_id)->where('ativo', true)->orderBy('descricao')->get()->pluck('descricao', 'id')->prepend('Selecione', '');
        $dataFechamento = $estoquefisico->created_at;

        return View('estoque.fisico.estoquefisico_form', compact('setores', 'estoquefisico', 'estoquefisicosetor', 'dataFechamento'));
    }

    public function show($id, Estoquefisico $fis)
    {
        $this->authorize('view', $fis);
        $estoquefisico = Estoquefisico::find($id);
        $this->authorize('igualdade', $estoquefisico);
        $empresa_id = Session::get('empresa_padrao')->id;
        $estoquefisicosetor = Estoquefisicosetor::where('estoquefisico_id', $id)->get();
        $estoquefisicosetor = $this->getEstoqueSetores($estoquefisicosetor);
        $setores = Setor::where('empresa_id', $empresa_id)->get()->pluck('descricao', 'id')->prepend('Selecione', '');
        $show = true;
        $dataFechamento = $estoquefisico->created_at;

        return View('estoque.fisico.estoquefisico_form', compact('setores', 'estoquefisico', 'estoquefisicosetor', 'show', 'dataFechamento'));
    }

    public function buscaEstoqueSetor($setor_id, $datacompetencia)
    {

        $setor_id = $setor_id !== 'null' ? $setor_id : null;
        $empresa_id = Session::get('empresa_padrao')->id;

        $estoquesetor = Estoquesetor::with('setor', 'produto')->where('setors.id', $setor_id !== null ? '=' : '!=', $setor_id)
                ->where('setors.empresa_id', $empresa_id)
                ->where('setors.ativo', 1)
                ->where('produtos.ativo', 1)
                ->join('setors', 'setors.id', '=', 'estoquesetors.setor_id')
                ->join('produtos', 'estoquesetors.produto_id', '=', 'produtos.id')
                ->select(['estoquesetors.id', 'estoquesetors.setor_id', 'estoquesetors.produto_id', 'estoquesetors.quantidade'])
                ->get();

        $historico = DB::table('estoquesetorhistoricos hist')->where('setors.id', $setor_id !== null ? '=' : '!=', $setor_id)
                ->where('setors.empresa_id', $empresa_id)
                ->where('setors.ativo', 1)
                ->where('produtos.ativo', 1)
                ->whereRaw("datahoracompetencia >= to_date('$datacompetencia', 'yyyy-mm-dd hh24:mi:ss')")
                ->join('setors', 'setors.id', '=', 'hist.setor_id')
                ->join('produtos', 'hist.produto_id', '=', 'produtos.id')
                ->select("hist.setor_id", "hist.produto_id", DB::raw("sum(CASE hist.movimentacao WHEN 'ENTRADA' THEN hist.quantidade ELSE hist.quantidade * -1 END) as quantidade"))
                ->groupBy('hist.setor_id', 'hist.produto_id')
                ->get();
        $estoque = [];
        foreach ($estoquesetor as $estsetor) {
            $dados = (object) [];
            $dados->id = $estsetor->id;
            $dados->setor = $estsetor->setor->descricao;
            $dados->produto = $estsetor->produto->descricao;
            $dados->unidademedida = strtoupper($estsetor->produto->unidademedida->sigla) . '-' . $estsetor->produto->unidademedida->descricao;
            $dados->quantidadeatualsistema = $estsetor->quantidade - $historico->where('produto_id', $estsetor->produto_id)->where('setor_id', $estsetor->setor_id)->pluck('quantidade')->first();
            $dados->quantidadeatualfisico = 0;
            $dados->diferenca = 0;
            $dados->zerar = '<input type="checkbox" id="zerar' . $estsetor->setor_id . '-' . $estsetor->produto_id . '">';
            $dados->remover = '<input type="checkbox" id="remover' . $estsetor->setor_id . '-' . $estsetor->produto_id . '">';
            array_push($estoque, $dados);
        }
        return response()->json($estoque);
    }

    private function getEstoqueSetores($estoquefisicosetor)
    {
        $estoque = [];
        foreach ($estoquefisicosetor as $estsetor) {
            $estoquesetor_id = Estoquesetor::where([['setor_id', $estsetor->setor_id], ['produto_id', $estsetor->produto_id]])->pluck('id')->first();
            $dados = (object) [];
            $dados->id = $estoquesetor_id;
            $dados->setor = $estsetor->setor->descricao;
            $dados->produto = $estsetor->produto->descricao;
            $dados->unidademedida = strtoupper($estsetor->produto->unidademedida->sigla) . '-' . $estsetor->produto->unidademedida->descricao;
            $dados->quantidadeatualsistema = $estsetor->quantidadesistema;
            $dados->quantidadeatualfisico = $estsetor->quantidadefisica;
            $dados->diferenca = $estsetor->quantidadediferenca;
            $dados->zerar = '<input type="checkbox" id="zerar' . $estsetor->setor_id . '-' . $estsetor->produto_id . '" ' . ($estsetor->estoquezerar == 1 ? ' checked="checked">' : '>');
            $dados->remover = '<input type="checkbox" id="remover' . $estsetor->setor_id . '-' . $estsetor->produto_id . '" ' . ($estsetor->estoqueremover == 1 ? ' checked="checked">' : '>');
            array_push($estoque, $dados);
        }
        return json_encode($estoque);
    }

    public function store(Request $request)
    {

        $data = $request->all();
        DB::beginTransaction();
        try {

            $data['empresa_id'] = Session::get('empresa_padrao')->id;
            $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
            $data['datacompetencia'] = insertDataOracle($data['datacompetencia']);
            $data['user_id'] = \Auth::user()->id;
            $estoquefisico = Estoquefisico::create($data);

            $insertEstoque = $this->insertUpdateEstqueFisico($data['estoqueAlterado'], $estoquefisico);

            if ($insertEstoque !== 'OK|')
                throw new \Exception($insertEstoque);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('estoquefisico/create')->withInput()->withErrors($e->getMessage());
        }
        DB::commit();
        return Redirect::to('estoquefisico')->withMessageSuccess("Estoque alterado com sucesso!");
    }

    public function update(Request $request, $id, Estoquefisico $fis)
    {
        $this->authorize('update', $fis);
        $estoquefisico = Estoquefisico::find($id);
        $this->authorize('igualdade', $estoquefisico);

        $data = $request->all();

        DB::beginTransaction();
        try {

            $data['datacompetencia'] = insertDataOracle($data['datacompetencia']);

            $estoquefisico->update($data);

            $insertEstoque = $this->insertUpdateEstqueFisico($data['estoqueAlterado'], $estoquefisico);

            if ($insertEstoque !== 'OK|')
                throw new \Exception($insertEstoque);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('estoquefisico/' . $id . '/edit')->withInput()->withErrors($e->getMessage());
        }
        return Redirect::to('estoquefisico')->withMessageSuccess("Estoque alterado com sucesso!");
    }

    private function insertUpdateEstqueFisico($data, $estoquefisico)
    {
        DB::beginTransaction();
        try {
            $toDelete = collect([]);
            $rows = json_decode($data);
            $estoquefisico->estoquefisicosetor()->delete();
            $estoquesetorhistoricos = [];
            foreach ($rows as $row) {
                $dados = [];
                $dados['empresa_id'] = Session::get('empresa_padrao')->id;
                $dados['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
                $estoquesetor = Estoquesetor::find($row->id);
                $dados['setor_id'] = $estoquesetor->setor_id;
                $dados['produto_id'] = $estoquesetor->produto_id;
                $dados['quantidadesistema'] = $row->quantidadeatualsistema;
                $dados['quantidadefisica'] = empty($row->quantidadeatualfisico) ? $row->quantidadeatualsistema : $row->quantidadeatualfisico;
                $dados['estoquefisico_id'] = $estoquefisico->id;
                $dados['quantidadediferenca'] = $row->diferenca;
                $dados['estoquezerar'] = $row->zerar;
                $dados['estoqueremover'] = $row->remover;
                $estoquefisicosetor = Estoquefisicosetor::create($dados);
                if ($estoquefisico->efetivado) {
                    if ($row->zerar)
                        $row->diferenca = $row->quantidadeatualsistema > 0 ? $row->quantidadeatualsistema : 0 - $row->quantidadeatualsistema * -1;

                    if ($row->diferenca > 0 || $row->quantidadeatualfisico == $row->quantidadeatualsistema)
                        $movimentacao = 'SAIDA';
                    else
                        $movimentacao = 'ENTRADA';

                    $estoquesetorhistorico = new Estoquesetorhistorico();
                    $estoquesetorhistorico->user_id = \Auth::user()->id;
                    $estoquesetorhistorico->setor_id = $estoquesetor->setor_id;
                    $estoquesetorhistorico->produto_id = $estoquesetor->produto_id;
                    $estoquesetorhistorico->movimentacao = $movimentacao;
                    $estoquesetorhistorico->quantidade = $row->diferenca >= 0 ? $row->diferenca : str_replace('-', '', $row->diferenca);
                    $estoquesetorhistorico->motivo = "Estoque físico nº: " . $estoquefisicosetor->id;
                    $estoquesetorhistorico->datahora = $estoquefisico->datacompetencia;
                    $estoquesetorhistorico->datahoracompetencia = $estoquefisico->datacompetencia;
                    $estoquesetorhistorico->entidade = 'Estoquefisico';
                    $estoquesetorhistorico->entidade_id = $estoquefisico->id;
                    $estoquesetorhistorico->grupo_id = $dados['grupo_id'];
                    $estoquesetorhistorico->empresa_id = $dados['empresa_id'];

                    array_push($estoquesetorhistoricos, $estoquesetorhistorico);

                    if ($row->remover)
                        $toDelete->push([['produto_id', $estoquesetor->produto_id], ['setor_id', $estoquesetor->setor_id]]);
                }
            }

            if ($estoquefisico->efetivado) {
                $estoqueProcessor = new EstoqueProcessor();
                $transferirEstoque = $estoqueProcessor->movimentarEstoque($estoquesetorhistoricos);
                if (!$transferirEstoque) {
                    $errors = '';
                    $errorsProcessor = $estoqueProcessor->getErrors();
                    for ($i = (count($errorsProcessor) - 1); $i >= 0; $i--) {
                        $errors .= $errorsProcessor[$i] . ' ';
                    }
                    throw new \Exception($errors);
                }
                foreach ($toDelete as $del) {
                    Estoquesetor::where($del)->delete();
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
        DB::commit();
        return "OK|";
    }

}
