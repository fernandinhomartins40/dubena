<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Empresa;
use App\Centrocusto;
use App\EmpresasGrupo;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class CentrocustoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Centrocusto $cen)
    {
        $this->authorize('view',$cen);
        $centrocusto_id = '';

        $centrocustos = Centrocusto::where('empresa_id', Session::get('empresa_padrao')->id)->where('ativo', 1)->get();;
        $centrocustosall = Centrocusto::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->get();
        return view('general.centrocustos', compact('centrocustos', 'centrocustosall', 'centrocusto_id'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Centrocusto $cen)
    {
        $this->authorize('create',$cen);
        $this->validate($request, [
            'descricao' => 'required|unique:centrocustos,descricao,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
            'codigo' => 'required|chave|unique:centrocustos,codigo,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
            ]);

        DB::beginTransaction();
        try {
            $data = $request->only('descricao', 'ativo', 'codigo', 'nivel');
            $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
            $data["empresa_id"] = Session::get('empresa_padrao')->id;
            $data["nivel"] = floor(strlen($data["codigo"]) / 3);
            $data["paicentrocusto_id"] = null;
            $data['finalizador'] = 1;
            if (strlen($data["codigo"]) > 3) {
                $chave_anterior = substr($data["codigo"], 0, strlen($data["codigo"]) - 3);
                $centrocusto = Centrocusto::where([
                    ['empresa_id', Session::get('empresa_padrao')->id],
                    ['codigo', $chave_anterior]
                    ])->get();
                if (count($centrocusto) == 0) {
                    return 'Não foi encontrado registro pai: ' . $chave_anterior;
                } else {
                    $data["paicentrocusto_id"] = $centrocusto->first()->id;
                    $cc_descricao = $centrocusto->first()->descricao;

                    if($this->isUsed($data['paicentrocusto_id']))
                        throw new \Exception('Registro pai " ' . $cc_descricao . '" já é um plano de conta final.');

                    if($this->isUsedByConfig($data['paicentrocusto_id']))
                        throw new \Exception('Registro pai " ' . $cc_descricao . '" já está sendo usando pelas configurações da empresa. <br>'
                                                . "Para poder realizar um cadastro, você deve remover o vínculo do plano de contas nas configurações.");

                    if($this->isUsedByNF($data['paicentrocusto_id']))
                        throw new \Exception('Registro pai " ' . $cc_descricao . '" já está sendo usando pelas configurações de Nota Fiscal. <br>'
                                                . "Para poder realizar um cadastro, você deve remover o vínculo do plano de contas na NF.");

                    Centrocusto::find($centrocusto->first()->id)->update(['finalizador' => 0]);
                }
            }
            $centrocusto = Centrocusto::create($data);
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        DB::commit();
        return 'OK|' . $centrocusto->id;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

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
    public function update(Request $request, $id, Centrocusto $cen)
    {
        $this->authorize('update',$cen);
        $this->validate($request, [
            'descricao' => 'required|unique:centrocustos,descricao,'.$id.',id,empresa_id,' . Session::get('empresa_padrao')->id,
            'codigo' => 'required|chave|unique:centrocustos,codigo,'.$id.',id,empresa_id,' . Session::get('empresa_padrao')->id,
            ]);
        DB::beginTransaction();
        try {
            $data = $request->only('descricao', 'ativo', 'codigo');

            $centrocusto = Centrocusto::findOrFail($id);

            $centrocustosfilhos = Centrocusto::where([
                ['paicentrocusto_id', $id],
                ])->get();

            if($data['codigo'] != $centrocusto->codigo && count($centrocustosfilhos) > 0)
                return "Este centro de custos possúi centros de custos dependentes, você deve remover as dependências antes de editar o código.";

            $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
            $data["empresa_id"] = Session::get('empresa_padrao')->id;
            $data["nivel"] = floor(strlen($data["codigo"]) / 3);

            if(count($centrocustosfilhos) > 0)
                return 'Este centro de custos possui centros de custos dependentes, para marcar como "Permite Lançamento" você deve remover seus dependentes!';

            $data["paicentrocusto_id"] = null;
            if (strlen($data["codigo"]) > 3) {
                $chave_anterior = substr($data["codigo"], 0, strlen($data["codigo"]) - 3);
                $centrocusto2 = Centrocusto::where([
                    ['empresa_id', Session::get('empresa_padrao')->id],
                    ['codigo', $chave_anterior]
                    ])->get();
                if (count($centrocusto2) == 0) {
                    return 'Não foi encontrado registro pai: ' . $chave_anterior;
                } else {
                    $data["paicentrocusto_id"] = $centrocusto2->first()->id;
                    $cc_descricao = $centrocusto->first()->descricao;

                    if($this->isUsed($data['paicentrocusto_id']))
                        throw new \Exception('Registro pai " ' . $cc_descricao . '" já é um plano de conta final.');

                    if($this->isUsedByConfig($data['paicentrocusto_id']))
                        throw new \Exception('Registro pai " ' . $cc_descricao . '" já está sendo usando pelas configurações da empresa. <br>'
                                                . "Para poder realizar um cadastro, você deve remover o vínculo do plano de contas nas configurações.");

                    if($this->isUsedByNF($data['paicentrocusto_id']))
                        throw new \Exception('Registro pai " ' . $cc_descricao . '" já está sendo usando pelas configurações de Nota Fiscal. <br>'
                                                . "Para poder realizar um cadastro, você deve remover o vínculo do plano de contas na NF.");

                    Centrocusto::find($centrocusto->first()->id)->update(['finalizador' => 0]);
                }
            }

            $centrocusto->update($data);
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        DB::commit();
        return 'OK|' . $centrocusto->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Centrocusto $cen)
    {
        $this->authorize('delete',$cen);
        DB::beginTransaction();
        try {
            $centrocusto = Centrocusto::find($id);
            $paicentrocusto_id = $centrocusto->paicentrocusto_id;
            try {
                $del = $centrocusto->delete();
            } catch (\Exception $e) {
                throw new \Exception("O registro não pôde ser excluído pois está sendo usado!");
            }

            if(!is_null($paicentrocusto_id)){
                $ccComPai = Centrocusto::where('paicentrocusto_id', $paicentrocusto_id)->get();
                if (count($ccComPai) == 0)
                    Centrocusto::find($paicentrocusto_id)->update(['finalizador' => 1]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        DB::commit();
        return 'OK|';
    }

    function centroCustoAjax($ccdependente_id, $ccedit_id)
    {
        if ($ccdependente_id == 0) {
            $ccdependente_id = null;
        }

        $ccdependente = CentroCusto::where([
                ['empresa_id', Session::get('empresa_padrao')->id],
                ['paicentrocusto_id', $ccdependente_id],
            ])->select('codigo', 'nivel', 'paicentrocusto_id')
            ->orderBy('codigo', 'DESC')
            ->latest()
            ->first();

        $ccedit = CentroCusto::where('id', $ccedit_id)->select('codigo', 'nivel', 'paicentrocusto_id')->first();
        if (is_null($ccdependente) && !is_null($ccdependente_id)) {
            $ccdependente = CentroCusto::where([
                ['empresa_id', Session::get('empresa_padrao')->id],
                ['id', $ccdependente_id],
                ])->select('codigo', 'nivel', 'paicentrocusto_id')->latest()->first();
            $codigo = $ccdependente->codigo . '000';
            $nivel = $ccdependente->nivel;
        } else if (is_null($ccdependente) && $ccdependente_id === null) {
            $codigo = '000';
            $nivel = 0;
        } else if ((!isset($ccedit->paicentrocusto_id) || !isset($ccdependente->paicentrocusto_id) || $ccedit->paicentrocusto_id != $ccdependente->paicentrocusto_id) && !is_null($ccdependente_id)) {
            $codigo = $ccdependente->codigo;
            $nivel = $ccdependente->nivel;
        }  else if (!is_null($ccedit)) {
            $codigo = $ccedit->codigo;
            $nivel = $ccedit->nivel;
        } else {
            $codigo = $ccdependente->codigo;
            $nivel = $ccdependente->nivel;
            $last = true;
        }
        if(is_null($ccdependente) || is_null($ccedit) || $ccdependente->paicentrocusto_id != $ccedit->paicentrocusto_id || is_null($ccdependente_id)) {
            $ccdependentepai_id = is_null($ccdependente_id) ? null : $ccdependente->paicentrocusto_id;
            for ($i=0; $i < $nivel; $i++) {
                if($ccedit_id == $ccdependentepai_id){
                    return 'O centro de custos selecionado não pode ser dependente do centro de custos que você está editando.';
                }
                $ccdependentepai_id = CentroCusto::where('id', $ccdependente->paicentrocusto_id)->select('paicentrocusto_id')->pluck('paicentrocusto_id')->first();
            }
        } else {
            $proximoCod = $ccdependente->codigo;
        }

        if(!isset($proximoCod) || $ccdependente_id != $ccedit_id){
            $qdeCaracteres = strlen($codigo);
            $ultimoNum = substr($codigo, $qdeCaracteres - 3, $qdeCaracteres);

            $incrementa = (!isset($ccedit->paicentrocusto_id) || !isset($ccdependente->paicentrocusto_id) || ($ccedit->paicentrocusto_id != $ccdependente->paicentrocusto_id || $ultimoNum == 0)) && !is_null($ccdependente_id);

            $incrementa || (isset($last) && $last) ? $ultimoNum++ : '';

            if(strlen($ultimoNum) == 1)
                $ultimoNum = '00' . $ultimoNum;
            else if(strlen($ultimoNum) == 2)
                $ultimoNum = '0' . $ultimoNum;

            $proximoCod = substr($codigo, 0, $qdeCaracteres - 3) . ($ultimoNum);
        }
        if($proximoCod === '000')
            $proximoCod = '001';
        return $proximoCod;
    }

    public function isUsed($cc_id)
    {
        $subSel = "(SELECT id FROM ESTOQUEREQUISICAOS WHERE CENTROCUSTO_ID = $cc_id AND ROWNUM <= 1) UNION ALL "
                                 . " (SELECT id FROM FINANCEIRORATEIOS WHERE CENTROCUSTO_ID = $cc_id AND ROWNUM <= 1)";
        $selectStatement = "SELECT id from ($subSel) WHERE ROWNUM <= 1 ";

        return collect(DB::select($selectStatement))->count() == 1;
    }

    public function isUsedByConfig($cc_id)
    {
        $subSel = "SELECT id FROM EMPRESACONFIGS WHERE (CENTROCUSTO_ID = $cc_id OR CCVALEGAS_ID = $cc_id OR CCFRETE_ID = $cc_id) AND ROWNUM <= 1";
        $selectStatement = "SELECT id from ($subSel) WHERE ROWNUM <= 1";
        return collect(DB::select($selectStatement))->count() == 1;
    }

    public function isUsedByNF($cc_id)
    {
        $subSel = "(SELECT id FROM NFRECEBIDAS WHERE CENTROCUSTO_ID = $cc_id OR FRETECENTROCUSTO_ID = $cc_id AND ROWNUM <= 1) UNION ALL "
                                . " (SELECT id FROM NFEMITIDAS WHERE CENTROCUSTO_ID = $cc_id OR FRETECENTROCUSTO_ID = $cc_id AND ROWNUM <= 1)";
        $selectStatement = "SELECT id from ($subSel) WHERE ROWNUM <= 1";
        return collect(DB::select($selectStatement))->count() == 1;
    }

    function centroCustoPorIdAjax($id)
    {
        $centroCusto = CentroCusto::find($id);
        return $centroCusto;
    }
}
