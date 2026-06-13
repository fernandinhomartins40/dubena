<?php

namespace App\Http\Controllers;

use App\Configuracoesgerais;
use App\Empresaconfig;
use App\Planoconta;
use App\Repository\FechamentomensalBalancoRepository;
use App\Repository\FechamentomensalDreRepository;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;
use PHPExcel_Worksheet_Drawing;
use PHPExcel_Worksheet_MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use Config;
use GuzzleHttp\Client;

class DashboardgerencialController extends Controller
{
    private $dataReferencia;

    public function __construct()
    {
        $this->dataReferencia = Carbon::now()->endOfDay();
        $env = env('APP_ENV', "local");
        if (strtolower($env) !== 'production') {
            $this->dataReferencia = Carbon::now()->subYear()->endOfMonth()->endOfDay();
        }
    }

    private function getTime($which)
	{
		$anos = [];
		for ($y=0; $y < 3; $y++) { 
			$year = Carbon::now()->year - $y;
			$anos[$year] = $year;
		}
		
		$meses = [];
		for ($m=1; $m < 13; $m++) {
			$mes = getDescricaoMes($m);
			$meses[$m] = $mes;
		}
		
		return $which == 'ano' ? $anos : $meses;
	}

    public function index()
    {
        $years = $this->getTime('ano');
		$meses = $this->getTime('mes');
        $ano = $this->dataReferencia->year;
        $mes = $this->dataReferencia->month;
        return view("reports.dashboardgerencial.index")->with(compact('years', 'meses', 'ano', 'mes'));
    }

    public function getDashboard()
    {
        try {
            $dataDreMensal = $this->getDataDreMensal(Carbon::Parse($this->dataReferencia)->endOfMonth()->endOfDay()->format('Y-m-d H:i:s'));
            $dataBalanco =$this->getDataBalanco(Carbon::Parse($this->dataReferencia)->endOfMonth()->endOfDay()->format('Y-m-d H:i:s'));
            return responseSuccess(["dataChartVendaDiaria" => $this->getDataChartVendaDiaria(),
                                    "dataDreMensal" => $dataDreMensal,
                                    "dataBalanco" => $dataBalanco,
                                    "dataMarketShare" => $this->getDataMarketShare($this->dataReferencia),
                                    "periodo" => Carbon::Parse($this->dataReferencia)->startOfMonth()->format('d/m/Y') . ' a ' . $this->dataReferencia->format('d/m/Y'),
                                    "ano" => $this->dataReferencia->year,
                                    "mes" => $this->dataReferencia->month
                                    ]);
        } catch(Exception $e){
            return responseError(getErrorsException($e));
        }
    }

