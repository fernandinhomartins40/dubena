<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use Exception;
use App\Conta;
use App\Setor;
use App\Cliente;
use App\Empresa;
use App\Nfoperacao;
use App\Planoconta;
use App\Centrocusto;
use App\Condicaopagamento;
use App\Empresaconfig;
use App\Enums\LogSenhaStatus;
use App\Pedidosituacao;
use App\Pedidooperacao;
use Illuminate\Http\Request;
use App\Helpers\Utils\NfUtil;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\EmpresaConfigRequest;
use App\Logsenha;
use App\Produto;
use App\Services\PixService;
use App\Veiculo;

class EmpresaconfigController extends Controller
{

    protected $empresa_id;
    protected $grupo_id;
    protected $empconfig;
    protected $nfoperacao;
    protected $planoconta;
    protected $centrocusto;
    protected $cliente;
    protected $setors;
    protected $contas;
    protected $status;
    protected $pedidooperacao;
    protected $errors = [];

    protected function addError($error)
    {
        array_push($this->errors, $error);
    }

    protected function getErrors()
    {
        return $this->errors;
    }

    public function index(Empresaconfig $ec)
    {
        $this->authorize('view', $ec);
        $this->definition();

        $centrocusto_descricao = "";
        $centrocusto_id = "";
        $planoconta_id = "";
        $planoconta_descricao = "";
        $pccartao_id = "";
        $pccartao_descricao = "";
        $pcreceitadesconto_id = "";
        $pcreceitadesconto_desc = "";
        $ccreceitadesc_id = "";
        $ccreceitadesc_desc = "";
        $pcrecetajuro_id = "";
        $pcreceitajuro_desc = "";
        $ccreceitajuros_id = "";
        $ccreceitajuros_desc = "";
        $pcdespesasdesconto_id = "";
        $pcdespesasdesconto_desc = "";
        $ccdespesasdesc_id = "";
        $ccdespesasdesc_desc = "";
        $pcdespesasjuro_id = "";
        $pcdespesasjuro_desc = "";
        $ccdespesasjuros_id = "";
        $ccdespesasjuros_desc = "";
        $ccvalegas_id = "";
        $ccvalegas_descricao = "";
        $pcvalegas_id = "";
        $pcvalegas_descricao = "";
        $pcfrete_id = "";
        $pcfrete_descricao = "";
        $ccfrete_id = "";
        $ccfrete_descricao = "";
        $cccartao_id = "";
        $cccartao_desc = "";
        $pcfretegp_id = "";
        $pcfretegp_descricao = "";
        $ccfretegp_id = "";
        $ccfretegp_descricao = "";

        $empconfig = $this->empconfig !== null ? $this->empconfig : null;
        if ($empconfig !== null) {
            $empconfig = $this->percentualConfig($empconfig);
            $centro_main = Centrocusto::where('empresa_id', $this->empresa_id)->get();
            $plano_main = Planoconta::where('grupo_id', $this->grupo_id)->get();

            $centrocusto_id = $empconfig->centrocusto_id;
            $centrocusto_descricao = $centro_main->where('id', $centrocusto_id)->pluck('descricao')->first();

            $planoconta_id = $empconfig->planoconta_id;
            $planoconta_descricao = $plano_main->where('id', $planoconta_id)->pluck('descricao')->first();

            $pccartao_id = $empconfig->pccartao_id; //cartao id
            $pccartao_descricao = $plano_main->where('id', $pccartao_id)->pluck('descricao')->first(); //cartao descricao

            $pcreceitadesconto_id = $empconfig->pcreceitadesconto_id; //PC receita desconto id
            $pcreceitadesconto_desc = $plano_main->where('id', $pcreceitadesconto_id)->pluck('descricao')->first(); //PC receita desconto descricao

            $ccreceitadesc_id = $empconfig->ccreceitasdescontos_id; //CC receita desconto id
            $ccreceitadesc_desc = $centro_main->where('id', $ccreceitadesc_id)->pluck('descricao')->first(); //CC receita desconto descricao

            $pcrecetajuro_id = $empconfig->pcrecetajuro_id; //PC receita jurto id
            $pcreceitajuro_desc = $plano_main->where('id', $pcrecetajuro_id)->pluck('descricao')->first(); //PC receita juro descricao

            $ccreceitajuros_id = $empconfig->ccreceitasjuros_id; //CC receita jurto id
            $ccreceitajuros_desc = $centro_main->where('id', $ccreceitajuros_id)->pluck('descricao')->first(); //CC receita juro descricao

            $pcdespesasdesconto_id = $empconfig->pcdespesasdesconto_id; //PC despesas desconto id
            $pcdespesasdesconto_desc = $plano_main->where('id', $pcdespesasdesconto_id)->pluck('descricao')->first(); //PC despesas desconto descricao

            $ccdespesasdesc_id = $empconfig->ccdespesasdescontos_id; //CC despesas desconto id
            $ccdespesasdesc_desc = $centro_main->where('id', $ccdespesasdesc_id)->pluck('descricao')->first(); //CC despesas desconto descricao

            $pcdespesasjuro_id = $empconfig->pcdespesasjuro_id; //PC despesas juro id
            $pcdespesasjuro_desc = $plano_main->where('id', $pcdespesasjuro_id)->pluck('descricao')->first(); //PC despesas juro descricao

            $ccdespesasjuros_id = $empconfig->ccdespesasjuros_id; //CC despesas juro id
            $ccdespesasjuros_desc = $centro_main->where('id', $ccdespesasjuros_id)->pluck('descricao')->first(); //CC despesas juro descricao

            $ccvalegas_id = $empconfig->ccvalegas_id; //centro custo vale gas id
            $ccvalegas_descricao = $centro_main->where('id', $ccvalegas_id)->pluck('descricao')->first(); //centro custo vale gas descricao

            $pcvalegas_id = $empconfig->pcvalegas_id; //plano conta vale gas id
            $pcvalegas_descricao = $plano_main->where('id', $pcvalegas_id)->pluck('descricao')->first(); //plano conta vale gas descricao

            $pcfrete_id = $empconfig->pcfrete_id; //planoconta frete id
            $pcfrete_descricao = $plano_main->where('id', $pcfrete_id)->pluck('descricao')->first(); //planoconta frete desc

            $ccfrete_id = $empconfig->ccfrete_id; //centrocusto frete id
            $ccfrete_descricao = $centro_main->where('id', $ccfrete_id)->pluck('descricao')->first(); //centrocusto frete desc

            $cccartao_id = $empconfig->cccartao_id; //centrocusto frete id
            $cccartao_desc = $centro_main->where('id', $cccartao_id)->pluck('descricao')->first(); //centrocusto frete desc

            $pcconvenio_id = $empconfig->pcconvenio_id; //planoconta convenio id
            $pcconvenio_descricao = $plano_main->where('id', $pcconvenio_id)->pluck('descricao')->first(); //planoconta convenio desc

            $ccconvenio_id = $empconfig->ccconvenio_id; //centrocusto convenio id
            $ccconvenio_descricao = $centro_main->where('id', $ccconvenio_id)->pluck('descricao')->first(); //centrocusto convenio desc

            $pcfreteconvenio_id = $empconfig->pcfreteconvenio_id; //planoconta frete convenio id
            $pcfreteconvenio_descricao = $plano_main->where('id', $pcfreteconvenio_id)->pluck('descricao')->first(); //planoconta frete convenio desc

            $ccfreteconvenio_id = $empconfig->ccfreteconvenio_id; //centrocusto frete convenio id
            $ccfreteconvenio_descricao = $centro_main->where('id', $ccfreteconvenio_id)->pluck('descricao')->first(); //centrocusto frete convenio desc

            $pcfretegp_id = $empconfig->pcfretegp_id; //planoconta frete id
            $pcfretegp_descricao = $plano_main->where('id', $pcfretegp_id)->pluck('descricao')->first(); //planoconta frete desc

            $ccfretegp_id = $empconfig->ccfretegp_id; //centrocusto frete id
            $ccfretegp_descricao = $centro_main->where('id', $ccfretegp_id)->pluck('descricao')->first(); //centrocusto frete desc
        }

        $nfoperacao = Nfoperacao::where(['empresa_id' => $this->empresa_id])
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = Planoconta::menuspermissoesAll();
        $ccvalegas = $centrocustos;
        $pcvalegas = $planocontas;

        $pcdespesasdesconto = $planocontas;
        $ccdespesasdesc = $centrocustos;

        $pcdespesasjuro = $planocontas;
        $ccdespesasj = $centrocustos;

        $pcreceitadesconto = $planocontas;
        $ccreceitadesc = $centrocustos;

        $pcreceitajuro = $planocontas;
        $ccreceitaj = $centrocustos;

        $pccartao = $planocontas;
        $cccartao = $centrocustos;
        $setors = Setor::where(['ativo' => 1, 'empresa_id' => $this->empresa_id])
            ->orderBy('id')->pluck('descricao', 'id');
        $contas = Conta::where(['ativo' => 1, 'empresa_id' => $this->empresa_id])
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $status = Pedidosituacao::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $oppedido = Pedidooperacao::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $transportadoras = DB::table('clientes c')->where('empresa_id', $this->empresa_id)
            ->where('transportador', true)->select('id', 'nome')->pluck('nome', 'id')->prepend('Selecione', '');
        $produtos = Produto::where(['empresa_id' => $this->empresa_id, 'ativo' => 1])->whereNotNull('tipo_glp')
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $controle = $this->controleKm();
        $empresaemitenfe = isset($empconfig->empresa->nfeemite) ? $empconfig->empresa->nfeemite : '';
        $empresaemitenfce = isset($empconfig->empresa->nfceemite) ? $empconfig->empresa->nfceemite : '';

        $comprador = $this->getPresencaFrete('comprador')->prepend('Selecione', '');
        $frete = $this->getPresencaFrete('frete')->prepend('Selecione', '');
        $pcfrete = $planocontas;
        $ccfrete = $centrocustos;
        $matriz = Session::get('empresa_padrao')->matriz;
        $cfop = Nfoperacao::where([['empresa_id', Session::get('empresa_padrao')->id]])
            ->whereIn('aparecetela', [9])->orderBy('cfop', 'asc')->pluck('descricao', 'id')->prepend('Selecione', '');

        $contasboleto = Conta::where(['ativo' => 1, 'empresa_id' => $this->empresa_id, "boletoemite" => 1])
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');

        $condicaopagamentosboleto = Condicaopagamento::where(['ativo' => 1, 'empresa_id' => $this->empresa_id, 'tipo' => 1])
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');

        $condicaopagamentos = Condicaopagamento::where(['ativo' => 1, 'empresa_id' => $this->empresa_id, 'ativo' => 1])
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');            

        $veiculos = Veiculo::where(['ativo' => 1, 'empresa_id' => $this->empresa_id])
            ->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');

        if (isset($empconfig->client_id)) {
            unset($empconfig->client_id);
            unset($empconfig->client_secret);
        }

        if (isset($empconfig->chavepix)) {
            unset($empconfig->chavepix);
        }

        $empconfig->valorfretegp = requestNumeroDecimalOracle($empconfig->valorfretegp);

        return view('empresaconfig.empresaconfig')->with(compact(
            'empconfig',
            'nfoperacao',
            'planocontas',
            'centrocustos',
            'setors',
            'centrocusto_descricao',
            'centrocusto_id',
            'planoconta_descricao',
            'planoconta_id',
            'pccartao_id',
            'pccartao_descricao',
            'pcreceitadesconto_id',
            'pcreceitadesconto_desc',
            'oppedido',
            'pcrecetajuro_id',
            'pcreceitajuro_desc',
            'pcdespesasdesconto_id',
            'pcdespesasdesconto_desc',
            'pcdespesasjuro_id',
            'pcdespesasjuro_desc',
            'ccvalegas_id',
            'ccvalegas_descricao',
            'pcvalegas_id',
            'pcvalegas_descricao',
            'contas',
            'status',
            'pccartao',
            'ccvalegas',
            'pcvalegas',
            'pcdespesasdesconto',
            'pcdespesasjuro',
            'pcreceitadesconto',
            'pcreceitajuro',
            'controle',
            'empresaemitenfe',
            'empresaemitenfce',
            'comprador',
            'frete',
            'pcfrete',
            'ccfrete',
            'pcfrete_id',
            'pcfrete_descricao',
            'ccfrete_id',
            'ccfrete_descricao',
            'ccdespesasdesc',
            'ccdespesasj',
            'ccreceitadesc',
            'ccreceitaj',
            'ccreceitadesc_desc',
            'ccreceitajuros_desc',
            'ccdespesasdesc_desc',
            'ccdespesasjuros_desc',
            'matriz',
            'cccartao',
            'cccartao_id',
            'cccartao_desc',
            'cfop',
            'transportadoras',
            'contasboleto',
            'pcconvenio_id',
            'pcconvenio_descricao',
            'ccconvenio_id',
            'ccconvenio_descricao',
            'pcfreteconvenio_id',
            'pcfreteconvenio_descricao',
            'ccfreteconvenio_id',
            'ccfreteconvenio_descricao',
            'veiculos',
            'condicaopagamentosboleto',
            'pcfretegp_id', 
            'pcfretegp_descricao', 
            'ccfretegp_id', 
            'ccfretegp_descricao',
            'produtos',
            'condicaopagamentos'
        ));
    }

