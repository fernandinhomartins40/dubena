<?php

namespace App\Http\Controllers;

use DB;
use Input;
use Redirect;
use Exception;
use App\Nfpis;
use App\Nficms;
use App\Estado;
use App\Produto;
use App\Nfcofins;
use App\Nfimposto;
use App\Nfoperacao;
use App\Beneficiario;
use App\Nfgrupofiscal;
use App\Nfimpostoestado;
use App\Creditopiscofins;
use App\Helpers\Utils\NfUtil as Util;
use App\Http\Requests\NfImpostoRequest;
use Illuminate\Support\Facades\Session;
use App\Services\CarbonCustom as Carbon;

class ImpostonfController extends Controller
{
    protected $cstSemCodBenef = ["00", "10", "60", "61", "90"];

    protected $empresa_id;
    protected $grupo_id;
    protected $impostos;
    protected $operacoes;
    protected $gruposfiscais;
    protected $nfcofins;
    protected $nfpis;
    protected $nficms;
    protected $estados;
    protected $spedemite;
    protected $tipocred;
    protected $natreceita;
    protected $tipobasecalc;
    protected $natcred;
    protected $beneficiario;

    function index(Nfimposto $nfimp)
    {
        $this->authorize('view', $nfimp);
        $this->basicDefition();
        $imposto = $this->impostos;
        $grupofiscal = $this->gruposfiscais;
        $operacoes = $this->operacoes;
        $operacao = (int) e(Input::get('operacoessearch', '0'));
        $grupo = (int) e(Input::get('grupofiscalsearch', '0'));

        // dd(Carbon::parse('2019-12-19 08:20:20')->format("Y-m-d\TH:i:sP"));
        $fullUrl = $this->getFullUrl();

        if ($operacao || $grupo)
            return $this->filter($operacao, $grupo);

        return View('nf.nfimpostos.nfimpostos')->with(compact(
            'imposto',
            'grupofiscal',
            'operacoes',
            'fullUrl'
        ));
    }

    function filter($operacao, $grupo)
    {
        $imposto = $this->impostos;
        $grupofiscal = $this->gruposfiscais;
        $operacoes = $this->operacoes;
        $impostos = Nfimposto::with('nfgrupofiscal', 'nfoperacao');

        if ($operacao) {
            $impostos->where(['nfoperacao_id' => $operacao]);
        }
        if ($grupo) {
            $impostos = $impostos->where(['nfgrupofiscal_id' => (int) $grupo]);
        }
        $impostos = $impostos->get();
        $fullUrl = $this->getFullUrl();
        return View('nf.nfimpostos.nfimpostos')->with(compact(
            'impostos',
            'imposto',
            'grupofiscal',
            'operacoes',
            'fullUrl'
        ));
    }

    function createByNf()
    {
        $parameters = "";
        $cofins = Nfcofins::whereCodigo(Input::get("cst_cofins", null))->get()->first();
        $cofins = $cofins ? $cofins->id : "";
        $parameters .= "nfcofins_id=" . $cofins;
        $parameters .= "&pfnfcofins_id=" . $cofins;
        $pis = Nfpis::whereCodigo(Input::get("cst_pis", null))->get()->first();
        $pis = $pis ? $pis->id : "";
        $parameters .= "&nfpis_id=" . $pis;
        $parameters .= "&pfnfpis_id=" . $pis;
        $icms = Nficms::whereCodigo(Input::get("cst_icms", null))->get()->first();
        $icms = $icms ? $icms->id : "";
        $parameters .= "&nficms_id_pj=" . $icms;
        $parameters .= "&pfnficms_id=" . $icms;
        if (Input::get("destino_uf") !== Input::get("origem_uf")) {
            $parameters .= "&estadosnficms_id=" . $icms;
            //            $parameters .= "&estadosmodicms=" . $icms;
            $parameters .= "&estadopfnficms_id=" . $icms;
        }
        $produto = Produto::find(Input::get("produto_id"));

        $parameters .= "&grupofiscal_id=" . ($produto ? $produto->nfgrupofiscal_id : null);

        $url = "nfimposto/create?" . $parameters . "&" . $this->filterUrlNfCreate();
        return redirect()->to($url);
    }

    function filterUrlNfCreate()
    {
        $exclude = [
            "cst_cofins", "cst_pis", "cst_icms", "produto_id"
        ];
        $url = "";
        $input = Input::all();
        foreach ($input as $i => $el) {
            if (!in_array($i, $exclude)) {
                $url .= $i . "=" . $el . "&";
            }
        }
        return $url;
    }

