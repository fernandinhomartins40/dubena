<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Veiculo;
use App\Veiculopneu;
use Illuminate\Http\Request;

class VeiculopneuController extends Controller
{
    protected $pneu;
    protected $veiculo;
    protected $empresa_id;

    function index(Veiculopneu $pn){
        $this->authorize('view',$pn);
        $this->definition();
        $veiculopneu = $pn->join('veiculos','veiculopneus.veiculo_id','veiculos.id')
                        ->where('veiculopneus.empresa_id',$this->empresa_id)
                        ->select('veiculopneus.id',DB::raw("to_char(veiculopneus.data,'yyyy-mm-dd') data"),
                            'veiculos.placa','veiculopneus.km','veiculopneus.valor','veiculopneus.quantidade',
                            'veiculopneus.medidapneus','veiculopneus.vidautilkm','veiculopneus.kmalertaantes')
                        ->get();


        return view('pneu.pneu')->with(compact('veiculopneu'));
    }

    function create(Veiculopneu $pn){
        $this->authorize('create',$pn);
        $this->definition();
        $veiculo = Veiculo::where(['empresa_id'=>$this->empresa_id,'ativo'=>1])->orderBy('placa')->pluck('placa', 'id')->prepend('Selecione','');

        return view('pneu.pneu_form')->with(compact('veiculo'));
    }

    function store(Request $request, Veiculopneu $pn){
        $this->authorize('create',$pn);
        $this->definition();

        $this->validate($request,['data' => 'required','kmatualpneu' => 'required|numeric',
            'valor' => 'required','medidapneus' => 'required', 'vidautilkm' => 'required',
            'quantidadepneu' => 'required|numeric']);
        $alerta = $request->get('alertaantespneu');
        if($alerta == 1){
            $this->validate($request,['kmalertaantespneu' => 'required|numeric']);
        }
        $data = $request->all();
        $extras = $this->dadosExtras($data);
        $data = array_merge($data,$extras);

        DB::begintransaction();
        try{
            Veiculopneu::create($data);
        } catch (Exception $ex) {
            DB::rollback();
            return Redirect::to('veiculopneu.create')
                        ->withErrors($ex->getMessage())
                        ->withInput();
        }
        DB::commit();
        return \Redirect::to('veiculopneu')->withMessageSuccess("Troca de Pneu cadastrada com sucesso!");
    }

    public function show($id, Veiculopneu $pn){
        $this->authorize('view',$pn);
        $veiculopneu = Veiculopneu::find($id);
        $this->authorize('igualdade',$veiculopneu);
        $this->definition();
        $veiculo = ['placa' => $veiculopneu->veiculo->placa, 'id' => $veiculopneu->veiculo->id];
        $show = true;
        $veiculopneu->valor = requestNumeroDecimalOracle($veiculopneu->valor);
        $veiculopneu->data = requestDataOracleSemHora($veiculopneu->data);
        return view('pneu.pneu_form')->with(compact('veiculopneu','veiculo','show'));
    }

    public function destroy($id, Veiculopneu $pn){
        $this->authorize('delete',$pn);
        $veiculopneu = Veiculopneu::find($id);
        $this->authorize('igualdade',$veiculopneu);
        DB::beginTransaction();
        try {
            $veiculopneu->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function dadosExtras($data)
    {
        $data["data"] = insertDataOracle($data["data"]);
        $data["valor"] = insertNumeroDecimalOracle($data["valor"]);
        $data["km"] = $data["kmatualpneu"];
        $data["quantidade"] = $data["quantidadepneu"];
        $data["kmalertaantes"] = $data["kmalertaantespneu"];
        $data["alertaantes"] = isset($data["alertaantespneu"]) ? $data["alertaantespneu"] : 0;
        $data["kmalertaantes"] = $data["kmalertaantespneu"];
        $data["empresa_id"] = $this->empresa_id;
        return $data;
    }

    private function definition(){
        $this->empresa_id = Session::get('empresa_padrao')->id;
    }
}
