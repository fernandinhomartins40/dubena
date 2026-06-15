<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\User;
use App\Empresa;
use App\Services\CarbonCustom as Carbon;
use Illuminate\Http\Request;
use App\Conta;

class ReportFinanceiroController extends Controller
{

    private $filtros;
    private $repositorio;

    private function getEmpresaUser()
    {
        return User::find(Auth::user()->id)->empresas()->pluck('id')->toArray();
    }

    private function getEmpresas($empresas_id, $prepend = false)
    {
        $empresa = Empresa::whereIn('id', $empresas_id)->orderBy('nome_informal')->get()->pluck('nome_informal', 'id');
        if ($prepend) $empresa = $empresa->prepend('Selecione','');
        return $empresa;
    }

    public function ReportContasReceber($pdf = false)
    {
        $empresas_id = $this->getEmpresaUser();
        $empresas = $this->getEmpresas($empresas_id);

        if(count($_GET) > 0)
            return $this->getFiltersContas($_GET, $empresas, $empresas_id, $pdf);

        return view('reports.contasreceber.contasreceber', compact('empresas'));
    }


    public function ReportContasReceberPDF()
    {
        return $this->ReportContasReceber(true);
    }

    private function setFiltersReport($empresas_id, $empresas, $cliente_id, $datainicio, $datafim, $tipo, $situacao, $ordem, $pagarreceber)
    {
        $this->filtros = "Período " . $datainicio . " a " . $datafim . ';';
        $this->filtros .= ' Empresa (s): ' . implode(', ', $empresas->only($empresas_id)->toArray()) . ';';
        // cliente_id vazio (= "Todos") não pode ir num WHERE id = '' no Postgres
        // (rejeita '' como integer). Só busca o nome quando há cliente.
        $cliente = $cliente_id !== '' && $cliente_id !== null
            ? DB::table('clientes')->where('id', $cliente_id)->select('nome')->get()->first()
            : null;
        $this->filtros .= $pagarreceber == 'R' ? ' Cliente: ' : ' Fornecedor: ';
        $this->filtros .= is_null($cliente) ? 'Todos;' : $cliente->nome . ';';
        $this->filtros .= 'Tipo de Filtro: ';
        switch ($tipo) {
            case 'E':
            $this->filtros .= 'Emissão;';
            $this->filtros .= ' Ordem: ' . ($ordem == 'C' ? 'Clientes;' : 'Data Emissão;');
            break;
            case 'V':
            $this->filtros .= 'Vencimento;';
            $this->filtros .= ' Ordem: ' . ($ordem == 'C' ? 'Clientes;' : 'Data Vencimento;');
            break;
            default:
            $this->filtros .= 'Pago;';
            $this->filtros .= ' Ordem: ' . ($ordem == 'C' ? 'Clientes;' : 'Data Pagamento;');
            break;
        }
        $this->filtros .= ' Situação:';
        switch ($situacao) {
            case '2':
            $this->filtros .= 'Todas.';
            break;
            case '1':
            $this->filtros .= 'Pagas.';
            break;
            default:
            $this->filtros .= 'Não Pagas.';
            break;
        }
    }

