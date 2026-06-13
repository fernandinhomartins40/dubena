<?php

namespace App\Http\Controllers;

use DB;
use Input;
use Session;
use App\Setor;
use App\Produto;
use App\Estoquesetor;
use App\Estoquefechamento;
use Illuminate\Http\Request;
use App\Processors\EstoqueProcessor;
use App\Empresaconfig;

class EstoquesetorController extends Controller
{

    function index(Estoquesetor $est)
    {
        $this->authorize('view', $est);
        $produtos = Produto::where([
                            ['empresa_id', Session::get('empresa_padrao')->id],
                            ['ativo', 1]
                        ])->orderby('descricao')->get()
                        ->pluck('descricao', 'id')->prepend('Selecione', '');

        $setor_id = (int) Input::get('setor', false);
        $operatorSetor = $setor_id ? '=' : '!=';

        $produto_id = (int) Input::get('produto', false);
        $operatorProduto = $produto_id ? '=' : '!=';

        $quantidade = Input::get('estoqueZerado', 'false') == 'true' ? 0 : null;
        $operatorQuantidade = $quantidade !== null ? '>' : '!=';
        $checked = $quantidade !== null;

        $where = [
            ['setors.empresa_id', Session::get('empresa_padrao')->id],
            ['setors.ativo', 1],
            ['produtos.ativo', 1],
            ['estoquesetors.quantidade', $operatorQuantidade, $quantidade],
            ['setors.id', $operatorSetor, $setor_id],
            ['produtos.id', $operatorProduto, $produto_id]
        ];
        $select = ['setors.id as setor_id',
            'setors.descricao as descricao',
            'produtos.id as produto_id',
            'produtos.descricao as produto_descricao',
            'estoquesetors.quantidade as quantidade',
            'produtos.customedio as customedio',
            'produtos.precovenda as precovenda'
        ];
        $estoquesetoritens = Setor::join('estoquesetors', 'setors.id', '=', 'estoquesetors.setor_id')
                        ->join('produtos', 'estoquesetors.produto_id', '=', 'produtos.id')
                        ->where($where)->select($select)->get();

        $setores = Setor::where([
                    ['ativo', 1],
                    ['empresa_id', Session::get('empresa_padrao')->id]
                ])->orderby('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');

        return view('estoque.setor.estoquesetor', compact('setores', 'estoquesetoritens', 'setor_id', 'produtos', 'produto_id', 'checked'));
    }

    function consultaEstoqueSetor($setor_id)
    {
        $setor_id = $setor_id !== 'null' ? $setor_id : null;
        $empresa_id = Input::get('empresa_id', Session::get('empresa_padrao')->id);

        if (array_key_exists("filterColumns", $_GET) && $_GET['filterColumns']) {
            $select = ['produtos.descricao', 'produtos.id', 'produtos.precovenda', 'produtos.precovendaminimo', 'produtos.precogasdopovo'];
        } else {
            $select = ["*"];
        }
        return Estoquesetor::join('produtos', 'estoquesetors.produto_id', '=', 'produtos.id')
            ->join('setors', 'estoquesetors.setor_id', '=', 'setors.id')
            ->where([
                ['estoquesetors.setor_id', $setor_id],
                ['setors.ativo', 1],
                ['produtos.ativo', 1],
                ['setors.empresa_id', $empresa_id]
            ])->select($select)
            ->orderby('produtos.descricao')
            ->get();
    }

