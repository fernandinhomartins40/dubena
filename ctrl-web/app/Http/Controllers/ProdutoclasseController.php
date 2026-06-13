<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Produto;
use App\Http\Requests;
use App\Produtoclasse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProdutoclasseController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Produtoclasse $class)
    {
        $this->authorize('view',$class);
        $produtoclasses = Produtoclasse::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        $tipos = self::tipoClasse();
        return view('produtos.produtoclasses', compact('produtoclasses','tipos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Produtoclasse $class)
    {
        $this->authorize('create',$class);
        $this->validate($request, [
            'descricao' => 'required|unique:produtoclasses,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'tipo' => 'required'
            ]);
        $data = $request->only('descricao', 'ativo', 'tipo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $produtoclasse = Produtoclasse::create($data);
        return 'OK|' . $produtoclasse->id;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Produtoclasse $class)
    {
        $this->authorize('update',$class);
        $produtoclasse = Produtoclasse::findOrFail($id);
        $this->authorize('igualdade',$produtoclasse);
        $this->validate($request, [
            'descricao' => 'required|unique:produtoclasses,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'tipo' => 'required'
            ]);
        $data = $request->only('descricao', 'ativo', 'tipo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $produtoclasse->update($data);
        return 'OK|' . $produtoclasse->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Produtoclasse $class)
    {
        $this->authorize('delete',$class);
        $produtoclasse = Produtoclasse::find($id);
        $this->authorize('igualdade',$produtoclasse);
        DB::beginTransaction();
        try {
            $produtoclasse->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';

    }

    public static function tipoClasse()
    {
        return [
            'B' => 'Brinde',
            'G' => 'GLP',
            'V' => 'Vasilha',
            'R' => 'Ressarcimento',
            'O' => 'Outros',
        ];
    }

}