    public function getFiltersContas($filtros, $empresas, $empresas_id, $pdf, $pagarreceber = 'R')
    {
        $empresas_id = $filtros['empresa_id'] == '' ? $empresas_id : explode(',', $filtros['empresa_id']);
        $cliente_id = $filtros['cliente_id'];
        $datainicio = insertDataOracle($filtros['datainicio']);
        $datafim = insertDataOracle($filtros['datafim']);
        $tipo = $filtros['tipo'];
        $situacao = isset($filtros['situacao']) ? $filtros['situacao'] : '1';
        $ordem = $filtros['ordem'];

        $this->setFiltersReport($empresas_id, $empresas, $cliente_id, $filtros['datainicio'], $filtros['datafim'], $tipo, $situacao, $ordem, $pagarreceber);
        $filtro = $this->filtros;

        switch ($tipo) {
            case 'E':
            $datafiltro = 'parcela.datacompetencia';
            $ordem = $ordem == 'C' ? $ordem : 'Emissão: ';
            break;
            case 'V':
            $datafiltro = 'parcela.datavencimento';
            $ordem = $ordem == 'C' ? $ordem : 'Vencimento: ';
            break;
            default:
            $datafiltro = 'parcela.datahorabaixa';
            $ordem = $ordem == 'C' ? $ordem : 'Pago: ';
            break;
        }

        $tipocheque = $pagarreceber == "R" ? 'recebido' : 'emitido';

        $parcelas = DB::table('financeiroparcelas as parcela')->whereIn('parcela.empresa_id', $empresas_id)
        ->whereBetween($datafiltro, [Carbon::parse($datainicio)->startOfDay(), Carbon::parse($datafim)->endOfDay()])
        ->where('parcela.pagarreceber', $pagarreceber)
        ->whereRaw("parcela.agrupamento_status NOT IN (2, 3)")
        ->join('financeiros as financeiro', 'financeiro.id', 'parcela.financeiro_id')
        ->join('clientes as cliente', 'cliente.id', 'financeiro.cliente_id')
        ->join('empresas as empresa', 'empresa.id', 'financeiro.empresa_id')
        ->leftJoin('cheque' . $tipocheque . 'financeiros as cheque', 'parcela.id', 'cheque.financeiroparcela_id');

        switch ($situacao) {
            case '1':
            $parcelas = $parcelas->where('parcela.baixado', 1);
            break;
            case '0':
            $parcelas = $parcelas->where('parcela.baixado', 0);
            break;
        }

        if($cliente_id != '')
            $parcelas = $parcelas->where('financeiro.cliente_id', $cliente_id);

        $parcelas = $parcelas->select('cliente.id as cliente_id', 'cliente.nome as cliente',  'parcela.id as parcela_id', 
            DB::raw("TO_CHAR(parcela.datacompetencia, 'yyyy-mm-dd') as datacompetencia"), 
            DB::raw("TO_CHAR(parcela.datavencimento, 'yyyy-mm-dd') as datavencimento"), DB::raw("TO_CHAR(parcela.datahorabaixa, 'yyyy-mm-dd') as datahorabaixa"), 
            'parcela.numero', 'financeiro.documento', 'cheque.numerocheque', 'parcela.valor', 'parcela.valorefetivado', 'parcela.baixado', 'parcela.agrupamento_status', 
            'empresa.id as empresa_id', 'empresa.nome_informal as empresa')->orderBy('empresa.nome_informal');

        if(strlen($ordem) > 1 )
            $parcelas = $parcelas->orderBy("$datafiltro");
        else 
            $parcelas = $parcelas->orderBy('cliente.nome');
        
        $parcelas = $parcelas->get();

        $parcelas = $this->montarTableContas($parcelas, $ordem, $pagarreceber);

        $titulo = 'Relatório de Contas a ';
        $titulo .= $pagarreceber == 'R' ? 'Receber' : 'Pagar';

        $view = $pagarreceber == 'R' ? 'receber' : 'pagar';

        if($pdf){
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('A4', 'landscape');
            $isPdf = true;
            $pdf->loadView('reports.contas' . $view . '.contas' . $view . 'PDF', compact('titulo', 'parcelas', 'filtro', 'ordem', 'isPdf'));
            return $pdf->stream();
        }
        return view('reports.contas' . $view . '.contas' . $view . 'PDF', compact('titulo', 'parcelas', 'filtro', 'ordem'));
    }