    public function store(EmpresaConfigRequest $request, Empresaconfig $ec)
    {
        $this->authorize('create', $ec);
        $this->definition();
        $data = $request->all();
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);
        $matriz = $data['matriz'] == 1;
        DB::beginTransaction();
        try {
            $this->checkPixFields($data);

            $empconfig = Empresaconfig::create($data);

            $this->checkPixwebhook(null, $empconfig);
            if ($matriz) {
                $this->matriz($data);
            } else {
                $empresamatriz = Empresa::where(['grupo_id' => $this->grupo_id, 'matriz' => 1])->get();
                if ($empresamatriz->isNotEmpty()) {
                    $replicado = $this->filial($empconfig, $empresamatriz->first());
                } else {
                    $this->addError("Não existe uma empresa Matriz no seu grupo, favor cadastrar alguma.");
                    $replicado = false;
                }
            }
            if (isset($replicado) && !$replicado) {
                throw new Exception(implode(', ', $this->getErrors()));
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return Redirect::to('/empresaconfig')
                ->withErrors($ex->getMessage())
                ->withInput();
        }
        DB::commit();
        $config = $empconfig->get();
        if (isset($config->senhamestre)) {
            unset($config->senhamestre);
        }

        if (isset($config->client_id)) {
            unset($config->client_id);
            unset($config->client_secret);
        }

        Session::forget('empresa_config');
        Session::put('empresa_config', $config);
        return Redirect::to('/home')->withMessageSuccess('Sucesso! As configurações foram salvas.');
    }