    function create(Nfimposto $nfimp)
    {
        $this->authorize('create', $nfimp);
        $this->definition();
        $operacoes = $this->operacoes;
        $grupofiscal = $this->gruposfiscais;
        $nfcofins = nfMake($this->nfcofins);
        $nfpis = nfMake($this->nfpis);
        $nficms = nfMake($this->nficms);
        $estados = $this->estados;
        $modalidade = $this->modalidadeBcIcms();
        $modalidadest = $this->modalidadeBcIcmsSt();
        $tipocred = nfMake($this->tipocred);
        $tipobase = nfMake($this->tipobasecalc);
        $sped = $this->spedemite;
        $motDesICMS = Util::getMotDesonICMS();
        $allCodICMS = $this->getCodICMS();
        $beneficiario = $this->beneficiario;

        $allowedICMSST = Util::useST(null, true);
        $allowedICMSDeson = Util::useDeson(null, true);
        $allowedICMSDesonGroup40 = Util::useDesonGroup40(null, true);
        $allowedICMSFCP = Util::useFCP(null, true);
        $allowedICMSREDBC = Util::useREDBC(null, true);
        $allowedICMSREDBCST = Util::useREDBCST(null, true);
        $allowedICMSTagPart = Util::useTagPart(null, true);
        $allowedICMSFCPNormal = Util::useFCPNormal(null, true);
        $allowedICMSFCPST = Util::useFCPST(null, true);
        $allowedDiferimento = Util::useDiferimento(null, true);
        $allowedMODBC = Util::useMODBC(null, true);
        $allowedMODBCST = Util::useMODBCST(null, true);
        $isSN = Util::isSN(null, true);

        return View(
            'nf.nfimpostos.nfimposto_form',
            compact(
                'operacoes',
                'grupofiscal',
                'nfcofins',
                'nfpis',
                'nficms',
                'estados',
                'modalidade',
                'modalidadest',
                'sped',
                'tipocred',
                'tipobase',
                'allCodICMS',
                'beneficiario',
                'motDesICMS',
                'allowedICMSST',
                'allowedICMSDeson',
                'allowedICMSDesonGroup40',
                'allowedICMSFCP',
                'allowedICMSREDBC',
                'allowedICMSREDBCST',
                'allowedICMSTagPart',
                'allowedICMSFCPNormal',
                'allowedICMSFCPST',
                'allowedDiferimento',
                'allowedMODBC',
                'allowedMODBCST',
                'isSN'
            )
        );
    }

    function store(NfImpostoRequest $request, Nfimposto $nfimp)
    {
        $this->authorize('create', $nfimp);
        $this->empGruDefinition();
        $data = $request->all();
        $existe = false;
        $existencia = $nfimp->where([
            ['nfoperacao_id', $data["nfoperacao_id"]],
            ['nfgrupofiscal_id', $data["grupofiscal_id"]],
            ['empresa_id', $this->empresa_id]
        ])->count();

        $existe = $existencia > 0;
        $gerais = $this->dadosExtras($data, "gerais");
        $impgerais = array_merge($data, $gerais);
        DB::begintransaction();
        try {
            if ($existe)
                throw new Exception('Imposto com o mesmo grupo fiscal e operação já foi cadastrado.');

            $this->validateCodBenef($impgerais);

            $impostos = Nfimposto::create($impgerais);

            $this->dadosExtras($data, "estados", $impostos->id);
        } catch (Exception $ex) {
            DB::rollback();
            return Redirect::to('nfimposto/create')
                ->withErrors($ex->getMessage())
                ->withInput();
        }
        DB::commit();
        return Redirect::to($this->getUrlIndex())->withMessageSuccess('Imposto cadastrado com sucesso!');
    }

