<?php

namespace App\Http\Controllers;

use DB;
use Excel;
use Session;
use Redirect;
use App\User;
use App\Setor;
use App\Bairro;
use App\Cidade;
use App\Estado;
use App\Cliente;
use App\Empresa;
use App\Produto;
use App\Segmento;
use App\Services\CarbonCustom as Carbon;
use App\Tipopessoa;
use App\Empresaconfig;
use App\Clientecontatotipo;
use Illuminate\Http\Request;
use App\Clientecontatosituacao;
use App\Repository\SelectRepository;
use PHPExcel_Worksheet_MemoryDrawing;

class ReportclientesController extends Controller
{
    private $estados;
    private $grupo_id;
    private $empresa_id;
    private $tipopessoa;
    private $segmento;
    private $repositorio;

    public function __construct(SelectRepository $repositorio)
    {
        $this->estados = new Estado();
        $this->tipopessoa = new Tipopessoa();
        $this->segmento = new Segmento();
        $this->repositorio = $repositorio;
    }

    public function fornecedoresIndex()
    {
        $this->definition();
        $estados = $this->estados->selectEstados($this->empresa_id)->prepend("Selecione", "");
        $tipopessoa = $this->tipopessoa->selectTipoPessoa($this->grupo_id)->prepend("Selecione", "");
        $segmentos = $this->segmento->selectSegmento($this->grupo_id)->prepend("Selecione", "");
        $empresas = $this->repositorio->getEmpresasUser();
        $cep = "";
        return view('reports.clientes.fornecedores.fornecedores')->with(compact('estados', 'tipopessoa', 'segmentos', 'cep', 'empresas'));
    }

    public function fornecedoresPdf()
    {
        $this->definition();
        $get = $_GET;
        $buscafornecedores = $this->fornecedoresBusca($get, 'fornecedor');
        $fornecedores = $buscafornecedores["clientefornecedores"];
        $filtro = $buscafornecedores["filtro"];
        $titulo = 'Relatório de Fornecedores';
        return view('reports.clientes.fornecedores.fornecedores_pdf')->with(compact('titulo', 'fornecedores', 'filtro'));
    }

    public function clientesIndex()
    {
        $this->definition();
        $estados = $this->estados->selectEstados($this->empresa_id)->prepend("Selecione", "");
        $tipopessoa = $this->tipopessoa->selectTipoPessoa($this->grupo_id)->prepend("Selecione", "");
        $cep = "";

        $empresa_id = $this->empresa_id;
        $grupo_id = $this->grupo_id;
        $produtos = Produto::where('empresa_id', $this->empresa_id)->select('descricao', 'id')->orderBy('descricao')->get();
        $segmentos = Segmento::where('grupo_id', $this->grupo_id)->select('descricao', 'id')->orderBy('descricao')->get();
        $setores = Setor::where('empresa_id', $this->empresa_id)->select('descricao', 'id')->orderBy('descricao')->get();

        if (count($_GET) > 0)
            return $this->returnReportSetor($_GET, $pdf, $empresa_id, $produtos, $segmentos, $setores);

        $produtos = $produtos->pluck('descricao', 'id')->prepend('Selecione', '');
        $segmentos = $segmentos->pluck('descricao', 'id')->prepend('Selecione', '');
        $setores = $setores->pluck('descricao', 'id')->prepend('Selecione', '');
        $config = Session::get('empresa_config');
        return view('reports.clientes.clientes')->with(compact('estados', 'tipopessoa', 'segmentos', 'cep', 'produtos', 'segmentos', 'setores'));
    }

    public function clientesPdf()
    {
        $this->definition();
        $get = $_GET;
        $buscaclientes = $this->fornecedoresBusca($get, "cliente");
        $clientes = $buscaclientes["clientefornecedores"];
        $filtro = $buscaclientes["filtro"];
        $titulo = 'Relatório de Clientes';
        return view('reports.clientes.clientes_pdf')->with(compact('titulo', 'clientes', 'filtro'));
    }

    public function clientesAtivosPdf()
    {
        $this->definition();
        $get = $_GET;
        $buscaclientes = $this->fornecedoresBuscaAtivos($get, "cliente");
        $clientes = $buscaclientes["clientefornecedores"];
        $filtro = $buscaclientes["filtro"];
        $filtrosetor = $buscaclientes["filtrosetor"];
        $titulo = 'Relatório de Clientes';

        if ($get["exportar"] != 1) {
            return view('reports.clientes.clientesativos_pdf')->with(compact('titulo', 'clientes', 'filtro', 'filtrosetor'));
        } else {
            $this->clientesAtivosXls($clientes, $filtro, $filtrosetor, $titulo);
        }
    }