    public function update(EmpresaConfigRequest $request, $id, Empresaconfig $ec)
    {
        $this->authorize('update', $ec);
        $empconfig = Empresaconfig::where('id', $id)->get()->first();
        $this->authorize('igualdade', $empconfig);
        $this->definition();
        $data = $request->all();
        $data = $this->dadosExtras($data);
        $matriz = $data["matriz"] == 1;
        DB::beginTransaction();
        try {
            $oldKey = $empconfig->chavepix;
            $this->checkPixFields($data, $empconfig);

            $empconfig->update($data);

            $this->checkPixwebhook($oldKey, $empconfig);

            if ($matriz) {
                $replicado = $this->matriz($data);
            } else {
                $empresamatriz = Empresa::where(['grupo_id' => $this->grupo_id, 'matriz' => 1])->get();
                if ($empresamatriz->isNotEmpty()) {
                    $replicado = $this->filial($empconfig, $empresamatriz->first());
                } else {
                    $replicado = $this->addError("Não existe uma empresa Matriz no seu grupo, favor cadastrar alguma.");
                }
            }

            if (!isset($replicado) && !$replicado) {
                throw new Exception(implode(', ', $this->getErrors()));
            }
        } catch (Exception $ex) {
            DB::rollback();
            return Redirect::to('/empresaconfig')
                ->withErrors($ex->getMessage())
                ->withInput();
        }
        DB::commit();
        $config = $empconfig;
        if (isset($config->senhamestre)) {
            unset($config->senhamestre);
        }

        if (isset($config->client_id)) {
            unset($config->client_id);
            unset($config->client_secret);
        }

        Session::forget('empresa_config');
        Session::put('empresa_config', $config);

        return Redirect::to('/home')->withMessageSuccess('Sucesso! Mudanças nas configurações salvas.');
    }

