<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use Exception;
use App\Veiculo;
use App\Colaborador;
use App\Veiculotrocaoleo;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;

class VeiculotrocaoleoController extends Controller {

    protected $empresa_id;
    protected $repository;

    function __construct(SelectRepository $repository) {
        $this->repository = $repository;
    }

    public function index(Veiculotrocaoleo $troca) {
        $this->authorize('view',$troca);
        $this->definition();
        $veiculotrocaoleo = Veiculotrocaoleo::join('veiculos','veiculotrocaoleos.veiculo_id','veiculos.id')
                ->join('colaboradors as col','veiculotrocaoleos.colaborador_id','col.id')
                ->where('veiculotrocaoleos.empresa_id', $this->empresa_id)
                ->select('veiculotrocaoleos.id',DB::raw("to_char(veiculotrocaoleos.data,'dd/mm/yyyy') data"),
                    'veiculos.placa','col.nome',
                    'veiculotrocaoleos.kmultimatrocaoleo','veiculotrocaoleos.kmtrocaoleo',
                    'veiculotrocaoleos.oleoproximatroca','veiculotrocaoleos.oleorendimento',
                    'veiculotrocaoleos.kmalertaantes')
                ->get();

        return view('trocaoleo.trocaoleo')->with(compact('veiculotrocaoleo'));
    }

    public function create(Veiculotrocaoleo $troca) {
        $this->authorize('create',$troca);
        $this->definition();
        $veiculo = $this->repository->getVeiculos()->prepend('Selecione','');
        $colaborador = $this->repository->getColaborador()->prepend('Selecione','');

        return view('trocaoleo.trocaoleo_form')->with(compact('veiculo', 'colaborador'));
    }

    public function store(Request $request, Veiculotrocaoleo $troca) {
        $this->authorize('create',$troca);
        $this->definition();
        $data = $request->all();
        $this->validate($request, ['data' => 'required', 'kmtrocaoleo' => 'required|numeric',
            'oleoproximotroca' => 'required|numeric', 'oleorendimento' => 'required|numeric']);
        $dadosextras = $this->dadosextras($data);
        $data = array_merge($data, $dadosextras);
        DB::begintransaction();
        try {
            $oleo = Veiculotrocaoleo::create($data);
            $updated = $this->updateVeiculo($oleo);
            if (!is_bool($updated)) {
                throw new Exception($updated);
            }
        } catch (Exception $ex) {
            DB::rollback();
            return Redirect::to('veiculotrocaoleo/create')
                            ->withErrors($ex->getMessage())
                            ->withInput();
        }
        DB::commit();
        return \Redirect::to('veiculotrocaoleo')->withMessageSuccess('Sucesso! Nova Troca de óleo cadastrado');
    }

    public function show($id, Veiculotrocaoleo $troca) {
        $this->authorize('view',$troca);
        $veiculotrocaoleo = Veiculotrocaoleo::find($id);
        $this->authorize('igualdade',$veiculotrocaoleo);
        $this->definition();
        $veiculo = ['placa' => $veiculotrocaoleo->veiculo->placa, 'id' => $veiculotrocaoleo->veiculo_id];
        $colaborador = ['nome' => $veiculotrocaoleo->colaborador->nome, 'id' => $veiculotrocaoleo->colaborador_id];
        $show = true;
        $veiculotrocaoleo->data = requestDataOracleSemHora($veiculotrocaoleo->data);
        return view('trocaoleo.trocaoleo_form')->with(compact('veiculotrocaoleo', 'veiculo', 'colaborador', 'show'));
    }

    private function dadosextras($data) {
        $data["oleoproximatroca"] = $data["oleoproximotroca"];
        $data["data"] = insertDataOracle($data["data"]);
        $data["alertaantes"] = isset($data["alertaantesoleo"]) && $data["alertaantesoleo"] == 1;
        $data["kmalertaantes"] = isset($data["kmalertaantesoleo"]) ? $data["kmalertaantesoleo"] : null;
        $data["empresa_id"] = $this->empresa_id;
        return $data;
    }

    public function edit($id){
        //
    }

    public function destroy($id, Veiculotrocaoleo $troca) {
        $this->authorize('delete',$troca);
        $oleo = Veiculotrocaoleo::find($id);
        $this->authorize('igualdade',$oleo);
        $veiculo = $oleo->veiculo_id;
        DB::beginTransaction();
        try {
            $oleo->delete();
            $up = $this->rollbackOleo($veiculo);
            if (!is_bool($up)) {
                throw new Exception($up);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    public function getTrocas()
    {
        $veiculo_id = $_GET["veiculo"];
        $empresa_id = Session::get('empresa_padrao')->id;
        $query = "select placa, kmatual, veiculo_ultima, colaborador_id, oleo_ultima ".
            "from( ".
                "select veiculos.placa, veiculos.kmatual, veiculos.kmultimatrocaoleo as veiculo_ultima, ".
                "veiculos.colaborador_id, oleo.kmtrocaoleo as oleo_ultima ".
                "from veiculos ".
                "left join veiculotrocaoleos oleo on oleo.veiculo_id = veiculos.id ".
                "where veiculos.empresa_id = $empresa_id and veiculos.id = $veiculo_id ".
                "order by oleo.data desc ".
            ") veiculos ".
            "where rownum <= 1";
        $veiculos = DB::select($query);
        return $veiculos;
    }

    private function updateVeiculo($oleo)
    {
        $up = [
            "kmultimatrocaoleo" => $oleo->kmtrocaoleo,
            "colaborador_id" => $oleo->colaborador_id
        ];
        DB::beginTransaction();
        try {
            $veiculo = Veiculo::find($oleo->veiculo_id);
            $veiculo->update($up);
        } catch (\Exception $e) {
            DB::rollback();
            $error = getErrorsException($e);
            return $error;
        }
        DB::commit();
        return true;
    }

    private function rollbackOleo($veiculo)
    {
        DB::beginTransaction();
        try {
            $veiculo = Veiculo::find($veiculo);
            $query = "select rank() over (order by ol.created_at desc) as rnk, ol.id ".
                    "from veiculotrocaoleos ol ".
                    "where veiculo_id = $veiculo->id";
            $anterior = collect(DB::select("select * from ($query) where rnk = 1 limit 1"))->first();
            if (count($anterior) > 0){
                $oleo = Veiculotrocaoleo::find($anterior->id);
                $up = [
                    "kmultimatrocaoleo" => $oleo->kmtrocaoleo,
                    "colaborador_id" => $oleo->colaborador_id
                ];
            } else {
                $up = [
                    "kmultimatrocaoleo" => 0,
                ];
            }
            $veiculo->update($up);
        } catch (Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
        DB::commit();
        return true;
    }

    private function definition(){
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->repository->setEmpresa($this->empresa_id);
    }

}
