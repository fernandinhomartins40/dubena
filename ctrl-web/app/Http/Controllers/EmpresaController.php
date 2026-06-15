<?php

namespace App\Http\Controllers;

use DB;
use App\Rua;
use Session;
use App\Menu;
use App\Regiao;
use App\Estado;
use App\Cidade;
use App\Bairro;
use App\Empresa;
use App\Menuuser;
use App\EmpresasGrupo;
use App\Empresaconfig;
use Illuminate\Http\Request;
use App\Http\Requests\EmpresaRequest;
use App\Repository\PedidoRepository;

class Empresacontroller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Empresa $empresa)
    {
        $this->authorize('view', $empresa);
        $user = \Auth::user();
        $support = $user->support;
        if ($support) {
            $Empresas = Empresa::select('id', 'nome_informal', 'ativo')->get();
        } else {
            $Empresas = Empresa::join('empresa_user as empu', 'empu.empresa_id', 'empresas.id')
                            ->where('empu.user_id', $user->id)
                            ->select('id', 'nome_informal', 'ativo')->get();
        }
        return view('empresas.empresas', compact('Empresas', 'support'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Empresa $empresa)
    {
        $this->authorize('create', $empresa);
        $this->authorize('support', $empresa);
        $support = \Auth::user()->support;
        $grupos = EmpresasGrupo::where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id');
        $regioes = Regiao::where(['grupo_id' => Session::get('empresa_padrao')->grupo_id, 'ativo' => 1])->pluck('descricao',
                        'id')->prepend('Selecione', '');
        $cidades = [];
        $estados = Estado::all()->pluck('descricao', 'uf');
        $bairros = [];
        $ruas = [];
        $contBairros = [];
        $contestado = Estado::all()->pluck('descricao', 'uf');
        $contruas = [];
        $nfetiposemissao = $this->getSelects('nfeemissao');
        $nfetiposambiente = $this->getSelects('nfeambiente');
        $nfecrt = $this->getSelects('nfecrt');
        $nfemodelo = $this->getSelects('nfemodelo');
        $nfcetiposemissao = $this->getSelects('nfceemissao');
        $nfcetiposambiente = $this->getSelects('nfceambiente');
        $nfcecrt = $this->getSelects('nfcecrt');
        $nfcemodelo = $this->getSelects('nfcemodelo');
        $spedperfils = $this->getSelects('spedperfis');
        $spedatividades = $this->getSelects('spedatividades');
        $sattipoambiente = $this->getSelects('satambiente');
        $spedincidenciatributarias = $this->getSelects('spedincidencia');
        $spedapropriacaocreditos = $this->getSelects('spedcreditos');
        $spedtipocontribuicao = $this->getSelects('spedtipo');
        $spedregimecumulativos = $this->getSelects('regime');
        $nfeemitemodelos = $this->getSelects('nfeemitemodelos');
        $cep = "";

        return view('empresas.empresa_form',
                compact('grupos', 'cidades', 'estados', 'bairros', 'nfetiposemissao',
                        'nfetiposambiente', 'nfecrt', 'nfemodelo', 'nfcetiposemissao',
                        'nfcetiposambiente', 'nfcecrt', 'nfcemodelo', 'spedperfils',
                        'spedatividades', 'spedincidenciatributarias', 'spedapropriacaocreditos',
                        'spedtipocontribuicao', 'spedregimecumulativos', 'ruas', 'contruas',
                        'contBairros', 'contestado', 'cep', 'regioes', 'support', 'nfeemitemodelos', 'sattipoambiente'));
    }

    public function change($id)
    {
        $empresa_anterior = Session::get('empresa_padrao');
        if ($id != $empresa_anterior->id) {
            $user = \Auth::user();
            $empresa = (object) Empresa::where('id', $id)->get()->first()->toArray();
            $config = (object) Empresaconfig::getForSession($id);

            Session::forget('empresa_padrao');
            Session::put('empresa_padrao', $empresa);

            $permissions = Menuuser::join('menus', 'menuusers.menu_id', 'menus.id')
                    ->where(['menuusers.user_id' => $user->id, 'menuusers.empresa_id' => $empresa->id])
                    ->get();
            $menu = Menu::menus();
            $empresas_id = PedidoRepository::getEmpresas();
            $empresas_user = DB::table('empresa_user')->where('user_id', $user->id)
                ->pluck('empresa_id');

            Session::forget('empresas_permitidas');
            Session::forget('empresa_config');
            Session::forget('empresas_user');
            Session::forget('permissoes');
            Session::forget('menu');
            Session::put('empresas_permitidas', $empresas_id);
            Session::put('empresas_user', $empresas_user);
            Session::put('permissoes', $permissions);
            Session::put('empresa_config', $config);
            Session::put('menu', $menu);
        }
        return redirect()->intended('home');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(EmpresaRequest $request, Empresa $emp)
    {
        $this->authorize('create', $emp);
        $data = $request->all();

        DB::beginTransaction();
        try {
            $extras = $this->dadosExtras($data);
            $data = array_merge($data, $extras);
            $img = explode(',', $data["logo"]);
            $empresa = Empresa::create($data);
            $this->updateMatriz($data, $empresa->id);
            saveLogoNfeStorage(isset($img[1]) ? $img[1] : $img[0], $empresa);

            $nf = $this->saveCertNf($request, $empresa);
            if (!$nf) {
                throw new \Exception("Por favor, informe uma senha para o certificado digital.");
            }

            if (count($img) >= 2) {
                $imga = base64_decode($img[1]);
                \App\Helpers\BlobWriter::update('empresas', $empresa->id, 'logoimg', $imga);
            }
        } catch (\Exception $e) {
            DB::rollback();
            $error = getErrorsException($e);
            return redirect('empresa/create')
                            ->withErrors($error)
                            ->withInput();
        }
        DB::commit();
        return \Redirect::route('empresa.index')->withMessageSuccess('Empresa cadastrada com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Empresa $empresa)
    {
        $Empresa = Empresa::find($id);
        $this->authorize('view', $Empresa);
        $Empresa['bairroDesc'] = $Empresa->bairro->descricao;
        $Empresa['cidadeDesc'] = $Empresa->cidade->descricao;
        if ($Empresa->rua != null)
            $Empresa['ruaDesc'] = $Empresa->rua->descricao;
        Session::put('empresa_padrao', $Empresa);

        $grupos = EmpresasGrupo::where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id');
        $regioes = Regiao::where(['grupo_id' => Session::get('empresa_padrao')->grupo_id, 'ativo' => 1])->pluck('descricao',
                        'id')->prepend('Selecione', '');
        $estados = Estado::all()->pluck('descricao', 'uf');
        $sattipoambiente = $this->getSelects('satambiente');

        if ($Empresa->{'contuf'} !== null) {
            $contestado = Estado::find($Empresa->{'contuf'})->pluck('descricao', 'uf');
        } else {
            $contestado = Estado::all()->pluck('descricao', 'uf');
        }

        $estado = Estado::find($Empresa->{'uf'});
        $cidades = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orWhere('grupo_id',
                        null)->orderby('descricao')->pluck('descricao', 'id');
        $cidade = Cidade::find($Empresa->{'cidade_id'});
        if ($Empresa->{'contcidade_id'} !== null) {
            $contcidades = Cidade::find($Empresa->{'contcidade_id'})->pluck('descricao', 'id');
        }
        $bairros = Bairro::where([['cidade_id', $cidade->id], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao',
                'id');
        $contbairros = Bairro::where([['cidade_id', $cidade->id], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao',
                'id');
        $ruas = Rua::where([['cidade_id', $Empresa->{'cidade_id'}], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao',
                'id');
        $contruas = Rua::where([['cidade_id', $Empresa->{'contcidade_id'}], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao',
                'id');
        if ($Empresa->contingenciadatahora != null) {
            $Empresa["contingenciadatahora"] = requestDataOracle($Empresa->contingenciadatahora);
        } else {
            $Empresa["contingenciadatahora"] = "";
        }
        $nfetiposemissao = $this->getSelects('nfeemissao');
        $nfetiposambiente = $this->getSelects('nfeambiente');
        $nfecrt = $this->getSelects('nfecrt');
        $nfemodelo = $this->getSelects('nfemodelo');
        $nfcetiposemissao = $this->getSelects('nfceemissao');
        $nfcetiposambiente = $this->getSelects('nfceambiente');
        $nfcecrt = $this->getSelects('nfcecrt');
        $nfcemodelo = $this->getSelects('nfcemodelo');
        $spedperfils = $this->getSelects('spedperfis');
        $spedatividades = $this->getSelects('spedatividades');
        $spedincidenciatributarias = $this->getSelects('spedincidencia');
        $spedapropriacaocreditos = $this->getSelects('spedcreditos');
        $spedtipocontribuicao = $this->getSelects('spedtipo');
        $spedregimecumulativos = $this->getSelects('regime');
        $Empresa->telefone1 = str_replace(['(', ')', '-', ' '], '', $Empresa->telefone1);
        $Empresa->telefone2 = str_replace(['(', ')', '-', ' '], '', $Empresa->telefone2);
        $nfeemitemodelos = $this->getSelects('nfeemitemodelos');

        $Empresa->nfcevalorlimite = requestNumeroDecimalOracle($Empresa->nfcevalorlimite);
        $Empresa->nfecreditosimplesnacional = requestPercentualOracle($Empresa->nfecreditosimplesnacional);
        $Empresa->capacidadearmazenamento = requestPesoInteiroOracle($Empresa->capacidadearmazenamento);

        $show = true;
        return view('empresas.empresa_form',
                compact('Empresa', 'show', 'grupos', 'cidades', 'bairros', 'estados', 'contestado',
                        'estado', 'nfetiposemissao', 'nfetiposambiente', 'nfecrt', 'nfemodelo',
                        'nfcetiposemissao', 'nfcetiposambiente', 'nfcecrt', 'nfcemodelo',
                        'spedperfils', 'spedatividades', 'spedincidenciatributarias',
                        'spedapropriacaocreditos', 'spedtipocontribuicao', 'spedregimecumulativos',
                        'ruas', 'contruas', 'contbairros', 'contcidades', 'regioes',
                        'nfeemitemodelos', "sattipoambiente")); //->withEmpresasGrupo($EmpresasGrupo);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $Empresa = Empresa::find($id);
        $this->authorize('update', $Empresa);
        $support = \Auth::user()->support;
        $Empresa['bairroDesc'] = $Empresa->bairro->descricao;
        $Empresa['cidadeDesc'] = $Empresa->cidade->descricao;
        if ($Empresa->rua != null)
            $Empresa['ruaDesc'] = $Empresa->rua->descricao;
        Session::put('empresa_padrao', $Empresa);

        $grupos = EmpresasGrupo::where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id');
        $estados = Estado::all()->pluck('descricao', 'uf');
        $regioes = Regiao::where(['grupo_id' => Session::get('empresa_padrao')->grupo_id, 'ativo' => 1])->pluck('descricao',
                        'id')->prepend('Selecione', '');

        if ($Empresa->{'contuf'} !== null) {
            $contestado = Estado::find($Empresa->{'contuf'})->pluck('descricao', 'uf');
        } else {
            $contestado = Estado::all()->pluck('descricao', 'uf');
        }
        $estado = Estado::find($Empresa->{'uf'});
        $cidades = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orWhere('grupo_id',
                        null)->orderby('descricao')->pluck('descricao', 'id');
        $cidade = Cidade::find($Empresa->{'cidade_id'});

        $contcidades = [];
        if ($Empresa->{'contcidade_id'} !== null) {
            $contcidades = Cidade::find($Empresa->{'contcidade_id'})->pluck('descricao', 'id');
        }
        $bairros = Bairro::where([['cidade_id', $cidade->id], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao',
                'id');
        $contbairros = Bairro::where([['cidade_id', $cidade->id], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao',
                'id');

        $ruas = Rua::where([['cidade_id', $Empresa->{'cidade_id'}], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao',
                'id');
        $contruas = Rua::where([['cidade_id', $Empresa->{'contcidade_id'}], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao',
                'id');

        if ($Empresa->contingenciadatahora != null) {
            $Empresa["contingenciadatahora"] = requestDataOracle($Empresa->contingenciadatahora); // $dt[2] . "/" . $dt[1] . "/" . $dt[0] . " " . $dthr[1];
        } else {
            $Empresa["contingenciadatahora"] = "";
        }

        $nfetiposemissao = $this->getSelects('nfeemissao');
        $nfetiposambiente = $this->getSelects('nfeambiente');
        $nfecrt = $this->getSelects('nfecrt');
        $nfemodelo = $this->getSelects('nfemodelo');
        $nfcetiposemissao = $this->getSelects('nfceemissao');
        $nfcetiposambiente = $this->getSelects('nfceambiente');
        $sattipoambiente = $this->getSelects('satambiente');
        $nfcecrt = $this->getSelects('nfcecrt');
        $nfcemodelo = $this->getSelects('nfcemodelo');
        $spedperfils = $this->getSelects('spedperfis');
        $spedatividades = $this->getSelects('spedatividades');
        $spedincidenciatributarias = $this->getSelects('spedincidencia');
        $spedapropriacaocreditos = $this->getSelects('spedcreditos');
        $spedtipocontribuicao = $this->getSelects('spedtipo');
        $spedregimecumulativos = $this->getSelects('regime');
        $nfeemitemodelos = $this->getSelects('nfeemitemodelos');

        $cep = $Empresa->cep;
        $Empresa->telefone1 = str_replace(['(', ')', '-', ' '], '', $Empresa->telefone1);
        $Empresa->telefone2 = str_replace(['(', ')', '-', ' '], '', $Empresa->telefone2);

        $Empresa->nfcevalorlimite = requestNumeroDecimalOracle($Empresa->nfcevalorlimite);
        $Empresa->nfecreditosimplesnacional = requestPercentualOracle($Empresa->nfecreditosimplesnacional);
        $Empresa->capacidadearmazenamento = requestPesoInteiroOracle($Empresa->capacidadearmazenamento);

        return view('empresas.empresa_form',
                compact(
                        'Empresa', 'grupos', 'cidades', 'bairros', 'estados', 'contestado',
                        'estado', 'nfetiposemissao', 'nfetiposambiente', 'nfecrt', 'nfemodelo',
                        'nfcetiposemissao', 'nfcetiposambiente', 'nfcecrt', 'nfcemodelo',
                        'spedperfils', 'spedatividades', 'spedincidenciatributarias',
                        'spedapropriacaocreditos', 'spedtipocontribuicao', 'spedregimecumulativos',
                        'ruas', 'contruas', 'contbairros', 'contcidades', 'cep', 'regioes',
                        'support', 'nfeemitemodelos', 'sattipoambiente'
                    )
                ); //->withEmpresasGrupo($EmpresasGrupo);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(EmpresaRequest $request, $id)
    {
        $Empresa = Empresa::find($id);
        $this->authorize('update', $Empresa);

        $data = $request->all();

        DB::beginTransaction();
        try {
            $data = $this->dadosExtras($data);

            $img = explode(',', $data["logo"]);

            $this->checarNfNumero($Empresa, $data);

            $Empresa->update($data);

            $this->updateMatriz($data, $Empresa->id);

            if ($Empresa->rua != null)
                $Empresa['ruaDesc'] = $Empresa->rua->descricao;


            Session::put('empresa_padrao', $Empresa);

            saveLogoNfeStorage(isset($img[1]) ? $img[1] : $img[0], $Empresa);

            $nf = $this->saveCertNf($request, $Empresa);

            if (!$nf) {
                throw new \Exception("Por favor, informe uma senha para o certificado digital.");
            }

            if (count($img) >= 2) {
                $imga = base64_decode($img[1]);
                \App\Helpers\BlobWriter::update('empresas', $id, 'logoimg', $imga);
            }
        } catch (\Exception $e) {
            DB::rollback();
            $error = getErrorsException($e);
            return redirect("empresa/$id/edit")
                            ->withErrors($error)
                            ->withInput();
        }
        DB::commit();
        return \Redirect::route('empresa.index')->withMessageSuccess('Empresa atualizada com sucesso!');
    }

    private function updateMatriz($data, $id)
    {
        if ($data['foi_matriz'] || $data['matriz'])
            Empresa::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->update(['matriz' => false]);
        if ($data['matriz'])
            Empresa::find($id)->update(['matriz' => true]);
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
            $Empresa = Empresa::find($id);
            $Empresa->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::route('empresa.index')->withMessageDanger('<br /><br />O registro não pôde ser excluído pois está sendo usado!');
        }
        DB::commit();
        return \Redirect::route('empresa.index');
    }

    public function form(Request $request)
    {
        // console.log($request);
        $EmpresasGrupo = EmpresasGrupo::all();
        return view('empresas.empresasgrupo_form', compact('EmpresasGrupo'));
    }

    public function carregaempresa($id)
    {
        $empresa = Empresa::find($id);
        unset($empresa->logoimg);
        unset($empresa->logo);
        if (isset($empresa->cidade)) {

        }
        if (isset($empresa->bairro)) {

        }
        if (isset($empresa->rua)) {

        }
        if (isset($empresa->estado)) {

        }
        return $empresa;
    }

    public function getMatrizGrupo($grupo_id)
    {
        $cidades = Empresa::where("grupo_id", $grupo_id)
                ->where('matriz', true)
                ->select(DB::raw("count(id) as count"));
        return $cidades->get()->pluck('count')->first();
    }

    /**
     * salva o certificado digital da empresa
     *
     * @param Request $request
     * @param Empresa $empresa
     * @return boolean
     */
    function saveCertNf($request, $empresa)
    {
        $file = $request->file('certificadodigital');
        if (is_null($file))
            return true;

        if (!$request->get('nfesenhapfx'))
            return false;

        $pathCertsFiles = getPathCertificateNFe();

        $filename = str_replace(['/', '.', '-'], '', $empresa->cnpj) . '.pfx';
        $file->move($pathCertsFiles, $filename);
        return true;
    }

    private function dadosExtras($data)
    {
        $data['ativo'] = isset($data["ativo"]) && $data["ativo"] == 1;
        $data['usasat'] = isset($data["usasat"]);
        if (! $data['usasat']) {
            $data["sattipoambiente"] = 2;
        }
        if ($data['nfeemitemodelos'] == '1') {
            $data["nfeemite"] = true;
        } elseif ($data['nfeemitemodelos'] == '2') {
            $data["nfceemite"] = true;
        } elseif ($data['nfeemitemodelos'] == '3') {
            $data["nfeemite"] = true;
            $data["nfceemite"] = true;
        } else {
            $data["nfeemite"] = false;
            $data["nfceemite"] = false;
        }
        $data["spedemite"] = isset($data["spedemite"]) && $data["spedemite"];

        if (isset($data['nfetipoemissao']) && $data['nfetipoemissao'] > 1) {
            $this->validateContingency($data['uf'], $data['nfetipoemissao']);
        }

        if (isset($data["nfceemite"]) && $data["nfceemite"]) {
            $data["nfcevalorlimite"] = insertNumeroDecimalOracle($data["nfcevalorlimite"]);
        }
        $data["nfecreditosimplesnacional"] = isset($data["nfecreditosimplesnacional"]) ? insertPercentualOracle($data["nfecreditosimplesnacional"]) : "";

        $data['contingenciaemissao'] = isset($data['contingenciaemissao']) && $data['contingenciaemissao'];

        $data["logo"] = str_replace('data:image/png;base64,', '', $data["logo"]);
        $data["logo"] = str_replace(' ', '+', $data["logo"]);
        $data["contingenciadatahora"] = isset($data["contingenciadatahora"]) ? insertDataOracle($data["contingenciadatahora"]) : null;
        $data["capacidadearmazenamento"] = conversaoPeso($data["capacidadearmazenamento"]);
        $latLong = buscaLatitudeLongitude($data['uf'], $data['cidade_id'], $data['bairro_id'],
                $data['rua_id'], $data['numero'], $data['cep']);
        if (isset($latLong->location->lat) && isset($latLong->location->lng)) {
            $data['latitude'] = $latLong->location->lat;
            $data['longitude'] = $latLong->location->lng;
        }
        //Valor fixo para nf-e
        $data['codigoibgepais'] = 1058;

        $data['matriz'] = isset($data['matriz']) && !empty($data['matriz']);

        if (array_key_exists('nfesenhapfx', $data) && mb_strlen($data['nfesenhapfx']) > 0) {
            $encoded = customCrypt($data['nfesenhapfx'], 6, "hash");
            $data['nfesenhapfx'] = $encoded;
        } else if (array_key_exists('nfesenhapfx', $data)) {
            unset($data['nfesenhapfx']);
        }
        $data["depd"] = isset($data["depd"]) && $data["depd"] == "1";
        $data["depr"] = isset($data["depr"]) && $data["depr"] == "1";
        $data["prt"] = isset($data["prt"]) && $data["prt"] == "1";
        $data["prr"] = isset($data["prr"]) && $data["prr"] == "1";
        $data["prd"] = isset($data["prd"]) && $data["prd"] == "1";
        $data['geraibscbs'] = isset($data["geraibscbs"]) && $data["geraibscbs"] == "1";
        return emptyToNull($data);
    }

    private function validateContingency($uf, $type)
    {
        $list = array(
            'AC' => 'SVCAN',
            'AL' => 'SVCAN',
            'AM' => 'SVCAN',
            'AP' => 'SVCRS',
            'BA' => 'SVCRS',
            'CE' => 'SVCRS',
            'DF' => 'SVCAN',
            'ES' => 'SVCRS',
            'GO' => 'SVCRS',
            'MA' => 'SVCRS',
            'MG' => 'SVCAN',
            'MS' => 'SVCRS',
            'MT' => 'SVCRS',
            'PA' => 'SVCRS',
            'PB' => 'SVCAN',
            'PE' => 'SVCRS',
            'PI' => 'SVCRS',
            'PR' => 'SVCRS',
            'RJ' => 'SVCAN',
            'RN' => 'SVCRS',
            'RO' => 'SVCAN',
            'RR' => 'SVCAN',
            'RS' => 'SVCAN',
            'SC' => 'SVCAN',
            'SE' => 'SVCAN',
            'SP' => 'SVCAN',
            'TO' => 'SVCAN'
        );

        if (isset($list[$uf])) {
            if (($list[$uf] === "SVCAN" && $type == 6) || ($list[$uf] === "SVCRS" && $type == 7) || $type == 4) {
                return true;
            } else {
                throw new \Exception("Tipo de emissão de NF-e não disponível para o UF da empresa");
            }
        } else {
            throw new \Exception("Estado não encontrado!");
        }
    }

    private function getSelects($which)
    {
        // Emissao
        //2 => '2 – Contingência FS',
        $nfetiposemissao = [
            1 => '1 – Normal',
            4 => '4 – Contingência EPEC',
            6 => '6 – Contingência SVC-AN',
            7 => '7 – Contingência SVC-RS'
        ];
        // Ambiente
        $nfetiposambiente = [
            1 => '1 - Produção',
            2 => '2 - Homologação'
        ];
        // CRT
        $nfecrt = [
            1 => '1 - Simples Nacional',
            2 => '2 - Simples Nacional - Excesso de Sublimite da Receita Bruta',
            3 => '3 - Regime Normal'
        ];
        // Modelo
        $nfemodelo = [
            55 => '55 - NF-e'
        ];
        // NFC-e Tipo Emissao
        $nfcetiposemissao = [
            1 => '1 - Normal',
            4 => '4 – Contingência EPEC',
            9 => '9 – OFF-LINE'
        ];
        // NFCE Tipos Ambiente
        $nfcetiposambiente = [
            1 => '1 - Produção',
            2 => '2 - Homologação'
        ];
        // SAT Tipos Ambiente
        $satambiente = [
            1 => '1 - Produção',
            2 => '2 - Homologação'
        ];
        // NFC-e CRT
        $nfcecrt = [
            1 => '1 - Simples Nacional',
            2 => '2 - Simples Nacional - Excesso de Sublimite da Receita Bruta',
            3 => '3 - Regime Normal'
        ];
        // NFC-e Modelo
        $nfcemodelo = [
            65 => '65 - NFC-e'
        ];
        // SPED Perfis
        $spedperfils = [
            'A' => 'A - Perfil A',
            'B' => 'B - Perfil B',
            'C' => 'C - Perfil C'
        ];
        // Sped Atividades
        $spedatividades = [
            0 => '0 - Industrial ou equiparado a industrial',
            1 => '1 - Outros'
        ];
        // Sped incidencia tributarias
        $spedincidenciatributarias = [
            1 => '1 - Regime não-cumulativo',
            2 => '2 - Regime Cumulativo',
            3 => '3 - Ambos'
        ];
        // Sped Apropriação Creditos
        $spedapropriacaocreditos = [
            1 => '1 - Apropriação Direta',
            2 => '2 - Rateio Proporcional'
        ];
        // Sped tipo Contribuicao
        $spedtipocontribuicao = [
            1 => '1 - Alíquota Básica',
            2 => '2 - Alíquotas Específicas'
        ];
        // Sped Regime Cumulativos
        $spedregimecumulativos = [
            1 => '1 - Regime de Caixa',
            2 => '2 – Regime de Competência - Escrituração consolidada',
            9 => '9 – Regime de Competência - Escrituração detalhada'
        ];
        // possibilidades de modelo de emissão de NF
        $nfeemitemodelos = [
            '' => 'Selecione',
            1  => "55 - NF-e",
            2  => "65 - NFC-e",
            3  => "Ambos",
        ];

        switch ($which) {
            case 'nfeemissao':
                return $nfetiposemissao;
                break;

            case 'nfeambiente':
                return $nfetiposambiente;
                break;

            case 'nfecrt':
                return $nfecrt;
                break;

            case 'nfemodelo':
                return $nfemodelo;
                break;

            case 'nfceemissao':
                return $nfcetiposemissao;
                break;

            case 'nfceambiente':
                return $nfcetiposambiente;
                break;

            case 'nfcecrt':
                return $nfcecrt;
                break;

            case 'nfcemodelo':
                return $nfcemodelo;
                break;

            case 'spedperfis':
                return $spedperfils;
                break;

            case 'spedatividades':
                return $spedatividades;
                break;

            case 'spedincidencia':
                return $spedincidenciatributarias;
                break;

            case 'spedcreditos':
                return $spedapropriacaocreditos;
                break;

            case 'spedtipo':
                return $spedtipocontribuicao;
                break;

            case 'nfeemitemodelos':
                return $nfeemitemodelos;
                break;

            case 'satambiente':
                return $satambiente;
                break;

            default:
                return $spedregimecumulativos;
                break;
        }
    }

    /**
     * Metodo para validar se o numero da NF foi alterado pela tela, se não foi retira do data
     * Caso tenha sido alterado compara com o registro atual da empresa se houver diferenca e
     * for menor joga erro para o usuario.
     *
     * Motivo de criacao caso uma nota seja criada e o numero alterado na empresa de outro local
     * e a tela de edicao estiver aberta nao deixar salvar um numero diferente.
     *
     * @param App\Empresa $empresa -> registro atual da empresa
     * @param array $data -> valores para edicao
     */
    private function checarNfNumero($empresa, &$data)
    {
        $isNfeProducao = isset($data["nfetipoambiente"]) && $data["nfetipoambiente"] == 1;
        $isNfceProducao = isset($data["nfcetipoambiente"]) && $data["nfcetipoambiente"] == 1;

        if ($isNfeProducao) {
            $numNfeEmp = $empresa->nfenumero;
            $numNfeDat = $data["nfenumero"];
            $numNfeAtu = $data["nfenumero_atual"];
            $campo = "nfenumero";
        } else {
            $numNfeEmp = $empresa->nfenumerohomologacao;
            $numNfeDat = $data["nfenumerohomologacao"];
            $numNfeAtu = $data["nfenumerohomologacao_atual"];
            $campo = "nfenumerohomologacao";
        }

        if ($numNfeAtu == $numNfeDat) {
            unset($data[$campo]);
        } else if ($numNfeEmp > $numNfeDat) {
            throw new \Exception(
                "O Número atual da NF-e está maior do que o recebido, isso irá causar duplicidade, " .
                "favor atualizar e alterar novamente. Número no Registro: $numNfeEmp, Recebido: $numNfeDat."
            );
        }

        if ($isNfceProducao) {
            $numNfceEmp = $empresa->nfcenumero;
            $numNfceDat = $data["nfcenumero"];
            $numNfceAtu = $data["nfcenumero_atual"];
            $campo = "nfcenumero";
        } else {
            $numNfceEmp = $empresa->nfcenumerohomologacao;
            $numNfceDat = $data["nfcenumerohomologacao"];
            $numNfceAtu = $data["nfcenumerohomologacao_atual"];
            $campo = "nfcenumerohomologacao";
        }

        if ($numNfceAtu == $numNfceDat) {
            unset($data[$campo]);
        } else if ($numNfceEmp > $numNfceDat) {
            throw new \Exception(
                "O Número atual da NFC-e está maior do que o recebido, isso irá causar duplicidade, " .
                "favor atualizar e alterar novamente. Número no Registro: $numNfceEmp, Recebido: $numNfceDat."
            );
        }

    }

}
