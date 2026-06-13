<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Rua;
use Session;
use DB;
use App\Estado;
use App\Cidade;

class RuaController extends Controller
{
    public function index(Rua $rua)
    {
        $this->authorize('view', $rua);
        $empresa = Session::get('empresa_padrao');
        $rua_id = request()->get("id", null);
        $grupo_id = $empresa->grupo_id;
        $uf_empresa = $empresa->uf;
        $cidade_empresa = $empresa->cidade_id;

        $estados = Estado::pluck('uf', 'uf');
        $uf_atual = isset($_GET['uf_filtro']) ? $_GET['uf_filtro'] : "";

        $cidades_filtro = Cidade::whereRaw("(grupo_id is null or grupo_id = $grupo_id) and uf = '$uf_atual'")
            ->orderBy('descricao')->select('id', 'descricao')->get()->pluck('descricao', 'id');

        $cidades = Cidade::whereRaw("(grupo_id is null or grupo_id = $grupo_id) and uf = '$uf_empresa'")
            ->orderBy('descricao')->select('id', 'descricao')->get()->pluck('descricao', 'id');

        $ruas = Rua::with('cidade')->whereRAw("grupo_id = $grupo_id");

        if (isset($_GET['cidade_id_filtro']) && !empty($_GET['cidade_id_filtro']))
            $ruas->where('cidade_id', $_GET['cidade_id_filtro']);
        else if (isset($_GET['uf_filtro']) && !empty($_GET['uf_filtro']))
            $ruas->whereIn('cidade_id', Cidade::where('uf', $_GET['uf_filtro'])->select('id')->pluck('id'));

        $descricao = strtolower(\Input::get("descricao_filtro", ""));
        if ($descricao)
            $ruas->whereRaw(rawTranslateSpecialChars("descricao") . " LIKE '%$descricao%'");

        $pars = $_GET;
        if (isset($pars['page']))
            unset($pars['page']);
        $url = 'rua?';
        foreach ($pars as $key => $value) {
            $url .= "&$key=$value";
        }

        if (!is_null($rua_id)) {
            $ruas = $ruas->where("id", $rua_id);
        }

        $ruas = $ruas->paginate(30)->withPath($url);

        return view('endereco.rua.rua_index', compact('ruas', 'cidades_filtro', 'cidades', 'estados', 'uf_empresa', 'cidade_empresa'));
    }


    public function store(Request $request)
    {
        return $this->insertUpdate($request);
    }

    public function update($id, Request $request)
    {
        return $this->insertUpdate($request, $id);
    }

    public function dropdown($id)
    {
        $rua = Rua::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('cidade_id', $id)->orderBy('descricao')->select('id', 'descricao')->get();
        return $rua;
    }

    public function destroy($id)
    {
        try {
            Rua::find($id)->delete();
        } catch (\Exception $e) {
            $errorMsg = getErrorsException($e);
            if (strpos($errorMsg, "ORA-02292") === false)
                return $errorMsg;
            return " O registro está sendo usado e não pode ser excluído";
        }
        return "OK|";
    }

    public function createRuaFromOther($data)
    {
        if (!isset($data["rua_descricao"]))
            throw new \Exception("Error: Falta a descrição da rua");
        if (!isset($data["cidade_id"]))
            throw new \Exception("Error: Falta o ID da cidade");

        $rua_data = [
            "descricao"         => $data["rua_descricao"],
            "cidade_id"         => $data["cidade_id"],
            "grupo_id"          => Session::get('empresa_padrao')->grupo_id,
            "empresa_id"        => Session::get('empresa_padrao')->id,
            "nfecompl"          => "Rua",
            "ativo"             => "1",
            "importacaocep_id"  => -1
        ];

        $existe = $this->getRuasIguais(null, $rua_data["cidade_id"], $rua_data["descricao"]);

        if ($existe > 0) throw new \Exception("Já existe uma rua com este nome para esta cidade");

        $rua = Rua::create($rua_data);

        return $rua;
    }

    private function insertUpdate($request, $id = null)
    {
        $this->validate($request, [
            'descricao' => 'required',
            'cidade_id' => 'required'
        ]);
        try {
            $data = $request->all();
            if ($this->getRuasIguais($id, $data['cidade_id'], $data['descricao']) > 0)
                throw new \Exception("Já existe uma rua com este nome para esta cidade");
            $data['importacaocep_id'] = -1;
            if (is_null($id)) {
                $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                $data['empresa_id'] = Session::get('empresa_padrao')->id;
                $data['nfecompl'] = 'Rua';

                //$data['ativo'] = '1';
                $rua = Rua::create($data);
            } else {
                $rua = Rua::find($id);

                $this->checkIfIgnored($data, $rua);

                $rua->update($data);
            }
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        if (isset($data['fromIndex']))
            return "OK|";
        return $rua->id;
    }

    private function getRuasIguais($id, $cidade_id, $descricao)
    {
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $descricao = str_encode_to_query($descricao);
        $id = is_null($id) ? -1 : $id;
        $cidades = Rua::whereRaw("id <> $id and " . rawTranslateSpecialChars("descricao") . " = '$descricao' and cidade_id = '$cidade_id' and (grupo_id is null or grupo_id = $grupo_id)")
            ->select(DB::raw("count(id) as count"));
        return $cidades->get()->pluck('count')->first();
    }

    private function checkIfIgnored($data, $rua)
    {
        if ($data["descricao"] == $rua->descricao) return;

        DB::table("inconsistencia_ignorada ign")
            ->where("ignored_type", Rua::class)
            ->where(function ($qry) use ($rua) {
                $qry->where("model_id", $rua->id)
                    ->orWhere("ignored_id", $rua->id);
            })
            ->delete();
    }
}
