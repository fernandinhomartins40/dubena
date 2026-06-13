<?php

/**
 * Created by PhpStorm.
 * User: jeff
 * Date: 04/06/2018
 * Time: 14:59
 */

namespace App\Repository;

use App\Planoconta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class FechamentomensalBalancoRepository
{

    public static function getDataBalancoContas($dataReferencia){
        
        $query = 
        " SELECT 0 as tipo, tipo as tipodescricao, conta as descricao, null as custo, " .
        " inicial + COALESCE(sum(CASE WHEN mov.PAGARRECEBER = 'R' THEN mov.VALOREFETIVADO ELSE mov.VALOREFETIVADO * -1 END), 0) AS valor, 0 as clicavel, 0 as cabecalho  " .
        " FROM ( " .
        " 	select co.id " .
        " 	AS conta_id, tipo.perfil as perfil, tipo.descricao as tipo, co.descricao as conta, " .
        " 	c.DATAHORAFECHAMENTO AS fechamento, c.SALDOFINAL AS inicial, " .
        " 	row_number() over(PARTITION BY c.CONTA_ID ORDER BY DATAHORAFECHAMENTO DESC) AS row_seq " .
        " 	FROM CONTAFECHAMENTOS c " .
        " 	INNER JOIN CONTAS co ON c.CONTA_ID = co.ID " .
        " 	INNER JOIN contatipos tipo on co.contatipo_id = tipo.id " .
        " 	INNER JOIN empresas on co.empresa_id = empresas.id " .
        " 	WHERE c.DATAHORAFECHAMENTO <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        " 	AND empresas.id = ". Session::get('empresa_padrao')->id . "  " .
        " 	AND c.FECHADO = 1 " .
        " 	AND co.ATIVO = 1 " .
        " ) con " .
        " LEFT JOIN CONTAMOVIMENTOS mov ON mov.CONTA_ID = con.conta_id " .
        " 	AND mov.DATAHORABAIXA BETWEEN con.fechamento AND to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        " WHERE con.row_seq = 1 " .
        " GROUP BY tipo, conta, inicial " .
        " order by tipodescricao, descricao ";
        $result = [];
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Caixas/Contas', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null, 'custo'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Tipo', 'plano_id' => -1, 'descricao' => 'Conta', 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>'Saldo', 'custo'=>null];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $total = array_reduce($data, function ($acc, $item) {
            if($item->descricao != 'Total'){
                return $acc + $item->valor;
            } else {
                return $acc;
            }
        }, 0);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Total', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total, 'custo'=>null];
        array_push($result, $rec);
        foreach($result as $row){
            if($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
            }
            if($row->custo != null && is_numeric($row->custo)){
                $row->custo = floatval($row->custo);
            }
        }
         return (object) ['result'=>$result, 'total'=>$total];
    }

    public static function getDataBalancoEstoque($dataReferencia){
        
        $query = 
        " select fec.id, " .
        " produtos.descricao as tipodescricao, " .
        " est.customedio as custo, " .
        " sum(est.quantidade) as descricao, 0 as cabecalho, 0 as clicavel, " .
        " round(sum(est.customedio * est.quantidade),2) as valor " .
        " from ( " .
        " 	select max(id) as id, max(updated_at) as updated_at  " .
        " 	from estoquefechamentos fec " .
        " 	where trunc(fec.datahorafechamento) = trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')) " .
        " ) fec " .
        " inner join estoquefechamentosetors est on est.estoquefechamento_id = fec.id  " .
        " inner join empresas on est.empresa_id = empresas.id " .
        " inner join produtos on est.produto_id = produtos.id " .
        " where est.quantidade <> 0  " .
        " and produtos.PRODUTOCLASSE_ID <> 128 " .
        " and empresas.id = ". Session::get('empresa_padrao')->id . " " .
        " group by produtos.descricao, fec.id, est.customedio  " .
        " order by tipodescricao ";
        $result = [];
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Estoque', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null, 'custo'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Produto', 'plano_id' => -1, 'descricao' => 'Quantidade', 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>'Valor', 'custo'=>'Custo Médio'];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $totais = array_reduce($data, function ($acc, $item) {
            if($item->descricao != 'Total'){
                return [$acc[0] + $item->valor, $acc[1] + $item->descricao];
            } else {
                return $acc;
            }
        }, [0, 0]);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Total', 'plano_id' => -1, 'descricao' => $totais[1], 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$totais[0], 'custo'=>null];
        array_push($result, $rec);
        foreach($result as $row){
            if($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
            }
            if($row->custo != null && is_numeric($row->custo)){
                $row->custo = floatval($row->custo);
            }
            if($row->descricao != null && is_numeric($row->descricao)){
                $row->descricao = floatval($row->descricao);
            }
        }
         return (object) ['result'=>$result, 'total'=>$totais[0]];
    }

    public static function getDataBalancoContasReceber($dataReferencia){
        
        $query = 
        " select plano_id, descricao as tipodescricao, null as descricao, 0 as cabecalho, 0 as clicavel, null as custo, sum(valor) as valor " .
        " from( " .
        "     select ( " .
        "         select id " .
        "         from planocontas plano " .
        "         where nivel = 1 " .
        "         start with id = rato.planoconta_id " .
        "         connect by id = prior paiplanoconta_id " .
        "     ) as plano_id,  " .
        "     ( " .
        "         select descricao " .
        "         from planocontas plano " .
        "         where nivel = 1 " .
        "         start with id = rato.planoconta_id " .
        "         connect by id = prior paiplanoconta_id " .
        "     ) as descricao, " .
        "     (case when rato.percentual > 1 then (rato.percentual / 100) * parc.valorefetivado else rato.percentual * parc.valorefetivado end) as valor " .
        "     from financeiroparcelas parc " .
        "     inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "     inner join empresas on parc.empresa_id = empresas.id " .
        "     INNER JOIN financeiros fin ON parc.FINANCEIRO_ID = fin.id " .
        "     where agrupamento_status < 2 " .
        " 	AND fin.DATACOMPETENCIA <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        "     AND ( " .
        "     	baixado = 0 " .
        "     	OR (parc.DATAHORABAIXA > to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')) " .
        "     ) " .
        "     AND parc.pagarreceber = 'R' " .
        "     AND empresas.id =  ". Session::get('empresa_padrao')->id . " " .
        "     group by parc.id,rato.planoconta_id,rato.percentual,rato.percentual,parc.valorefetivado,rato.id " .
        " ) rec_pag " .
        " group by plano_id, descricao " .
        " UNION ALL " .
        " select plano_id, descricao||' **' AS tipodescricao, null as descricao, 0 as cabecalho, 0 as clicavel, null as custo, sum(valor) as valor " .
        " from( " .
        "     select ( " .
        "         select id " .
        "         from planocontas plano " .
        "         where nivel = 1 " .
        "         start with id = rato.planoconta_id " .
        "         connect by id = prior paiplanoconta_id " .
        "     ) as plano_id,  " .
        "     ( " .
        "         select descricao " .
        "         from planocontas plano " .
        "         where nivel = 1 " .
        "         start with id = rato.planoconta_id " .
        "         connect by id = prior paiplanoconta_id " .
        "     ) as descricao, " .
        "     (case when rato.percentual > 1 then (rato.percentual / 100) * parc.valorefetivado else rato.percentual * parc.valorefetivado end) as valor " .
        "     from financeiroparcelas parc " .
        "     inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "     inner join empresas on parc.empresa_id = empresas.id " .
        "     INNER JOIN financeiros fin ON parc.FINANCEIRO_ID = fin.id " .
        "     where agrupamento_status < 2 " .
        "     AND fin.DATACOMPETENCIA > LAST_DAY(ADD_MONTHS(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),-1)) " .
        " 	AND fin.DATACOMPETENCIA <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        "     AND baixado = 1 " .
        " 	AND parc.DATAHORABAIXA <= LAST_DAY(ADD_MONTHS(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),-1)) " .
        "     AND parc.pagarreceber = 'R' " .
        "     AND empresas.id =  ". Session::get('empresa_padrao')->id . " " .
        "     group by parc.id,rato.planoconta_id,rato.percentual,rato.percentual,parc.valorefetivado,rato.id " .
        " ) rec_pag " .
        " group by plano_id, descricao " .
        " order by tipodescricao ";
        $result = [];
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Contas a Receber', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null, 'custo'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Descrição', 'plano_id' => -1, 'descricao' => null, 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>'Valor', 'custo'=>null];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $total = array_reduce($data, function ($acc, $item) {
            if($item->descricao != 'Total'){
                return $acc + $item->valor;
            } else {
                return $acc;
            }
        }, 0);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Total', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total, 'custo'=>null];
        array_push($result, $rec);
        foreach($result as $row){
            if($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
            }
            if($row->descricao != null && is_numeric($row->descricao)){
                $row->descricao = floatval($row->descricao);
            }
        }
         return (object) ['result'=>$result, 'total'=>$total];
    }

    public static function getDataBalancoComodatoCliente($dataReferencia){
        
        $query = 
        " SELECT produto as tipodescricao, quantidade as descricao, 0 as cabecalho, 0 as clicavel, null as custo,  " .
        " round(customedio * quantidade, 2) as valor " .
        " FROM ( " .
        " 	SELECT produto_id, max(produto) AS produto, sum(customedio) AS customedio, sum(quantidade) AS quantidade " .
        " 	from( " .
        " 		select produtos.id AS produto_id, produtos.descricao as produto, " .
        " 		0 AS customedio, sum(quantidade) as quantidade " .
        " 		from comodatos " .
        " 		inner join empresas on comodatos.empresa_id = empresas.id " .
        " 		inner join comodatoitems items on items.comodato_id = comodatos.id " .
        " 		inner join produtos on items.produto_id = produtos.id " .
        " 		where empresas.id = ". Session::get('empresa_padrao')->id . "  " .
        " 		AND items.quantidade <> 0 " .
        " 		AND tipo <> 2 " .
        " 		AND comodatos.DATACONTRATO <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        " 		AND comodatos.ativo = 1 " .
        " 		group by produtos.id, produtos.descricao " .
        " 		 " .
        " 		UNION ALL " .
        " 		 " .
        " 		SELECT est.PRODUTO_ID AS produto_id, '' AS produto, max(est.CUSTOMEDIO) AS customedio, 0 AS quantidade " .
        " 		FROM ( " .
        " 			SELECT max(id) AS id, max(updated_at) AS UPDATED_AT  " .
        " 			FROM ESTOQUEFECHAMENTOS fec " .
        " 			WHERE trunc(fec.DATAHORAFECHAMENTO) = trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')) " .
        " 		) fec " .
        " 		INNER JOIN ESTOQUEFECHAMENTOSETORS est ON est.ESTOQUEFECHAMENTO_ID = fec.id " .
        " 		inner join empresas on est.empresa_id = empresas.id " .
        " 		WHERE empresas.id  = ". Session::get('empresa_padrao')->id . "  " .
        " 		GROUP BY est.PRODUTO_ID  " .
        " 	) prods " .
        " 	GROUP BY produto_id " .
        " ) cust " .
        " WHERE produto IS NOT null " .
        " order by tipodescricao ";
        $result = [];
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Estoque Comodatado - Cliente', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null, 'custo'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Produto', 'plano_id' => -1, 'descricao' => 'Quantidade', 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>'Valor', 'custo'=>null];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $totais = array_reduce($data, function ($acc, $item) {
            if($item->descricao != 'Total'){
                return [$acc[0] + $item->valor, $acc[1] + $item->descricao];
            } else {
                return $acc;
            }
        }, [0, 0]);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Total', 'plano_id' => -1, 'descricao' => $totais[1], 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$totais[0], 'custo'=>null];
        array_push($result, $rec);
        foreach($result as $row){
            if($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
            }
            if($row->descricao != null && is_numeric($row->descricao)){
                $row->descricao = floatval($row->descricao);
            }
        }
         return (object) ['result'=>$result, 'total'=>$totais[0]];
    }

    public static function getDataBalancoComodatoDistribuidora($dataReferencia){
        $query = 
        " SELECT produto as tipodescricao, quantidade as descricao, 0 as cabecalho, 0 as clicavel, null as custo,  " .
        " round(customedio * quantidade, 2) as valor " .
        " FROM ( " .
        " 	SELECT produto_id, max(produto) AS produto, sum(customedio) AS customedio, sum(quantidade) AS quantidade " .
        " 	from( " .
        " 		select produtos.id AS produto_id, produtos.descricao as produto, " .
        " 		0 AS customedio, sum(quantidade) as quantidade " .
        " 		from comodatos " .
        " 		inner join empresas on comodatos.empresa_id = empresas.id " .
        " 		inner join comodatoitems items on items.comodato_id = comodatos.id " .
        " 		inner join produtos on items.produto_id = produtos.id " .
        " 		where empresas.id = ". Session::get('empresa_padrao')->id . " " .
        " 		AND items.quantidade <> 0 " .
        " 		AND tipo = 2 " .
        " 		AND comodatos.DATACONTRATO <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        " 		AND comodatos.ativo = 1 " .
        " 		group by produtos.id, produtos.descricao " .
        " 		 " .
        " 		UNION ALL " .
        " 		 " .
        " 		SELECT est.PRODUTO_ID AS produto_id, '' AS produto, max(est.CUSTOMEDIO) AS customedio, 0 AS quantidade " .
        " 		FROM ( " .
        " 			SELECT max(id) AS id, max(updated_at) AS UPDATED_AT  " .
        " 			FROM ESTOQUEFECHAMENTOS fec " .
        " 			WHERE trunc(fec.DATAHORAFECHAMENTO) = trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')) " .
        " 		) fec " .
        " 		INNER JOIN ESTOQUEFECHAMENTOSETORS est ON est.ESTOQUEFECHAMENTO_ID = fec.id " .
        " 		inner join empresas on est.empresa_id = empresas.id " .
        " 		WHERE empresas.id = ". Session::get('empresa_padrao')->id . " " .
        " 		GROUP BY est.PRODUTO_ID  " .
        " 	) prods " .
        " 	GROUP BY produto_id " .
        " ) cust " .
        " WHERE produto IS NOT null " .
        " order by produto ";
        $result = [];
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Estoque Comodatado - Distribuidora', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null, 'custo'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Produto', 'plano_id' => -1, 'descricao' => 'Quantidade', 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>'Valor', 'custo'=>null];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $totais = array_reduce($data, function ($acc, $item) {
            if($item->descricao != 'Total'){
                return [$acc[0] + $item->valor, $acc[1] + $item->descricao];
            } else {
                return $acc;
            }
        }, [0, 0]);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Total', 'plano_id' => -1, 'descricao' => $totais[1], 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$totais[0], 'custo'=>null];
        array_push($result, $rec);
        foreach($result as $row){
            if($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
            }
            if($row->descricao != null && is_numeric($row->descricao)){
                $row->descricao = floatval($row->descricao);
            }
        }
         return (object) ['result'=>$result, 'total'=>$totais[0]];
    } 

    public static function getDataBalancoContasPagar($dataReferencia){
        
        $query = 
        " select plano_id, descricao as tipodescricao, null as descricao, 0 as cabecalho, 0 as clicavel, null as custo, sum(valor) as valor " .
        " from( " .
        "     select ( " .
        "         select id " .
        "         from planocontas plano " .
        "         where nivel = 1 " .
        "         start with id = rato.planoconta_id " .
        "         connect by id = prior paiplanoconta_id " .
        "     ) as plano_id,  " .
        "     ( " .
        "         select descricao " .
        "         from planocontas plano " .
        "         where nivel = 1 " .
        "         start with id = rato.planoconta_id " .
        "         connect by id = prior paiplanoconta_id " .
        "     ) as descricao, " .
        "     (case when rato.percentual > 1 then (rato.percentual / 100) * parc.valorefetivado else rato.percentual * parc.valorefetivado end) as valor " .
        "     from financeiroparcelas parc " .
        "     inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "     inner join empresas on parc.empresa_id = empresas.id " .
        "     INNER JOIN financeiros fin ON parc.FINANCEIRO_ID = fin.id " .
        "     where agrupamento_status < 2 " .
        " 	AND fin.DATACOMPETENCIA <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        "     AND ( " .
        "     	baixado = 0 " .
        "     	OR (parc.DATAHORABAIXA > to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')) " .
        "     ) " .
        "     AND parc.pagarreceber = 'P' " .
        "     AND empresas.id =  ". Session::get('empresa_padrao')->id . " " .
        "     group by parc.id,rato.planoconta_id,rato.percentual,rato.percentual,parc.valorefetivado,rato.id " .
        " ) rec_pag " .
        " group by plano_id, descricao " .
        " UNION ALL " .
        " select plano_id, descricao||' **' AS tipodescricao, null as descricao, 0 as cabecalho, 0 as clicavel, null as custo, sum(valor) as valor " .
        " from( " .
        "     select ( " .
        "         select id " .
        "         from planocontas plano " .
        "         where nivel = 1 " .
        "         start with id = rato.planoconta_id " .
        "         connect by id = prior paiplanoconta_id " .
        "     ) as plano_id,  " .
        "     ( " .
        "         select descricao " .
        "         from planocontas plano " .
        "         where nivel = 1 " .
        "         start with id = rato.planoconta_id " .
        "         connect by id = prior paiplanoconta_id " .
        "     ) as descricao, " .
        "     (case when rato.percentual > 1 then (rato.percentual / 100) * parc.valorefetivado else rato.percentual * parc.valorefetivado end) as valor " .
        "     from financeiroparcelas parc " .
        "     inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "     inner join empresas on parc.empresa_id = empresas.id " .
        "     INNER JOIN financeiros fin ON parc.FINANCEIRO_ID = fin.id " .
        "     where agrupamento_status < 2 " .
        "     AND fin.DATACOMPETENCIA > LAST_DAY(ADD_MONTHS(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),-1)) " .
        " 	AND fin.DATACOMPETENCIA <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        "     AND baixado = 1 " .
        " 	AND parc.DATAHORABAIXA <= LAST_DAY(ADD_MONTHS(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),-1)) " .
        "     AND parc.pagarreceber = 'P' " .
        "     AND empresas.id =  ". Session::get('empresa_padrao')->id . " " .
        "     group by parc.id,rato.planoconta_id,rato.percentual,rato.percentual,parc.valorefetivado,rato.id " .
        " ) rec_pag " .
        " group by plano_id, descricao " .
        " order by descricao ";
        $result = [];
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Contas a Pagar', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null, 'custo'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Descrição', 'plano_id' => -1, 'descricao' => null, 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>'Valor', 'custo'=>null];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $total = array_reduce($data, function ($acc, $item) {
            if($item->descricao != 'Total'){
                return $acc + $item->valor;
            } else {
                return $acc;
            }
        }, 0);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Total', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total, 'custo'=>null];
        array_push($result, $rec);
        foreach($result as $row){
            if($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
            }
            if($row->descricao != null && is_numeric($row->descricao)){
                $row->descricao = floatval($row->descricao);
            }
        }
         return (object) ['result'=>$result, 'total'=>$total];
    } 

    public static function getDataBalancoPatrimonio($dataReferencia, $subtotal){
        
        $query = 
        " select (case when length(descricao) > 30 then (substr(descricao,1,30) || '...') else descricao end) as tipodescricao,  " .
        " null as descricao, 0 as cabecalho, 0 as clicavel, null as custo, " .
        " valororiginal - (((sysdate - to_date(datacadastro)) * (depreciacaoporcentagem / depreciacaodias)) / 100 * valororiginal) as valor " .
        " from empresabems bens " .
        " inner join empresas on bens.empresa_id = empresas.id " .
        " where " .
        " empresas.id  =  ". Session::get('empresa_padrao')->id . " " . 
        " order by tipodescricao ";
        $result = [];
        $rec = (object) ['tipo'=> 4, 'tipodescricao' => '', 'plano_id' => -4, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>'', 'custo'=>null];
        array_push($result, $rec);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Resultado', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$subtotal, 'custo'=>null];
        array_push($result, $rec);
        $rec = (object) ['tipo'=> 4, 'tipodescricao' => '', 'plano_id' => -4, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>'', 'custo'=>null];
        array_push($result, $rec);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Patrimônio', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null, 'custo'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Descrição', 'plano_id' => -1, 'descricao' => null, 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>'Valor', 'custo'=>null];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $total = array_reduce($data, function ($acc, $item) {
            if($item->descricao != 'Total'){
                return $acc + $item->valor;
            } else {
                return $acc;
            }
        }, 0);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Total', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total, 'custo'=>null];
        array_push($result, $rec);
        $rec = (object) ['tipo'=> 4, 'tipodescricao' => '', 'plano_id' => -4, 'descricao' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>'', 'custo'=>null];
        array_push($result, $rec);
        $rec = (object) ['tipo'=> 0, 'tipodescricao' => 'Resultado', 'plano_id' => -1, 'descricao' => '', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$subtotal + $total, 'custo'=>null];
        array_push($result, $rec);
        foreach($result as $row){
            if($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
            }
            if($row->descricao != null && is_numeric($row->descricao)){
                $row->descricao = floatval($row->descricao);
            }
        }
         return $result;
    } 
}