    function edit($id, Nfimposto $nfimp)
    {
        $this->authorize('update', $nfimp);
        $impostos = Nfimposto::find($id);
        $this->authorize('igualdade', $impostos);
        $this->definition();
        $natreceita = $impostos->piscofinsnatreceita;
        $grupofiscal = $this->gruposfiscais;
        $operacoes = $this->operacoes;
        $nfcofins = nfMake($this->nfcofins);
        $nfpis = nfMake($this->nfpis);
        $nficms = nfMake($this->nficms);
        $estados = $this->estados;
        $modalidade = $this->modalidadeBcIcms();
        $modalidadest = $this->modalidadeBcIcmsSt();
        $sped = $this->spedemite;
        $tipocred = nfMake($this->tipocred);
        $tipobase = nfMake($this->tipobasecalc);
        $natcred = nfMake($this->natcred);
        $estadosimp = $this->showImpostoEstado($impostos->id);
        $imposto = $this->requestDataGerais($impostos);
        $motDesICMS = Util::getMotDesonICMS();
        $allCodICMS = $this->getCodICMS();
        $beneficiario = $this->beneficiario;

        $allowedICMSST = Util::useST(null, true);
        $allowedICMSDeson = Util::useDeson(null, true);
        $allowedICMSDesonGroup40 = Util::useDesonGroup40(null, true);
        $allowedICMSFCP = Util::useFCP(null, true);
        $allowedICMSREDBC = Util::useREDBC(null, true);
        $allowedICMSREDBCST = Util::useREDBCST(null, true);
        $allowedICMSTagPart = Util::useTagPart(null, true);
        $allowedICMSFCPNormal = Util::useFCPNormal(null, true);
        $allowedICMSFCPST = Util::useFCPST(null, true);
        $allowedDiferimento = Util::useDiferimento(null, true);
        $allowedMODBC = Util::useMODBC(null, true);
        $allowedMODBCST = Util::useMODBCST(null, true);
        $isSN = Util::isSN(null, true);

        return view('nf.nfimpostos.nfimposto_form')->with(compact(
            'imposto',
            'grupofiscal',
            'operacoes',
            'nfcofins',
            'nfpis',
            'nficms',
            'estados',
            'modalidade',
            'modalidadest',
            'sped',
            'estadosimp',
            'tipocred',
            'tipobase',
            'natcred',
            'natreceita',
            'allCodICMS',
            'beneficiario',
            'motDesICMS',
            'allowedICMSST',
            'allowedICMSDeson',
            'allowedICMSDesonGroup40',
            'allowedICMSFCP',
            'allowedICMSREDBC',
            'allowedICMSREDBCST',
            'allowedICMSTagPart',
            'allowedICMSFCPNormal',
            'allowedICMSFCPST',
            'allowedDiferimento',
            'allowedMODBC',
            'allowedMODBCST',
            'isSN'
        ));
    }

    function update(NfImpostoRequest $request, $id, Nfimposto $nfimp)
    {
        $this->authorize('update', $nfimp);
        $this->empGruDefinition();
        $data = $request->all();
        $impostos = Nfimposto::with('nfimpostoestado')->find($id);
        $existe = false;
        $existencia = $nfimp->where([
            ['nfoperacao_id', $data["nfoperacao_id"]],
            ['nfgrupofiscal_id', $data["grupofiscal_id"]],
            ['empresa_id', $this->empresa_id],
            ['id', '!=', $id]
        ])->count();
        $existe = $existencia > 0;
        $this->authorize('igualdade', $impostos);
        $gerais = $this->dadosExtras($data, "gerais");
        $impgerais = array_merge($data, $gerais);

        DB::begintransaction();
        try {
            if ($existe)
                throw new Exception('Já existe um imposto cadastrado com este grupo fiscal e operação.');

            $items = $impostos->nfimpostoestado;
            $this->validateCodBenef($impgerais);

            $impostos->update($impgerais);
            $this->validatePISCOFINS($impostos);
            $this->dadosExtras($data, "estados", $id, $items);
        } catch (Exception $ex) {
            DB::rollback();
            $error = getErrorsException($ex);
            return Redirect::to('nfimposto/' . $id . '/edit')
                ->withErrors($error)
                ->withInput();
        }
        DB::commit();

        return Redirect::to($this->getUrlIndex())->withMessageSuccess('Imposto atualizado com sucesso!');
    }

