<?php

namespace App\Http\Controllers;

use Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use DB;
use Redirect;
use App\Veiculo;
use App\Veiculotipo;
use App\Device;
use App\Ultimaposicao;

class VeiculoController extends Controller
{

    protected $msgsValidacao = array(
        'descricao.required' => 'O campo Descrição é obrigatório.',
        'placa.required' => 'O campo Placa é obrigatório.',
        'placa.unique' => 'A Placa já esta em uso.',
        'descricao.required' => 'O campo Descrição é obrigatório.',
    );

    public function index()
    {
        $veiculos = Veiculo::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
        ])->get();

        return view('veiculos.veiculos', compact('veiculos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $veiculotipos = Veiculotipo::orderBy('descricao')->pluck('descricao', 'id');

        return view('veiculos.veiculo_form', compact('veiculotipos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'placa' => 'required|unique:veiculos,placa,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
            'descricao' => 'required',
        ], $this->msgsValidacao);

        $data = $request->only('grupo_id', 'empresa_id', 'veiculotipo_id', 'deviceid', 'placa', 'descricao', 'km_atual', 'ativo', 'motorista', 'veiculoerp_id');

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["unique_id"] = $data["deviceid"];
        //        dd($data);
        DB::beginTransaction();
        try {
            $veiculo = Veiculo::create($data);
            //CRIAR DEVICE
            $device = Device::where('uniqueid', $veiculo->deviceid)->first();
            if ($device == null) {
                $device = new Device();
                $device->uniqueid = $veiculo->deviceid;
                $device->name = $veiculo->placa;
                $device->description = $veiculo->descricao;
                $device->veiculo_id = $veiculo->id;
                $device->save();
            } else {
                $device->uniqueid = $veiculo->deviceid;
                $device->name = $veiculo->placa;
                $device->description = $veiculo->descricao;
                $device->veiculo_id = $veiculo->id;
                $device->save();
            }
            //CRIAR ULTIMAPOSICAOS
            $ultimaposicaos = Ultimaposicao::where('deviceid', $veiculo->deviceid)->where('veiculo_id', '<>', $veiculo->id);
            if ($ultimaposicaos != null) {
                $ultimaposicaos->delete();
            }
            $ultimaposicao = Ultimaposicao::where('veiculo_id', $veiculo->id)->first();
            if ($ultimaposicao == null) {
                $ultimaposicao = new Ultimaposicao();
                $ultimaposicao->veiculo_id = $veiculo->id;
                $ultimaposicao->deviceid = $veiculo->deviceid;
                $ultimaposicao->save();
            } else {
                $ultimaposicao->veiculo_id = $veiculo->id;
                $ultimaposicao->deviceid = $veiculo->deviceid;
                $device->save();
            }
        } catch (ValidationException $e) {
            DB::rollback();
            return Redirect::to('/veiculo/create')
                ->withErrors($e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/veiculo/create')
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('veiculo.index')->withMessageSuccess('Veículo cadastrado com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $veiculo = Veiculo::find($id);
        $veiculotipos = Veiculotipo::orderBy('descricao')->pluck('descricao', 'id');
        $show = true;
        return view('veiculos.veiculo_form', compact('veiculo', 'show', 'veiculotipos'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $veiculo = Veiculo::find($id);
        $veiculotipos = Veiculotipo::orderBy('descricao')->pluck('descricao', 'id');

        return view('veiculos.veiculo_form', compact('veiculo', 'veiculotipos'));
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
        $this->validate($request, [
            'placa' => 'required|unique:veiculos,placa,' . $id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
            'descricao' => 'required',
            'deviceid' => 'required',
        ], $this->msgsValidacao);

        $data = $request->only('grupo_id', 'empresa_id', 'veiculotipo_id', 'deviceid', 'placa', 'descricao', 'motorista', 'km_atual', 'ativo', 'veiculoerp_id');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["unique_id"] = $data["deviceid"];
        if ($data["ativo"] == null) {
            $data["ativo"] = false;
        }
        //        dd($data);
        $veiculo = Veiculo::findOrFail($id);
        DB::beginTransaction();
        try {
            $veiculo->update($data);
            //CRIAR DEVICE
            $devices = Device::where('veiculo_id', $veiculo->id)->where('uniqueid', '<>', $veiculo->deviceid);
            if ($devices != null) {
                $devices->delete();
            }
            $device = Device::where('uniqueid', $veiculo->deviceid)->first();
            if ($device == null) {
                $device = new Device();
                $device->uniqueid = $veiculo->deviceid;
                $device->name = $veiculo->placa;
                $device->description = $veiculo->descricao;
                $device->veiculo_id = $veiculo->id;
                $device->save();
            } else {
                $device->uniqueid = $veiculo->deviceid;
                $device->name = $veiculo->placa;
                $device->description = $veiculo->descricao;
                $device->veiculo_id = $veiculo->id;
                $device->save();
            }
            //CRIAR ULTIMAPOSICAOS
            $ultimaposicaos = Ultimaposicao::where('deviceid', $veiculo->deviceid)->where('veiculo_id', '<>', $veiculo->id);
            if ($ultimaposicaos != null) {
                $ultimaposicaos->delete();
            }
            $ultimaposicao = Ultimaposicao::where('veiculo_id', $veiculo->id)->first();

            if ($ultimaposicao == null) {
                $ultimaposicao = new Ultimaposicao();
                $ultimaposicao->grupo_id = Session::get('empresa_padrao')->grupo_id;
                $ultimaposicao->empresa_id = Session::get('empresa_padrao')->id;
                $ultimaposicao->veiculo_id = $veiculo->id;
                $ultimaposicao->deviceid = $veiculo->deviceid;
                $ultimaposicao->save();
            } else {
                $ultimaposicao->veiculo_id = $veiculo->id;
                $ultimaposicao->deviceid = $veiculo->deviceid;
                $ultimaposicao->save();
            }
        } catch (ValidationException $e) {
            DB::rollback();
            return Redirect::to('/veiculo/' . $id . '/edit')
                ->withErrors($e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/veiculo/' . $id . '/edit')
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('veiculo.index')->withMessageSuccess('Veículo atualizado com sucesso!');
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
            Veiculo::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    //Manutenção de veiculos Ajax --> Lucas
    public function buscarVeiculoAjax($id)
    {
        return Veiculo::join('colaboradors', 'colaboradors.id', '=', 'veiculos.colaborador_id')
            ->where([
                ['veiculos.empresa_id', Session::get('empresa_padrao')->id],
                ['veiculos.ativo', 1],
                ['veiculos.id', $id]
            ])
            ->select([
                'colaboradors.id as colaborador_id',
                'colaboradors.nome',
                'veiculos.kmatual',
                'veiculos.kmultimatrocaoleo',
                'veiculos.kmtrocaoleo',
            ])
            ->get();
    }
}
