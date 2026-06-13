<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Nfoperacao;
use App\Produto;
use App\Http\Requests\NfOperacaoRequest;
use App\Nfoperacaoproduto;
use App\Nfoperacaoprodutoconvenio;
use Illuminate\Support\Facades\Redirect;

class NfOperacaoController extends Controller
{

    public function index(Nfoperacao $nfop)
    {
        $this->authorize('view', $nfop);
        $nfoperacao = Nfoperacao::where([
                    ['empresa_id', Session::get('empresa_padrao')->id]
                ])->get();

        return view('nf.nfoperacao.nfoperacao', compact('nfoperacao'));
    }

    public function create(Nfoperacao $nfop)
    {
        $this->authorize('create', $nfop);
        return $this->returnView('create');
    }

    function store(NfOperacaoRequest $request, Nfoperacao $nfop)
    {
        try {
            $this->authorize('create', $nfop);
            $data = emptyToNull($request->all());
            $data['empresa_id'] = Session::get('empresa_padrao')->id;
            $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
            $data['atualizacusto'] = isset($data['atualizacusto']);
            $this->validateCustom($data);
            $nfoperacao = Nfoperacao::create($data);
            if (isset($data["produtos"])) {
                $produtos = json_decode($data["produtos"]);
                $prods_ids = Array();
                foreach ($produtos as $produto) {
                    if (!$produto[0]) {
                        $prod = new Nfoperacaoproduto();
                        $prod["nfoperacao_id"] = $nfoperacao->id;
                        $prod["produto_id"] = $produto[1];
                        $prod["nfoperacaoapp_id"] = $produto[3];
                        $prod->save();
                        array_push($prods_ids, $prod->id);
                    } else {
                        array_push($prods_ids, $produto[0]);
                    }
                }
                if(count($prods_ids)>0){
                    $operprods = Nfoperacaoproduto::where('nfoperacao_id', $nfoperacao->id)
                                                  ->whereNotIn('id', $prods_ids);
                    $operprods->delete();
                }
        
            }
            if (isset($data["produtoconvenios"])) {
                $produtos = json_decode($data["produtoconvenios"]);
                $prods_ids = Array();
                foreach ($produtos as $produto) {
                    if (!$produto[0]) {
                        $prod = new Nfoperacaoprodutoconvenio();
                        $prod["nfoperacao_id"] = $nfoperacao->id;
                        $prod["produto_id"] = $produto[1];
                        $prod["nfoperacaoconvenio_id"] = $produto[3];
                        $prod->save();
                        array_push($prods_ids, $prod->id);
                    } else {
                        array_push($prods_ids, $produto[0]);
                    }
                }
                if(count($prods_ids)>0){
                    $operprods = Nfoperacaoprodutoconvenio::where('nfoperacao_id', $nfoperacao->id)
                                                  ->whereNotIn('id', $prods_ids);
                    $operprods->delete();
                }
        
            }

        } catch (\Exception $e) {
            return Redirect::to('nfoperacao/create')->withErrors(getErrorsException($e))->withInput();
        }
        return Redirect::to('nfoperacao')->withMessageSuccess('Operação cadastrada com sucesso!');
    }

    function edit($id, Nfoperacao $nfop)
    {
        $this->authorize('update', $nfop);
        return $this->returnView('edit', $id);
    }

    public function returnView($view, $id = null)
    {
        if (!is_null($id)) {
            $nfoperacao = Nfoperacao::find($id);
            $this->authorize('igualdade', $nfoperacao);
        }

        $movimentaestoque = [
            0 => 'Sem Movimento',
            1 => 'Entrada',
            2 => 'Saída'
        ];

        $movimentafinanceiro = [
            0 => 'Sem Financeiro',
            1 => 'Pagar',
            2 => 'Receber'
        ];

        $cadastronf = [
            'C' => 'Cliente',
            'F' => 'Fornecedor',
            'A' => 'Ambos'
        ];
        //0-NFEntrada 1-NFSaida 2-Ambos | 9-Config
        $aparecetela = [
            9 => 'Selecione',
            0 => 'NF Entrada',
            1 => 'NF/SAT Saída',
            2 => 'Ambos'
        ];
        $produtos = Produto::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $nfoperacaos = NFoperacao::where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        if ($view == 'show') {
            $show = true;
            return view('nf.nfoperacao.nfoperacao_form', compact('nfoperacao', 'show', 'movimentaestoque', 'movimentafinanceiro', 'cadastronf', 'aparecetela', 'produtos', 'nfoperacaos'));
        } else if ($view == 'edit') {
            return view('nf.nfoperacao.nfoperacao_form', compact('nfoperacao', 'movimentaestoque', 'movimentafinanceiro', 'cadastronf', 'aparecetela', 'produtos', 'nfoperacaos'));
        } else {
            return view('nf.nfoperacao.nfoperacao_form', compact('movimentaestoque', 'movimentafinanceiro', 'cadastronf', 'aparecetela', 'produtos', 'nfoperacaos'));
        }
    }