    public function getDre()
    {

        try {
            $mes = \Input::get("mes", "");
            $ano = \Input::get("ano", "");
            $dataReferencia = Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, "0", STR_PAD_LEFT)."01")->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');
            $dataDreMensal = $this->getDataDreMensal($dataReferencia);
            return responseSuccess(["dataDreMensal" => $dataDreMensal]);
        } catch(Exception $e){
            return responseError(getErrorsException($e));
        }
    }

     public function getDataChartVendaDiaria(){
        $query = 
        " SELECT    " .
        "     p.DESCRICAO, dia_seq, COALESCE(qryvenda.quantidade, 0) AS quantidade, COALESCE(qryvenda.valor, 0) AS valor   " .
        " FROM (   " .
        "    SELECT " .
        "	     TO_CHAR(TRUNC(TO_DATE('".$this->dataReferencia->format('Y-m')."' || '-01', 'YYYY-MM-DD'), 'MONTH') + LEVEL - 1, 'YYYYMMDD') AS dia_seq " .
        "	FROM " .
        "	    DUAL " .
        "	CONNECT BY " .
        "	    LEVEL <= TO_CHAR(TO_DATE('".$this->dataReferencia->format('Y-m-d')."', 'YYYY-MM-DD'), 'DD') " .
        " ) CROSS JOIN (  " .
        "     SELECT 998 AS id, 'Realizado' AS descricao FROM dual  " .
        "       UNION ALL SELECT 999 AS id, 'Meta' AS descricao FROM dual) p   " .
        "     LEFT JOIN (   " .
        "         select 998 AS produto_id,    " .
        "                 to_char(datahoraprevisaoentrega,'yyyymmdd') as dia,   " .
        "                 sum(items.quantidade) AS quantidade,    " .
        "                 sum(items.precovendaunitario*items.quantidade) as valor   " .
        "         from pedidos   " .
        "         inner join pedidoitems items on items.pedido_id = pedidos.id   " .
        "         inner join produtos on items.produto_id = produtos.id   " .
        "         inner join condicaopagamentos cond on pedidos.condicaopagamento_id = cond.id  " .
        "         inner join pedidooperacaos op on pedidos.pedidooperacao_id = op.id " .
        "         INNER JOIN SETORS setor ON pedidos.ENTREGASETOR_ID = setor.ID AND setor.ESTOQUEPROPRIO = 1 " .
        "         where pedidos.empresa_id = ".Session::get('empresa_padrao')->id." and   " .
        "         pedidos.datahoraprevisaoentrega BETWEEN TO_DATE('".$this->dataReferencia->format('Y-m')."' || '-01', 'YYYY-MM-DD')  AND TO_DATE('".$this->dataReferencia->format('Y-m-d')." 23:59:59', 'YYYY-MM-DD HH24:MI:SS') and   " .
        "         pedidos.pedidosituacao_id in (select id from pedidosituacaos where empresa_id = ".Session::get('empresa_padrao')->id." and fechadoconcluido = 1) and   " .
        "         produtos.tipo_glp IN (3) AND produtos.PESOLIQUIDO IN (13)   " .
        "        /* (op.disk = 1 or op.vendadireta = 1) */   " .
        "         GROUP BY produtos.id, to_char(datahoraprevisaoentrega,'yyyymmdd')   " .
        "  " .
        "         UNION ALL   " .
        "           " .


        "        SELECT produto_id, dia_seq AS dia, quantidade, precomedio FROM ( " . 
        "            SELECT " . 
        "                    TO_CHAR(TRUNC(TO_DATE('".$this->dataReferencia->format('Y-m')."' || '-01', 'YYYY-MM-DD'), 'MONTH') + LEVEL - 1, 'YYYYMMDD') AS dia_seq " . 
        "                FROM " . 
        "                    DUAL " . 
        "                CONNECT BY " . 
        "                    LEVEL <= TO_CHAR(TO_DATE('".$this->dataReferencia->format('Y-m-d')."', 'YYYY-MM-DD'), 'DD') " . 
        "        ) CROSS JOIN  " . 
        "        ( " . 
        "            select 999 AS produto_id, sum(meta.quantidade)/".Carbon::Parse($this->dataReferencia)->endOfMonth()->day." as quantidade, 0 AS precomedio  " . 
        "                        from metavendas meta  " . 
        "                        inner join produtos on meta.produto_id = produtos.id  " . 
        "                        inner join empresas on meta.empresa_id = empresas.id " . 
        "                        INNER JOIN SETORS setor ON meta.SETOR_ID = SETOR.ID AND setor.ESTOQUEPROPRIO = 1 " . 
        "                        where empresas.id = ".Session::get('empresa_padrao')->id."  and  " . 
        "                        datameta BETWEEN TO_DATE('".$this->dataReferencia->format('Y-m')."' || '-01', 'YYYY-MM-DD')  AND TO_DATE('".$this->dataReferencia->format('Y-m-d')." 23:59:59', 'YYYY-MM-DD HH24:MI:SS') and    " . 
        "                        produtos.tipo_glp = 3  " . 
        "                        group by to_char(meta.datameta,'yyyymmdd')  " . 
        "        ) " .
        "     )  qryvenda ON qryvenda.produto_id = p.id AND qryvenda.dia = dia_seq   " .
        "      " .
        "     ORDER BY p.DESCRICAO, DIA_SEQ    ";
        return DB::select($query);
    }


    public function getDataDreMensal($dataReferencia){
        $valorfaturamento = $this->getValorFaturamentoDre($dataReferencia);
        $dataTabela = FechamentomensalDreRepository::getDataDreCustosFixos($dataReferencia);
        $result = [];
        foreach($dataTabela as $row){
            if($row->cabecalho== 0){
                $row->percentualfat = $row->plano=='Descrição' ? '%Fat' : ($row->valor == null ? null : ($valorfaturamento == 0 || !is_numeric($row->valor) ? 0 : $row->valor/$valorfaturamento*100));
                array_push($result, $row);
            }
            
        }
        return $result;
    }

    public function getValorFaturamentoDre($dataReferencia){
        $receitas =  FechamentomensalDreRepository::getDataDreReceita($dataReferencia);
        $valorfaturamento = 0;
        if(isset($receitas[count($receitas)-1])){
            $valorfaturamento = $receitas[count($receitas)-1]->valor;
        }
        return $valorfaturamento;
    }

    public function getDataMarketShare($dataReferencia){
        $query = 
        " SELECT   " .
        "     p.DESCRICAO, mes_seq, COALESCE(qryvenda.quantidade, 0) AS quantidade, COALESCE(qryvenda.precomedio, 0) AS precomedio  " .
        " FROM (  " .
        "     SELECT" .
        "         TO_CHAR(TRUNC(ADD_MONTHS(TO_DATE('".$dataReferencia->format('Y-m-d')."', 'YYYY-MM-DD'), 1 - LEVEL), 'MM'), 'YYYYMM') AS mes_seq" .
        "     FROM" .
        "         dual" .
        "     CONNECT BY" .
        "         LEVEL <= 12" .
        "     ORDER BY" .
        "         mes_seq" .
        " ) CROSS JOIN ( " .
        "     SELECT 998 AS id, 'Realizado' AS descricao FROM dual " .
        "     UNION ALL SELECT 999 AS id, 'Meta' AS descricao FROM dual) p  " .
        "     LEFT JOIN (  " .
        "         select 998 AS produto_id,   " .
        "                 to_char(datahoraprevisaoentrega,'yyyymm') as mes,  " .
        "                 sum(items.quantidade) AS quantidade,   " .
        "                 sum(items.precovendaunitario*items.quantidade)/sum(items.quantidade) as precomedio  " .
        "         from pedidos  " .
        "         inner join pedidoitems items on items.pedido_id = pedidos.id  " .
        "         inner join produtos on items.produto_id = produtos.id  " .
        "         inner join condicaopagamentos cond on pedidos.condicaopagamento_id = cond.id " .
        "         inner join pedidooperacaos op on pedidos.pedidooperacao_id = op.id " .
        "         INNER JOIN setors ON setors.id = pedidos.ENTREGASETOR_ID " .
        "         where pedidos.empresa_id = ".Session::get('empresa_padrao')->id." and  " .
        "         pedidos.datahoraprevisaoentrega BETWEEN TO_DATE('".Carbon::parse($dataReferencia)->subYear()->format('Y-m-d')." 00:00:00', 'YYYY-MM-DD HH24:MI:SS') AND TO_DATE('".$dataReferencia->format('Y-m-d')." 23:59:59', 'YYYY-MM-DD HH24:MI:SS') and" .
        "         pedidos.pedidosituacao_id in (select id from pedidosituacaos where empresa_id = ".Session::get('empresa_padrao')->id." and fechadoconcluido = 1) and  " .
        "         produtos.tipo_glp IN (3) AND produtos.PESOLIQUIDO IN (13) and " .
        "         setors.ESTOQUEPROPRIO = 1 and " .
        "         (op.disk = 1 or op.vendadireta = 1)  " .
        "         GROUP BY produtos.id, to_char(datahoraprevisaoentrega,'yyyymm')  " .
        "         " .
        "         UNION ALL " .
        "         " .
        "         SELECT 999 AS produto_id, MES_SEQ AS mes, round(quantidade) AS quantidade, precomedio FROM (" .
        "             SELECT" .
        "                 TO_CHAR(TRUNC(ADD_MONTHS(TO_DATE('".$dataReferencia->format('Y-m-d')."', 'YYYY-MM-DD'), 1 - LEVEL), 'MM'), 'YYYYMM') AS mes_seq" .
        "             FROM" .
        "                 dual" .
        "             CONNECT BY" .
        "                 LEVEL <= 12" .
        "             ORDER BY" .
        "                 mes_seq" .
        "         ) CROSS JOIN (" .
        "             SELECT sum(coalesce(s.qtderesidencias, 0)*COALESCE(e.FATORPOTENCIALVENDA, 0)/13) AS  quantidade, 0 AS precomedio" .
        "             FROM setors s " .
        "             INNER JOIN EMPRESACONFIGS e ON e.EMPRESA_ID = s.EMPRESA_ID " .
        "             WHERE s.EMPRESA_ID = ".Session::get('empresa_padrao')->id." AND s.ATIVO = 1 AND s.ESTOQUEPROPRIO = 1" .
        "         )" .
        "         " .
        "     )  qryvenda ON qryvenda.produto_id = p.id AND qryvenda.mes = mes_seq  " .
        "     ORDER BY p.DESCRICAO, MES_SEQ";
        return DB::select($query);
    }

    public function getDataBalanco($dataReferencia){
        $dataTabela = [];
        $data = FechamentomensalBalancoRepository::getDataBalancoContas($dataReferencia);
        $dataTabela =  $data->result;
        $result = [];
        foreach($dataTabela as $row){
            if($row->cabecalho== 0 && $row->valor != 0){
                if($row->tipodescricao == 'Total'){
                    $row->descricao = 'Total';
                }
                array_push($result, $row);
            }
        }
        return $result;
    }

    public function getDetalhes()
    {
        try {
            $mes = \Input::get("mes", "");
            $ano = \Input::get("ano", "");
            $plano_id = \Input::get("plano_id", "");
            $juros = \Input::get("juros", "");
            $dataReferencia = Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, "0", STR_PAD_LEFT)."01")->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');
            $valorfaturamento = $this->getValorFaturamentoDre($dataReferencia);
            $data = [];
            if($plano_id == -1){
                $data = FechamentomensalDreRepository::getDataDetalhesFaturamento($dataReferencia, 2);
            } elseif($plano_id == -2){
                $data = FechamentomensalDreRepository::getDataDetalhesCustoVariavel($dataReferencia);
            } elseif($plano_id > 0){
                $data = FechamentomensalDreRepository::getDataDetalhesCustoFixo($dataReferencia, $plano_id, $juros);
            }
            foreach($data as $row){
                $row->percentualfat = $row->descricao=='Descrição' ? '%Fat' : ($row->valor == null ? null : ($valorfaturamento == 0 || !is_numeric($row->valor) ? 0 : $row->valor/$valorfaturamento*100));
                
            }
            return responseSuccess($data);
        } catch(Exception $e){
            return responseError(getErrorsException($e));
        }
    }

    public function getCentroCustos()
    {
        try {
            $mes = \Input::get("mes", "");
            $ano = \Input::get("ano", "");
            $plano_id = \Input::get("plano_id", "");
            $dataReferencia = Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, "0", STR_PAD_LEFT)."01")->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');
            $valorfaturamento = $this->getValorFaturamentoDre($dataReferencia);
            $result = FechamentomensalDreRepository::getDataCentroCustos($plano_id, $dataReferencia);
            foreach($result as $row){
                $row->percentualfat = $row->descricao=='Descrição' ? '%Fat' : ($row->valor == null ? null : ($valorfaturamento == 0 || !is_numeric($row->valor) ? 0 : $row->valor/$valorfaturamento*100));
                
            }
            return responseSuccess($result);
        } catch(Exception $e){
            return responseError(getErrorsException($e));
        }
    }

}