    public function montarTableContas($parcelas, $ordem, $pagarreceber)
    {
        $dados = [];
        $dadosAux = collect([]);
        $empresaAnterior = null;
        $count = 0;
        $clienteAnterior = null;
        $dataAnterior = null;
        $totalCliente = 0;
        $totalData = 0;
        foreach ($parcelas as $parcela) {
            $empresaDiff = $empresaAnterior != $parcela->empresa_id;
            
            if($ordem == 'Emissão: '){
                $ordemData = $parcela->datacompetencia;
                $tipodata = 'datacompetencia';
            } else if($ordem == 'Vencimento: '){
                $ordemData =  $parcela->datavencimento;
                $tipodata = 'datavencimento';
            } else{
                $ordemData =  $parcela->datahorabaixa;
                $tipodata = 'datahorabaixa';
            }

            if(strlen($ordem) > 1 ){
                if(!is_null($dataAnterior) && ($empresaDiff || $dataAnterior != $ordemData)){
                    if($totalData > 1)
                        array_push($dados, $this->createObjTotal($totalData, $empresaAnterior, $parcelas, $dataAnterior, $tipodata, 'totalData'));
                    $totalData = 0;	
                }
            } else {
                if(!is_null($clienteAnterior) && ($empresaDiff || $clienteAnterior != $parcela->cliente_id)){
                    if($totalCliente > 1)
                        array_push($dados, $this->createObjTotal($totalCliente, $empresaAnterior, $parcelas, $clienteAnterior, 'cliente_id', 'totalCliente'));
                    $totalCliente = 0;
                }
            }
            if(is_null($empresaAnterior) || $empresaDiff)
                array_push($dados, (object) ['empresa' => $parcela->empresa]);

            if(strlen($ordem) > 1 ){
                if(is_null($dataAnterior) || $dataAnterior != $ordemData || $empresaDiff){
                    $objVcto = (object) [];
                    $objVcto->ordemData =  $ordem . requestDataOracle($ordemData, false);
                    array_push($dados, $objVcto);
                }
            } else {
                if(is_null($clienteAnterior) || $clienteAnterior != $parcela->cliente_id || $empresaDiff){
                    $objCliente = (object) [];
                    $objCliente->ordemCliente = true;
                    $objCliente->cliente = $parcela->cliente;
                    array_push($dados, $objCliente);
                }
            }
            $totalCliente++;
            $totalData++;
            if(strlen($ordem) > 1){
                if(count($parcelas) == $count)
                    $dadosAux->push($this->createObjParcela($parcela));

                if((!is_null($dataAnterior) && ($empresaDiff || $dataAnterior != $ordemData)) || count($parcelas) == $count){
                    // if($empresaDiff && is_null($empresaAnterior))
                    // 	$dados = $this->organizarArray($dados, $dadosAux->sortBy('cliente')->toArray(), true);
                    // else
                        $dados = $this->organizarArray($dados, $dadosAux->sortBy('cliente')->toArray());
                    $dadosAux = collect([]);
                }
                $dadosAux->push($this->createObjParcela($parcela));
            } else {
                array_push($dados, $this->createObjParcela($parcela));
            }
            $count++;
            $empresaAnterior = $parcela->empresa_id;
            $clienteAnterior = $parcela->cliente_id;
            $dataAnterior = $ordemData;
            if(count($parcelas) == $count){
                $dados = $this->organizarArray($dados, $dadosAux->sortBy('cliente')->toArray(), true);
                if(strlen($ordem) > 1 ){
                    if($totalData > 1)
                        array_push($dados, $this->createObjTotal($totalData, $empresaAnterior, $parcelas, $dataAnterior, $tipodata, 'totalData'));
                } else {
                    if($totalCliente > 1)
                        array_push($dados, $this->createObjTotal($totalCliente, $empresaAnterior, $parcelas, $clienteAnterior, 'cliente_id', 'totalCliente'));
                }
            }
        }

        if(count($parcelas) > 0)
            array_push($dados, $this->createObjTotal(count($parcelas), null, $parcelas, null, null, 'totalGeral'));
        return $dados;
    }

    private function organizarArray($dados, $clientes, $last = false)
    {
        if(!$last){
            $lastInsert = array_pop($dados);
            if(isset($dados[count($dados) - 1]->totalValor))
                $totalGeral = array_pop($dados);
        }

        if(isset($dados[count($dados) - 1]->empresa)){
            $empresa = array_pop($dados);
            if(isset($dados[count($dados) - 1]->totalValor))
                $totalBeforeEmpresa = array_pop($dados);
        }

        foreach ($clientes as $cliente) {
            array_push($dados, $cliente);		
        }
        
        if(isset($empresa)){
            if(isset($totalBeforeEmpresa))
                array_push($dados, $totalBeforeEmpresa);
            array_push($dados, $empresa);
        }

        if(!$last){
            if(isset($totalGeral))
                array_push($dados, $totalGeral);

            array_push($dados, $lastInsert);
        }
        return $dados;
    }

    private function createObjTotal($totalData, $empresa_id, $parcelas, $valortipopesquisaordem, $tipopesquisaordem, $tipoTotal)
    {
        $parcela = $parcelas;
        if(!is_null($empresa_id))
            $parcela = $parcela->where('empresa_id', $empresa_id);
        if(!is_null($tipopesquisaordem))
            $parcela = $parcela->where($tipopesquisaordem, $valortipopesquisaordem);
        return (object) [
        $tipoTotal => $totalData, 
        'totalValor' => requestNumeroDecimalOracle($parcela->sum('valor')),
        'totalLiquido' => requestNumeroDecimalOracle($parcela->sum('valorefetivado'))
        ];
    }

