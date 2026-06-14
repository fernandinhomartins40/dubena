<?php

namespace App\Http\Controllers;

use Dompdf\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use App\Promocao;
use App\Produto;
use Session;
use App\Clientepromocao;

class PromocaoController extends Controller
{

    private $msgValidacao = ['datahorainicio.before' => 'A data inicial da promoção não pode ser depois nem no mesmo dia da data final.'];

    public function index(Promocao $promo)
    {
        $this->authorize('view',$promo);
        $promocoes = Promocao::where([
                    ['empresa_id', Session::get('empresa_padrao')->id],
                ])->get();

        return view('promocoes.promocoes', compact('promocoes'));
    }

    public function create(Promocao $promo)
    {
        $datahorainicio = '';
        $datahorafim = '';
        $this->authorize('create',$promo);
        $produtos = Produto::where([
                    ['ativo', 1],
                    ['empresa_id', Session::get('empresa_padrao')->id]
                ])->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $premioProduto = $produtos;
        $vinculado = false;
        return view('promocoes.promocao_form', compact('produtos', 'premioProduto', 'datahorainicio', 'datahorafim', 'vinculado'));
    }

    public function show($id,Promocao $promo)
    {
        $this->authorize('view',$promo);
        $promocao = Promocao::find($id);
        $this->authorize('igualdade',$promocao);
        $clientePromocao = Clientepromocao::where('promocao_id', $id)->first();
        $produtos = Produto::where([
                    ['ativo', 1],
                    ['empresa_id', Session::get('empresa_padrao')->id]
                ])->pluck('descricao', 'id')->prepend('Selecione', '');
        $premioProduto = $produtos;

        $datahorainicio = Promocao::where('id', $id)->pluck('datahorainicio');
        $datahorainicio = requestDataOracle($datahorainicio);

        $datahorafim = Promocao::where('id', $id)->pluck('datahorafim');
        $datahorafim = requestDataOracle($datahorafim);
        $show = true;
        $vinculado = false;
        return view('promocoes.promocao_form', compact('promocao', 'show', 'produtos', 'premioProduto', 'datahorafim', 'datahorainicio', 'vinculado'));
    }

    public function store(Request $request,Promocao $promo)
    {
        $this->authorize('create',$promo);
        $data = $request->only('descricao', 'produto_id', 'premioproduto_id', 'datahorainicio', 'datahorafim', 'quantidadepremios', 'quantidadepedidos', 'ativo');

        $data['datahorafim'] = insertDataOracle($data['datahorafim']);
        $data['datahorainicio'] = insertDataOracle($data['datahorainicio']);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $this->validate($request, [
            'descricao' => 'required|max:255|'
            . 'unique:promocaos,descricao,null,id,datahorainicio,' . $data['datahorainicio'] . ','
            . 'datahorafim,' . $data['datahorafim'] . ',empresa_id,' . Session::get('empresa_padrao')->id,
            'datahorainicio' => 'required',
            'datahorafim' => 'required',
            'quantidadepedidos' => 'required_with:quantidadepremios|numeric',
            'quantidadepremios' => 'numeric|required_with:quantidadepedidos'
                ], $this->msgValidacao);

        DB::beginTransaction();
        try {

            if ($data['datahorafim'] <= $data['datahorainicio']) {
                DB::rollback();
                return Redirect::to('/promocao/create')
                                ->withErrors($this->msgValidacao)
                                ->withInput();
            }
            $verificarPeriodoPromocoes = $this->verificarPeriodoPromocoes($data['datahorainicio'], $data['datahorafim']);
            if ($verificarPeriodoPromocoes) {
                DB::rollback();
                return \Redirect::to('/promocao/create')
                                ->withErrors("Já existe uma promoção ativa cadastrada nesse período.")
                                ->withInput();
            }
            Promocao::create($data);
        } catch (ValidationException $e) {
            DB::rollback();
            return Redirect::to('/promocao/create')
                            ->withErrors($e->getMessage())
                            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/promocao/create')
                            ->withErrors($e->getMessage())
                            ->withInput();
        }

        DB::commit();
        return \Redirect::route('promocao.index')->withMessageSuccess("Promoção cadastrada com sucesso!");
    }

    private function verificarPeriodoPromocoes($dataInicio, $dataFim, $id = null)
    {
        $promocoes = Promocao::where([
                    ['ativo', 1],
                    ['empresa_id', Session::get('empresa_padrao')->id],
                    ['id', '!=', $id]
                ])->get();
        if (count($promocoes) > 0) {
            foreach ($promocoes as $promocao) {
                if ($promocao['datahorainicio'] <= $dataInicio && $dataInicio <= $promocao['datahorafim']) {
                    return true;
                } else if ($promocao['datahorainicio'] <= $dataFim && $dataFim <= $promocao['datahorafim']) {
                    return true;
                } else if ($dataFim <= $promocao['datahorainicio'] && $promocao['datahorainicio'] <= $dataFim) {
                    return true;
                } else if ($promocao['datahorainicio'] <= $dataFim && $dataFim <= $promocao['datahorafim']) {
                    return true;
                }
            }
        } else {
            return false;
        }
        return false;
    }

