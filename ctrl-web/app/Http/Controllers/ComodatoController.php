<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Empresa;
use App\Cliente;
use App\Comodato;
use App\Services\CarbonCustom as Carbon;
use App\Comodatoitem;
use App\Produtoclasse;
use App\Empresaconfig;
use Illuminate\Http\Request;
use App\Estoquesetorhistorico;
use App\Processors\EstoqueProcessor;
use App\Http\Requests\ComodatoRequest;

class ComodatoController extends Controller
{

    protected $empresa_id;
    protected $grupo_id;
    protected $produtoClasse;

    public function index(Comodato $com)
    {
        $this->authorize('view',$com);
        $this->definition();
        $comodatos = Comodato::where('empresa_id', $this->empresa_id)->get();
        return view('comodatos.index', compact('comodatos'));
    }

    public function create(Comodato $com)
    {
        $this->authorize('create',$com);
        $this->definition();
        $produtoClasses = $this->produtoClasse;
        return view('comodatos.create', compact('produtoClasses'));
    }

    public function store(ComodatoRequest $request, Comodato $com)
    {
        $this->authorize('create',$com);
        $this->definition();
        DB::beginTransaction();
        try {
            $data = $request->all();
            $dadosextras = $this->dadosExtras($data);
            $data = array_merge($data, $dadosextras);

            $comodato = Comodato::create($data);
            $this->insertUpdateComodatoProdutos($data, $comodato);

            $produtos = json_decode($data['produtos']);
            $movimentacao = 'SAIDA';
            if ($comodato->tipo == 2 && $comodato->ativo == '1') {
                $movimentacao = 'ENTRADA';
            } else if ($comodato->tipo != 2 && $comodato->ativo == '0') {
                $movimentacao = 'ENTRADA';
            }
            $this->movimentaEstoque($comodato, $produtos, $movimentacao, $comodato->datacontrato);

        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('comodato/create')->withErrors($e->getMessage())->withInput();
        }
        DB::commit();
        if (isset($data['ativo']) && $data['ativo'] === '1') {
            return Redirect::to('comodato?cod=' . $comodato->id)->withMessageSuccess('Comodato cadastrado com sucesso!');
        } else {
            return redirect()->route('comodato.index')->withMessageSuccess('Comodato cadastrado com sucesso!');
        }
    }

    public function show($id, Comodato $com)
    {
        $this->authorize('view',$com);
        $comodato = Comodato::find($id);
        $this->authorize('igualdade',$comodato);
        $this->definition();
        $comodatoprodutos = Comodatoitem::where('comodato_id', $id)->get();
        $comodato->datacontrato = requestDataOracle($comodato->datacontrato);
        $comodato->datavencimento = requestDataOracle($comodato->datavencimento);
        $produtoClasses = $this->produtoClasse;
        $show = true;
        return view('comodatos.create', compact('produtoClasses', 'show', 'comodato', 'comodatoprodutos'));
    }

    public function edit($id, Comodato $com)
    {
        $this->authorize('update',$com);
        $comodato = Comodato::find($id);
        $this->authorize('igualdade',$comodato);
        $this->definition();
        $comodatoprodutos = Comodatoitem::where('comodato_id', $id)->get();
        $comodato->datacontrato = requestDataOracle($comodato->datacontrato);
        $comodato->datavencimento = requestDataOracle($comodato->datavencimento);
        $produtoClasses = $this->produtoClasse;
        $edit = true;
        return view('comodatos.create', compact('produtoClasses', 'edit', 'comodato', 'comodatoprodutos'));
    }

