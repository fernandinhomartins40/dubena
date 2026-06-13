<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Empresabem;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Requests\EmpresaBensRequest;

/**
 * 
 * data não editável
 * nº serie gravar zero em caso de nulo
 * tipo
 * dias
 * semanas
 * meses 
 * anos
 * editar somente se a ação de depreciação não foi gerada
 * Menu: Gerais->Bens
 * 
 */
class EmpresabensController extends Controller
{

    function index(Empresabem $bem)
    {
        $this->authorize('view',$bem);
        $bens = Empresabem::where('empresa_id', Session::get('empresa_padrao')->id)->get();
        return View('empresabens.empresabens', compact('bens'));
    }

    function create(Empresabem $bem)
    {
        $this->authorize('create',$bem);
        return view('empresabens.empresabens_form');
    }

    function store(EmpresaBensRequest $request, Empresabem $bem)
    {
        $this->authorize('create',$bem);
        DB::beginTransaction();
        try {
            $data = $request->all();
            $data['valororiginal'] = insertNumeroDecimalOracle($data['valororiginal']);
            $data['valor'] = $data['valororiginal'];
            $data['valoratual'] = $data['valor'];
            $data['depreciacaoporcentagem'] = insertPercentualOracle($data['depreciacaoporcentagem']);
            $data['depreciacaovalor'] = insertNumeroDecimalOracle($data['hiddendepreciacaovalor']);
            $data['empresa_id'] = Session::get('empresa_padrao')->id;
            $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
            $data['datacadastro'] = insertDataOracle($data['datacadastro']);

            if ($data['tipodepreciacao'] === '0') {
                $data['depreciacaodias'] = $data['depreciacaodias'];
            } else if ($data['tipodepreciacao'] === '1') {
                $data['depreciacaodias'] = $data['depreciacaodias'] * 30;
            } else if ($data['tipodepreciacao'] === '2') {
                $data['depreciacaodias'] = $data['depreciacaodias'] * 365;
            }
            $data['ativo'] = isset($data['ativo']) ? 1 : 0;
            Empresabem::create($data);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/empresabens/create')
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return Redirect::to('empresabens')->withMessageSuccess("Bem cadastrado com sucesso!");
    }

    function update(EmpresaBensRequest $request, $id, Empresabem $empbem)
    {
        $this->authorize('update',$empbem);
        $bem = Empresabem::find($id);
        $this->authorize('igualdade',$bem);
        DB::beginTransaction();
        try {
            $data = $request->all();
            $data['valororiginal'] = insertNumeroDecimalOracle($data['valororiginal']);
            $data['valor'] = $data['valororiginal'];
            $data['valoratual'] = $data['valor'];
            $data['depreciacaoporcentagem'] = insertPercentualOracle($data['depreciacaoporcentagem']);
            $data['depreciacaovalor'] = insertNumeroDecimalOracle($data['hiddendepreciacaovalor']);
            $data['empresa_id'] = Session::get('empresa_padrao')->id;
            $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
            $data['datacadastro'] = insertDataOracle($data['datacadastro']);
            if ($data['tipodepreciacao'] === '0') {
                $data['depreciacaodias'] = $data['depreciacaodias'];
            } else if ($data['tipodepreciacao'] === '1') {
                $data['depreciacaodias'] = $data['depreciacaodias'] * 30;
            } else if ($data['tipodepreciacao'] === '2') {
                $data['depreciacaodias'] = $data['depreciacaodias'] * 365;
            }
            
            $data['ativo'] = isset($data['ativo']) ? 1 : 0;

            $bem->update($data);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/empresabens/' . $id . '/edit')
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return Redirect::to('empresabens')->withMessageSuccess("Bem atualizado com sucesso!");
    }

    function show($id, Empresabem $bem)
    {
        $this->authorize('view',$bem);
        $empresabens = Empresabem::findOrFail($id);     
        $this->authorize('igualdade',$empresabens);
        if ($empresabens->tipodepreciacao === '2') {
            $empresabens->depreciacaodias = $empresabens->depreciacaodias / 365;
        } else if ($empresabens->tipodepreciacao === '1') {
            $empresabens->depreciacaodias = $empresabens->depreciacaodias / 30;
        }
        $empresabens->valororiginal = requestNumeroDecimalOracle($empresabens->valororiginal);
        $empresabens->valor = requestNumeroDecimalOracle($empresabens->valor);
        $empresabens->depreciacaoporcentagem = requestPercentualOracle($empresabens->depreciacaoporcentagem);
        $empresabens->depreciacaovalor = requestNumeroDecimalOracle($empresabens->depreciacaovalor);
        $empresabens->valoratual = requestNumeroDecimalOracle($empresabens->valoratual);
        $show = true;
        return View('empresabens.empresabens_form', compact('empresabens', 'show'));
    }

    function edit($id, Empresabem $bem)
    {
        $this->authorize('update',$bem);
        $empresabens = Empresabem::findOrFail($id);     
        $this->authorize('igualdade',$empresabens);
        if ($empresabens->tipodepreciacao === '2') {
            $empresabens->depreciacaodias = $empresabens->depreciacaodias / 365;
        } else if ($empresabens->tipodepreciacao === '1') {
            $empresabens->depreciacaodias = $empresabens->depreciacaodias / 30;
        }
        $empresabens->valororiginal = requestNumeroDecimalOracle($empresabens->valororiginal);
        $empresabens->valor = requestNumeroDecimalOracle($empresabens->valor);
        $empresabens->depreciacaoporcentagem = requestPercentualOracle($empresabens->depreciacaoporcentagem);
        $empresabens->depreciacaovalor = requestNumeroDecimalOracle($empresabens->depreciacaovalor);
        $empresabens->valoratual = requestNumeroDecimalOracle($empresabens->valoratual);
        return View('empresabens.empresabens_form', compact('empresabens'));
    }

    function destroy($id, Empresabem $bem)
    {
        $this->authorize('delete',$bem);
        $empresabens = Empresabem::findOrFail($id);
        $this->authorize('igualdade',$empresabens);
        DB::beginTransaction();
        try {
            $empresabens->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
