<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Bairro;
use App\Cidade;
use DB;
use App\Estado;
use Session;

class BairroController extends Controller
{
    public function index(Bairro $bairro)
    {
        $this->authorize('view', $bairro);
        $bairro_id = request()->get("id", null);
        $empresa = Session::get('empresa_padrao');
        $grupo_id = $empresa->grupo_id;

        $estados = Estado::pluck('uf', 'uf');
        $uf_atual = isset($_GET['uf_filtro']) ? $_GET['uf_filtro'] : "";

        $cidades_filtro = Cidade::whereRaw("(grupo_id is null or grupo_id = $grupo_id) and uf = '$uf_atual'")
            ->orderBy('descricao')->select('id', 'descricao')->get()->pluck('descricao', 'id');

        $cidades = Cidade::whereRaw("(grupo_id is null or grupo_id = $grupo_id)")
            ->orderBy('descricao')->select('id', 'descricao')->get()->pluck('descricao', 'id');

        $bairros = Bairro::with('cidade')->whereRAw("grupo_id = $grupo_id");

        if (isset($_GET['cidade_id_filtro']) && !empty($_GET['cidade_id_filtro']))
            $bairros->where('cidade_id', $_GET['cidade_id_filtro']);
        else if (isset($_GET['uf_filtro']) && !empty($_GET['uf_filtro']))
            $bairros->whereIn('cidade_id', Cidade::where('uf', $_GET['uf_filtro'])->select('id')->pluck('id'));

        $descricao = strtolower(\Input::get("descricao_filtro", ""));
        if ($descricao)
            $bairros->whereRaw(rawTranslateSpecialChars("descricao") . " LIKE '%$descricao%'");

        $pars = $_GET;
        if (isset($pars['page']))
            unset($pars['page']);
        $url = 'bairro?';
        foreach ($pars as $key => $value) {
            $url .= "&$key=$value";
        }

        if (!is_null($bairro_id)) {
            $bairros = $bairros->where("id", $bairro_id);
        }

        $bairros = $bairros->paginate(30)->withPath($url);

        $uf_empresa = $empresa->uf;
        $cidade_empresa = $empresa->cidade_id;

        return view('endereco.bairro.bairro_index', compact('bairros', 'cidades_filtro', 'cidades', 'estados', 'uf_empresa', 'cidade_empresa'));
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
            'cidade_id' => 'required'
        ]);
        try {
            $data = $request->all();

            if ($this->getBairrosIguais($id, $data['cidade_id'], $data['descricao']) > 0)
                throw new \Exception("Já existe um bairro com este nome para esta cidade");

            if (is_null($id)) {
                $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                $bairro = Bairro::create($data);
            } else {
                $bairro = Bairro::find($id);

                $this->checkIfIgnored($data, $bairro);

                $bairro->update($data);
            }
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        if (isset($data['fromIndex']))
            return "OK|";
        return $bairro->id;
    }

    private function getBairrosIguais($id, $cidade_id, $descricao)
    {
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $descricao = str_encode_to_query($descricao);
        $id = is_null($id) ? -1 : $id;
        $cidades = Bairro::whereRaw("id <> $id and " . rawTranslateSpecialChars("descricao") . " = '$descricao' and cidade_id = '$cidade_id' and (grupo_id is null or grupo_id = $grupo_id)")
            ->select(DB::raw("count(id) as count"));
        return $cidades->get()->pluck('count')->first();
    }

    function buscaPorNomeECidade($nome, $cidade)
    {
        $bairro = Bairro::where([
            ['cidade_id', $cidade],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id],
            ['descricao', $nome]
        ])->select('id')->get()->first();
        return $bairro->id;
    }

    function dropdown($id, $getOptions = 0)
    {
        if ($getOptions) {
            $bairro = Bairro::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
                ->where('cidade_id', $id)
                ->orderBy('descricao')
                ->pluck('descricao', 'id')
                ->prepend('Selecione', '');

            $select = \Form::select('bairro_id', $bairro, null);
            return $select;
        } else {
            $bairro = Bairro::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('cidade_id', $id)->orderBy('descricao');
            return $bairro->select('id', 'descricao')->get();
        }
    }

    public function destroy($id)
    {
        try {
            Bairro::find($id)->delete();
        } catch (\Exception $e) {
            $errorMsg = getErrorsException($e);
            if (strpos($errorMsg, "ORA-02292") === false)
                return $errorMsg;
            return " O registro está sendo usado e não pode ser excluído";
        }
        return "OK|";
    }

    private function checkIfIgnored($data, $bairro)
    {
        if ($data["descricao"] == $bairro->descricao) return;

        DB::table("inconsistencia_ignorada ign")
            ->where("ignored_type", Bairro::class)
            ->where(function ($qry) use ($bairro) {
                $qry->where("model_id", $bairro->id)
                    ->orWhere("ignored_id", $bairro->id);
            })
            ->delete();
    }
}
