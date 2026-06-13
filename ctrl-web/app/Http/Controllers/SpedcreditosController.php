<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use Exception;
use App\Creditopiscofins;
use Illuminate\Http\Request;
use App\Services\CarbonCustom as Carbon;
use App\Spedcontribuicoescredito as Creditos;

class SpedcreditosController extends Controller
{

    protected $empresa_id;
    protected $grupo_id;

    protected function setEmpresaGrupo($empresa_id, $grupo_id)
    {
        $this->empresa_id = $empresa_id;
        $this->grupo_id = $grupo_id;
    }

    public function index(Creditos $cre)
    {
        $this->authorize('view',$cre);
        $this->setEmpresaGrupo(Session::get('empresa_padrao')->id, Session::get('empresa_padrao')->grupo_id);

        $creditos = Creditos::where('empresa_id',$this->empresa_id);

        if (isset($_GET["registro"])){
            $creditos = $creditos->where('registro',$_GET["registro"]);
        }

        $creditos = $creditos->selectRaw("id, to_char(per_apu_cred,'mm/yyyy') as descricao, registro, sld_cred_fim as saldo")
                ->get();
        $registros = $this->orgiemCredito(true);
        return view('speds.creditos.index')->with(compact('creditos','registros'));
    }

    public function create(Creditos $cre)
    {
        $this->authorize('create',$cre);
        $origem = $this->orgiemCredito();
        $registros = $this->orgiemCredito(true);
        $cred = Creditopiscofins::whereBetween('identificador', [2, 30])
            ->selectRaw("codigo, (codigo || ' - ' || descricao) as descricao")
            ->pluck('descricao','codigo')->prepend('Selecione','');
        return view('speds.creditos.creditos_form')->with(compact('origem','cred','registros'));
    }

