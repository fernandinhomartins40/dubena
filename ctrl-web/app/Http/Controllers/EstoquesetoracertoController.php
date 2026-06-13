<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Setor;
use App\Produto;
use App\Services\CarbonCustom as Carbon;
use App\Estoquesetor;
use App\Estoquesetoracerto;
use Illuminate\Http\Request;
use App\Estoquesetorhistorico;
use App\Processors\EstoqueProcessor;

class EstoquesetoracertoController extends Controller
{

    protected $empresa_id;
    protected $grupo_id;
    protected $setores;
    protected $produtos;
    protected $user_id;

    protected $rules = [
        'quantidadenova'    => 'required|numeric',
        'setor_id'          => 'required',
        'produto_id'        => 'required',
        'observacao'        => 'required'
    ];

    protected $msgs = [
        'quantidadenova.required'   => 'O campo Nova Qde. é obrigatório.',
        'quantidadenova.numeric'    => 'O campo Nova Qde. deve ser números 0 - 9.',
        'setor_id.required'         => 'O campo Setor é obrigatório.',
        'produto_id.required'       => 'O campo Produto é obrigatório.',
        'observacao.required'       => 'O campo Descrição é obrigatório.',
    ];

    function index(Estoquesetoracerto $ac)
    {
        $this->authorize('view',$ac);
        $this->definition();

        $dataInicio = Carbon::today()->subDays(2)->toDateTimeString();

        $estoquesetores = Estoquesetoracerto::where('empresa_id', $this->empresa_id)
        ->whereBetween('datahora', [$dataInicio, Carbon::now()])->get();

        if(count($_GET) > 0 && isset($_GET['dataInicio']) && isset($_GET['dataFim']) && strlen($_GET['dataInicio']) > 0 && strlen($_GET['dataFim']) > 0)
            return $this->getFiltros($_GET);

        $dataInicio = requestDataOracle($dataInicio, false);
        return view('estoque.setoracerto.setoracerto', compact('estoquesetores', 'dataInicio'));
    }

    private function getFiltros($get)
    {
        $dataInicio = insertDataOracle($get['dataInicio']) . ' 00:00:00';
        // dd($dataInicio);
        $dataFim = insertDataOracle($get['dataFim']) . ' 23:59:59';
        $estoquesetores = Estoquesetoracerto::where('empresa_id', $this->empresa_id)->whereBetween('datahora', [$dataInicio, $dataFim])->get();
        return view('estoque.setoracerto.setoracerto', compact('estoquesetores', 'dataInicio', 'dataFim'));
    }

    function create(Estoquesetoracerto $ac)
    {
        $this->authorize('create',$ac);
        $this->definition();
        $setores = $this->setores;
        $produtos = $this->produtos;
        $user_id = $this->user_id;
        return view('estoque.setoracerto.setoracerto_form', compact('setores', 'user_id', 'produtos'));
    }

    function show($id, Estoquesetoracerto $ac)
    {
        $this->authorize('view',$ac);
        $estoquesetor = Estoquesetoracerto::find($id);
        $this->authorize('igualdade',$estoquesetor);
        $this->definition();
        $setores = $this->setores;
        $produtos = $this->produtos;
        $show = true;
        return view('estoque.setoracerto.setoracerto_form', compact('setores', 'produtos', 'estoquesetor', 'show'));
    }

    function store(Request $request, Estoquesetoracerto $ac)
    {
        $this->authorize('create',$ac);
        $this->definition();
        $this->validate($request, $this->rules, $this->msgs);
        $data = $request->all();
        DB::beginTransaction();
        try {
            $data['empresa_id'] = $this->empresa_id;
            $data['grupo_id'] = $this->grupo_id;
            $data['user_id'] = \Auth::user()->id;
            $data['datahora'] = insertDataOracle($data['datahora']);
            $estoquesetor = Estoquesetoracerto::create($data);

            $data['diferenca'] = $data['quantidadenova'] - $data['quantidadeantiga'];
            $estoquesetorhistoricos = [];
            $estoquesetorhistorico = new Estoquesetorhistorico();
            $estoquesetorhistorico->user_id = \Auth::user()->id;
            $estoquesetorhistorico->setor_id = $data['setor_id'];
            $estoquesetorhistorico->produto_id = $data['produto_id'];
            $estoquesetorhistorico->movimentacao = $data['diferenca'] < 0 ? 'SAIDA' : 'ENTRADA';
            $estoquesetorhistorico->quantidade = $data['diferenca'] < 0 ? $data['diferenca'] * -1 : $data['diferenca'];
            $estoquesetorhistorico->motivo = 'Acerto de Estoque - ' . $data['observacao'];
            $estoquesetorhistorico->datahora = $data['datahora'];
            $estoquesetorhistorico->datahoracompetencia = $data['datahora'];
            $estoquesetorhistorico->entidade = 'Estoquesetoracerto';
            $estoquesetorhistorico->entidade_id = $estoquesetor->id;
            $estoquesetorhistorico->grupo_id = $this->grupo_id;
            $estoquesetorhistorico->empresa_id = $this->empresa_id;
            array_push($estoquesetorhistoricos, $estoquesetorhistorico);

            $estoqueProcessor = new EstoqueProcessor();
            $transferirEstoque = $estoqueProcessor->movimentarEstoque($estoquesetorhistoricos);
            $errors = '';
            if (!$transferirEstoque) {
                $errorsProcessor = $estoqueProcessor->getErrors();
                for ($i = count($errorsProcessor) - 1; $i >= 0; $i--) {
                    $errors .= $errorsProcessor[$i];
                }
                DB::rollback();
                return \Redirect::to('/estoquesetor/create')
                ->withErrors($errors)
                ->withInput();
            }
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/estoquesetor/create')
            ->withErrors($e->getMessage())
            ->withInput();
        } catch (ValidationException $e) {
            DB::rollback();
            return \Redirect::to('/estoquesetor/create')
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return \Redirect::to('/estoquesetor')->withMessageSuccess("Estoque do setor atualizado com sucesso!");
    }

    function buscaPorSetorProduto($setor_id, $produto_id)
    {
        return Estoquesetor::where([
            ['produto_id', $produto_id],
            ['setor_id', $setor_id]
            ])->get();
    }

    private function definition()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->setores = Setor::where([
            ['empresa_id', $this->empresa_id],
            ['ativo', 1]
            ])->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $this->produtos = Produto::where([
            ['empresa_id', $this->empresa_id],
            ['ativo', 1]
            ])->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $this->user_id = \Auth::user()->name;
    }

}