    private function createObjParcela($parcela)
    {
        $strDataVto = strtotime($parcela->datavencimento);
        $now = Carbon::now()->toDateString();
        if ($parcela->baixado == '1') 
            $situacao = 'Baixada'; 
        else if ($parcela->agrupamento_status == '3') 
            $situacao =  'Cancelada';
        else if($strDataVto < strtotime($now))
            $situacao = 'Atrasada';
        else
            $situacao = 'Pendente'; 
        $objParcela = (object) [];
        $objParcela->datacompetencia = requestDataOracle($parcela->datacompetencia, false);
        $objParcela->cliente = $parcela->cliente;
        $objParcela->datavencimento = requestDataOracle($parcela->datavencimento, false);
        $objParcela->datahorabaixa = requestDataOracle($parcela->datahorabaixa, false);
        $objParcela->documento = $parcela->documento;
        $objParcela->numero = $parcela->numero;
        $objParcela->parcela_id = $parcela->parcela_id;
        $objParcela->numerocheque = $parcela->numerocheque;
        $objParcela->valor = requestNumeroDecimalOracle($parcela->valor);
        $objParcela->valorefetivado = requestNumeroDecimalOracle($parcela->valorefetivado);
        $objParcela->situacao = $situacao;
        return $objParcela;
    }

    public function ReportContasPagar($pdf = false)
    {
        $empresas_id = $this->getEmpresaUser();
        $empresas = $this->getEmpresas($empresas_id);

        if(count($_GET) > 0)
            return $this->getFiltersContas($_GET, $empresas, $empresas_id, $pdf, "P");

        return view('reports.contaspagar.contaspagar', compact('empresas'));
    }

    public function ReportContasPagarPDF()
    {
        return $this->ReportContasPagar(true);
    }


    ////// Report Lançamentos Retroativo
    public function indexRetro()
    {
        $empresas_id = $this->getEmpresaUser();
        $empresas = $this->getEmpresas($empresas_id, true);

        return view('reports.retroativo.index')->with(compact('empresas'));
    }

    public function retroFiltrar()
    {
        $filtro = $_GET;
        $retroativos = $this->buscaLancamentos($filtro);
        $filtro = $this->filtros;
        $titulo = "Relatório de Lançamentos Retroativos";
        return view('reports.retroativo.retroativo_pdf')->with(compact('titulo','filtro','retroativos'));
    }

    private function buscaLancamentos($filtro)
    {
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $empresas_id = $filtro["empresa"] == '0' ? implode(',',$this->getEmpresaUser()) : $filtro["empresa"];
        $contas_id = $filtro["conta"] == '0' ? null : $filtro["conta"];
        $user = $filtro["user"] == '0' ? null : $filtro["user"];
        $query = $this->getQuery($datainicio, $datafim, $empresas_id, $user, $contas_id);
        $movimentacoes = DB::select($query);

        $retroativos = collect([]);
        $anterior = null;
        $userant  = null;
        $est = null;
        foreach ($movimentacoes as $mov) {
            if (is_null($anterior) || $anterior != $mov->empresa_id) {
                $objemp = (object) array();
                $objemp->empresa_id = $mov->empresa_id;
                $objemp->empresa = $mov->empresa;
                $retroativos->push($objemp);
            }
            if (is_null($userant) || ($userant != $mov->user_id || $anterior != $mov->empresa_id)) {
                $objuser = (object) array();
                $objuser->user_id = $mov->user_id;
                $objuser->user = $mov->usuario;
                $retroativos->push($objuser);
            }
            $lancamento = null;
            if (!is_null($mov->est_transf)) $lancamento = $mov->est_transf;
            else if (!is_null($mov->est_finan)) $lancamento = $mov->est_finan;
            else if (!is_null($mov->mov_transf)) $lancamento = $mov->mov_transf;
            else if (!is_null($mov->mov_finan)) $lancamento = $mov->mov_finan;

            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($mov->datahorabaixa);
            $objnormal->conta = $mov->conta;
            $objnormal->pagarreceber = $mov->pagarreceber == 'R' ? 'Entrada' : 'Saída';
            $objnormal->descricao = $mov->descricao;
            $objnormal->origem = $mov->origem;
            $objnormal->lancamento = $lancamento;
            $objnormal->valor = $mov->valorefetivado;
            $retroativos->push($objnormal);
            $anterior = $mov->empresa_id;
            $userant = $mov->user_id;
        }

        $this->organizarFiltros($filtro);
        return $retroativos;
    }