    private function fornecedoresBusca($filtro, $clientefornecedor)
    {
        $empresas_id = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
        $uf = $filtro["uf"] == "0" ? null : $filtro["uf"];
        $cidade = $filtro["cidade"] == 0 ? null : $filtro["cidade"];
        $segmento = $filtro["segmento"] == 0 ? null : $filtro["segmento"];
        $tipo = $filtro["tipo"] == 0 ? null : $filtro["tipo"];
        if ($clientefornecedor == "fornecedor") {
            $empresa = $filtro["empresa"] == 0 ? $empresas_id : explode(',', $filtro["empresa"]);
        } else {
            $empresa = null;
        }
        $ativo = $filtro["ativo"];
        $empresas = null;

        $fornecedores = DB::table('clientes')
            ->join('ruas', 'ruas.id', 'clientes.rua_id')
            ->join('bairros', 'bairros.id', 'clientes.bairro_id')
            ->join('cidades', 'cidades.id', 'clientes.cidade_id')
            ->join('estados', 'estados.uf', 'clientes.uf')
            ->leftJoin('tipopessoas', 'tipopessoas.id', 'clientes.tipopessoa_id')
            ->leftJoin('segmentos', 'segmentos.id', 'clientes.segmento_id')
            ->join('empresas', 'clientes.empresa_id', 'empresas.id')
            ->where([
                ['clientes.ativo', $ativo],
                [$clientefornecedor, 1]
            ]);

        if (!is_null($uf))
            $fornecedores = $fornecedores->where('clientes.uf', $uf);
        if (!is_null($cidade)) {
            if (str_contains($cidade, ",")) {
                $fornecedores = $fornecedores->whereRaw('clientes.cidade_id in ( ' . $cidade . ")");
            } else {
                $fornecedores = $fornecedores->where('clientes.cidade_id', $cidade);
            }
        }
        if (!is_null($tipo))
            $fornecedores = $fornecedores->where('clientes.tipopessoa_id', $tipo);
        if (!is_null($segmento))
            $fornecedores = $fornecedores->where('clientes.segmento_id', $segmento);
        if ($clientefornecedor == "fornecedor") {
            $empresas = count($empresa) == count($empresas_id) ? null :
                Empresa::whereIn('id', $empresa)->orderBy('nome_informal')->get()->pluck('nome_informal');
            $fornecedores = $fornecedores->whereIn('clientes.empresa_id', $empresa);
        } else {
            $fornecedores = $fornecedores->where('clientes.empresa_id', Session::get('empresa_padrao')->id);
        }
        $fornecedores = $fornecedores->select(
            'estados.descricao as estado',
            'segmentos.descricao as segmento',
            'clientes.id',
            'cidades.descricao as cidade',
            DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"),
            'clientes.nome as razao_social',
            'tipopessoas.descricao as tipo',
            'clientes.cnpj',
            'clientes.cpf',
            'empresas.nome_informal as empresa',
            // Oracle LISTAGG+ROWNUM -> Postgres STRING_AGG + subquery LIMIT.
            // (pega até 2 telefones do cliente e concatena com vírgula.)
            DB::raw("(select string_agg(tel.telefone, ', ') from
                        (select telefone from clientetelefones
                          where cliente_id = clientes.id order by telefone limit 2) tel) as telefone")
        )
            ->orderBy('empresa')->orderBy('clientes.uf')
            ->orderBy('cidade')->orderBy('razao_social')->get();

        $filtro = $uf == null ? "Filtro: Estado: todos" : "Filtro: Estado: $uf";
        $filtro = $cidade == null ? "$filtro, Cidades: todas" : "$filtro, Cidade(s): ";
        if (str_contains($cidade, ",")) {
            $filtro .= implode(', ', Cidade::whereRaw('id in (' . $cidade . ')')->pluck('descricao')->toArray());
        } else {
            $filtro .= Cidade::where('id', $cidade)->pluck('descricao')->first();
        }
        $filtro = $segmento == null ? "$filtro, Segmento: todos" : "$filtro, Segmento: " . Segmento::where('id', $segmento)->pluck('descricao')->first();
        $filtro = $tipo == null ? "$filtro, Tipo pessoa: todos" : "$filtro, Tipo pessoa: " . Tipopessoa::where('id', $tipo)->pluck('descricao')->first();
        if ($clientefornecedor == "fornecedor") {
            $filtro = $empresas == null ? "$filtro, Empresas: todas" : "$filtro, Empresas: " . implode(', ', $empresas->toArray());
            $filtro = $ativo == 0 ? "$filtro, Fornecedores inativos." : "$filtro, Fornecedores ativos.";
        } else {
            $filtro = $ativo == 0 ? "$filtro, Clientes inativos." : "$filtro, Clientes ativos.";
        }
        $retorno["clientefornecedores"] = $fornecedores;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    public function indexBairros()
    {
        $this->definition();
        $setores = $this->repositorio->getSetores()->prepend("Selecione", "");
        $segmentos = $this->repositorio->getSegmentos()->prepend("Selecione", "");
        $tipopessoas = $this->repositorio->getTipoPessoa()->prepend("Selecione", "");
        $cidades = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
            ->orWhere('grupo_id', null)->select('descricao', 'id')->orderBy('descricao')->get();
        $bairrosa = Bairro::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
            ->orderBy('cidade_id')->select('descricao', 'id', 'cidade_id')->orderBy('descricao')->get();
        $bairros = [];
        $bairros[""] = ["" => "Selecione"];
        foreach ($cidades as $cidade) {
            $s = $bairrosa->where('cidade_id', $cidade->id)->pluck('descricao', 'id')->toArray();
            if ($s) {
                $bairros[$cidade->descricao] = array_key_exists($cidade->descricao, $bairros) ? array_merge($s, $bairros[$bairros->descricao]) : $s;
            }
        }
        return view('reports.clientes.clientesbairros')->with(compact('setores', 'segmentos', 'tipopessoas', 'bairros'));
    }

    public function filtroClientesBairro()
    {
        $this->definition();
        $get = $_GET;
        $cli = $this->buscaClientesBairro($get);
        $titulo = "Relatório de Clientes por Bairro";
        $filtro = $cli["filtro"];
        $clientes = $cli["clientes"];
        return view('reports.clientes.clientesbairros_pdf')->with(compact('titulo', 'filtro', 'clientes'));
    }

    private function buscaClientesBairro($filtro)
    {
        $setor_id = $filtro["setor"] == "0" ? null : $filtro["setor"];
        $segmento_id = $filtro["segmento"] == "0" ? null : $filtro["segmento"];
        $tipo_id = $filtro["tipopessoa"] == "0" ? null : $filtro["tipopessoa"];
        $bairro_id = $filtro["bairro"] == "0" ? null : $filtro["bairro"];

        $clientesbairro = DB::table("clientes")->leftJoin('pedidos', 'pedidos.cliente_id', 'clientes.id')
            ->leftJoin('setors as setor', 'clientes.setor_id', 'setor.id')
            ->join('segmentos', 'clientes.segmento_id', 'segmentos.id')
            ->join('bairros', 'clientes.bairro_id', 'bairros.id')
            ->join('ruas', 'clientes.rua_id', 'ruas.id')
            ->join('cidades', 'clientes.cidade_id', 'cidades.id')
            ->where([
                ['clientes.empresa_id', $this->empresa_id],
                ['clientes.cliente', 1],
                ['clientes.ativo', 1]
            ]);
        if (!is_null($setor_id))
            $clientesbairro = $clientesbairro->where('clientes.setor_id', $setor_id);
        if (!is_null($segmento_id))
            $clientesbairro = $clientesbairro->where('clientes.segmento_id', $segmento_id);
        if (!is_null($tipo_id))
            $clientesbairro = $clientesbairro->where('clientes.tipopessoa_id', $tipo_id);
        if (!is_null($bairro_id))
            $clientesbairro = $clientesbairro->where('clientes.bairro_id', $bairro_id);

        $clientesbairro = $clientesbairro->select(
            'clientes.id',
            'clientes.nome',
            'bairros.id as bairro_id',
            'ruas.descricao as rua',
            'clientes.numero',
            DB::raw("max(datahoraprevisaoentrega) as ultimacompra"),
            'setor.descricao as setor',
            'segmentos.descricao as segmento',
            'bairros.descricao as bairro',
            // Oracle LISTAGG+ROWNUM -> Postgres STRING_AGG + subquery LIMIT.
            DB::raw("(select string_agg(tel.telefone, ', ') from" .
                " (select telefone from clientetelefones" .
                "  where cliente_id = clientes.id order by telefone limit 2) tel) as telefone"),
            'cidades.id as cidade_id',
            'cidades.descricao as cidade'
        )
            ->groupBy('clientes.id')->groupBy('clientes.nome')->groupBy('bairros.id')->groupBy('bairros.descricao')
            ->groupBy('ruas.descricao')->groupBy('clientes.numero')->groupBy('setor.descricao')
            ->groupBy('segmentos.descricao')->groupBy('cidades.id')->groupBy('cidades.descricao')
            ->orderBy('cidade')->orderBy('bairro')->orderBy('clientes.nome')->get();

        $clientes = collect([]);
        $cityanterior = null;
        $anterior = null;
        $count = 0;
        $countb = 0;
        $countbb = 0;
        foreach ($clientesbairro as $cliente) {
            if (!is_null($anterior) && $anterior != $cliente->bairro_id) {
                $objtotalcli = (object) array();
                $objtotalcli->total = $countb;
                $clientes->push($objtotalcli);
            }
            if (is_null($cityanterior) || $cityanterior != $cliente->cidade_id) {
                $objcity = (object) array();
                $objcity->cidade_id = $cliente->cidade_id;
                $objcity->cidade = $cliente->cidade;
                $clientes->push($objcity);
            }
            if (is_null($anterior) || $anterior != $cliente->bairro_id) {
                $objbairro = (object) array();
                $objbairro->bairro_id = $cliente->bairro_id;
                $objbairro->bairro = $cliente->bairro;
                $clientes->push($objbairro);
                $countbb++;
                $countb = 0;
            }
            $objnormal = (object) array();
            $objnormal->cliente_id = $cliente->id;
            $objnormal->cliente = $cliente->nome;
            $telefone = $cliente->telefone;
            $telefone = str_replace('(', '', $telefone);
            $telefone = str_replace(')', '', $telefone);
            $telefone = str_replace('-', '', $telefone);
            $objnormal->telefone = $telefone;
            $objnormal->ultimacompra = requestDataOracle($cliente->ultimacompra, false);
            $objnormal->endereco = "$cliente->rua, $cliente->numero.";
            $objnormal->setor = $cliente->setor;
            $objnormal->segmento = $cliente->segmento;
            $clientes->push($objnormal);
            $anterior = $cliente->bairro_id;
            $cityanterior = $cliente->cidade_id;
            $count++;
            $countb++;
            if ($count == count($clientesbairro)) {
                if ($countb > 1 && $countbb > 1) {
                    $objtotalcli = (object) array();
                    $objtotalcli->total = $countb;
                    $clientes->push($objtotalcli);
                }
                $objtotal = (object) array();
                $objtotal->totalgeral = $count;
                $clientes->push($objtotal);
            }
        }
        $filtro = $bairro_id == null ? "Filtros: Bairros: todos" : "Filtros: Bairro: " . Bairro::where('id', $bairro_id)->pluck('descricao')->first();
        $filtro = $setor_id == null ? $filtro .  ", Setores: todos" : $filtro .  ", Setor: " . Setor::where('id', $setor_id)->pluck('descricao')->first();
        $filtro = $segmento_id == null ? $filtro . ", Segmentos: todos" : $filtro . ", Segmento: " . Segmento::where('id', $segmento_id)->pluck('descricao')->first();
        $filtro = $tipo_id == null ? $filtro . ", Tipo de Pessoa: todos." : $filtro . ", Tipo de Pessoa: " . Tipopessoa::where('id', $tipo_id)->pluck('descricao')->first() . ".";
        $retorno["filtro"] = $filtro;
        $retorno["clientes"] = $clientes;
        return $retorno;
    }

    ///Interações com o Cliente
    public function indexInteracoes()
    {
        $this->definition();
        $empresas_id = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
        $emp = Empresa::whereIn('id', $empresas_id)->orderBy('nome_informal')->get();
        $empresas = $this->repositorio->getEmpresasUser();
        $setores = Setor::where([['ativo', 1]])->whereIn('empresa_id', $empresas_id)->orderBy('descricao')->get();
        $setor = [];
        foreach ($emp as $empresa) {
            $s = $setores->where('empresa_id', $empresa->id);
            $setor[$empresa->nome_informal] = $s->pluck('descricao', 'id')->toArray();
        }
        $segmentos = $this->repositorio->getSegmentos()->prepend('Selecione', '');
        $tipopessoa = $this->repositorio->getTipoPessoa()->prepend('Selecione', '');
        $situacao = Clientecontatosituacao::where(['ativo' => 1, 'grupo_id' => $this->grupo_id])->pluck('descricao', 'id')->prepend('Selecione', '');
        $tipos = Clientecontatotipo::where(['grupo_id' => $this->grupo_id, 'ativo' => 1])->pluck('descricao', 'id')->prepend('Selecione', '');
        $bairros = Bairro::where('grupo_id', $this->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        return view('reports.clientes.interacoes.interacoes')->with(compact('empresas', 'setor', 'segmentos', 'tipopessoa', 'situacao', 'tipos', 'bairros'));
    }

    public function interacoesFiltro()
    {
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"] == "1";
        $int = $this->buscaInteracoes($get);
        $titulo = "Relatório de Interações com Clientes";
        $interacoes = $int["clientes"];
        $filtro = $int["filtro"];
        if ($tipo) {
            return view('reports.clientes.interacoes.interacoes_pdf')->with(compact('titulo', 'filtro', 'interacoes'));
        } else {
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.clientes.interacoes.interacoes_pdf', compact('titulo', 'filtro', 'interacoes'));
            return $pdf->stream();
        }
    }

    private function buscaInteracoes($filtro)
    {
        $empresas_id = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $empresa_id = $filtro["empresa"] == "0" ? null : explode(',', $filtro["empresa"]);
        $setor_id = $filtro["setor"] == "0" ? null : explode(',', $filtro["setor"]);

        $interacoes = DB::table('clientecontatos as contatos')
            ->join('clientes', 'contatos.cliente_id', 'clientes.id')
            ->join('clientecontatotipos as tipos', 'contatos.tipo_id', 'tipos.id')
            ->join('clientecontatosituacaos as situacao', 'contatos.situacao_id', 'situacao.id')
            ->join('segmentos', 'clientes.segmento_id', 'segmentos.id')
            ->join('bairros', 'clientes.bairro_id', 'bairros.id')
            ->join('ruas', 'clientes.rua_id', 'ruas.id')
            ->join('empresas', 'clientes.empresa_id', 'empresas.id')
            ->whereBetween('contatos.datahora', [$datainicio, $datafim])
            ->where([
                ['clientes.ativo', 1]
            ]);
        $interacoes = is_null($empresa_id) ? $interacoes->whereIn('contatos.empresa_id', $empresas_id) : $interacoes->whereIn('contatos.empresa_id', $empresa_id);
        $interacoes = is_null($setor_id) ? $interacoes : $interacoes->whereIn('clientes.setor_id', $setor_id);
        $interacoes = $filtro["segmento"] == "0" ? $interacoes : $interacoes->where('clientes.segmento_id', $filtro["segmento"]);
        $interacoes = $filtro["pessoa"] == "0" ? $interacoes : $interacoes->where('clientes.tipopessoa_id', $filtro["pessoa"]);
        $interacoes = $filtro["situacao"] == "0" ? $interacoes : $interacoes->where('contatos.situacao_id', $filtro["situacao"]);
        $interacoes = $filtro["contato"] == "0" ? $interacoes : $interacoes->where('contatos.tipo_id', $filtro["contato"]);

        $interacoes = $interacoes->select(
            'clientes.empresa_id',
            'empresas.nome_informal as empresa',
            'contatos.id',
            'contatos.datahora',
            'situacao.descricao as situacao',
            'clientes.nome',
            'contatos.descricao',
            'contatos.acao',
            'tipos.descricao as tipo',
            'segmentos.descricao as segmento',
            DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco")
        )
            ->orderBy('empresa')->orderBy('contatos.datahora')->get();
        $clientes = collect([]);
        $anterior = null;
        $count = 0;
        foreach ($interacoes as $interacao) {
            if (!is_null($anterior) && $anterior != $interacao->empresa_id) {
                $wint = $interacoes->where('empresa_id', $anterior);
                $objtotal = (object) array();
                $objtotal->total = $wint->count();
                $clientes->push($objtotal);
            }
            if (is_null($anterior) || $anterior != $interacao->empresa_id) {
                $objempresa = (object) array();
                $objempresa->empresa_id = $interacao->empresa_id;
                $objempresa->empresa = $interacao->empresa;
                $clientes->push($objempresa);
            }
            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($interacao->datahora, false);
            $objnormal->tipo = $interacao->tipo;
            $objnormal->situacao = $interacao->situacao;
            $objnormal->cliente = $interacao->nome;
            $objnormal->endereco = $interacao->endereco;
            $objnormal->descricao = $interacao->descricao;
            $objnormal->acao = $interacao->acao;
            $objnormal->segmento = $interacao->segmento;
            $clientes->push($objnormal);
            $anterior = $interacao->empresa_id;
            $count++;
        }
        if ($count > 0) {
            $wint = $interacoes->where('empresa_id', $anterior);
            $objtotal = (object) array();
            $objtotal->total = $wint->count();
            $clientes->push($objtotal);
        }
        $filtr = "Filtro: Período " . requestDataOracle($datainicio, false) . " a " . requestDataOracle($datafim, false);
        $filtr = is_null($empresa_id) || count($empresas_id) == count($empresa_id) ? $filtr . ", Empresa(s): todas" : $filtr . ", Empresa(s): " . implode(', ', Empresa::whereIn('id', $empresa_id)->pluck('nome_informal')->toArray());
        $filtr = is_null($setor_id) ? "$filtr, Setor(es): todos" : "$filtr, Setor(es): " . implode(', ', Setor::whereIn('id', $setor_id)->pluck('descricao')->toArray());
        $filtr = $filtro["segmento"] == "0" ? "$filtr, Segmento: todos" : "$filtr, Segmento: " . Segmento::where('id', $filtro["segmento"])->pluck('descricao')->first();
        $filtr = $filtro["pessoa"] == "0" ? "$filtr, Tipo de Pessoa: todos" : "$filtr, Tipo de Pessoa: " . Tipopessoa::where('id', $filtro["pessoa"])->pluck('descricao')->first();
        $filtr = $filtro["contato"] == "0" ? "$filtr, Tipo de Contato: todos" : "$filtr, Tipo de Contato: " . Clientecontatotipo::where('id', $filtro["contato"])->pluck('descricao')->first();
        $filtr = $filtro["situacao"] == "0" ? "$filtr, Situação do Contato: todas." : "$filtr, Situação de Contato: " . Clientecontatosituacao::where('id', $filtro["situacao"])->pluck('descricao')->first();
        $retorno["filtro"] = $filtr;
        $retorno["clientes"] = $clientes;
        return $retorno;
    }

    private function definition()
    {
        //Busca id da empresa logada
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //Busca grupo da empresa logada
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //Set Empresa
        $this->repositorio->setEmpresa($this->empresa_id);
        //Set Grupo
        $this->repositorio->setGrupo($this->grupo_id);
    }

    public function segSetorProd($pdf = false)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $produtos = Produto::where('empresa_id', $empresa_id)->select('descricao', 'id')->orderBy('descricao')->get();
        $segmentos = Segmento::where('grupo_id', $grupo_id)->select('descricao', 'id')->orderBy('descricao')->get();
        $setores = Setor::where('empresa_id', $empresa_id)->select('descricao', 'id')->orderBy('descricao')->get();

        if (count($_GET) > 0)
            return $this->returnReportSetor($_GET, $pdf, $empresa_id, $produtos, $segmentos, $setores);

        $produtos = $produtos->pluck('descricao', 'id')->prepend('Selecione', '');
        $segmentos = $segmentos->pluck('descricao', 'id')->prepend('Selecione', '');
        $setores = $setores->pluck('descricao', 'id')->prepend('Selecione', '');

        return view('reports.clientes.segmento_setor_produto.segmento_setor_produto', compact('produtos', 'segmentos', 'setores'));
    }

    public function segSetorProdPDF()
    {
        return $this->segSetorProd(true);
    }

    private function returnReportSetor($filtros, $pdf, $empresa_id, $produtos, $segmentos, $setores)
    {
        $tipo = $filtros['tipo'];
        $produto_id = $filtros['produto_id'];
        $setor_id = $filtros['setor_id'];
        $segmento_id = $filtros['segmento_id'];
        $datainicio = Carbon::createFromFormat('d/m/Y', $filtros['datainicio'])->startOfDay();
        $datafim = Carbon::createFromFormat('d/m/Y', $filtros['datafim'])->endOfDay();

        $clientes = DB::table('clientes as cliente')
            ->rightJoin(
                DB::raw("(SELECT cliente_id, datahoraprevisaoentrega, id as pedido_id, valordesconto
                FROM pedidos
                WHERE datahoraprevisaoentrega
                BETWEEN TO_DATE('$datainicio', 'yyyy-mm-dd hh24:mi:ss') and TO_DATE('$datafim', 'yyyy-mm-dd hh24:mi:ss')
                and pedidosituacao_id in (SELECT id FROM pedidosituacaos WHERE fechadoconcluido = 1)
                group by cliente_id, datahoraprevisaoentrega, id, valordesconto) pedido"),
                'pedido.cliente_id',
                'cliente.id'
            )
            ->join('pedidoitems as item', 'item.pedido_id', 'pedido.pedido_id')
            ->join('ruas', 'ruas.id', 'cliente.rua_id')
            ->join('bairros', 'bairros.id', 'cliente.bairro_id')
            ->join('cidades', 'cidades.id', 'cliente.cidade_id')
            ->join('produtos as produto', 'produto.id', 'item.produto_id')
            ->leftJoin('segmentos as segmento', 'segmento.id', 'cliente.segmento_id')
            ->leftJoin('setors as setor', 'setor.id', 'cliente.setor_id')
            ->where('cliente.empresa_id', $empresa_id)
            ->select(
                DB::raw("(ruas.descricao || ',' || cliente.numero || ' - ' || bairros.descricao) as endereco"),
                'pedido.*',
                'cliente.segmento_id',
                'cliente.setor_id',
                'item.quantidade',
                'item.precovendatotal',
                'cliente.nome',
                'segmento.descricao as segmento',
                'produto.descricao as produto',
                'item.produto_id',
                'item.id as item_id',
                DB::raw("CASE WHEN setor.descricao IS NULL THEN 'ZZZZZZZ-SEMSETOR' ELSE setor.descricao END as setor")
            );

        $titulo = 'Relatório de Clientes por ';
        $filtro = "Período de " . $filtros['datainicio'] . " a " . $filtros['datafim']  . ";";
        switch ($tipo) {
            case '0':
                $titulo .= " Produto";
                $filtro .= " Produto: ";
                $filtro .= empty($produto_id) ? 'Todos;' : $produtos->where('id', $produto_id)->pluck('descricao')->first();
                empty($produto_id) ? '' : $clientes = $clientes->where('item.produto_id', $produto_id);
                $order = 'produto';
                break;
            case '1':
                $titulo .= "Segmento";
                $filtro .= " Segmento: ";
                $filtro .= empty($segmento_id) ? 'Todos;' : $segmentos->where('id', $segmento_id)->pluck('descricao')->first();
                empty($segmento_id) ? '' : $clientes = $clientes->where('cliente.segmento_id', $segmento_id);
                $order = 'segmento';
                break;
            default:
                $titulo .= "Setor";
                $filtro .= " Setor: ";
                $filtro .= empty($setor_id) ? 'Todos;' : $setores->where('id', $setor_id)->pluck('descricao')->first();
                $clientes = empty($setor_id) ? $clientes : $clientes->where('cliente.setor_id', $setor_id);
                $order = 'setor';
                break;
        }

        $clientes = $clientes->orderBy('datahoraprevisaoentrega', 'DESC')->get();
        $peds = [];
        $cli = collect([]);
        foreach ($clientes as $cliente) {
            $countClientes = $cli->filter(
                function ($cli) use ($cliente) {
                    $time = strtotime($cli->datahoraprevisaoentrega) != strtotime($cliente->datahoraprevisaoentrega);
                    $c_id = $cliente->cliente_id == $cli->cliente_id;
                    $prod = $cliente->produto_id == $cli->produto_id;
                    return $time && $c_id && $prod;
                }
            )->count();
            if ($countClientes == 0) {
                $objCliente = $clientes->where('cliente_id', $cliente->cliente_id)->where('produto_id', $cliente->produto_id)->max();
                $clienteComFiltro = $clientes->filter(function ($cli) use ($objCliente) {
                    $cliente_id = $objCliente->cliente_id == $cli->cliente_id;
                    $produto_id = $objCliente->produto_id == $cli->produto_id;
                    return $cliente_id && $produto_id;
                });

                $valortotal = $clientes->where('pedido_id', $cliente->pedido_id)->sum('precovendatotal');
                $precovendaItens = $clienteComFiltro->sum('precovendatotal');

                $objnormal = (object) array();
                $objnormal->endereco = $cliente->endereco;
                $objnormal->cliente_id = $cliente->cliente_id;
                $objnormal->datahoraprevisaoentrega = $cliente->datahoraprevisaoentrega;
                $objnormal->pedido_id = $cliente->pedido_id;
                $objnormal->desconto = ($precovendaItens / $valortotal) * $cliente->valordesconto;
                $objnormal->segmento_id = $cliente->segmento_id;
                $objnormal->setor_id = $cliente->setor_id;
                $objnormal->quantidade = $clienteComFiltro->sum('quantidade');
                $objnormal->precovendatotal = $precovendaItens - $objnormal->desconto;
                $objnormal->nome = $cliente->nome;
                $objnormal->segmento = $cliente->segmento;
                $objnormal->produto = $cliente->produto;
                $objnormal->produto_id = $cliente->produto_id;
                $objnormal->item_id = $cliente->item_id;
                $objnormal->setor = $cliente->setor;

                if (! in_array($objCliente->pedido_id, $peds)) {
                    $cli->push($objnormal);
                    array_push($peds, $objCliente->pedido_id);
                }
            }
        }

        $clientes = $cli->unique(function ($value) {
            return $value->datahoraprevisaoentrega . $value->cliente_id . $value->produto_id;
        })->sortBy($order);

        $produtoAnterior = -1;
        $segmentoAnterior = -1;
        $setorAnterior = -1;
        $count = 0;
        $cliFinal = collect([]);
        $collectClienteAux = collect([]);
        $qdeClientes = count($clientes);
        foreach ($clientes as $cliente) {
            $count++;
            $tipo0 = $tipo == '0' && $produtoAnterior != $cliente->produto_id;
            $tipo1 = $tipo == '1' && $segmentoAnterior != $cliente->segmento_id;
            $tipo2 = $tipo == '2' && $setorAnterior != $cliente->setor_id;
            if ($tipo0 || $tipo1 || $tipo2) {
                $cliFinal = $cliFinal->merge($collectClienteAux->sortBy('nome')->toArray());
                $collectClienteAux = collect([]);
            }
            if ($tipo == '0')
                $produtoAnterior = $cliente->produto_id;
            else if ($tipo == '1')
                $segmentoAnterior = $cliente->segmento_id;
            else
                $setorAnterior = $cliente->setor_id;
            $collectClienteAux->push($cliente);
            if ($qdeClientes == $count)
                $cliFinal = $cliFinal->merge($collectClienteAux->sortBy('nome')->toArray());
        }
        $clientes = $cliFinal;
        return view('reports.clientes.segmento_setor_produto.segmento_setor_produtoPDF', compact('titulo', 'filtro', 'clientes', 'tipo'));
    }

    public function inativoFaltaIndex()
    {
        $config = Session::get('empresa_config');
        if ($config == null)
            return Redirect::to('empresaconfig')->withMessageInfo('É necessario ter um config cadastrada para gerar os relatórios.');
        $dias = $config->qnddiasinativocompra;
        return view('reports.clientes.inativos_sem_compra.inativos_sem_compra', compact('dias'));
    }

    public function inativoFaltaCompraPDF()
    {
        return $this->returnReportInativo($_GET);
    }

    private function returnReportInativo($filtros)
    {
        // $dias = $filtros['dias'];
        $empresa_id = Session::get('empresa_padrao')->id;

        $config = Session::get('empresa_config');
        if ($config == null)
            return Redirect::to('empresaconfig')->withMessageInfo('É necessario ter um config cadastrada para gerar os relatórios.');
        $diasConfig = $config->qnddiasinativocompra;

        $dataInativacao = Carbon::now()->subDays($diasConfig)->startOfDay()->toDateTimeString();
        $now = Carbon::now()->endOfDay()->toDateTimeString();
        // $dataFiltro = Carbon::now()->subDays($dias)->startOfDay()->toDateTimeString();

        $clientes = DB::table('clientes as cliente')
            ->leftJoin(DB::raw('(SELECT max(datahoraprevisaoentrega) as datahoraprevisaoentrega, cliente_id FROM pedidos
            where pedidosituacao_id in (SELECT id FROM pedidosituacaos WHERE fechadoconcluido = 1)
            GROUP BY cliente_id) pedido'), 'pedido.cliente_id', 'cliente.id')
            ->leftJoin('setors as setor', 'cliente.setor_id', 'setor.id')
            ->whereRaw("(TO_CHAR(datahoraprevisaoentrega,  'yyyy-mm-dd hh24:mi:ss') NOT BETWEEN '$dataInativacao' and '$now' or datahoraprevisaoentrega is null)")
            ->where('cliente.empresa_id', $empresa_id)
            ->where('cliente.cliente', 1)
            ->whereRaw("TO_CHAR(cliente.created_at,  'yyyy-mm-dd hh24:mi:ss') <= '$dataInativacao'")
            // ->whereRaw("(TO_CHAR(datahoraprevisaoentrega,  'yyyy-mm-dd hh24:mi:ss') NOT BETWEEN '$dataFiltro' and '$dataInativacao' or datahoraprevisaoentrega is null)")
            //        ->whereRaw("(TO_CHAR(datahoraprevisaoentrega,  'yyyy-mm-dd hh24:mi:ss') <= '$dataInativacao' or datahoraprevisaoentrega is null)")
            ->select(
                'cliente.nome',
                'pedido.datahoraprevisaoentrega as ultimacompra',
                'cliente.id as cliente_id',
                'setor.descricao as setor',
                'cliente.setor_id',
                'cliente.created_at as datacadastro',
                DB::raw("CASE WHEN datahoraprevisaoentrega IS NULL
                                THEN TO_DATE('$now', 'YYYY-MM-DD hh24:mi:ss') - TO_DATE(TO_CHAR(cliente.created_at, 'YYYY-MM-DD'), 'YYYY-MM-DD hh24:mi:ss') - 1
                                ELSE TO_DATE('$now', 'YYYY-MM-DD hh24:mi:ss') - TO_DATE(TO_CHAR(datahoraprevisaoentrega, 'YYYY-MM-DD') || ' 00:00:00', 'YYYY-MM-DD hh24:mi:ss') - 1
                            END as diasinativo"),
                DB::raw("CASE WHEN datahoraprevisaoentrega IS NULL
                                THEN MONTHS_BETWEEN(TO_DATE('$now', 'YYYY-MM-DD hh24:mi:ss'), cliente.created_at)
                                ELSE MONTHS_BETWEEN(TO_DATE('$now', 'YYYY-MM-DD hh24:mi:ss'), datahoraprevisaoentrega)
                            END as mesessemcompras"),
                DB::raw("TO_CHAR(CASE WHEN datahoraprevisaoentrega IS NULL
                                THEN TO_DATE(TO_CHAR(cliente.created_at + $diasConfig, 'YYYY-MM-DD'), 'yyyy-mm-dd hh24:mi:ss')
                                ELSE datahoraprevisaoentrega + $diasConfig
                            END, 'yyyy-mm-dd') as datainativacao")
            )->orderBy('setor.descricao')->orderBy('cliente.nome')->get();

        $clientes = $this->reorderClientes($clientes);

        // $filtro = "Clientes sem compras de " . requestDataOracle($dataFiltro, false) . " a " . requestDataOracle($dataInativacao, false);
        $filtro = "Clientes sem compras até " . requestDataOracle($dataInativacao, false);
        $titulo = "Relatório de Clientes Inativos por Falta de Compra";

        return view('reports.clientes.inativos_sem_compra.inativos_sem_compraPDF', compact('titulo', 'filtro', 'clientes'));
    }
    public function reorderClientes($clientes)
    {
        $dados = collect([]);
        $setorAnterior = 'none';
        $collectAux = collect([]);
        $dataAnterior = null;
        $qdeClientes = count($clientes);
        $count = 0;
        foreach ($clientes as $cliente) {
            $count++;
            if ((!is_null($dataAnterior) || $count == $qdeClientes) && ($setorAnterior != $cliente->setor || $count == $qdeClientes)) {
                if ($count == $qdeClientes && $setorAnterior == $cliente->setor)
                    $collectAux->push($cliente);
                $dados = $dados->merge($collectAux->sortBy('datainativacao'));
                $collectAux = collect([]);
                if ($count == $qdeClientes && $setorAnterior != $cliente->setor)
                    $dados = $dados->merge([$cliente]);
            }
            $collectAux->push($cliente);
            $dataAnterior = $cliente->datainativacao;
            $setorAnterior = $cliente->setor;
        }
        return $dados;
    }

    private function fornecedoresBuscaAtivos($filtro, $clientefornecedor)
    {
        $empresas_id = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
        $segmento = $filtro["segmento"] == 0 ? null : $filtro["segmento"];
        $tipo = $filtro["tipopessoa"] == 0 ? null : $filtro["tipopessoa"];
        $setor = $filtro["setor"] == 0 ? null : $filtro["setor"];
        if ($clientefornecedor == "fornecedor") {
            $empresa = $filtro["empresa"] == 0 ? $empresas_id : explode(',', $filtro["empresa"]);
        } else {
            $empresa = null;
        }
        $ativo = $filtro["ativo"];
        $empresas = null;

        $fornecedores = DB::table('clientes')
            ->join('ruas', 'ruas.id', 'clientes.rua_id')
            ->join('bairros', 'bairros.id', 'clientes.bairro_id')
            ->join('cidades', 'cidades.id', 'clientes.cidade_id')
            ->join('estados', 'estados.uf', 'clientes.uf')
            ->leftJoin('tipopessoas', 'tipopessoas.id', 'clientes.tipopessoa_id')
            ->leftJoin('segmentos', 'segmentos.id', 'clientes.segmento_id')
            ->join('empresas', 'clientes.empresa_id', 'empresas.id')
            ->where([
                ['clientes.ativo', $ativo],
                [$clientefornecedor, 1]
            ]);

        if (!is_null($tipo))
            $fornecedores = $fornecedores->where('clientes.tipopessoa_id', $tipo);
        if (!is_null($setor))
            $fornecedores = $fornecedores->where('clientes.setor_id', $setor);
        if (!is_null($segmento))
            $fornecedores = $fornecedores->where('clientes.segmento_id', $segmento);
        if ($clientefornecedor == "fornecedor") {
            $empresas = count($empresa) == count($empresas_id) ? null :
                Empresa::whereIn('id', $empresa)->orderBy('nome_informal')->get()->pluck('nome_informal');
            $fornecedores = $fornecedores->whereIn('clientes.empresa_id', $empresa);
        } else {
            $fornecedores = $fornecedores->where('clientes.empresa_id', Session::get('empresa_padrao')->id);
        }
        $fornecedores = $fornecedores->select(
            'estados.descricao as estado',
            'segmentos.descricao as segmento',
            'clientes.id',
            'cidades.descricao as cidade',
            'clientes.email',
            DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || clientes.numero) as endereco"),
            'clientes.nome as razao_social',
            'tipopessoas.descricao as tipo',
            'clientes.cnpj',
            'clientes.cpf',
            'empresas.nome_informal as empresa',
            // Oracle LISTAGG+ROWNUM -> Postgres STRING_AGG + subquery LIMIT.
            // (pega até 2 telefones do cliente e concatena com vírgula.)
            DB::raw("(select string_agg(tel.telefone, ', ') from
                        (select telefone from clientetelefones
                          where cliente_id = clientes.id order by telefone limit 2) tel) as telefone")
        )
            ->orderBy('razao_social')->get();

        $filtro = $setor == null ? "Setor: todos" : "Setor: " . Setor::where('id', $setor)->pluck('descricao')->first();
        $filtrosetor = $filtro;
        if ($clientefornecedor == "fornecedor") {
            $filtro = $empresas == null ? "$filtro, Empresas: todas" : "$filtro, Empresas: " . implode(', ', $empresas->toArray());
            $filtro = $ativo == 0 ? "$filtro, Fornecedores inativos" : "$filtro, Fornecedores ativos";
        } else {
            $filtro = $ativo == 0 ? "$filtro, Clientes inativos." : "$filtro, Clientes ativos.";
        }
        $filtro = $segmento == null ? "$filtro, Segmento: todos" : "$filtro, Segmento: " . Segmento::where('id', $segmento)->pluck('descricao')->first();
        $filtro = $tipo == null ? "$filtro, Tipo pessoa: todos" : "$filtro, Tipo pessoa: " . Tipopessoa::where('id', $tipo)->pluck('descricao')->first();
        $retorno["clientefornecedores"] = $fornecedores;
        $retorno["filtro"] = $filtro;
        $retorno["filtrosetor"] = $filtrosetor;
        return $retorno;
    }

    private function clientesAtivosXls($clientes, $filtro, $filtrosetor, $titulo)
    {
        $newclientes[] = [$titulo];
        $newclientes[] = [$filtro];
        $newclientes[] = ['Emitido em ' . Carbon::now()->format('d/m/Y H:i:s')];
        $newclientes[] = [''];
        $newclientes[] = [$filtrosetor];
        $newclientes[] = [''];
        $newclientes[] = ['Cód', 'Nome/Razão Social', 'Endereço', 'Telefone', 'email'];
        foreach ($clientes as $cliente) {
            $newcliente = array();
            array_push($newcliente, $cliente->id);
            array_push($newcliente, $cliente->razao_social);
            array_push($newcliente, $cliente->endereco);
            array_push($newcliente, $cliente->telefone);
            array_push($newcliente, $cliente->email);
            $newclientes[] =  $newcliente;
        }
        $newclientes[] =  ['Total', count($clientes)];
        $clientes = $newclientes;
        Excel::create('Clientes', function ($excel) use ($clientes) {
            $excel->sheet('Clientes', function ($sheet) use ($clientes) {
                $sheet->setAutoSize(false);
                $sheet->setPageMargin(0.5);
                $sheet->fromArray($clientes, null, 'A1', true, false);
                $sheet->mergeCells('A1:D1');
                $sheet->mergeCells('A2:D2');
                $sheet->mergeCells('A3:D3');
                $sheet->cells('A1:A1', function ($cells) {
                    $cells->setFontWeight('bold');
                    $cells->setFontSize(14);
                });
                $sheet->cells('A2:A2', function ($cells) {
                    $cells->setFontWeight('bold');
                    $cells->setFontSize(12);
                });
                $sheet->cells('A3:A3', function ($cells) {
                    $cells->setFontSize(10);
                });
                $sheet->cells('A5:A5', function ($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->cells('A7:E7', function ($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->cells('A' . (count($clientes)) . ':E' . (count($clientes)), function ($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->setWidth('A', 15);
                $sheet->setWidth('B', 50);
                $sheet->setWidth('C', 50);
                $sheet->setWidth('D', 30);
                $sheet->setWidth('E', 40);

                if (Session::get('empresa_padrao')->logo != null) {
                    $image = imagecreatefromstring(base64_decode(Session::get('empresa_padrao')->logo));
                    imagesavealpha($image, true);
                    $drawing = new PHPExcel_Worksheet_MemoryDrawing();
                    $drawing->setName('teste');
                    $drawing->setImageResource($image);
                    $drawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_PNG);
                    $drawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                    $drawing->setWidthAndHeight(148, 70);
                    $drawing->setResizeProportional(true);
                    $drawing->setCoordinates('E1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                }
            });
        })->download('xlsx');
    }
}