    private function dadosExtras($data)
    {
        $data["percentualencargos"] = insertPercentualOracle($data["percentualencargos"]);
        $data["percentualprovisaodevedores"] = insertPercentualOracle($data["percentualprovisaodevedores"]);
        $data["percentualremuneracaocapital"] = insertPercentualOracle($data["percentualremuneracaocapital"]);
        $data["percentualdistribuicaoresul"] = insertPercentualOracle($data["percentualdistribuicaoresul"]);
        $data["pedidostatuspadrao"] = $data["pedidostatuspadrao"];
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["validaatraso"] = isset($data["validaatraso"]) && $data["validaatraso"];
        $data["pedidovalidacartao"] = isset($data["pedidovalidacartao"]) && $data["pedidovalidacartao"];
        $data["validagasbolso"] = isset($data["validagasbolso"]) && $data["validagasbolso"];
        $data["validapixentrega"] = isset($data["validapixentrega"]) && $data["validapixentrega"];
        $data["androidutiliza"] = isset($data["androidutiliza"]) && $data["androidutiliza"];
        $data["androidenviatodos"] = isset($data["androidenviatodos"]) && $data["androidenviatodos"];
        $data["validacordenadasentrega"] = isset($data["validacordenadasentrega"]) && $data["validacordenadasentrega"];
        $data["impressaoautomatica"] = isset($data["impressaoautomatica"]) && $data["impressaoautomatica"];
        $data["emailrequerautenticacao"] = isset($data["emailrequerautenticacao"]) && $data["emailrequerautenticacao"];
        $data["emailrequerconexaotls"] = isset($data["emailrequerconexaotls"]) && $data["emailrequerconexaotls"];
        $data["pedidocontrolatempoligacoes"] = isset($data["pedidocontrolatempoligacoes"]) && $data["pedidocontrolatempoligacoes"];
        $data["permiteestoquenegativo"] = isset($data["permiteestoquenegativo"]) && $data["permiteestoquenegativo"];
        $data["pedidoemitenfce"] = isset($data["pedidoemitenfce"]) && $data["pedidoemitenfce"];
        $data["pedidomovimenta"] = isset($data["pedidomovimenta"]) && $data["pedidomovimenta"];
        $data["valorfretegp"] = insertNumeroDecimalOracle($data["valorfretegp"]);
        if (! $data["emailsenha"])
            unset($data["emailsenha"]);
        else
            $data["emailsenha"] = customCrypt($data["emailsenha"], 8);
        return $data;
    }