    public function update(Request $request, $id, Comodato $com)
    {
        $this->authorize('update',$com);
        $comodato = Comodato::find($id);
        $this->authorize('igualdade',$comodato);
        $this->definition();
        $data = $request->all();
        DB::beginTransaction();
        try {
            if (!isset($data['ativo'])) {
                $data['ativo'] = 0;
            }
            $produtos = json_decode($data['produtos']);
            if ($comodato->ativo != $data['ativo']) {
                $movimentacao = 'ENTRADA';
                if ($comodato->tipo == 2 && $comodato->ativo == '1') {
                    $movimentacao = 'SAIDA';
                } else if ($comodato->tipo != 2 && $comodato->ativo == '0') {
                    $movimentacao = 'SAIDA';
                }
                $this->movimentaEstoque($comodato, $produtos, $movimentacao, $comodato->datacontrato, $comodato->ativo);
            }
            $dados = [
                'ativo' => $data['ativo'],
                'nomerepresentante' => $data['nomerepresentante'],
                'cpfrepresentante' => $data['cpfrepresentante'],
                'rgrepresentante' => $data['rgrepresentante']
            ];

            $comodato->update($dados);

        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/comodato/' . $id . '/edit')
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        if ($data['ativo'] !== 0) {
            return Redirect::to('comodato?cod=' . $comodato->id)->withMessageSuccess('Comodato atualizado com sucesso!');
        } else {
            return redirect()->route('comodato.index')->withMessageSuccess('Comodato atualizado com sucesso!');
        }
    }

    protected function dadosExtras($data)
    {
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["datacontrato"] = insertDataOracle($data["datacontrato"]);
        $data["datavencimento"] = insertDataOracle($data["datavencimento"]);
        return $data;
    }

    protected function insertUpdateComodatoProdutos($data, $comodato)
    {
        $comodato->comodatoprodutos()->delete();
        $items = [];
        $produtos = json_decode($data['produtos']);
        foreach ($produtos as $produto) {
            $item = new Comodatoitem();
            $item['comodato_id'] = $comodato->id;
            $item['produto_id'] = $produto[0];
            $item['quantidade'] = $produto[2];
            array_push($items, $item);
        }
        $comodato->comodatoprodutos()->saveMany($items);
    }

    protected function movimentaEstoque($comodato, $produtos, $movimentacao, $datahoracompetencia, $inativa = false)
    {
        $estoquesetorhistoricos = [];
        $empresaconfig = Empresaconfig::where('empresa_id', $this->empresa_id)->get()->first();

        if ($empresaconfig !== null && $empresaconfig->setorprincipal_id) {
            foreach ($produtos as $produto) {
                $estoquesetorhistorico = new Estoquesetorhistorico();
                $estoquesetorhistorico->user_id = \Auth::user()->id;
                $estoquesetorhistorico->setor_id = $empresaconfig->setorprincipal_id;
                $estoquesetorhistorico->produto_id = $produto[0];
                $estoquesetorhistorico->movimentacao = $movimentacao;
                $estoquesetorhistorico->quantidade = $produto[2];
                $estoquesetorhistorico->motivo = 'Comodato - ' . $movimentacao;
                $estoquesetorhistorico->datahora = Carbon::now();
                $estoquesetorhistorico->datahoracompetencia = $inativa ? Carbon::now() : $datahoracompetencia;
                $estoquesetorhistorico->entidade = 'Comodato';
                $estoquesetorhistorico->entidade_id = $comodato->id;
                $estoquesetorhistorico->grupo_id = Session::get('empresa_padrao')->grupo_id;
                $estoquesetorhistorico->empresa_id = Session::get('empresa_padrao')->id;
                array_push($estoquesetorhistoricos, $estoquesetorhistorico);
            }
            $estoqueProcessor = new EstoqueProcessor();
            $transferirEstoque = $estoqueProcessor->movimentarEstoque($estoquesetorhistoricos);
            if (!$transferirEstoque) {
                $errors = '';
                $errorsProcessor = $estoqueProcessor->getErrors();
                for ($i = (count($errorsProcessor) - 1); $i >= 0 ; $i--) {
                    $errors .= $errorsProcessor[$i] . ' ';
                }
                throw new \Exception($errors);
            } else {
                return;
            }
        } else {
            throw new \Exception('Não há um setor principal selecionado para esta empresa! '
                . 'Por favor, acesse as configurações da empresa para realizar esta operação!');
        }
    }

    public function contrato($id, Comodato $com)
    {
        $this->authorize('view',$com);
        $comodato = Comodato::find($id);
        $this->authorize('igualdade',$comodato);
        $this->definition();
        $empresa = Empresa::find($this->empresa_id);
        $comodatoprodutos = Comodatoitem::where('comodato_id', $id)->get();
        $codigo = $comodato->id;
        $titulo = 'Comodato Nº ' . $id;
        $filtro = '';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('comodatos.contrato', compact('titulo', 'filtro', 'codigo', 'comodato', 'empresa', 'comodatoprodutos'));
    //    return view('comodatos.contrato', compact('titulo', 'filtro', 'codigo', 'comodato', 'empresa', 'comodatoprodutos'));
       return $pdf->stream();
    }

    private function definition()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->produtoClasse = Produtoclasse::where([
                ['ativo', 1],
                ['grupo_id', $this->grupo_id]
            ])->orderby('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
    }

}
