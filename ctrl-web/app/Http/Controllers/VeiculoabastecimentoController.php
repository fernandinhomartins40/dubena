<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Veiculo;
use App\Colaborador;
use Illuminate\Http\Request;
use App\Veiculoabastecimento;

class VeiculoabastecimentoController extends Controller {

    protected $empresa_id;
    protected $abastecimento;
    protected $buscarveiculo;
    protected $colaborador;
    protected $veiculo;
    protected $empresa_config;

    public function index(Veiculoabastecimento $aba) {
        $this->authorize('view',$aba);
        $abastecimento = Veiculoabastecimento::join('veiculos','veiculoabastecimentos.veiculo_id','veiculos.id')
                                    ->join('colaboradors','veiculoabastecimentos.colaborador_id','colaboradors.id')
                                    ->where('veiculoabastecimentos.empresa_id',Session::get('empresa_padrao')->id)
                                    ->select('veiculoabastecimentos.id','veiculos.placa','colaboradors.nome',
                                        'veiculoabastecimentos.kmanterior','veiculoabastecimentos.kmatual',
                                        'veiculoabastecimentos.kmrodado','veiculoabastecimentos.totallitros',
                                        'veiculoabastecimentos.mediaconsumo',
                                        DB::raw("to_char(veiculoabastecimentos.data, 'dd/mm/yyyy') as data"))
                                    ->orderBY('id')->get();
        return view('abastecimento.abastecimento')->with(compact('abastecimento'));
    }

    public function create(Veiculoabastecimento $aba) {
        $this->authorize('create',$aba);
        $this->definition();
        $veiculo = $this->veiculo;
        $colaborador = $this->colaborador;

        return view('abastecimento.abastecimento_form')->with(compact('veiculo', 'colaborador'));
    }

    public function store(Request $request, Veiculoabastecimento $aba) {
        $this->authorize('create',$aba);
        $this->definition();
        $id_veiculo = $request->veiculo_id;
        $id_colaborador = $request->colaborador_id;
        $this->validate($request, [
            'data' => 'required',
            'kmatual' => 'required',
            'totallitros' => 'required'
        ]);
        $data = $request->all();
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);
        DB::begintransaction();
        try {
            Veiculoabastecimento::create($data);
            if(isset($data["telacontrolakm"])){
                $this->updateOthers($id_veiculo, $id_colaborador,$data);
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return Redirect::to('/veiculoabastecimento/create')
                            ->withErrors($ex->getMessage())
                            ->withInput();
        }
        DB::commit();
        return Redirect::to('veiculoabastecimento')->withMessageSuccess('Abastecimento salvo com sucesso!');
    }

    public function update() {
        //
    }

    public function updateOthers($veiculo_id, $colaborador_id,$data) {
        $this->definition();
        $request = request();
        DB::table('veiculos')->where('veiculos.id', $veiculo_id)
                ->update(array('veiculos.colaborador_id' => $colaborador_id, 'kmatual' => $data["kmatual"]));
    }

    public function show($id, Veiculoabastecimento $aba) {
        $this->authorize('view',$aba);
        $abastecimento = Veiculoabastecimento::find($id);
        $this->authorize('igualdade',$abastecimento);
        $this->definition();
        $veiculo = ['placa' => $abastecimento->veiculo->placa, 'id' => $abastecimento->veiculo_id];
        $colaborador = ['nome' => $abastecimento->colaborador->nome, 'id' => $abastecimento->colaborador_id];
        $show = true;
        
        $abastecimento->data = requestDataOracle($abastecimento->data);
        return view('abastecimento.abastecimento_form')->with(compact('abastecimento', 'veiculo', 'colaborador', 'show'));
    }

    private function dadosExtras($data) {
        $data['empresa_id'] = $this->empresa_id;
        $data["data"] = insertDataOracle($data['data']);
        $data["totallitros"] = converterLitrosBanco($data["totallitros"]);
        $data["mediaconsumo"] = converterLitrosBanco($data["mediaconsumo_hd"]);
        $data["kmanterior"] = $data["kmanterior_hd"];
        $data["kmrodado"] = $data["kmrodado_hd"];
        if($this->empresa_config->telacontrolakm == "0"){
            $data["telacontrolakm"] = true;
        }
        return $data;
    }

    public function destroy($id, Veiculoabastecimento $aba) {
        $this->authorize('delete',$aba);
        $abastecimento = Veiculoabastecimento::find($id);
        $this->authorize('igualdade',$abastecimento);
        DB::beginTransaction();
        try {
            if($abastecimento->telacontrolakm == "1"){
                $veiculo_id = $abastecimento->veiculo_id;
                $veiculo = Veiculo::find($veiculo_id);
                $veiculokm = $veiculo->kmatual;
                $kmultima = $veiculokm - $abastecimento->kmrodado;
                DB::table('veiculos')->where('id',$veiculo_id)
                    ->update(['kmatual'=>$kmultima]);
            }
            $abastecimento->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    public function showAbastecimentoAjax($id) {
        return Veiculoabastecimento::find($id);
    }

    private function definition()
    {
        $this->abastecimento = new Veiculoabastecimento();
        //busca id da empresa padrao
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //pega a config da empresa
        $this->empresa_config = Session::get('empresa_config');
        //busca colaborades
        $this->colaborador = Colaborador::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->pluck('nome','id');
        //busca veiculos
        $this->veiculo = Veiculo::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->pluck('placa', 'id');
    }
}