    function show($id, Nfoperacao $nfop)
    {
        $this->authorize('view', $nfop);
        return $this->returnView('show', $id);
    }

    function update(NfOperacaoRequest $request, $id, Nfoperacao $nfop)
    {
        try {
            $this->authorize('update', $nfop);
            $nfoperacao = Nfoperacao::findOrFail($id);
            $this->authorize('igualdade', $nfoperacao);
            $data = emptyToNull($request->all());
            $data['atualizacusto'] = isset($data['atualizacusto']);
            $this->validateCustom($data);
            $nfoperacao->update($data);
            if (isset($data["produtos"])) {
                $produtos = json_decode($data["produtos"]);
                $prods_ids = Array();
                foreach ($produtos as $produto) {
                    if (!$produto[0]) {
                        $prod = new Nfoperacaoproduto();
                        $prod["nfoperacao_id"] = $nfoperacao->id;
                        $prod["produto_id"] = $produto[1];
                        $prod["nfoperacaoapp_id"] = $produto[3];
                        $prod->save();
                        array_push($prods_ids, $prod->id);
                    } else {
                        array_push($prods_ids, $produto[0]);
                    }
                }
                if(count($prods_ids)>0){
                    $operprods = Nfoperacaoproduto::where('nfoperacao_id', $nfoperacao->id)
                                                  ->whereNotIn('id', $prods_ids);
                    $operprods->delete();
                }
            }
            if (isset($data["produtoconvenios"])) {
                $produtos = json_decode($data["produtoconvenios"]);
                $prods_ids = Array();
                foreach ($produtos as $produto) {
                    if (!$produto[0]) {
                        $prod = new Nfoperacaoprodutoconvenio();
                        $prod["nfoperacao_id"] = $nfoperacao->id;
                        $prod["produto_id"] = $produto[1];
                        $prod["nfoperacaoconvenio_id"] = $produto[3];
                        $prod->save();
                        array_push($prods_ids, $prod->id);
                    } else {
                        array_push($prods_ids, $produto[0]);
                    }
                }
                if(count($prods_ids)>0){
                    $operprods = Nfoperacaoprodutoconvenio::where('nfoperacao_id', $nfoperacao->id)
                                                  ->whereNotIn('id', $prods_ids);
                    $operprods->delete();
                }
        
            }
        } catch (\Exception $e) {
            return Redirect::to('nfoperacao/'.$id.'/edit')->withErrors(getErrorsException($e))->withInput();
        }
        return \Redirect::route('nfoperacao.index')->withMessageSuccess('Operacao atualizada com sucesso!');
    }

    function validateCustom(&$data)
    {
        $data["usasat"] = isset($data['usasat']);

        $len = mb_strlen($data['descricao']);
        if ($len > 60)
            throw new \Exception('O campo Descrição possui ' . $len . ' caracteres e não pode ser maior que 60 caracteres.');

        $len = mb_strlen($data['descricaofiscal']);
        if ($len > 60)
            throw new \Exception('O campo Descrição Fiscal possui ' . $len . ' caracteres e não pode ser maior que 60 caracteres.');
    }

    function destroy($id, Nfoperacao $nfop)
    {
        $this->authorize('delete', $nfop);
        $nfoperacao = Nfoperacao::find($id);
        $this->authorize('igualdade', $nfoperacao);
        DB::beginTransaction();
        try {
            $nfoperacao->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    public function carregaoperacaoid($id)
    {
        $nfoperacao = Nfoperacao::find($id);
        return $nfoperacao;
    }

}