    public function edit($id,Promocao $promo)
    {
        $this->authorize('update',$promo);
        $promocao = Promocao::find($id);
        $this->authorize('igualdade',$promocao);
        $produtos = Produto::where([
                    ['ativo', 1],
                    ['empresa_id', Session::get('empresa_padrao')->id]
                ])->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $premioProduto = $produtos;

        $datahorainicio = Promocao::where('id', $id)->pluck('datahorainicio');
        $datahorainicio = requestDataOracle($datahorainicio);

        $datahorafim = Promocao::where('id', $id)->pluck('datahorafim');
        $datahorafim = requestDataOracle($datahorafim);
        $clientePromocao = Clientepromocao::where('promocao_id', $id)->first();
        if (!is_null($clientePromocao)) {
            $vinculado = true;
        } else {
            $vinculado = false;
        }
        return view('promocoes.promocao_form', compact('promocao', 'produtos', 'premioProduto', 'datahorafim', 'datahorainicio', 'vinculado'));
    }

    public function update(Request $request, $id, Promocao $promo)
    {
        $this->authorize('update',$promo);
        $promocao = Promocao::findOrFail($id);
        $data = $request->all();
        $data['ativo'] = isset($data['ativo']) ? 1 : 0;
        if (isset($data['vinculado']) && $data['vinculado'] == 1) {
            $ativo = $data['ativo'];
            $promocao->update(['ativo' => $ativo]);
            return \Redirect::route('promocao.index')->withMessageSuccess("Promoção atualizada com sucesso!");
        }
        $this->validate($request, [
            'descricao' => 'required|max:255'
            . 'unique:promocaos,descricao,' . $id . ',id,datahorainicio,' . $promocao['datahorainicio'] . ','
            . 'datahorafim,' . $promocao['datahorafim'] . ',empresa_id,' . Session::get('empresa_padrao')->id,
            'datahorainicio' => 'required',
            'datahorafim' => 'required',
            'quantidadepedidos' => 'required_with:quantidadepremios|numeric',
            'quantidadepremios' => 'numeric|required_with:quantidadepedidos'
                ], $this->msgValidacao);
        $data['datahorafim'] = insertDataOracle($data['datahorafim']);
        $data['datahorainicio'] = insertDataOracle($data['datahorainicio']);

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        DB::beginTransaction();
        try {
            if ($data['datahorafim'] <= $data['datahorainicio']) {
                DB::rollback();
                return Redirect::to('/promocao/' . $id . '/edit')
                                ->withErrors($this->msgValidacao)
                                ->withInput();
            }

            $verificarPeriodoPromocoes = $this->verificarPeriodoPromocoes($data['datahorainicio'], $data['datahorafim'], $id);
            if ($verificarPeriodoPromocoes) {
                DB::rollback();
                return \Redirect::to('/promocao/' . $id . '/edit')
                                ->withErrors("Já existe uma promoção ativa cadastrada nesse período.")
                                ->withInput();
            }
            $promocao->update($data);
        } catch (ValidationException $e) {
            DB::rollback();
            return Redirect::to('/promocao/' . $id . '/edit')
                            ->withErrors($e->getMessage())
                            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/promocao/' . $id . '/edit')
                            ->withErrors($e->getMessage())
                            ->withInput();
        }

        DB::commit();
        return \Redirect::route('promocao.index')->withMessageSuccess("Promoção atualizada com sucesso!");
    }

    public function destroy($id, Promocao $promo)
    {
        $this->authorize('delete',$promo);
        $promocao = Promocao::find($id);
        $this->authorize('igualdade',$promocao);
        $clientePromocao = Clientepromocao::where('promocao_id', $id)->first();
        if (count($clientePromocao) !== 0) {
            return "Não é possível remover a promoção, pois existe um ou mais clientes atribuídos a ela.";
        }
        DB::beginTransaction();
        try {
            $promocao->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return "Não é possível remover a promoção, pois existe um ou mais clientes atribuídos a ela.";
        }
        DB::commit();
        return "OK|";
    }

    public function buscaPromocaoAjax($id)
    {
        try {
            $promocao = DB::table("promocaos as p")->where("id", $id)->select(["datahorafim", "datahorainicio"])->get()->first();

            if (!$promocao) {
                throw new \Exception("");
            }
            $promocao->datahorainiciof = requestDataOracle($promocao->datahorainicio, false);
            $promocao->datahorafimf = requestDataOracle($promocao->datahorafim, false);
            $promocao->datahorainicio = explode(" ", $promocao->datahorainicio)[0];
            $promocao->datahorafim = explode(" ", $promocao->datahorafim)[0];
            return response()->json($promocao);
        } catch (\Exception $e) {
            return "Não foi possível buscar a promoção";
        }
    }

}