    private function percentualConfig($data)
    {
        $data["percentualencargos"] = requestPercentualOracle($data["percentualencargos"]);
        $data["percentualprovisaodevedores"] = requestPercentualOracle($data["percentualprovisaodevedores"]);
        $data["percentualremuneracaocapital"] = requestPercentualOracle($data["percentualremuneracaocapital"]);
        $data["percentualdistribuicaoresul"] = requestPercentualOracle($data["percentualdistribuicaoresul"]);
        return $data;
    }

    public function senhaMestre(Empresaconfig $ec)
    {
        $this->authorize('viewSenhaMestre', $ec);
        $this->definition();
        $empconfig = isset($this->empconfig) ? $this->empconfig : null;
        if ($empconfig === null) {
            return Redirect::to('empresaconfig')->withMessageInfo('É necessario ter um config cadastrada para então cadastrar a senha mestre.');
        }
        return view('empresaconfig.novasenha')->with(compact('empconfig'));
    }

    public function changePassword(Request $request)
    {
        $this->definition();
        $empconfig = $this->empconfig;
        $senhamestre = $empconfig["senhamestre"];
        $data = $request->all();
        $id = $data["config_id"];
        $novasenha = Hash::make($data["senhanova"]);
        if (isset($id)) {
            if (isset($empconfig->senhamestre)) {
                $senhaatual = $data["senhaatual"];
                if (Hash::check($senhaatual, $senhamestre)) {
                    $empconfig = Empresaconfig::findOrFail($id);
                    $empconfig->update(["senhamestre" => $novasenha]);
                    return Redirect::to('/home')->withMessageSuccess('Sucesso! A senha mestre foi alterada.');
                } else {
                    return Redirect::to('/empresaconfig.senhamestre')->withMessageDanger('Erro! A senha está incorreta');
                }
            } else {
                $empconfig = Empresaconfig::findOrFail($id);
                $empconfig->update(["senhamestre" => $novasenha]);
                return Redirect::to('/home')->withMessageSuccess('Sucesso! Nova senha mestre salva.');
            }
        }
    }

