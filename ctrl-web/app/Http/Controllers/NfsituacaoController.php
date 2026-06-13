<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Input;
use Session;
use DB;
use App\Nfsituacao;
use Exception;

class NfsituacaoController extends Controller
{

    private $msgErrors = [
        'codigo.unique' => "O Código já está sendo usado por outra situação!"
    ];

    public function index(Nfsituacao $sit)
    {
        $this->authorize('view', $sit);
        $codigo = Input::get('codigo_s', false);
        $msgerroreceita_s = replaceAccents(str_encode_to_query(e(Input::get('msgerroreceita_s', false))));

        $pars = Input::all();

        if (isset($pars['page']))
            unset($pars['page']);

        $url = 'nfsituacao?';
        foreach ($pars as $key => $value) {
            $url .= $key . "=" . $value . "&";
        }

        $url = substr($url, 0, strlen($url) - 1);

        if ($codigo) {
            $nfsituacaos = Nfsituacao::where('id', $codigo);
        }

        if ($msgerroreceita_s) {
            $raw = rawTranslateSpecialChars("msgerroreceita") . " LIKE '%$msgerroreceita_s%'";
            if (!isset($nfsituacaos))
                $nfsituacaos = Nfsituacao::whereRaw($raw);
            else
                $nfsituacaos->whereRaw($raw);
        }
        if (!isset($nfsituacaos))
            $nfsituacaos = Nfsituacao::paginate(10)->withPath($url);
        else
            $nfsituacaos = $nfsituacaos->paginate(10)->withPath($url);

        return view('nf.nfsituacao.index', compact('nfsituacaos'));
    }

    public function store(Request $request, Nfsituacao $sit)
    {
        $this->authorize('create', $sit);
        $validation = [
            'msgerroreceita' => 'required|max:255',
            'codigo'         => 'required|unique:nfsituacaos,id,null,id'
        ];
        $this->validate($request, $validation, $this->msgErrors);
        try {

            DB::beginTransaction();
            $data = $request->only('msgerroreceita', 'codigo');
            $sit = new Nfsituacao();
            $sit->id = $data['codigo'];
            $sit->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $sit->empresa_id = Session::get('empresa_padrao')->id;
            $sit->msgerroreceita = $data['msgerroreceita'];
            $sit->save();

            DB::commit();
            return 'OK|' . $sit->id;
        } catch (Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
    }

    public function update(Request $request, $id, Nfsituacao $sit)
    {
        $this->authorize('update', $sit);
        $validation = [
            'msgerroreceita' => 'required|max:255'
        ];
        $this->validate($request, $validation, $this->msgErrors);
        try {
            DB::beginTransaction();
            $sit = Nfsituacao::find($id);
            $data = $request->only('msgerroreceita', 'codigo');
            $sit->update($data);

            DB::commit();
            return 'OK|' . $sit->id;
        } catch (Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
    }
}