    private function organizarFiltros($filtro)
    {
        $empresa = $filtro["empresa"] == '0' ? "todas" : Empresa::find($filtro["empresa"])->nome_informal;
        $user = $filtro["user"] == '0' ? 'todos' : User::find($filtro["user"])->name;
        $contas = $filtro["conta"] == '0' ? 'todas' : implode(', ',Conta::whereRaw('id in ('.$filtro["conta"].')')->pluck('descricao')->toArray());

        $this->filtros = "Filtro: Período " . requestDataOracle($filtro["datainicio"]) . " a " . requestDataOracle($filtro["datafim"]);
        $this->filtros .= ", Empresa: $empresa";
        $this->filtros .= ", Usuário: $user";
        $this->filtros .= ", Conta(s): $contas";
    }

    private function getQuery($datainicio, $datafim, $empresas_id, $user, $contas_id)
    {
        $query = "select empresa_id, empresa, user_id, usuario, datahorabaixa, conta, ".
        "pagarreceber, origem, descricao, valorefetivado, motivo, ".
        "est_transf, est_finan, mov_transf, mov_finan ".
        "from( ".
            "select contas.empresa_id, empresas.nome_informal as empresa, est.user_id, users.name as usuario,  ".
            "est.created_at as datahorabaixa, contas.descricao as conta, est.pagarreceber, est.origem, est.descricao, ".
            "est.valorefetivado, est.motivo, est.contatransferencia_id as est_transf, est.financeiroparcela_id as est_finan, ".
            "cast('' as int) as mov_transf, cast('' as int) as mov_finan ".
            "from contamovimentoestornos est ".
            "inner join contafechamentos fech on est.conta_id = fech.conta_id ".
            "inner join contas on est.conta_id = contas.id  ".
            "inner join empresas on contas.empresa_id = empresas.id  ".
            "inner join users on est.user_id = users.id  ".
            "where contas.empresa_id in ($empresas_id) and ".
            "fech.datahorafechamento is not null and est.datahorabaixa between ".
            "fech.datahoraabertura and fech.datahorafechamento and ".
            "est.created_at > fech.datahorafechamento and ".
            "est.created_at between ".
            "to_date('$datainicio', 'yyyy-mm-dd hh24:mi:ss') and ".
            "to_date('$datafim', 'yyyy-mm-dd hh24:mi:ss') ";

            if (!is_null($user)) {
                $query .= "and est.user_id = $user ";
            }
            if (!is_null($contas_id)) {
                $query .= "and est.conta_id in ($contas_id) ";
            }

            $query .= "union all ".

            "select contas.empresa_id, empresas.nome_informal as empresa, mov.user_id, users.name as usuario,  ".
            "mov.datahorabaixa, contas.descricao as conta, mov.pagarreceber, mov.origem, mov.descricao, ".
            "mov.valorefetivado, cast('' as varchar(1)) as motivo, cast('' as int) as est_trasnf, ".
            "cast('' as int) as est_finan, mov.contatransferencia_id as mov_transf, mov.financeiroparcela_id as mov_finan ".
            "from contamovimentos mov  ".
            "inner join contas on mov.conta_id = contas.id  ".
            "inner join empresas on contas.empresa_id = empresas.id  ".
            "inner join users on mov.user_id = users.id  ".
            "where contas.empresa_id in ($empresas_id) and retroativo = 1 and  ".
            "mov.datahorabaixa between  ".
            "to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and  ".
            "to_date('$datafim','yyyy-mm-dd hh24:mi:ss') ";
            if (!is_null($user)) {
                $query .= "and mov.user_id = $user ";
            }
            if (!is_null($contas_id)) {
                $query .= "and mov.conta_id in ($contas_id) ";
            }
        $query .= ") retroativos ".
        "order by empresa asc, empresa_id asc, usuario asc, user_id asc, datahorabaixa asc";
        
        return $query;
    }
}