    public function verificaSenhaMestre(Request $request, $empresa_id = null)
    {
        $this->definition();

        $data = $request->all();
        if ($empresa_id !== null) {
            $this->empconfig = Empresaconfig::where([
                'empresa_id' => $empresa_id,
            ])->get()->first();
        }

        if (isset($this->empconfig) && isset($this->empconfig->senhamestre)) {
            $senhamestre = $this->empconfig->senhamestre;
            if (isset($data["pass"])) {
                if (Hash::check($data["pass"], $senhamestre)) {
                    return $this->logSenha(LogSenhaStatus::Correto);
                    // return 'OK|';
                } else {
                    return $this->logSenha(LogSenhaStatus::Incorreto);
                    // return 'A senha está incorreta.';
                }
            } else {
                return 'Invalid Request';
            }
        } else {
            return 'OPS';
        }
    }

    public function controleKm()
    {
        return [
            ""  => "Selecione",
            "0" => "Abastecimento",
            "1" => "Entrada/Saida"
        ];
    }

    public function getPresencaFrete($which)
    {
        $presencascomprador = collect(NfUtil::getPrecencaComprador());

        $fretemodalidades = collect(NfUtil::getFreteModalidades());

        return $which == "frete" ? $fretemodalidades : $presencascomprador;
    }

