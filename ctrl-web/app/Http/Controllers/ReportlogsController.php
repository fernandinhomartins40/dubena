<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Session;
use App\User;
use App\Services\CarbonCustom as Carbon;
use Illuminate\Http\Request;

class ReportlogsController extends Controller
{

    protected $filtro;

    public function index()
    {
        $telas = $this->getTelas();
        $empresas = $this->getEmpresasUser();
        $empresas = $empresas->pluck('nome_informal', 'id')->prepend('Selecione', '');
        return view('reports.logs.index')->with(compact('telas', 'empresas'));
    }

    public function filtarLogs()
    {
        $filtros = $_GET;
        $logs = $this->getLogs($filtros);
        $titulo = "Relatório de Logs";
        $filtro = $this->filtro;
        return view('reports.logs.logs_pdf')->with(compact('titulo', 'filtro', 'logs'));
    }

    /**
     * Get empresas allowed to user
     *
     * @return array $empresas
     */
    private function getEmpresasUser()
    {
        return Auth::user()->empresas()->select('id', 'nome_informal');
    }

    /**
     * Return revisionable models
     *
     * @return array $telas
     */
    private function getTelas()
    {
        return [
            ""      => "Selecione",
            "0"     => "Centro de Custos",
            "1"     => "Cheque Emitido",
            "2"     => "Cheque Recebido",
            "3"     => "Clientes",
            "4"     => "Contas",
            "5"     => "Empresa",
            "6"     => "Financeiros",
            "7"     => "Financeiro Parcelas",
            "8"     => "Emissão de Notas Fiscais",
            "9"     => "Imposto",
            "10"    => "Imposto Estado",
            "11"    => "Operações NFe",
            "12"    => "Lançamento de Documentos",
            "13"    => "Pedidos",
            "14"    => "Plano de Contas",
            "15"    => "Produtos",
            "16"    => "Promover Vendas",
        ];
    }

    /**
     * Get Eloquent models of Telas
     *
     * @param string $key
     * @return array $models
     */
    private function getModel($key)
    {
        $models = [
            "0"     => "App\Centrocusto",
            "1"     => "App\Chequeemitido",
            "2"     => "App\Chequerecebido",
            "3"     => "App\Cliente",
            "4"     => "App\Conta",
            "5"     => "App\Empresa",
            "6"     => "App\Financeiro",
            "7"     => "App\Financeiroparcela",
            "8"     => "App\Nfemitida",
            "9"     => "App\Nfimposto",
            "10"    => "App\Nfimpostoestado",
            "11"    => "App\Nfoperacao",
            "12"    => "App\Nfrecebida",
            "13"    => "App\Pedido",
            "14"    => "App\Planoconta",
            "15"    => "App\Produto",
            "16"    => "App\Promotorvenda",
        ];

        return array_get($models, $key);
    }

    /**
     * Return the name of the models on the revisionable table
     *
     * @return array $telas
     */
    private function getTela($key)
    {
        $models = [
            "App\Centrocusto"           => "Centro de Custos",
            "App\Chequeemitido"         => "Cheque Emitido",
            "App\Chequerecebido"        => "Cheque Recebido",
            "App\Cliente"               => "Clientes",
            "App\Empresa"               => "Empresa",
            "App\Conta"                 => "Contas",
            "App\Financeiro"            => "Financeiros",
            "App\Financeiroparcela"     => "Financeiro Parcelas",
            "App\Nfemitida"             => "Emissão de Notas Fiscais",
            "App\Nfimposto"             => "Imposto",
            "App\Nfimpostoestado"       => "Imposto Estado",
            "App\Nfoperacao"            => "Operações NFe",
            "App\Nfrecebida"            => "Lançamento de Documentos",
            "App\Pedido"                => "Pedidos",
            "App\Planoconta"            => "Plano de Contas",
            "App\Produto"               => "Produtos",
            "App\Empresainutilizacao"   => "Empresa Inutilização",
            "App\Promotorvenda"         => "Promover Vendas"
        ];

        return array_get($models, $key);
    }

    /**
     * Return if model is identified by grupo_id on revisions table
     *
     * @param string $key
     * @return bool  $exist
     */
    private function byGrupo($key)
    {
        $byemp = [
            "App\Planoconta",
        ];

        return in_array($key, $byemp);
    }