    function destroy($id, Nfimposto $nfimp)
    {
        $this->authorize('delete', $nfimp);
        $nf = Nfimposto::find($id);
        $this->authorize('igualdade', $nf);
        $this->empGruDefinition();
        DB::beginTransaction();
        try {
            $nf->nfimpostoestado()->delete();
            $nf->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function dadosExtras($data, $tipo, $id = null, $items = null)
    {
        $this->empGruDefinition();
        $impostosestados = json_decode($data["impostosestados"]);
        $estados = [];
        $gerais = [];
        if ($tipo === "gerais") {
            $i = new Nfimposto();
            $allowedFields = $i->getFillable();

            $gerais["empresa_id"] = $this->empresa_id;
            $gerais["grupo_id"] = $this->grupo_id;
            $gerais["nfgrupofiscal_id"] = $data["grupofiscal_id"];
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedFields)) {
                    $gerais[$key] = $this->formatFieldsToInsert($key, $value);
                }
            }
            $gerais["nficms_id"] = $data["nficms_id_pj"];

            $gerais["pftaxafecop"] = insertNumeroDecimalOracle($data["pftaxafecopgr"]);
            $gerais["taxafecop"] = insertNumeroDecimalOracle($data["taxafecop"]);
            $gerais["pfmodalidadebcicms"] = isset($data["modalidadebcicmspf"]) ? $data["modalidadebcicmspf"] : null;
            $gerais["nfmotdesonicms"] = isset($data["nfmotdesonicms"]) ? $data["nfmotdesonicms"] : null;
            $gerais["pfnfmotdesonicms"] = isset($data["pfnfmotdesonicms"]) ? $data["pfnfmotdesonicms"] : null;
            $gerais["origemicms"] = 0;
            $gerais["pforigemicms"] = 0;
            $gerais["piscofinsgeracredito"] = isset($gerais["piscofinsgeracredito"]);
            if ($data["nfpisaliqcred"] !== '') {
                $gerais["nfpisaliqcred"] = insertNumeroDecimalOracle($data["nfpisaliqcred"]);
            }
            if ($data["nfcofinsaliqcred"] !== '') {
                $gerais["nfcofinsaliqcred"] = insertNumeroDecimalOracle($data["nfcofinsaliqcred"]);
            }
            $gerais['pfmodalidadebcicms'] = isset($data['modalidadebcicmspf']) ? $data['modalidadebcicmspf'] : null;
            $gerais['pfmodalidadebcicmsst'] = isset($data['modalidadebcicmsstpf']) ? $data['modalidadebcicmsstpf'] : null;
            $gerais['pftaxafecop'] = isset($data['pftaxafecopgr']) ? insertNumeroDecimalOracle($data['pftaxafecopgr']) : null;
            $gerais['taxafecop'] = isset($data['taxafecop']) ? insertNumeroDecimalOracle($data['taxafecop']) : null;
            return emptyToNull($gerais);
        } else {
            $count = count($impostosestados);
            $imp_estados = new Nfimpostoestado();
            $imp = collect([]);
            $items = $items ? $items->keyBy('id') : null;
            for ($i = 0; $i < $count; $i++) {
                $insert = false;
                $imposto = $impostosestados[$i];
                $estados = new Nfimpostoestado();
                $allowedFields = $estados->getFillable();
                $item = null;

                if (!$imposto->id) {
                    $insert = true;
                } else if ($items) {
                    $item = $items->get($imposto->id);
                    $items = $items->forget($imposto->id);
                }
                unset($imposto->buttons);
                foreach ($imposto as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        $estados->{$key} = $this->formatFieldsToInsert($key, $value);
                    }
                }
                $estados->nficmsorigem = 0;
                $estados->pfnficmsorigem = 0;
                $estados->pfnficmsstaliq = 0;
                $estados->nfimposto_id = $id;
                $estados->empresa_id = $this->empresa_id;
                $estados->grupo_id = $this->grupo_id;
                $estados->created_at = Carbon::now();
                $estados->updated_at = Carbon::now();
                $this->validateCodBenef($estados->toArray(), true);

                if (!$insert) {
                    $data = emptyToNull($estados->toArray());
                    $item->update($data);
                } else {
                    $imp->push($estados);
                }
            }
            if ($imp && $imp->isNotEmpty()) {
                $imp_estados->insert($imp->toArray());
            }
            if ($items && $items->isNotEmpty()) {
                $ids = $items->keys()->toArray();
                DB::table('nfimpostoestados')->whereIn('id', $ids)->delete();
            }
            return true;
        }
    }

    public function show($id, Nfimposto $im)
    {
        $this->authorize('view', $im);
        $impostos = Nfimposto::find($id);
        $this->authorize('igualdade', $impostos);
        $this->definition();
        $natreceita = $impostos->piscofinsnatreceita;
        $grupofiscal = $this->gruposfiscais;
        $operacoes = $this->operacoes;
        $nfcofins = nfMake($this->nfcofins);
        $nfpis = nfMake($this->nfpis);
        $nficms = nfMake($this->nficms);
        $estados = $this->estados;
        $modalidade = $this->modalidadeBcIcms();
        $modalidadest = $this->modalidadeBcIcmsSt();
        $sped = $this->spedemite;
        $estadosimp = $this->showImpostoEstado($impostos->id);
        $imposto = $this->requestDataGerais($impostos);
        $show = 1;
        $tipocred = nfMake($this->tipocred);
        $tipobase = nfMake($this->tipobasecalc);
        $natcred = nfMake($this->natcred);
        $motDesICMS = Util::getMotDesonICMS();
        $beneficiario = $this->beneficiario;

        $allowedICMSST = Util::useST(null, true);
        $allowedICMSDeson = Util::useDeson(null, true);
        $allowedICMSDesonGroup40 = Util::useDesonGroup40(null, true);
        $allowedICMSFCP = Util::useFCP(null, true);
        $allowedICMSREDBC = Util::useREDBC(null, true);
        $allowedICMSREDBCST = Util::useREDBCST(null, true);
        $allowedICMSTagPart = Util::useTagPart(null, true);
        $allowedICMSFCPNormal = Util::useFCPNormal(null, true);
        $allowedICMSFCPST = Util::useFCPST(null, true);
        $allowedDiferimento = Util::useDiferimento(null, true);
        $allowedMODBC = Util::useMODBC(null, true);
        $allowedMODBCST = Util::useMODBCST(null, true);
        $isSN = Util::isSN(null, true);

        $allCodICMS = $this->getCodICMS();
        return view('nf.nfimpostos.nfimposto_form')->with(compact(
            'imposto',
            'operacoes',
            'natreceita',
            'grupofiscal',
            'nfcofins',
            'nfpis',
            'nficms',
            'estados',
            'modalidade',
            'modalidadest',
            'sped',
            'estadosimp',
            'show',
            'tipocred',
            'tipobase',
            'natcred',
            'allCodICMS',
            'motDesICMS',
            'beneficiario',
            'allowedICMSST',
            'allowedICMSDeson',
            'allowedICMSDesonGroup40',
            'allowedICMSFCP',
            'allowedICMSREDBC',
            'allowedICMSREDBCST',
            'allowedICMSTagPart',
            'allowedICMSFCPNormal',
            'allowedICMSFCPST',
            'allowedDiferimento',
            'allowedMODBC',
            'allowedMODBCST',
            'isSN'
        ));
    }

    private function showImpostoEstado($id)
    {
        $impestado = Nfimpostoestado::with('pfnficms', 'nficms', 'pf_beneficiario', 'pj_beneficiario')->where('nfimposto_id', $id)->get();
        $impostosestado = [];
        foreach ($impestado as $imposto) {
            $estado = (object) $this->formatValues($imposto)->toArray();
            $estado->icmsbasest = $imposto->nficmsbasest;
            $estado->nficmsstmodalidadebc_desc = $this->getDescSelect(
                $this->modalidadeBcIcmsSt(),
                $imposto->nficmsstmodalidadebc
            );
            $estado->nficmsmodalidadebc_desc = $this->getDescSelect(
                $this->modalidadeBcIcms(),
                $imposto->nficmsmodalidadebc
            );
            $estado->pfnficmsmodalidadebc_desc = $this->getDescSelect(
                $this->modalidadeBcIcms(),
                $imposto->pfnficmsmodalidadebc
            );
            $estado->pfnficmsstmodalidadebc_desc = $this->getDescSelect(
                $this->modalidadeBcIcmsSt(),
                $imposto->pfnficmsstmodalidadebc
            );

            $estado->nficms_id_desc = $imposto->nficms->descricao;
            $estado->pfnficms_id_desc = $imposto->nficms->descricao;
            $mot = $imposto->pfnfmotdesonicms;
            $estado->pfnfmotdesonicms_desc = $this->getDescMotDesonICMS(
                $mot,
                $imposto->pfnficms->codigo
            );
            $mot = $imposto->nfmotdesonicms;
            $estado->nfmotdesonicms_desc = $this->getDescMotDesonICMS($mot, $imposto->nficms->codigo);
            $estado->beneficiario_id_desc = isset($imposto->pj_beneficiario->descricao) ? $imposto->pj_beneficiario->descricao : "";
            $estado->pfbeneficiario_id_desc = isset($imposto->pf_beneficiario->descricao) ? $imposto->pf_beneficiario->descricao : "";

            array_push($impostosestado, $estado);
        }

        return $impostosestado;
    }

    private function getDescSelect($array, $index)
    {
        $desc = $array[$index];
        return trim(strtolower($desc)) !== "selecione" ? $desc : '';
    }

    private function requestDataGerais($impostos)
    {
        $this->definition();
        $impostos = $this->formatValues($impostos);
        $impostos->grupofiscal_id = $impostos->nfgrupofiscal_id;
        $impostos->nficms_id_pj = $impostos->nficms_id;
        $impostos->pftaxafecopgr = $impostos->pftaxafecop;
        $impostos->taxafecop = $impostos->taxafecop;
        $impostos->modalidadebcicmspf = $impostos->pfmodalidadebcicms;
        $impostos->modalidadebcicmsstpf = $impostos->pfmodalidadebcicmsst;

        return $impostos;
    }

    private function formatValues($impostos)
    {
        $aKeys = $impostos->toArray();
        $specialFiels = $this->getSpecialPrecision();
        foreach ($aKeys as $key => $value) {
            if (!array_key_exists($key, $specialFiels)) continue;

            $precision = $specialFiels[$key];
            $prefix = empty($precision["prefix"]) ? "" : $precision["prefix"] . " ";
            $sufix = empty($precision["sufix"]) ? "" : " " . $precision["sufix"];
            $impostos->{$key} = $prefix . requestBaseCalcNfOracle($value, $precision["precision"]) . $sufix;
        }
        return $impostos;
    }

    public function modalidadeBcIcms()
    {
        return [
            ""  => "Selecione",
            "0" => "0 - Margem Valor Agregado (%)",
            "1" => "1 - Pauta(Valor)",
            "2" => "2 - Preço Tabelado Máx . (valor)",
            "3" => "3 - Valor da Operação"
        ];
    }

    public function modalidadeBcIcmsSt()
    {
        return [
            ""  => "Selecione",
            "0" => "0 - Preço Tabelado ou Máximo Sugerido",
            "1" => "1 - Lista Negativa (valor)",
            "2" => "2 - Lista Positiva (valor)",
            "3" => "3 - Lista Neutra (valor)",
            "4" => "4 - Margem Valor Agregado (%)",
            "5" => "5 - Pauta (valor)"
        ];
    }

    public function ajaxNatureza($id)
    {
        $cofins = Nfcofins::find($id);
        $nat = Creditopiscofins::whereBetween('identificador', [2045, 2057])->get()->toArray(); # 2045 - 2057
        switch ($cofins->codigo) {
            case "02":
                $natreceita = Creditopiscofins::whereBetween('identificador', [2058, 2070])->get()->toArray(); # 2058 - 2070
                $natureza = array_merge($nat, $natreceita);
                return $natureza;
                break;
            case "03":
                $natreceita = Creditopiscofins::where('parent_identificador', '3000')->get()->toArray(); # parent 3000
                $natureza = array_merge($nat, $natreceita);
                return $natureza;
                break;
            case "04":
                $nat = Creditopiscofins::where('parent_identificador', '2000')->get()->toArray(); # parent 2000
                $natreceita = Creditopiscofins::where('parent_identificador', '3000')->get()->toArray(); # parent 3000
                $natureza = array_merge($nat, $natreceita);
                return $natureza;
                break;
            case "05":
                $natreceita = Creditopiscofins::where('parent_identificador', '4000')->get()->toArray(); # parent 4000
                return $natreceita;
                break;
            case "06":
                $natreceita = Creditopiscofins::where('parent_identificador', '5000')->get()->toArray(); # parent 5000
                return $natreceita;
                break;
            case "07":
                $natreceita = Creditopiscofins::where('parent_identificador', '6000')->get()->toArray(); # parent 6000
                return $natreceita;
                break;
            case "08":
                $natreceita = Creditopiscofins::where('parent_identificador', '7000')->get()->toArray(); # parent 7000
                return $natreceita;
                break;
            case "09":
                $natreceita = Creditopiscofins::where('parent_identificador', '8000')->get()->toArray(); # parent 8000
                return $natreceita;
                break;
        }
    }

    private function empGruDefinition()
    {
        //id da empresa padrão
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //id do grupo da empresa padrão
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
    }

    private function basicDefition()
    {
        $this->empGruDefinition();
        //busca impostos
        $this->impostos = Nfimposto::where('empresa_id', $this->empresa_id)->get();
        //busca operações
        $this->operacoes = Nfoperacao::where('empresa_id', $this->empresa_id)
            ->where('aparecetela', "!=", 9)
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        //busca grupo fiscal
        $this->gruposfiscais = Nfgrupofiscal::where('empresa_id', $this->empresa_id)
            ->where('ativo', true)
            ->orderBy('id')->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    private function definition()
    {
        $this->empGruDefinition();
        //busca operações
        $this->operacoes = Nfoperacao::where('empresa_id', $this->empresa_id)
            ->where('aparecetela', "!=", 9)
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        //busca grupo fiscal
        $this->gruposfiscais = Nfgrupofiscal::where('empresa_id', $this->empresa_id)
            ->where('ativo', true)
            ->orderBy('id')->pluck('descricao', 'id')->prepend('Selecione', '');
        //busca cofins
        $this->nfcofins = DB::table('nfcofins')->select('*')->get()->sortBy('codigo');
        //busca PIS
        $this->nfpis = Nfpis::orderBy('codigo')->get();

        $nfecrt = Session::get('empresa_padrao')->nfecrt;
        //busca icms
        $icms = Nficms::orderBy('codigo')->get();
        $this->nficms = collect([]);

        foreach ($icms as $el) {
            $filter = (int) $nfecrt <= 2 ? (int) $el->codigo > 100 : $el->codigo <= 90;
            if ($filter)
                $this->nficms->push($el);
        }

        //busca estados
        $this->estados = Estado::all()->pluck('descricao', 'uf')->prepend('Selecione', '');
        //emite sped
        $this->spedemite = Session::get('empresa_padrao')->spedemite;
        //Busca tipo Cred
        $this->tipocred = Creditopiscofins::where('parent_identificador', '1')->get();
        //Busca tipo Base Cred
        $this->tipobasecalc = Creditopiscofins::where('parent_identificador', '1028')->get();
        //Busca Natureza Credito
        $this->natcred = Creditopiscofins::whereIn(
            'parent_identificador',
            [2000, 3000, 4000, 5000, 6000, 7000, 8000]
        )->get();
        // Beneficiario
        $this->beneficiario = Beneficiario::select(DB::raw("id, (codbenef || ' ' || descricao) as descricao"))
            ->whereRaw("datafim is null")
            ->orderBy("codbenef")
            ->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    private function getCodICMS()
    {
        return Nficms::orderBy("codigo")->get()->pluck('codigo', 'id')->toArray();
    }

    private function getDescMotDesonICMS($motOriginal, $cst)
    {
        $mots = Util::getMotDesonICMS(null, true);
        foreach ($mots as $mot) {
            if (in_array($cst, $mot['cst']))
                return array_key_exists($motOriginal, $mot['elements']) ? $mot['elements'][$motOriginal] : '';
        }
        return '';
    }

    private function getSpecialPrecision()
    {
        return [
            'nficmsaliq'            => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nficmsstaliq'          => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'mva'                   => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pfnficmsaliq'          => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pfaliqicmsdest'        => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pftaxafecop'           => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'taxafecop'             => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pfmva'                 => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'mvareduzido'           => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nfpisaliqcred'         => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nfcofinsaliqcred'      => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nfaliqdiferimento'     => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nfcofinsaliq'          => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nfcofinsbase'          => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pfnfcofinsaliq'        => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pfnfcofinsbase'        => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nfpisaliq'             => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nfpisbase'             => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pfnfpisaliq'           => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pfnfpisbase'           => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'aliqicmsst'            => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'pfnfpisaliqcred'       => ['precision' => 2, 'prefix' => '', 'sufix' => '%'],
            'nficmsalimono'         => ['precision' => 4, 'prefix' => '', 'sufix' => '%'],
            'pfnficmsalimono'       => ['precision' => 4, 'prefix' => '', 'sufix' => '%'],
            'nficmsbase'            => ['precision' => 3, 'prefix' => '', 'sufix' => '%'],
            'pfnficmsbase'          => ['precision' => 3, 'prefix' => '', 'sufix' => '%'],
            'nficmsbasest'          => ['precision' => 3, 'prefix' => '', 'sufix' => '%'],
            'estadosnficmsbase'     => ['precision' => 3, 'prefix' => '', 'sufix' => '%'],
            'estadonficmsbasest'    => ['precision' => 3, 'prefix' => '', 'sufix' => '%'],
            'nficmsalimono'         => ['precision' => 4, 'prefix' => 'R$', 'sufix' => ''],
            'pfnficmsalimono'       => ['precision' => 4, 'prefix' => 'R$', 'sufix' => ''],
        ];
    }

    private function formatFieldsToInsert($key, $value)
    {
        if (!array_key_exists($key, $this->getSpecialPrecision())) return $value;

        $value = insertNumeroDecimalOracle($value);

        return str_replace(
            " %",
            "",
            $value
        );
    }

    private function getFullUrl()
    {
        $redirectUrl = '?index=' . str_replace("&", 'extPar', \Request::fullUrl());
        return route('nfimposto.create') . $redirectUrl;
    }

    private function getUrlIndex()
    {
        $url = str_replace('extPar', "&", Input::get('index', ""));
        if (!$url) {
            $url = url('nfimposto');
        }
        return str_replace(url('/') . '/', '', $url);
    }

    private function validatePISCOFINS($impostos)
    {
        $impostos->load(['nfcofins', 'pfnfcofins', 'nfpis', 'pfnfpis']);

        $err = (!is_null($impostos->nfcofins) && $impostos->nfcofins->codigo == "03")
            || (!is_null($impostos->pfnfcofins) && $impostos->pfnfcofins->codigo == "03")
            || (!is_null($impostos->nfpis) && $impostos->nfpis->codigo == "03")
            || (!is_null($impostos->pfnfpis) && $impostos->pfnfpis->codigo == "03");

        if ($err) {
            throw new Exception("Ocorreu um erro ao gravar os impostos, contate o suporte");
        }
    }

    private function validateCodBenef($impostos, $estado = false)
    {
        $cst_pf = $impostos["pfnficms_id"];
        if ($estado)
            $cst_pj = $impostos["nficms_id"];
        else
            $cst_pj = $impostos["nficms_id_pj"];

        $cst = [];
        array_push($cst, $cst_pf, $cst_pj);

        $csts = Nficms::whereIn("id", $cst)->select("id", "codigo")->get();

        $nao_msg_pf = "O Código do Beneficiário de Pessoa Física não pode ser informado quando a CSTs for 00 ou 10.";
        $obr_msg_pf = "O Código do Beneficiário de Pessoa Física é obrigatório.";
        $nao_msg_pj = "O Código do Beneficiário de Pessoa Jurídica não pode ser informado quando a CSTs for 00 ou 10.";
        $obr_msg_pj = "O Código do Beneficiário de Pessoa Jurídica é obrigatório.";
        if ($estado) {
            $uf_orig = strtoupper($impostos["origem_uf"]);
            $uf_dest = strtoupper($impostos["destino_uf"]);
            $nao_msg_pf = "O Código do Beneficiário de Pessoa Física no ICMS por Estado ($uf_orig a $uf_dest) " .
                "não pode ser informado quando a CSTs for 00 ou 10.";
            $obr_msg_pf = "O Código do Beneficiário de Pessoa Física no ICMS por Estado ($uf_orig a $uf_dest) é obrigatório.";
            $nao_msg_pj = "O Código do Beneficiário de Pessoa Jurídica no ICMS por Estado ($uf_orig a $uf_dest) " .
                "não pode ser informado quando a CSTs for 00 ou 10.";
            $obr_msg_pj = "O Código do Beneficiário de Pessoa Jurídica no ICMS por Estado ($uf_orig a $uf_dest) é obrigatório.";
        }

        $benef_pf = !empty($impostos["pfbeneficiario_id"]);
        $cst_pf = $csts->where("id", $cst_pf)->first();
        if (in_array($cst_pf->codigo, $this->cstSemCodBenef) && $benef_pf) {
            throw new \Exception($nao_msg_pf);
        } else if (!in_array($cst_pf->codigo, $this->cstSemCodBenef) && !$benef_pf) {
            throw new \Exception($obr_msg_pf);
        }

        $benef_pj = !empty($impostos["beneficiario_id"]);
        $cst_pj = $csts->where("id", $cst_pj)->first();
        if (in_array($cst_pj->codigo, $this->cstSemCodBenef) && $benef_pj) {
            throw new \Exception($nao_msg_pj);
        } else if (!in_array($cst_pj->codigo, $this->cstSemCodBenef) && !$benef_pj) {
            throw new \Exception($obr_msg_pj);
        }
    }
}
