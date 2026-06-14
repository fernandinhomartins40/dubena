<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Empresa;
use App\Planoconta;
use App\EmpresasGrupo;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PlanocontaController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Planoconta $plan)
    {
        $this->authorize('view', $plan);
        $pagarreceber = ['R' => 'RECEITA', 'D' => 'DESPESA', 'A' => 'AMBOS'];
        $planocontasall = Planoconta::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
        ])->get();
        $planoconta_id = '';
        $planocontas = Planoconta::planoContasFinalizador0();
        return view('general.planocontas', compact('planocontas', 'planocontasall', 'pagarreceber', 'planoconta_id'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Planoconta $plan)
    {
        $this->authorize('create', $plan);
        $this->validate($request, [
            'descricao' => 'required|unique:planocontas,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'codigo' => 'required|chave|unique:planocontas,codigo,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id
        ]);
        DB::beginTransaction();
        try {
            $data = $this->getDataRequest($request);
            if (strlen($data["codigo"]) > 3) {
                $chave_anterior = substr($data["codigo"], 0, strlen($data["codigo"]) - 3);
                $planoconta = Planoconta::where([
                    ['grupo_id', Session::get('empresa_padrao')->grupo_id],
                    ['codigo', $chave_anterior]
                ])->get();
                if (count($planoconta) == 0) {
                    throw new \Exception('Não foi encontrado registro pai: ' . $chave_anterior);
                } else {
                    $data["paiplanoconta_id"] = $planoconta->first()->id;
                    $pc_descricao = $planoconta->first()->descricao;

                    if ($this->isUsed($data['paiplanoconta_id']))
                        throw new \Exception('Registro pai " ' . $pc_descricao . '" já é um plano de conta final.');

                    if ($this->isUsedByConfig($data['paiplanoconta_id']))
                        throw new \Exception('Registro pai " ' . $pc_descricao . '" já está sendo usando pelas configurações da empresa. <br>'
                            . "Para poder realizar um cadastro, você deve remover o vínculo do plano de contas nas configurações.");

                    if ($this->isUsedByNF($data['paiplanoconta_id']))
                        throw new \Exception('Registro pai " ' . $pc_descricao . '" já está sendo usando pelas configurações de Nota Fiscal. <br>'
                            . "Para poder realizar um cadastro, você deve remover o vínculo do plano de contas na NF.");

                    if (!isset($data['pagarreceber']) || (isset($data['pagarreceber']) && is_null($data['pagarreceber'])))
                        $data['pagarreceber'] = $planoconta->first()->pagarreceber;
                    PlanoConta::find($planoconta->first()->id)->update(['finalizador' => 0]);
                }
            }
            if (!isset($data['pagarreceber']) || (isset($data['pagarreceber']) && is_null($data['pagarreceber'])))
                throw new \Exception("O campo Tipo é obrigatório.");
            $data = emptyToNull($data);
            $planoconta = Planoconta::create($data);
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        DB::commit();
        return 'OK|' . $planoconta->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Planoconta $plan)
    {
        $this->authorize('update', $plan);
        $planoconta = Planoconta::findOrFail($id);
        $this->authorize('igualdade', $planoconta);

        $this->validate($request, [
            'descricao' => 'required|unique:planocontas,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'codigo' => 'required|chave|unique:planocontas,codigo,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
        ]);

        DB::beginTransaction();
        try {

            $data = $this->getDataRequest($request);

            $planocontafilhos = Planoconta::where([
                ['paiplanoconta_id', $id],
            ]);
            if ($data['codigo'] != $planoconta->codigo && count($planocontafilhos->get()) > 0)
                return "Este plano de contas possúi planos de contas dependentes, você deve remover as dependências antes de editar o código.";
            if (isset($data['pagarreceber']))
                $planocontafilhos->update(['pagarreceber' => $data['pagarreceber']]);

            if (strlen($data["codigo"]) > 3) {
                $chave_anterior = substr($data["codigo"], 0, strlen($data["codigo"]) - 3);
                $planoconta2 = Planoconta::where([
                    ['grupo_id', Session::get('empresa_padrao')->grupo_id],
                    ['codigo', $chave_anterior]
                ])->get();
                if (count($planoconta2) == 0) {
                    return 'Não foi encontrado registro pai: ' . $chave_anterior;
                } else {
                    $data["paiplanoconta_id"] = $planoconta2->first()->id;
                    $pc_descricao = $planoconta2->first()->descricao;

                    if ($this->isUsed($data['paiplanoconta_id']))
                        throw new \Exception('Registro pai " ' . $pc_descricao . '" já é um plano de conta final.');

                    if ($this->isUsedByConfig($data['paiplanoconta_id']))
                        throw new \Exception('Registro pai " ' . $pc_descricao . '" já está sendo usando pelas configurações da empresa. <br>'
                            . "Para poder realizar um cadastro, você deve remover o vínculo do plano de contas nas configurações.");

                    if ($this->isUsedByNF($data['paiplanoconta_id']))
                        throw new \Exception('Registro pai " ' . $pc_descricao . '" já está sendo usando pelas configurações de Nota Fiscal. <br>'
                            . "Para poder realizar um cadastro, você deve remover o vínculo do plano de contas na NF.");

                    if (isset($data['pagarreceber']) && is_null($data['pagarreceber']))
                        $data['pagarreceber'] = $planoconta->first()->pagarreceber;
                    PlanoConta::find($planoconta->first()->id)->update(['finalizador' => 0]);
                }
            }
            if (isset($data['pagarreceber']) && is_null($data['pagarreceber']))
                unset($data['pagarreceber']);
            if (Planoconta::where('paiplanoconta_id', $id)->get()->count() > 0)
                $data['finalizador'] = 0;
            $data = emptyToNull($data);
            $planoconta->update($data);
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        DB::commit();
        return 'OK|' . $planoconta->id;
    }

    public function getDataRequest($request)
    {
        $data = $request->all();
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["nivel"] = floor(strlen($data["codigo"]) / 3);
        $data["insumo_valor"] = false;
        $data["provisao"] = false;
        $data["ativo"] = isset($data['ativo']);
        $data['finalizador'] = 1;
        $data["investimento"] = $data['tipo'] == '1' ? 1 : 0;
        $data["custosvariaveis"] = $data['tipo'] == '0' ? 1 : 0;
        $data["paiplanoconta_id"] = null;
        $data['naturezasped'] = isset($data['naturezasped']) ? $data['naturezasped'] : null;

        if ($this->hasEmpresaEmitSped() && strlen($data['naturezasped']) === 0)
            throw new \Exception("O campo Natureza Sped é obrigatório pois alguma empresa do seu grupo emite Sped!");

        if (Empresa::where("grupo_id", $data['grupo_id'])->where('spedemite', true)->select('spedemite')->get()->count() > 0 && is_null($data['naturezasped']))
            throw new \Exception("O campo Natureza Sped é obrigatório quando alguma empresa do grupo Emite Sped");

        return $data;
    }

    private function hasEmpresaEmitSped()
    {
        return !is_null(DB::table('empresas as e')->whereRaw("ativo = 1 AND grupo_id = " . Session::get('empresa_padrao')->grupo_id . " AND spedemite = 1")->get()->first());
    }

    function planoContaAjax($pcdependente_id, $pcedit_id)
    {
        if ($pcdependente_id == 0) {
            $pcdependente_id = null;
        }

        $pcdependente = PlanoConta::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id],
            ['paiplanoconta_id', $pcdependente_id],
        ])
            ->select('codigo', 'nivel', 'paiplanoconta_id', 'pagarreceber')
            ->orderBy('codigo', 'DESC')
            ->first();

        $pcedit = PlanoConta::where('id', $pcedit_id)->select('codigo', 'nivel', 'paiplanoconta_id')->first();
        if (is_null($pcdependente) && $pcdependente_id !== null) {
            $pcdependente = PlanoConta::where([
                ['grupo_id', Session::get('empresa_padrao')->grupo_id],
                ['id', $pcdependente_id],
            ])->select('codigo', 'nivel', 'paiplanoconta_id', 'pagarreceber')->latest()->first();
            $codigo = $pcdependente->codigo . '000';
            $nivel = $pcdependente->nivel;
        } else if (is_null($pcdependente) && $pcdependente_id === null) {
            $codigo = '000';
            $nivel = 0;
        } else if ((!isset($pcedit->paiplanoconta_id) || !isset($pcdependente->paiplanoconta_id) || $pcedit->paiplanoconta_id != $pcdependente->paiplanoconta_id) && !is_null($pcdependente_id)) {
            $codigo = $pcdependente->codigo;
            $nivel = $pcdependente->nivel;
        } else if (!is_null($pcedit)) {
            $codigo = $pcedit->codigo;
            $nivel = $pcedit->nivel;
        } else {
            $codigo = $pcdependente->codigo;
            $nivel = $pcdependente->nivel;
            $last = true;
        }

        if (is_null($pcdependente) || is_null($pcedit) || $pcdependente->paiplanoconta_id != $pcedit->paiplanoconta_id || is_null($pcdependente_id)) {
            $pcdependentespai_id = is_null($pcdependente_id) ? null : $pcdependente->paiplanoconta_id;
            for ($i = 0; $i < $nivel; $i++) {
                if ($pcedit_id == $pcdependentespai_id && !is_null($pcdependente_id)) {
                    return 'O plano de contas selecionado não pode ser dependente do plano de contas que você está editando.';
                }
                $pcdependentespai_id = PlanoConta::where('id', $pcdependente->paiplanoconta_id)->select('paiplanoconta_id')->pluck('paiplanoconta_id')->first();
            }
        } else {
            $proximoCod = $pcdependente->codigo;
        }

        if (!isset($proximoCod) || $pcdependente_id != $pcedit_id) {
            $qdeCaracteres = strlen($codigo);
            $ultimoNum = substr($codigo, $qdeCaracteres - 3, $qdeCaracteres);

            $incrementa = (!isset($pcedit->paiplanoconta_id) || !isset($pcdependente->paiplanoconta_id) || ($pcedit->paiplanoconta_id != $pcdependente->paiplanoconta_id || $ultimoNum == 0)) && !is_null($pcdependente_id);

            $incrementa || (isset($last) && $last) ? $ultimoNum++ : '';

            if (strlen($ultimoNum) == 1)
                $ultimoNum = '00' . $ultimoNum;
            else if (strlen($ultimoNum) == 2)
                $ultimoNum = '0' . $ultimoNum;

            $proximoCod = substr($codigo, 0, $qdeCaracteres - 3) . ($ultimoNum);
        }
        if ($proximoCod == '000')
            $proximoCod = '001';
        return ['codigo' => $proximoCod, 'pagarreceber' => is_null($pcdependente) ? '' : $pcdependente->pagarreceber];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Planoconta $plan)
    {
        $this->authorize('delete', $plan);
        $planoconta = Planoconta::find($id);
        $this->authorize('igualdade', $planoconta);
        DB::beginTransaction();
        try {
            $paiplanoconta_id = $planoconta->paiplanoconta_id;
            try {
                $del = $planoconta->delete();
            } catch (\Exception $e) {
                throw new \Exception("O registro não pôde ser excluído pois está sendo usado!");
            }

            if (!is_null($paiplanoconta_id)) {
                $pcComPai = Planoconta::where('paiplanoconta_id', $paiplanoconta_id)->get();
                if (count($pcComPai) == 0)
                    Planoconta::find($paiplanoconta_id)->update(['finalizador' => 1]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        DB::commit();
        return 'OK|';
    }

    public function isUsed($pc_id)
    {
        $subSel = "(SELECT id FROM ESTOQUEREQUISICAOS WHERE PLANOCONTA_ID = $pc_id AND ROWNUM <= 1) UNION ALL "
            . " (SELECT id FROM FINANCEIRORATEIOS WHERE PLANOCONTA_ID = $pc_id AND ROWNUM <= 1)";
        $selectStatement = "SELECT id from ($subSel) WHERE ROWNUM <= 1 ";
        return collect(DB::select($selectStatement))->count() == 1;
    }

    public function isUsedByConfig($pc_id)
    {
        $subSel = "SELECT id FROM EMPRESACONFIGS WHERE PLANOCONTA_ID = $pc_id OR PCCARTAO_ID = $pc_id "
            . " OR PCRECEITADESCONTO_ID = $pc_id OR PCRECETAJURO_ID = $pc_id OR PCDESPESASDESCONTO_ID = $pc_id "
            . " OR PCDESPESASJURO_ID = $pc_id OR PCVALEGAS_ID = $pc_id OR PCFRETE_ID = $pc_id AND ROWNUM <= 1";
        $selectStatement = "SELECT id from ($subSel) WHERE ROWNUM <= 1";
        return collect(DB::select($selectStatement))->count() == 1;
    }

    public function isUsedByNF($pc_id)
    {
        $subSel = "(SELECT id FROM NFRECEBIDAS WHERE PLANOCONTA_ID = $pc_id OR FRETEPLANOCONTA_ID = $pc_id AND ROWNUM <= 1) UNION ALL "
            . " (SELECT id FROM NFEMITIDAS WHERE PLANOCONTA_ID = $pc_id OR FRETEPLANOCONTA_ID = $pc_id AND ROWNUM <= 1)";
        $selectStatement = "SELECT id from ($subSel) WHERE ROWNUM <= 1";
        return collect(DB::select($selectStatement))->count() == 1;
    }

    function planoContaPorIdAjax($id)
    {
        $planoconta = Planoconta::find($id);
        return $planoconta;
    }
}