    /**
     * Filter logs for the report
     *
     * @param array $filtros
     * @return array $logs
     */
    private function getLogs($filtros)
    {
        $datainicio = Carbon::parse($filtros["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtros["datafim"])->endOfDay();
        $model = $filtros["tela"] === "0" ? null : $this->getModel($filtros["tela"]);
        $iden = $filtros["empresa"];
        $user = $filtros["user"] === "0" ? null : $filtros["user"];
        $param = $this->byGrupo($model) ? "grupo_id" : "empresa_id";

        $dblogs = DB::table('revisions rev')
                ->join('users', 'rev.user_id', 'users.id')
                ->whereBetween('rev.created_at', [$datainicio, $datafim])
                ->where([
            ['rev.identityby', $param],
        ]);

        if (!is_null($model)) {
            $table = with(new $model)->getTable() . " t1";
            $dblogs = $dblogs->join($table, 'rev.revisionable_id', 't1.id')
                    ->where('rev.revisionable_type', $model);
        }

        if ($param === "grupo_id") {
            $dblogs = $dblogs->whereRaw("identity in (select grupo_id from empresas where id = $iden)");
        } else {
            $dblogs = $dblogs->where('identity', $iden);
        }

        $dblogs = $dblogs->select('users.name', 'rev.created_at as data', 'rev.revisionable_type as model',
                            'rev.old_value as antigo', 'rev.new_value as novo', 'rev.key', 'rev.revisionable_id')
                        ->orderBy('rev.id')->orderBy('rev.created_at')->get();

        $logs = collect([]);
        foreach ($dblogs as $log) {
            $objnormal = (object) array();
            $objnormal->tela = $this->getTela($log->model);
            $objnormal->id = $log->revisionable_id;
            $objnormal->data = requestDataOracle($log->data);
            $objnormal->usuario = $log->name;
            $objnormal->campo = $log->key;
            // $objnormal->antigo = strpos($log->key, "data") !== false ? requestDataOracle($log->antigo) : $log->antigo;
            // $objnormal->novo = strpos($log->key, "data") !== false ? requestDataOracle($log->novo) : $log->novo;

            if (strpos($log->key, "data") !== false) {
                $objnormal->antigo = is_null($log->antigo) ? $log->antigo : requestDataOracle($log->antigo);
                $objnormal->novo = is_null($log->novo) ? $log->novo : requestDataOracle($log->novo);
            } else {
                $objnormal->antigo = $log->antigo;
                $objnormal->novo = $log->novo;
            }

            $logs->push($objnormal);
        }

        $this->definirFiltros($filtros);
        return $logs;
    }

    /**
     * Set filtros for the report
     *
     * @param array $filtros
     * @return void
     */
    private function definirFiltros($filtros)
    {
        $this->filtro = "Filtro: Período " . requestDataOracle($filtros["datainicio"]) . " a " . requestDataOracle($filtros["datafim"]);
        $this->filtro .= ", Empresa: " . $this->getEmpresasUser()->where('id', $filtros["empresa"])->pluck('nome_informal')->first();
        $this->filtro .= $filtros["user"] === "0" ? ", Usuários: todos" : User::find($filtros["user"])->name;
        $this->filtro .= $filtros["tela"] === "0" ? ", Tela: todas" : ", Tela: " . $this->getTelas()[$filtros["tela"]];
    }

    public function logJs(Request $request)
    {
        $data = $request->all();
        $file = 'logs' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . $data['logName'] . '.txt';
        $fileContent = $data['message'];
        $fileError = explode('at', $data['stack']);
        if (!isset($fileError[1])) {
            $fileError = 'desconhecido';
        } else {
            $explodeError = explode('/', $fileError[1]);
            $lastKey = count($explodeError) - 1;
            if(array_key_exists($lastKey, $explodeError))
                $fileError = $explodeError[$lastKey];
            else
                $fileError = $fileError[1];
        }
        $fileContent .= " in $fileError\n url: " . $data['url']
                . "\n datetime: " . $data['datetime'] . "\n stack: " . $data['stack'] . "\n\n";
        \Storage::append($file, $fileContent);
        return "OK";
    }

}