    public function store(Request $request, Creditos $cre)
    {
        $this->authorize('create',$cre);
        $this->setEmpresaGrupo(Session::get('empresa_padrao')->id, Session::get('empresa_padrao')->grupo_id);
        $data = $request->all();
        $extras = $this->dadosExtras($data);
        $data = array_merge($data, $extras);

        DB::beginTransaction();
        try {
            $creditos = Creditos::create($data);
        } catch (Exception $e) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            return redirect('spedcreditos/create')->withErrors($error)->withInput();
        }
        DB::commit();
        return redirect('spedcreditos')->withMessageSuccess("Créditos PIS/Cofins gravado com sucesso!");
    }

    public function edit($id, Creditos $cre)
    {
        $this->authorize('update',$cre);
        return $this->showEdit($id,false);
    }

    public function update(Request $request, $id, Creditos $cre)
    {
        $this->authorize('update',$cre);
        $credito = Creditos::find($id);
        $this->authorize('igualdade',$credito);
        $this->setEmpresaGrupo( Session::get('empresa_padrao')->id, Session::get('empresa_padrao')->grupo_id );
        $data = $request->all();
        $extras = $this->dadosExtras($data);
        $data = array_merge($data, $extras);

        DB::beginTransaction();
        try {
            $credito->update($data);
        } catch (Exception $e) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            return redirect('spedcreditos/create')->withErrors($error)->withInput();
        }
        DB::commit();
        return redirect('spedcreditos')->withMessageSuccess("Créditos PIS/Cofins atualizado com sucesso!");
    }

    public function show($id, Creditos $cre)
    {
        $this->authorize('view',$cre);
        return $this->showEdit($id);
    }

    private function showEdit($id, $show = true)
    {
        $credito = Creditos::find($id);
        $this->authorize('igualdade',$credito);
        $credito = $this->consolidar($credito);
        $origem = $this->orgiemCredito();
        $registros = $this->orgiemCredito(true);
        $cred = Creditopiscofins::whereBetween('identificador', [2, 30])
            ->selectRaw("codigo, (codigo || ' - ' || descricao) as descricao")
            ->pluck('descricao','codigo')->prepend('Selecione','');
        if ($show) {
            return view('speds.creditos.creditos_form')->with(compact('credito','origem','cred','registros','show'));
        } else {
            return view('speds.creditos.creditos_form')->with(compact('credito','origem','cred','registros'));
        }
    }

    public function destroy($id, Creditos $cre)
    {
        $this->authorize('delete',$cre);
        $credito = Creditos::find($id);
        $this->authorize('igualdade',$credito);
        DB::beginTransaction();
        try {
            $credito->delete();
        } catch (Excpetion $e) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            return $error;
        }
        DB::commit();
        return 'OK|';
    }

    private function orgiemCredito($regs = false)
    {
        $creditos = [
            ""   => "Selecione",
            "01" => "01 – Crédito decorrente de operações próprias.",
            "02" => "02 – Crédito transferido por pessoa jurídica sucedida."
        ];
        $registros = [
            ""  => "Selecione",
            "1100" => "PIS",
            "1500" => "COFINS",
        ];
        if ($regs) return $registros;
        return $creditos;
    }

    private function dadosExtras($data)
    {
        $data["empresa_id"] = $this->empresa_id;
        $data["grupo_id"] = $this->grupo_id;
        $data["cnpj_suc"] = preg_replace("/[^0-9]/",'',$data["cnpj_suc"]);
        $data["per_apu_cred"] = insertDataOracle('01/'.$data["per_apu_cred"]);
        $data["vl_cred_apu"] = insertNumeroDecimalOracle($data["vl_cred_apu"]);
        $data["vl_cred_ext_apu"] = insertNumeroDecimalOracle($data["vl_cred_ext_apu"]);
        $data["vl_tot_cred_apu"] = insertNumeroDecimalOracle($data["vl_tot_cred_apu"]);
        $data["vl_cred_desc_pa_ant"] = insertNumeroDecimalOracle($data["vl_cred_desc_pa_ant"]);
        $data["vl_cred_per_pa_ant"] = insertNumeroDecimalOracle($data["vl_cred_per_pa_ant"]);
        $data["vl_cred_dcomp_pa_ant"] = insertNumeroDecimalOracle($data["vl_cred_dcomp_pa_ant"]);
        $data["sd_cred_disp_efd"] = insertNumeroDecimalOracle($data["sd_cred_disp_efd"]);
        $data["vl_cred_desc_efd"] = insertNumeroDecimalOracle($data["vl_cred_desc_efd"]);
        $data["vl_cred_per_efd"] = insertNumeroDecimalOracle($data["vl_cred_per_efd"]);
        $data["vl_cred_dcomp_efd"] = insertNumeroDecimalOracle($data["vl_cred_dcomp_efd"]);
        $data["vl_cred_trans"] = insertNumeroDecimalOracle($data["vl_cred_trans"]);
        $data["vl_cred_out"] = insertNumeroDecimalOracle($data["vl_cred_out"]);
        $data["sld_cred_fim"] = insertNumeroDecimalOracle($data["sld_cred_fim"]);
        return $data;
    }

    private function consolidar($credito)
    {
        $credito->per_apu_cred = Carbon::parse($credito->per_apu_cred)->format('m/Y');
        $credito->sd_cred_disp_efd = requestNumeroDecimalOracle($credito->sd_cred_disp_efd);
        $credito->sld_cred_fim = requestNumeroDecimalOracle($credito->sld_cred_fim);
        $credito->vl_cred_apu = requestNumeroDecimalOracle($credito->vl_cred_apu);
        $credito->vl_cred_dcomp_efd = requestNumeroDecimalOracle($credito->vl_cred_dcomp_efd);
        $credito->vl_cred_dcomp_pa_ant = requestNumeroDecimalOracle($credito->vl_cred_dcomp_pa_ant);
        $credito->vl_cred_desc_efd = requestNumeroDecimalOracle($credito->vl_cred_desc_efd);
        $credito->vl_cred_desc_pa_ant = requestNumeroDecimalOracle($credito->vl_cred_desc_pa_ant);
        $credito->vl_cred_ext_apu = requestNumeroDecimalOracle($credito->vl_cred_ext_apu);
        $credito->vl_cred_out = requestNumeroDecimalOracle($credito->vl_cred_out);
        $credito->vl_cred_per_efd = requestNumeroDecimalOracle($credito->vl_cred_per_efd);
        $credito->vl_cred_per_pa_ant = requestNumeroDecimalOracle($credito->vl_cred_per_pa_ant);
        $credito->vl_cred_trans = requestNumeroDecimalOracle($credito->vl_cred_trans);
        $credito->vl_tot_cred_apu = requestNumeroDecimalOracle($credito->vl_tot_cred_apu);
        return $credito;
    }
}