    function fecharEstoque(Request $request, Estoquesetor $est)
    {
        $this->authorize('create', $est);
        $estoqueProcessor = new EstoqueProcessor();
        $estoqueFechamento = new Estoquefechamento;
        try {
            $this->validate($request, [
                'dataFechamento' => 'required',
            ]);
            $data = $request->only('dataFechamento');

            $estoqueFechamento->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $estoqueFechamento->empresa_id = Session::get('empresa_padrao')->id;
            $estoqueFechamento->fechamentomensal = false;
            $data['dataFechamento'] = insertDataOracle($data['dataFechamento']);
            $estoqueFechamento->datahorafechamento = $data['dataFechamento'];

            if ($estoqueProcessor->fecharEstoque($estoqueFechamento)) {

            } else {
                $erros = "";
                $getErros = $estoqueProcessor->getErrors();
                foreach ($getErros as $erro) {
                    $erros .= $erro;
                }
                return $erros;
            }
        } catch (ValidationException $e) {
            return "<br />" . $e->getMessage();
        } catch (\Exception $e) {
            $erros = "";
            foreach ($estoqueProcessor->getErrors() as $erro) {
                $erros .= "<br />" . $erro;
            }
            return "<br />" . "<br />" . $e->getMessage() . "<br />" . $erros;
        }
        return "OK|";
    }

    function abrirEstoque(Request $request, Estoquesetor $est)
    {
        $this->authorize('update', $est);
        $this->validate($request, [
            'dataAbertura' => 'required',
        ]);
        $data = $request->all();
        $estoqueProcessor = new EstoqueProcessor();
        try {
            $estoqueFechamento = new Estoquefechamento;

            $estoqueFechamento->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $estoqueFechamento->empresa_id = Session::get('empresa_padrao')->id;
            $estoqueFechamento->fechamentomensal = false;
            $estoqueFechamento->datahorafechamento = $data['dataAbertura'];
            $estoqueFechamento->reaberto = true;
            $estoqueFechamento->reabertouser_id = \Auth::user()->id;
            $estoqueFechamento->reabertomotivo = $data['motivo'];

            if ($estoqueProcessor->abrirEstoque($estoqueFechamento)) {

            } else {
                $erros = "";
                $getErros = $estoqueProcessor->getErrors();
                foreach ($getErros as $erro) {
                    $erros .= $erro;
                }
                return $erros;
            }
        } catch (\Exception $e) {
            $erros = "";
            foreach ($estoqueProcessor->getErrors() as $erro) {
                $erros .= "<br />" . $erro;
            }
            return "<br />" . "<br />" . $e->getMessage() . "<br />" . $erros;
        }
        return "OK|";
    }

    function consultaQuantidadeProduto($produto_id, $setor_id)
    {
        $estoqueSetor = Estoquesetor::where([
                    ['produto_id', $produto_id],
                    ['setor_id', $setor_id]
                ])->select('quantidade')->get();
        if (isset($estoqueSetor[0])) {
            if (isset($estoqueSetor[0]->quantidade)) {
                return $estoqueSetor[0]->quantidade;
            }
        }
        return "ERRO! Não foram encontrados registros para este setor e produto";
    }

    function consultaQuantidadeProdutoPermiteNegativar($produto_id, $setor_id, $qtdemovimentar, $entradasaida = false)
    {
        $empresaconfig = Empresaconfig::where('empresa_id', Session::get('empresa_padrao')->id)->get()->first();
        $estoqueSetor = Estoquesetor::where([
                    ['produto_id', $produto_id],
                    ['setor_id', $setor_id]
                ])->select('quantidade')->get();
        if (!is_bool($entradasaida) && $entradasaida == 1) {
            return isset($estoqueSetor[0]) ? $estoqueSetor[0]->quantidade : 0;
        }
        if (isset($estoqueSetor[0])) {
            if (isset($estoqueSetor[0]->quantidade)) {
                if ($qtdemovimentar <= $estoqueSetor[0]->quantidade) {
                    return $estoqueSetor[0]->quantidade;
                } else {
                    if ($empresaconfig != null) {
                        if ($empresaconfig->permiteestoquenegativo) {
                            return $estoqueSetor[0]->quantidade;
                        } else {
                            return "Produto não tem a quantidade em estoque e a empresa não permite negativar o estoque.";
                        }
                    } else {
                        return "Produto não tem a quantidade em estoque e não foi possivel carregar config da empresa para validar se permite negativar o estoque.";
                    }
                }
                return $estoqueSetor[0]->quantidade;
            }
        }
        return "Problemas ao consultar estoque para este setor e produto";
    }

}
