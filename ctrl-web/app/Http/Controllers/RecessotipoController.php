<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\EmpresasGrupo;
use App\Empresa;
use App\Recessotipo;
use App\Http\Controllers\Controller;
use App\Menu;
use Session;
use Illuminate\Support\Facades\Input;
use DB;

class RecessotipoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
      $recessotipos = Recessotipo::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->get();

      //dd(Menu::menus());
      return view('general.recessotipos',compact('recessotipos'));
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
    public function store(Request $request)
    {
		$data = $request->only('descricao', 'ativo', 'cor', 'legenda');
		$data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
		$recessotipo = Recessotipo::create($data);
		return 'OK|' . $recessotipo->id;
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
    public function update(Request $request, $id)
    {
		$data = $request->only('descricao', 'ativo', 'cor', 'legenda');
		$data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
		$recessotipo = Recessotipo::findOrFail($id);
		$recessotipo->update($data);
		return 'OK|' . $recessotipo->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
		DB::beginTransaction();
		try {
		$recessotipo = Recessotipo::findOrFail($id);
		$recessotipo->delete();
		} catch (\Exception $e) {
		DB::rollback();
		return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
		}
		DB::commit();
		return 'OK|';
    }
}
