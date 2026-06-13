<?php

namespace App\Http\controllers;

//use Request;
use DB;
use Image;
use Session;
use App\Cerca;
use App\Setor;
use App\Cercapoligono;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class Cercacontroller extends controller
{

    protected $msgsValidacao = array(
        'descricao.required' => 'O campo descrição é obrigatório.',
    );

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $Cercas = Cerca::with('setor')->where('empresa_id', $empresa_id)->get();
        return view('cercas.cercas', compact('Cercas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $latlng = Array();
        $latlng["latitude"] = Session::get('empresa_padrao')->latitude;
        $latlng["longitude"] = Session::get('empresa_padrao')->longitude;
        $keygooglemaps=Session::get('empresa_padrao')->keygooglemaps;
        if($keygooglemaps==null){
            $keygooglemaps=Session::get('config')->keygooglemaps;
        }
        $setors = Setor::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao')->get();
        $cercas = Cerca::where('empresa_id', Session::get('empresa_padrao')->id)
                       ->orderBy('descricao')
                       ->pluck('descricao', 'id')
                       ->prepend('nenhuma', '-1');
        if(count($setors)>0){
            $setor = $setors->first();
            $latlng["latitude"] = $setor->latitude;
            $latlng["longitude"] = $setor->longitude;
        }
        $setors = $setors->pluck('descricao', 'id');
        return view('cercas.cerca_form', compact('setors', 'latlng', 'cercas', 'keygooglemaps'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'descricao' => 'required|unique:cercas,descricao,null,id,ativo,1,empresa_id,' . Session::get('empresa_padrao')->id,
        ];
        $this->validate($request, $rules, $this->msgsValidacao);
        $data = $request->only('descricao', 'ativo', 'cor', 'setor_id');
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        if (!isset($data['ativo'])) {
            $data['ativo'] = '0';
        }

        DB::beginTransaction();
        try {
            $cerca = Cerca::create($data);
            $coordenadas = json_decode($request->only('poligono')['poligono']);
            $coords = [];
            foreach ($coordenadas as $coordenada) {
                $coord = new Cercapoligono();
                $coord["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                $coord["empresa_id"] = Session::get('empresa_padrao')->id;
                $coord["latitude"] = $coordenada[0];
                $coord["longitude"] = $coordenada[1];
                array_push($coords, $coord);
            }
            $cerca->coordenadas()->delete();
            $cerca->coordenadas()->saveMany($coords);
        } catch (ValidationException $e) {
            DB::rollback();
            return \Redirect::back()->withErrors($e->getMessage())
                            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::back()->withErrors($e->getMessage())->withInput();
        }
        DB::commit();
        return \Redirect::route('cerca.index')->withMessageSuccess('Cerca cadastrada com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $Cerca = Cerca::find($id);
        $setors = Setor::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao')->pluck('descricao', 'id');
        $cercas = Cerca::where('empresa_id', Session::get('empresa_padrao')->id)
                       ->where('id', '<>', $id)
                       ->orderBy('descricao')
                       ->pluck('descricao', 'id')
                       ->prepend('nenhuma', '-1');
        $show = true;
        $latlng = Array();
        if ($Cerca->setor_id) {
            $latlng["latitude"] = $Cerca->setor->latitude;
            $latlng["longitude"] = $Cerca->setor->longitude;
        } else {
            $latlng["latitude"] = "";
            $latlng["longitude"] = "";
        }
        $keygooglemaps=Session::get('empresa_padrao')->keygooglemaps;
        if($keygooglemaps==null){
            $keygooglemaps=Session::get('config')->keygooglemaps;
        }
        return view('cercas.cerca_form', compact('Cerca', 'show', 'setors', 'latlng', 'cercas', 'keygooglemaps'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $Cerca = Cerca::find($id);
        $setors = Setor::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao')->pluck('descricao', 'id');
        $cercas = Cerca::where('empresa_id', Session::get('empresa_padrao')->id)
                       ->where('id', '<>', $id)
                       ->orderBy('descricao')
                       ->pluck('descricao', 'id')
                       ->prepend('nenhuma', '-1');
        $empresa_id = $Cerca->empresa_id;
        $latlng = Array();
        if ($Cerca->setor_id) {
            $latlng["latitude"] = $Cerca->setor->latitude;
            $latlng["longitude"] = $Cerca->setor->longitude;
        } else {
            $latlng["latitude"] = "";
            $latlng["longitude"] = "";
        }
        $keygooglemaps=Session::get('empresa_padrao')->keygooglemaps;
        if($keygooglemaps==null){
            $keygooglemaps=Session::get('config')->keygooglemaps;
        }
        return view('cercas.cerca_form', compact('Cerca', 'setors', 'latlng', 'cercas', 'keygooglemaps'));
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
        $rules = [
            'descricao' => 'required',
        ];
        $this->validate($request, $rules, $this->msgsValidacao);
        $data = $request->only('descricao', 'ativo', 'cor', 'setor_id');
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        if (!isset($data['ativo'])) {
            $data['ativo'] = '0';
        }
        DB::beginTransaction();
        try {
            $cerca = Cerca::findOrFail($id);
            $cerca->update($data);
            $coordenadas = json_decode($request->only('poligono')['poligono']);
            $coords = [];
            foreach ($coordenadas as $coordenada) {
                $coord = new Cercapoligono();
                $coord["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                $coord["empresa_id"] = Session::get('empresa_padrao')->id;
                $coord["latitude"] = $coordenada[0];
                $coord["longitude"] = $coordenada[1];
                array_push($coords, $coord);
            }
            $cerca->coordenadas()->delete();
            $cerca->coordenadas()->saveMany($coords);
        } catch (ValidationException $e) {
            DB::rollback();
            return \Redirect::back()->withErrors($e->getMessage())
                            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::back()->withErrors($e->getMessage())->withInput();
        }
        DB::commit();
        return \Redirect::route('cerca.index')->withMessageSuccess('Cerca atualizada com sucesso!');        
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
            $Cerca = Cerca::findOrFail($id);
            $Cerca->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
