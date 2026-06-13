<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Estado;
use App\Cidade;
use Session;
use DB;

class CidadeController extends Controller
{

    public function index(Cidade $cidade)
    {
        $this->authorize('view',$cidade);
        $cidades = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id);
        
        if (isset($_GET['uf_filtro']) && !empty($_GET['uf_filtro']))
            $cidades->where('uf', $_GET['uf_filtro']);

        $descricao = strtolower(\Input::get("descricao_filtro", ""));
        if ($descricao)
            $cidades->whereRaw(rawTranslateSpecialChars("descricao") . " LIKE '%$descricao%'");

        $pars = $_GET;
        if (isset($pars['page']))
            unset($pars['page']);
        $url = 'cidade?';
        foreach ($pars as $key => $value) {
            $url .= "&$key=$value";
        }
        $cidades = $cidades->paginate(30)->withPath($url);

        $estados = Estado::pluck('uf', 'uf');
        $uf_empresa = Session::get('empresa_padrao')->uf;
        
        return view('endereco.cidade.cidade_index', compact('cidades', 'uf_empresa', 'estados'));   
    }

    public function store(Request $request)
    {
        return $this->insertUpdate($request);
    }

    public function update($id, Request $request)
    {
        return $this->insertUpdate($request, $id);
    }

    private function insertUpdate($request, $id = null)
    {   
        $this->validate($request, [
            'descricao' => 'required',
            'uf' => 'required',
            'cod_ibge' => 'required|numeric',
        ]);

        try {
            $data = $request->all();

            if($this->getCidadesIguais($id, $data['uf'], $data['descricao']) > 0)
                throw new \Exception("Já existe uma cidade com este nome para este estado");
            if(is_null($id)){  
                $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                $cidade = Cidade::create($data);
            } else {
                $cidade = Cidade::find($id);
                $cidade->update($data);;
            }
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        if(isset($data['fromIndex']))
            return "OK|";
        return $cidade->id;
    }

    private function getCidadesIguais($id, $uf, $descricao)
    {
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $descricao = str_encode_to_query($descricao);
        $id = is_null($id) ? -1 : $id;
        $cidades = Cidade::whereRaw("id <> $id and  " . rawTranslateSpecialChars("descricao") . " "
                . " = '$descricao' and uf = '$uf' and (grupo_id is null or grupo_id = $grupo_id)")
                        ->select(DB::raw("count(id) as count"));
        return $cidades->get()->pluck('count')->first();
    }

    function buscaPorNomeEEstado($nome, $estado)
    {
        return DB::table('cidades')->where([
                    ['uf', $estado],
                    ['grupo_id', Session::get('empresa_padrao')->grupo_id],
                    ['descricao', $nome]
                ])->orWhere([
                    ['uf', $estado],
                    ['grupo_id', null],
                    ['descricao', $nome]
                ])->select('id', 'descricao')->get();
    }

    function dropdown($uf)
    {

        $cidade = DB::table('cidades')->where([
                    ['uf', $uf],
                    ['grupo_id', Session::get('empresa_padrao')->grupo_id],
        ])->orWhere([
                    ['uf', $uf],
                    ['grupo_id', null],
        ])
        ->select('id', 'descricao')
        ->pluck('descricao', 'id')
        ->prepend('Selecione', '');

        $select = \Form::select('cidade_id', $cidade, null);
        // $select = str_replace('<select name="cidade_id">', '');
        // $select = str_replace('</select>', '');
        return $select;
    }

    public function destroy($id)
    {
        try {
            Cidade::find($id)->delete();
        } catch (\Exception $e) {
            $errorMsg = getErrorsException($e);
            if(strpos($errorMsg, "ORA-02292") === false)
                return $errorMsg;
            return " O registro está sendo usado e não pode ser excluído";
        }
        return "OK|";
    }
}