    private function matriz($data)
    {
        $configs = Empresaconfig::where('grupo_id', $this->grupo_id)->get();
        if ($configs->isEmpty())
            return true;

        DB::beginTransaction();
        try {
            foreach ($configs as $config) {
                $config->pccartao_id = $data["pccartao_id"];
                $config->pcreceitadesconto_id = $data["pcreceitadesconto_id"];
                $config->pcrecetajuro_id = $data["pcrecetajuro_id"];
                $config->pcdespesasdesconto_id = $data["pcdespesasdesconto_id"];
                $config->pcdespesasjuro_id = $data["pcdespesasjuro_id"];
                $config->pcvalegas_id = $data["pcvalegas_id"];
                $config->pcfrete_id = $data["pcfrete_id"];
                $config->planoconta_id = $data["planoconta_id"];
                $config->save();
            }
        } catch (Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    private function filial($empconfig, $empresamatriz)
    {
        DB::beginTransaction();
        try {
            $empconfig->pccartao_id = $empresamatriz->pccartao_id;
            $empconfig->pcreceitadesconto_id = $empresamatriz->pcreceitadesconto_id;
            $empconfig->pcrecetajuro_id = $empresamatriz->pcrecetajuro_id;
            $empconfig->pcdespesasdesconto_id = $empresamatriz->pcdespesasdesconto_id;
            $empconfig->pcdespesasjuro_id = $empresamatriz->pcdespesasjuro_id;
            $empconfig->pcvalegas_id = $empresamatriz->pcvalegas_id;
            $empconfig->pcfrete_id = $empresamatriz->pcfrete_id;
            $empconfig->planoconta_id = $empresamatriz->planoconta_id;
        } catch (Exception $e) {
            DB::rollback();
            $this->addError($e->getMessage());
            return false;
        }
        DB::commit();
        return true;
    }

    private function definition()
    {
        //Pega id da empresa
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //Pega id do grupo
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //Pega as Configs ja salvas
        $this->empconfig = Empresaconfig::with('nfcecliente')->where(['empresa_id' => $this->empresa_id, 'grupo_id' => $this->grupo_id])->get()->first();
    }

    public function sendEmail(Request $request)
    {
        $data = $request->all();
        try {
            $send = [
                "subject" => $data["subject"],
                "content" => $data["content"],
                "to" => $data["to"],
            ];
            sendMail($send);
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        return "OK|";
    }

    private function checkPixFields(&$data, $empconfig = null)
    {
        if (!empty($data["client_id"])) {
            $data["client_id"] = encrypt($data["client_id"]);
            $data["client_secret"] = encrypt($data["client_secret"]);
        } else if (!is_null($empconfig)) {
            $data["client_id"] = $empconfig->client_id;
            $data["client_secret"] = $empconfig->client_secret;
        }

        if (!empty($data["chavepix"])) {
            $data["chavepix"] = encrypt($data["chavepix"]);
        } else if (!is_null($empconfig)) {
            $data["chavepix"] = $empconfig->chavepix;
        }
    }

    private function checkPixwebhook($oldKey, $empconfig)
    {
        if (env("APP_ENV") == "local") return;

        $service = new PixService(null, $empconfig);

        $service->processNewKey($empconfig->chavepix, $oldKey);
    }

    private function motivosLog($rota)
    {
        $motivos = [
            "cliente.edit" => "Alteração de cadastro de Cliente com comodato ativo.",
            "estoquerequisicao.index" => "Exclusão de Requisição de Estoque.",
            "estoquesetor.create" => "Novo Acerto de Estoque.",
            "consultaestoquesetor.index" => "Reabertura de Estoque.",
            "contasreceber.index" => "Cancelamento de títulos.",
            "pedido.edit" => "Edição de pedido fechado concluido.",
            "pedido.index" => "Edição de pedido fechado concluido.",
            "vendavalegas.index" => "Cancelamento de Venda de Vale Gás.",
        ];

        return isset($motivos[$rota]) ? $motivos[$rota] : null;
    }

    private function logSenha($status)
    {
        $user = \Auth::user();
        $rota = request()->get("rota");
        $motivoDesc = request()->get("motivo");
        $motivo = $this->motivosLog($rota);

        DB::beginTransaction();
        try {
            Logsenha::create([
                "empresa_id" => $this->empresa_id,
                "grupo_id" => $this->grupo_id,
                "user_id" => $user->id,
                "datahora" => now(),
                "rota" => $rota,
                "motivo" => $motivoDesc ? $motivoDesc : $motivo,
                "status" => $status,
            ]);

            DB::commit();

            return $status === LogSenhaStatus::Correto ? "OK|" : "A senha está incorreta.";
        } catch (Exception $ex) {
            DB::rollBack();

            return is_null($motivo) && empty($motivoDesc) ? "Motivo é obrigatório" : $ex->getMessage();
        }
    }
}
