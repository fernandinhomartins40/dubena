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

class FechamentomensalDreRepository
{

    public static function getDataDreReceita($dataReferencia){
        
        $query = 

        "    select 0 as tipo, 'Receitas' as tipodescricao, -1 as plano_id, plano, 0 as cabecalho, 0 as clicavel, sum(valor) as valor " .
        "    from( " .
        "    select plano.descricao as plano, rato.valor*parc.valor/fi.valor as valor " .
        "    from financeiroparcelas parc " .
        "    inner join financeiros fi on fi.id = parc.financeiro_id " .
        "    inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "    inner join planocontas plano on rato.planoconta_id = plano.id " .
        "    where parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "    parc.datacompetencia between  " .
        "    trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        "    to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and " .
        "    parc.agrupamento_status < 2 and plano.investimento = 0 and " .
        "    parc.pagarreceber = 'R'  " .
        "     " .
        "    union all " .
        "     " .
        "    select descricao as plano, " .
        "    sum(valor) as valor " .
        "    from(  " .
        "        select plano_id, string_agg(codigo, '' order by codigo) as codigo, " .
        "        string_agg(descricao, '' order by descricao) as descricao, sum(nivel) as nivel, " .
        "        string_agg(finalizador, '' order by finalizador) as finalizador, " .
        "        sum(juros + multa) as valor " .
        "        from( " .
        "        select id as plano_id, codigo, descricao, nivel, 0 as juros, 0 as multa, finalizador  " .
        "        from planocontas  " .
        "        where id in (  " .
        "            select pcrecetajuro_id  " .
        "            from empresaconfigs config  " .
        "            where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
        "        ) " .
        "         " .
        "        union all  " .
        "         " .
        "        select sum(plano_id) as plano_id, '' as codigo, '' as descricao, " .
        "        0 as nivel, sum(juros) as juros, sum(multa) as multa, '' as finalizador  " .
        "        from(  " .
        "            select pcrecetajuro_id as plano_id, pcdespesasdesconto_id as plano_desconto, 0 as juros, " .
        "            0 as multa, 0 as desconto  " .
        "            from empresaconfigs config  " .
        "            where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1  " .
        "             " .
        "            union all  " .
        "             " .
        "            select 0 as plano_id, 0 as plano_desconto, sum(juros) as juros, " .
        "            sum(multa) as multa, 0 as desconto  " .
        "            from financeiroparcelas parc " .
        "            where parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "            parc.datacompetencia between  " .
        "            trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and  " .
        "            to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and  " .
        "            pagarreceber = 'R' and agrupamento_status < 2 and  " .
        "            (juros <> 0 or multa <> 0) " .
        "        ) juros_multas  " .
        "        ) recurssive  " .
        "        group by plano_id " .
        "         " .
        "        union all " .
        "         " .
        "        select plano_desconto, " .
        "        string_agg(codigo, '' order by codigo) as codigo,  " .
        "        string_agg(descricao, '' order by descricao) as descricao, " .
        "        sum(nivel) as nivel, " .
        "        string_agg(finalizador, '' order by finalizador) as finalizador, sum(desconto) as valor  " .
        "        from(  " .
        "        select id as plano_desconto, codigo, descricao, nivel, 0 as desconto, finalizador  " .
        "        from planocontas plano  " .
        "        where id in (  " .
        "            select pcreceitadesconto_id  " .
        "            from empresaconfigs config " .
        "            where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1  " .
        "        ) " .
        "         " .
        "        union all  " .
        "         " .
        "        select sum(plano_desconto) as plano_desconto, '' as codigo, " .
        "        '' as descricao, 0 as nivel, sum(desconto) as desconto,  " .
        "        '' as finalizador  " .
        "        from(  " .
        "            select pcreceitadesconto_id as plano_desconto, 0 as desconto  " .
        "            from empresaconfigs config " .
        "            where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
        "             " .
        "            union all  " .
        "             " .
        "            select 0 as plano_desconto, sum(desconto) as desconto  " .
        "            from financeiroparcelas parc " .
        "            where parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "            parc.datacompetencia between " .
        "            trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        "            to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and " .
        "            pagarreceber = 'P' and agrupamento_status < 2 and " .
        "            parc.desconto <> 0 " .
        "        ) descontos " .
        "        ) query_2  " .
        "        group by plano_desconto  " .
        "    ) principal  " .
        "    group by descricao " .
        "    ) plano_contas " .
        "    where plano is not null and valor is not null and valor <> 0 " .
        "    group by plano " .
        "    order by valor desc ";
        $result = [];
        $rec = (object) ['tipo'=> 0, 'tipodescricao ' => 'Receitas', 'plano_id' => -1, 'plano' => 'Receitas', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 0, 'tipodescricao ' => 'Receitas', 'plano_id' => -1, 'plano' => 'Descrição', 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>$dtaux];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $total = array_reduce($data, function ($acc, $item) {
            if($item->plano != 'Total'){
                return $acc + $item->valor;
            } else {
                return $acc;
            }
        }, 0);
        $rec = (object) ['tipo'=> 0, 'tipodescricao ' => 'Receitas', 'plano_id' => -1, 'plano' => 'Total', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total];
        array_push($result, $rec);
        foreach($result as $row){
            $row->percentual = '';
            if($row->tipo == 3){
                $row->percentual = '';
            } elseif($row->cabecalho == 1 && $row->plano != 'Total'){
                $row->percentual = '%';
            } elseif($row->cabecalho == 2){
                $row->percentual = '';
            } elseif($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
                $row->percentual = $total == 0 ? 0 : $row->valor/$total*100;
            }
        }
        return $result;
    }

    public static function getDataDreCustosVariaveis($dataReferencia){
        $query = 
        " SELECT 1 as tipo, 'Custos Variáveis' as tipodescricao, -2 as plano_id, '' as plano, 0 as cabecalho, 0 as clicavel, sum(valor) AS valor FROM ( " .
        " SELECT  " .
        " sum(valorant) - sum(valor) AS valor " .
        " FROM ( " .
        " select fec.id, " .
        " produtos.descricao as produto, " .
        " est.customedio as custoant, " .
        " sum(est.quantidade) as quantidadeant, " .
        " round(sum(est.customedio * est.quantidade),2) as valorant, " .
        " 0 AS custo, 0 AS quantidade, 0 AS valor " .
        " from ( " .
        " 	select max(id) as id, max(updated_at) as updated_at  " .
        " 	from estoquefechamentos fec " .
        " 	where trunc(fec.datahorafechamento) = LAST_DAY(ADD_MONTHS(trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')),-1)) " .
        " ) fec " .
        " inner join estoquefechamentosetors est on est.estoquefechamento_id = fec.id  " .
        " inner join empresas on est.empresa_id = empresas.id " .
        " inner join produtos on est.produto_id = produtos.id " .
        " where est.quantidade <> 0  " .
        " and produtos.PRODUTOCLASSE_ID <> 128 " .
        " and empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " group by produtos.descricao, fec.id, est.customedio  " .
        " UNION all " .
        " select fec.id, " .
        " produtos.descricao as produto, " .
        " 0 AS custoant, 0 AS quantidadeant, 0 AS valorant, " .
        " est.customedio as custo, " .
        " sum(est.quantidade) as quantidade, " .
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
        " and empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " group by produtos.descricao, fec.id, est.customedio  " .
        " order by produto " .
        " )  " .
        " UNION ALL " .
        " select sum(valor) as valor " .
        " from( " .
        "   select 0 as juros, " .
        "   ( " .
        "     select id " .
        "     from planocontas " .
        "     where nivel = 1 " .
        "     start with id = plano_id " .
        "     connect by id = prior paiplanoconta_id " .
        "   ) as plano_id, " .
        "   ( " .
        "     select descricao " .
        "     from planocontas " .
        "     where nivel = 1 " .
        "     start with id = plano_id " .
        "     connect by id = prior paiplanoconta_id " .
        "   ) as plano, sum(valor) as valor " .
        "   from( " .
        "     select plano_id,sum(valor) as valor " .
        "     from( " .
        "       select rato.planoconta_id as plano_id, rato.valor*parc.valor/fi.valor as valor " .
        "       from financeiroparcelas parc " .
        "       inner join financeiros fi on fi.id = parc.financeiro_id " .
        "       inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "       inner join planocontas plano on rato.planoconta_id = plano.id " .
        "       where parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "       parc.datacompetencia between  " .
        "       trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        "       to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and " .
        "       parc.agrupamento_status < 2 and plano.investimento = 0 and " .
        "       parc.pagarreceber = 'P' and plano.custosvariaveis = 1 " .
        "     ) " .
        "     group by plano_id " .
        "   ) financas " .
        "   group by plano_id " .
        " ) plano_contas " .
        " where plano is not null and valor is not null and valor <> 0  " .
        " UNION ALL " .
        " SELECT sum(valorant) - sum(valor) AS valor FROM ( " .
        " SELECT 0 AS valorant, sum(customedio * quantidade) as valor " .
        " FROM ( " .
        " 	SELECT produto_id, max(produto) AS produto, sum(customedio) AS customedio, sum(quantidade) AS quantidade " .
        " 	from( " .
        " 		select produtos.id AS produto_id, produtos.descricao as produto, " .
        " 		0 AS customedio, sum(CASE WHEN tipo = 2 THEN quantidade*-1 ELSE quantidade end) as quantidade " .
        " 		from comodatos " .
        " 		inner join empresas on comodatos.empresa_id = empresas.id " .
        " 		inner join comodatoitems items on items.comodato_id = comodatos.id " .
        " 		inner join produtos on items.produto_id = produtos.id " .
        " 		AND empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " 		AND items.quantidade <> 0 " .
        " 		AND comodatos.DATACONTRATO <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        " 		AND comodatos.ativo = 1 " .
        " 		group by produtos.id, produtos.descricao " .
        " 		UNION ALL " .
        " 		SELECT est.PRODUTO_ID AS produto_id, '' AS produto, max(est.CUSTOMEDIO) AS customedio, 0 AS quantidade " .
        " 		FROM ( " .
        " 			SELECT max(id) AS id, max(updated_at) AS UPDATED_AT  " .
        " 			FROM ESTOQUEFECHAMENTOS fec " .
        " 			WHERE trunc(fec.DATAHORAFECHAMENTO) = trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')) " .
        " 		) fec " .
        " 		INNER JOIN ESTOQUEFECHAMENTOSETORS est ON est.ESTOQUEFECHAMENTO_ID = fec.id " .
        " 		inner join empresas on est.empresa_id = empresas.id " .
        " 		WHERE empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " 		GROUP BY est.PRODUTO_ID  " .
        " 	) prods " .
        " 	GROUP BY produto_id " .
        " ) cust " .
        " WHERE produto IS NOT NULL " .
        " UNION ALL  " .
        " SELECT   " .
        " sum(customedio * quantidade) as valorant, 0 AS valor " .
        " FROM ( " .
        " 	SELECT produto_id, max(produto) AS produto, sum(customedio) AS customedio, sum(quantidade) AS quantidade " .
        " 	from( " .
        " 		select produtos.id AS produto_id, produtos.descricao as produto, " .
        " 		0 AS customedio, sum(CASE WHEN tipo = 2 THEN quantidade*-1 ELSE quantidade end) as quantidade " .
        " 		from comodatos " .
        " 		inner join empresas on comodatos.empresa_id = empresas.id " .
        " 		inner join comodatoitems items on items.comodato_id = comodatos.id " .
        " 		inner join produtos on items.produto_id = produtos.id " .
        " 		where empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " 		AND items.quantidade <> 0 " .
        " 		AND comodatos.DATACONTRATO <= LAST_DAY(ADD_MONTHS(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),-1)) " .
        " 		AND comodatos.ativo = 1 " .
        " 		group by produtos.id, produtos.descricao " .
        " 		UNION ALL " .
        " 		SELECT est.PRODUTO_ID AS produto_id, '' AS produto, max(est.CUSTOMEDIO) AS customedio, 0 AS quantidade " .
        " 		FROM ( " .
        " 			SELECT max(id) AS id, max(updated_at) AS UPDATED_AT  " .
        " 			FROM ESTOQUEFECHAMENTOS fec " .
        " 			WHERE trunc(fec.DATAHORAFECHAMENTO) = LAST_DAY(ADD_MONTHS(trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')),-1)) " .
        " 		) fec " .
        " 		INNER JOIN ESTOQUEFECHAMENTOSETORS est ON est.ESTOQUEFECHAMENTO_ID = fec.id " .
        " 		inner join empresas on est.empresa_id = empresas.id " .
        " 		WHERE empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " 		GROUP BY est.PRODUTO_ID  " .
        " 	) prods " .
        " 	GROUP BY produto_id " .
        " ) cust " .
        " WHERE produto IS NOT null " .
        " ) " .
        " ) ";
        $result = [];
        $rec = (object) ['tipo'=> 1, 'tipodescricao ' => 'Custos Variáveis', 'plano_id' => -2, 'plano' => 'Custos Variáveis', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 1, 'tipodescricao ' => 'Custos Variáveis', 'plano_id' => -2, 'plano' => 'Descrição', 'cabecalho'=> 1, 'clicavel' => 1, 'valor'=>$dtaux];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $total = array_reduce($data, function ($acc, $item) {
            if($item->plano != 'Total'){
                return $acc + $item->valor;
            } else {
                return $acc;
            }
        }, 0);
        $rec = (object) ['tipo'=> 1, 'tipodescricao ' => 'Custos Variáveis', 'plano_id' => -2, 'plano' => 'Total', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total];
        array_push($result, $rec);
        foreach($result as $row){
            $row->percentual = '';
            if($row->tipo == 3){
                $row->percentual = '';
            } elseif($row->cabecalho == 1 && $row->plano != 'Total'){
                $row->percentual = '%';
            } elseif($row->cabecalho == 2){
                $row->percentual = '';
            } elseif($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
                $row->percentual = $total == 0 ? 0 : $row->valor/$total*100;
            }
        }
        return $result;

    }

    public static function getDataDreCustosFixos($dataReferencia){
        $query = 
        " select 2 as tipo, 'Custos Fixos' as tipodescricao, plano_id, plano, 0 as cabecalho, 1 as clicavel, sum(valor) as valor, sum(juros) as juros " .
        " from( " .
        "   select 0 as juros, " .
        "   ( " .
        "     select id " .
        "     from planocontas " .
        "     where nivel = 1 " .
        "     start with id = plano_id " .
        "     connect by id = prior paiplanoconta_id " .
        "   ) as plano_id, " .
        "   ( " .
        "     select descricao " .
        "     from planocontas " .
        "     where nivel = 1 " .
        "     start with id = plano_id " .
        "     connect by id = prior paiplanoconta_id " .
        "   ) as plano, sum(valor) as valor " .
        "   from( " .
        "     select plano_id,sum(valor) as valor " .
        "     from( " .
        "       select rato.planoconta_id as plano_id, rato.valor*parc.valor/fi.valor as valor " .
        "       from financeiroparcelas parc " .
        "       inner join financeiros fi on fi.id = parc.financeiro_id " .
        "       inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "       inner join planocontas plano on rato.planoconta_id = plano.id " .
        "       where parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "       parc.datacompetencia between  " .
        "       trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        "       to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and " .
        "       parc.agrupamento_status < 2 and plano.investimento = 0 and " .
        "       parc.pagarreceber = 'P' and plano.custosvariaveis = 0 " .
        "     ) " .
        "     group by plano_id " .
        "   ) financas " .
        "   group by plano_id " .
        "    " .
        "   union all " .
        "    " .
        "   select 1 as juros, plano_id,descricao as plano, " .
        "   sum(valor) as valor " .
        "   from( " .
        "     select plano_id, string_agg(codigo, '' order by codigo) as codigo, " .
        "     string_agg(descricao, '' order by descricao) as descricao, sum(nivel) as nivel, " .
        "     string_agg(finalizador, '' order by finalizador) as finalizador, " .
        "     sum(juros + multa) as valor " .
        "     from( " .
        "       select id as plano_id, codigo, descricao, nivel, 0 as juros, 0 as multa, finalizador  " .
        "       from planocontas  " .
        "       where nivel = 1 " .
        "       start with id in (  " .
        "         select pcdespesasjuro_id  " .
        "         from empresaconfigs config " .
        "         where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
        "       ) " .
        "       connect by prior paiplanoconta_id = id " .
        "        " .
        "       union all  " .
        "        " .
        "       select sum(plano_id) as plano_id, '' as codigo, '' as descricao, " .
        "       0 as nivel, sum(juros) as juros, sum(multa) as multa, '' as finalizador  " .
        "       from(  " .
        "         select  " .
        "         ( " .
        "           select id from planocontas " .
        "           where nivel = 1 " .
        "           start with id = pcdespesasjuro_id " .
        "           connect by prior paiplanoconta_id = id " .
        "         ) as plano_id, " .
        "         pcdespesasjuro_id as plano_desconto, 0 as juros, " .
        "         0 as multa, 0 as desconto  " .
        "         from empresaconfigs config " .
        "         where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1  " .
        "          " .
        "         union all  " .
        "          " .
        "         select 0 as plano_id, 0 as plano_desconto, sum(juros), sum(multa), 0 as desconto  " .
        "         from( " .
        "           select  parc.id, juros, multa " .
        "           from financeiroparcelas parc  " .
        "           inner join financeirorateios rato on rato.financeiro_id = parc.financeiro_id " .
        "           inner join planocontas plans on rato.planoconta_id = plans.id " .
        "           where parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "           parc.datacompetencia between  " .
        "           trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and  " .
        "           to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and  " .
        "           parc.pagarreceber = 'P' and parc.agrupamento_status < 2 and  " .
        "           (juros <> 0 or multa <> 0) " .
        "           group by parc.id, parc.juros, parc.multa " .
        "         ) juros_multa " .
        "       ) juros_multas  " .
        "     ) recurssive  " .
        "     group by plano_id " .
        "      " .
        "     union all " .
        "      " .
        "     select plano_desconto, " .
        "     string_agg(codigo, '' order by codigo) as codigo,  " .
        "     string_agg(descricao, '' order by descricao) as descricao, " .
        "     sum(nivel) as nivel, " .
        "     string_agg(finalizador, '' order by finalizador) as finalizador, sum(desconto) as valor  " .
        "     from( " .
        "       select id as plano_desconto, codigo, descricao, nivel, 0 as desconto, finalizador  " .
        "       from planocontas  " .
        "       where nivel = 1 " .
        "       start with id in (  " .
        "         select pcdespesasdesconto_id  " .
        "         from empresaconfigs config " .
        "         where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
        "       ) " .
        "       connect by prior paiplanoconta_id = id " .
        "        " .
        "       union all  " .
        "        " .
        "       select sum(plano_desconto) as plano_desconto, '' as codigo, " .
        "       '' as descricao, 0 as nivel, sum(desconto) as desconto,  " .
        "       '' as finalizador  " .
        "       from(  " .
        "         select  " .
        "         ( " .
        "           select id from planocontas " .
        "           where nivel = 1 " .
        "           start with id = pcdespesasdesconto_id " .
        "           connect by prior paiplanoconta_id = id " .
        "         ) as plano_desconto, 0 as desconto " .
        "         from empresaconfigs config " .
        "         where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
        "          " .
        "         union all " .
        "          " .
        "         select 0 as plano_desconto, sum(desconto) " .
        "         from( " .
        "           select  parc.id, desconto " .
        "           from financeiroparcelas parc  " .
        "           inner join financeirorateios rato on rato.financeiro_id = parc.financeiro_id " .
        "           inner join planocontas plans on rato.planoconta_id = plans.id " .
        "           where parc.datacompetencia between " .
        "           trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        "           to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and " .
        "           parc.pagarreceber = 'R' and agrupamento_status < 2 and " .
        "           parc.desconto <> 0 and parc.empresa_id = ".Session::get("empresa_padrao")->id." " .
        "           group by parc.id, parc.desconto " .
        "         ) descon         " .
        "       ) descontos " .
        "     ) query_2  " .
        "     group by plano_desconto " .
        "   ) principal  " .
        "   group by descricao, plano_id " .
        " ) plano_contas " .
        " where plano is not null and valor is not null and valor <> 0  " .
        " group by plano, plano_id " .
        " order by valor desc ";
        $result = [];
        $rec = (object) ['tipo'=> 2, 'tipodescricao ' => 'Custos Fixos', 'plano_id' => -3, 'plano' => 'Custos Fixos', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 2, 'tipodescricao ' => 'Custos Fixos', 'plano_id' => -3, 'plano' => 'Descrição', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$dtaux];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $total = array_reduce($data, function ($acc, $item) {
            if($item->plano != 'Total'){
                return $acc + $item->valor;
            } else {
                return $acc;
            }
        }, 0);
        $rec = (object) ['tipo'=> 2, 'tipodescricao ' => 'Custos Fixos', 'plano_id' => -3, 'plano' => 'Total', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total];
        array_push($result, $rec);
        foreach($result as $row){
            $row->percentual = '';
            if($row->tipo == 3){
                $row->percentual = '';
            } elseif($row->cabecalho == 1 && $row->plano != 'Total'){
                $row->percentual = '%';
            } elseif($row->cabecalho == 2){
                $row->percentual = '';
            } elseif($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
                $row->percentual = $total == 0 ? 0 : $row->valor/$total*100;
            }
        }
        return $result;
    }

    public static function getDataDreResultado($data, $dataReferencia){
        $query = 
        " select 'Config' as descricao, 0 as valor, 0 as depreciacao, encargos, devedores, capital, distribuicao " .
        " from( " .
        "   select percentualencargos as encargos, " .
        "   percentualprovisaodevedores as devedores, " .
        "   percentualremuneracaocapital as capital, " .
        "   percentualdistribuicaoresul as distribuicao " .
        "   from empresaconfigs " .
        "   where empresa_id = ".Session::get("empresa_padrao")->id." " .
        " ) config";
        $result = DB::select($query);
        $resultado = array_reduce($data, function ($acc, $item) {
            if($item->plano != 'Total' && is_numeric($item->valor)){
                if($item->tipo==0){
                    return $acc + ($item->valor==null?0:$item->valor);
                } else {
                    return $acc - ($item->valor==null?0:$item->valor);
                }
            } else {
                return $acc;
            }
        }, 0);
        $encargos = 0; $duvidosos = 0; $capital = 0; $distribuicao = 0;
        $resultadofinal = 0;
        $retorno = [];
        if($result && count($result)>0) {
            $encargos = $result[0]->encargos > 0 && $resultado > 0 ? $resultado * $result[0]->encargos / 100 : 0;
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>'', 'percentual' => ''];
            array_push($retorno, $rec);
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => 'Resultado (Lucro)', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$resultado, 'percentual' => ''];
            array_push($retorno, $rec);
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => 'Provisão de Encargos Trabalhistas', 'cabecalho'=> 0, 'clicavel' => 0, 'valor'=>$encargos, 'percentual' => ''];
            array_push($retorno, $rec);
            $duvidosos = $result[0]->devedores > 0 && $resultado > 0 ? $resultado * $result[0]->devedores / 100 : 0;
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => 'Provisão Devedores Duvidosos', 'cabecalho'=> 0, 'clicavel' => 0, 'valor'=>$duvidosos, 'percentual' => ''];
            array_push($retorno, $rec);
            $capital = $result[0]->capital > 0 && $resultado > 0 ? $resultado * $result[0]->capital / 100 : 0;
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => 'Remuneração de Capital', 'cabecalho'=> 0, 'clicavel' => 0, 'valor'=>$capital, 'percentual' => ''];
            array_push($retorno, $rec);
            $distribuicao = $result[0]->distribuicao > 0 && $resultado > 0 ? $resultado * $result[0]->distribuicao / 100 : 0;
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => 'Provisão Distribuição de Resultados', 'cabecalho'=> 0, 'clicavel' => 0, 'valor'=>$distribuicao, 'percentual' => ''];
            array_push($retorno, $rec);
            $resultadofinal = $resultado - $encargos - $duvidosos - $capital - $distribuicao;
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>'', 'percentual' => ''];
            array_push($retorno, $rec);
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => 'Resultado a Considerar', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$resultadofinal, 'percentual' => ''];
            array_push($retorno, $rec);
            $rec = (object) ['tipo'=>3, 'tipodescricao ' => 'Resultado', 'plano_id' => -3, 'plano' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>'', 'percentual' => ''];
            array_push($retorno, $rec);
        }

        return ['result'=>$retorno, 'resultado'=>$resultado];
    }

    public static function getDataDreInvestimentos($dataReferencia, $resultadofinal){
        $query = 
        " select 4 as tipo, 'Investimentos' as tipodescricao, plano_id, descricao as plano, 0 as cabecalho, 0 as clicavel, sum(valor) as valor " .
        " from( " .
        "   select rato.planoconta_id as plano_id, plano.descricao as descricao,  " .
        "   case when parc.pagarreceber = 'R' then parc.valor* rato.valor/fi.valor else -parc.valor* rato.valor/fi.valor end as valor " .
        "   from financeiroparcelas parc " .
        "   inner join financeiros fi on fi.id = parc.financeiro_id " .
        "   inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "   inner join planocontas plano on rato.planoconta_id = plano.id " .
        "   where parc.datacompetencia between trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        "   to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and  " .
        "   parc.empresa_id = ".Session::get("empresa_padrao")->id." and " .
        "   parc.agrupamento_status < 2 and plano.investimento = 1 " .
        " ) investimentos " .
        " group by plano_id, descricao " .
        " order by valor desc ";
        $result = [];
        $rec = (object) ['tipo'=> 4, 'tipodescricao ' => 'Investimentos', 'plano_id' => -4, 'plano' => 'Investimentos no Período (Sem Impacto no Resultado)', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['tipo'=> 4, 'tipodescricao ' => 'Investimentos', 'plano_id' => -4, 'plano' => 'Investimentos', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$dtaux];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $total = array_reduce($data, function ($acc, $item) {
            if($item->plano != 'Total'){
                return $acc + $item->valor;
            } else {
                return $acc;
            }
        }, 0);
        $rec = (object) ['tipo'=> 4, 'tipodescricao ' => 'Investimentos', 'plano_id' => -4, 'plano' => 'Total', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total];
        array_push($result, $rec);
        foreach($result as $row){
            $row->percentual = '';
            if($row->tipo == 3){
                $row->percentual = '';
            } elseif($row->cabecalho == 1 && $row->plano != 'Total'){
                $row->percentual = '%';
            } elseif($row->cabecalho == 2){
                $row->percentual = '';
            } elseif($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
                $row->percentual = $total == 0 ? 0 : $row->valor/$total*100;
            }
        }


        
        $rec = (object) ['tipo'=> 4, 'tipodescricao ' => 'Investimentos', 'plano_id' => -4, 'plano' => '', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>'', 'percentual' => ''];
        array_push($result, $rec);
        $rec = (object) ['tipo'=> 3, 'tipodescricao ' => 'Investimentos', 'plano_id' => -4, 'plano' => 'Incremento de Caixa (Sem Impacto no Resultado)', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$resultadofinal + $total, 'percentual' => ''];
        array_push($result, $rec);
        return $result;
    }

    public static function getDataDetalhesFaturamento($dataReferencia, $tipo){
        $query = 
        " select plano as descricao, 0 as cabecalho, 0 as clicavel, sum(valor) as valor " .
        " FROM ( " .
        " SELECT  " .
        "  	1 AS tipo, " .
        " 	cond.descricao AS plano, " .
        " 	sum(CASE WHEN rato.id IS NULL THEN  " .
        " 	   CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR end " .
        " 	ELSE (CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR END)*rato.valor/fi.valor end) AS valor " .
        " 	FROM FINANCEIROPARCELAS parc  " .
        " 	left join financeiros fi on fi.id = parc.financeiro_id " .
        " 	left join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        " 	left join planocontas plano on rato.planoconta_id = plano.id " .
        " 	left join condicaopagamentos cond on fi.condicaopagamento_id = cond.id " .
        " 	WHERE  " .
        " 		parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        " 		parc.DATACOMPETENCIA between  " .
        " 		trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        " 		to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  and plano.investimento = 0 and " .
        " 		parc.pagarreceber = 'R' and " .
        " 		parc.AGRUPAMENTO_STATUS <= 1 AND " .
        " 		plano.investimento = 0 " .
        " 	GROUP BY cond.descricao " .
        " UNION ALL " .
        " SELECT  " .
        "     2 AS tipo, " .
        " 	cond.descricao as plano, " .
        " 	sum(CASE WHEN rato.id IS NULL THEN  " .
        " 	   CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR end " .
        " 	ELSE (CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR END)*rato.valor/fi.valor end) AS valor " .
        " 	FROM FINANCEIROPARCELAS parc  " .
        " 	left join financeiros fi on fi.id = parc.financeiro_id " .
        " 	left join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        " 	left join planocontas plano on rato.planoconta_id = plano.id " .
        " 	left join condicaopagamentos cond on fi.condicaopagamento_id = cond.id " .
        " 	WHERE  " .
        " 		parc.AGRUPAMENTO_STATUS <= 1 and " .
        " 	    parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        " 		parc.DATACOMPETENCIA between  " .
        " 		trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        " 		to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  and plano.investimento = 0 and " .
        " 		parc.pagarreceber = 'R' AND " .
        " 		plano.investimento = 0 " .
        " 	GROUP BY cond.descricao  " .
        "   union all " .
        "   SELECT 2 AS tipo, 'Juros/Multa' as plano, " .
        "   sum(valor) as valor " .
        "   from(  " .
        "     select plano_id, string_agg(codigo, '' order by codigo) as codigo, " .
        "     string_agg(descricao, '' order by descricao) as descricao, sum(nivel) as nivel, " .
        "     string_agg(finalizador, '' order by finalizador) as finalizador, " .
        "     sum(juros + multa) as valor " .
        "     from( " .
        "       select id as plano_id, codigo, descricao, nivel, 0 as juros, 0 as multa, finalizador  " .
        "       from planocontas  " .
        "       where id in (  " .
        "         select pcrecetajuro_id  " .
        "         from empresaconfigs config  " .
        "         where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
        "       ) " .
        "       union all  " .
        "       select sum(plano_id) as plano_id, '' as codigo, '' as descricao, " .
        "       0 as nivel, sum(juros) as juros, sum(multa) as multa, '' as finalizador  " .
        "       from(  " .
        "         select pcrecetajuro_id as plano_id, pcdespesasdesconto_id as plano_desconto, 0 as juros, " .
        "         0 as multa, 0 as desconto  " .
        "         from empresaconfigs config  " .
        "         where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1  " .
        "         union all  " .
        "         SELECT  " .
        "         	0 as plano_id, 0 as plano_desconto,  " .
        " 			sum(CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.JUROS ELSE parc.juros END) AS juros, " .
        " 			sum(CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.multa ELSE parc.multa END) AS multa,  " .
        " 			0 as desconto  " .
        " 			FROM FINANCEIROPARCELAS parc  " .
        " 			WHERE  " .
        " 			parc.AGRUPAMENTO_STATUS <= 1 and " .
        " 			parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        " 			parc.DATACOMPETENCIA between  " .
        " 			  trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        " 			  to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  and " .
        " 			  parc.pagarreceber = 'R' and " .
        " 			  (parc.juros <> 0 or parc.multa <> 0) " .
        "       ) juros_multas  " .
        "     ) recurssive  " .
        "     group by plano_id " .
        "     union all " .
        "     select plano_desconto, " .
        "     string_agg(codigo, '' order by codigo) as codigo,  " .
        "     string_agg(descricao, '' order by descricao) as descricao, " .
        "     sum(nivel) as nivel, " .
        "     string_agg(finalizador, '' order by finalizador) as finalizador, sum(desconto) as valor  " .
        "     from(  " .
        "       select id as plano_desconto, codigo, descricao, nivel, 0 as desconto, finalizador  " .
        "       from planocontas plano  " .
        "       where id in (  " .
        "         select pcreceitadesconto_id  " .
        "         from empresaconfigs config " .
        "         where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1  " .
        "       ) " .
        "       union all  " .
        "       select sum(plano_desconto) as plano_desconto, '' as codigo, " .
        "       '' as descricao, 0 as nivel, sum(desconto) as desconto,  " .
        "       '' as finalizador  " .
        "       from(  " .
        "         select pcreceitadesconto_id as plano_desconto, 0 as desconto  " .
        "         from empresaconfigs config " .
        "         where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
        "         union all  " .
        "         SELECT  " .
        " 	        0 as plano_desconto,  " .
        " 			abs(sum(CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.desconto ELSE parc.desconto END)) AS desconto " .
        " 			FROM FINANCEIROPARCELAS parc  " .
        " 			WHERE  " .
        " 				parc.AGRUPAMENTO_STATUS < 2 and " .
        " 			    parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        " 				parc.DATACOMPETENCIA between  " .
        " 			      trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        " 			      to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  and " .
        " 			      parc.pagarreceber = 'P' and parc.desconto <> 0 " .
        "       ) descontos " .
        "     ) query_2  " .
        "     group by plano_desconto  " .
        "   ) principal  " .
        "   group by descricao " .
        " ) plano_contas " .
        " where plano is not null and valor is not null and valor <> 0 " .
        " AND tipo = " .$tipo .
        " group by plano " .
        " order by valor desc ";
        $result = [];
        $rec = (object) ['descricao'=>'Condições de Pagamento', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['descricao' => 'Descrição', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$dtaux];
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
        $rec = (object) ['descricao' => 'Total', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total];
        array_push($result, $rec);
        foreach($result as $row){
            $row->percentual = '';
            if($row->cabecalho == 2){
                $row->percentual = '';
            } elseif($row->cabecalho == 1 && $row->descricao != 'Total'){
                $row->percentual = '%';
            } elseif($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
                $row->percentual = $total == 0 ? 0 : $row->valor/$total*100;
            }
        }
        return $result;
    }

    public static function getDataDetalhesCustoVariavel($dataReferencia){
        $query = 
        " SELECT  " .
        " produto AS descricao,  " .
        " (valor/nullif(sum(valor) OVER(), 0)*VALORBYESTOQUECOMPRA)/nullif(quantidade, 0) AS custo, " .
        " quantidade,  " .
        " valor/nullif(sum(valor) OVER(), 0)*VALORBYESTOQUECOMPRA AS valor, 0 as cabecalho, 0 as clicavel " .
        " FROM ( " .
        " select 'Mercadorias para Revenda' AS descricao, produtos.DESCRICAO AS produto, max(coalesce(qrycusto.custo, 0)) AS customedio,  " .
        " sum(items.quantidade) AS quantidade, sum(items.quantidade * coalesce(qrycusto.custo, 0)) AS valor, " .
        " max(qq.valor) AS valorbyestoquecompra " .
        "   from pedidos " .
        "   inner join pedidoitems items on items.pedido_id = pedidos.id " .
        "   inner join produtos on items.produto_id = produtos.id " .
        "   inner join empresas on pedidos.empresa_id = empresas.id " .
        "   LEFT JOIN ( " .
        "           select produto_id, max(customedio) as custo " .
        "           from estoquefechamentosetors " .
        "           where empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "           estoquefechamento_id = (select id from(select id from estoquefechamentos where empresa_id = ".Session::get("empresa_padrao")->id." and " .
        "             datahorafechamento <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  " .
        "             order by datahorafechamento DESC, reaberto asc) where rownum <= 1) " .
        "            GROUP BY produto_id " .
        "           ) qrycusto ON qrycusto.produto_id = items.PRODUTO_ID  " .
        " CROSS JOIN ( " .
        " SELECT sum(valor) AS valor FROM ( " .
        " SELECT  " .
        " sum(valorant) - sum(valor) AS valor " .
        " FROM ( " .
        " select fec.id, " .
        " produtos.descricao as produto, " .
        " est.customedio as custoant, " .
        " sum(est.quantidade) as quantidadeant, " .
        " round(sum(est.customedio * est.quantidade),2) as valorant, " .
        " 0 AS custo, 0 AS quantidade, 0 AS valor " .
        " from ( " .
        " 	select max(id) as id, max(updated_at) as updated_at  " .
        " 	from estoquefechamentos fec " .
        " 	where trunc(fec.datahorafechamento) = LAST_DAY(ADD_MONTHS(trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')),-1)) " .
        " ) fec " .
        " inner join estoquefechamentosetors est on est.estoquefechamento_id = fec.id  " .
        " inner join empresas on est.empresa_id = empresas.id " .
        " inner join produtos on est.produto_id = produtos.id " .
        " where est.quantidade <> 0  " .
        " and produtos.PRODUTOCLASSE_ID <> 128 " .
        " and empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " group by produtos.descricao, fec.id, est.customedio  " .
        " UNION all " .
        " select fec.id, " .
        " produtos.descricao as produto, " .
        " 0 AS custoant, 0 AS quantidadeant, 0 AS valorant, " .
        " est.customedio as custo, " .
        " sum(est.quantidade) as quantidade, " .
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
        " and empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " group by produtos.descricao, fec.id, est.customedio  " .
        " order by produto " .
        " )  " .
        " UNION ALL " .
        " select sum(valor) as valor " .
        " from( " .
        "   select 0 as juros, " .
        "   ( " .
        "     select id " .
        "     from planocontas " .
        "     where nivel = 1 " .
        "     start with id = plano_id " .
        "     connect by id = prior paiplanoconta_id " .
        "   ) as plano_id, " .
        "   ( " .
        "     select descricao " .
        "     from planocontas " .
        "     where nivel = 1 " .
        "     start with id = plano_id " .
        "     connect by id = prior paiplanoconta_id " .
        "   ) as plano, sum(valor) as valor " .
        "   from( " .
        "     select plano_id,sum(valor) as valor " .
        "     from( " .
        "       select rato.planoconta_id as plano_id, rato.valor*parc.valor/fi.valor as valor " .
        "       from financeiroparcelas parc " .
        "       inner join financeiros fi on fi.id = parc.financeiro_id " .
        "       inner join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
        "       inner join planocontas plano on rato.planoconta_id = plano.id " .
        "       where parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "       parc.datacompetencia between  " .
        "       trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        "       to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and " .
        "       parc.agrupamento_status < 2 and plano.investimento = 0 and " .
        "       parc.pagarreceber = 'P' and plano.custosvariaveis = 1 " .
        "     ) " .
        "     group by plano_id " .
        "   ) financas " .
        "   group by plano_id " .
        " ) plano_contas " .
        " where plano is not null and valor is not null and valor <> 0  " .
        " UNION ALL " .
        " SELECT sum(valorant) - sum(valor) AS valor FROM ( " .
        " SELECT 0 AS valorant, sum(customedio * quantidade) as valor " .
        " FROM ( " .
        " 	SELECT produto_id, max(produto) AS produto, sum(customedio) AS customedio, sum(quantidade) AS quantidade " .
        " 	from( " .
        " 		select produtos.id AS produto_id, produtos.descricao as produto, " .
        " 		0 AS customedio, sum(CASE WHEN tipo = 2 THEN quantidade*-1 ELSE quantidade end) as quantidade " .
        " 		from comodatos " .
        " 		inner join empresas on comodatos.empresa_id = empresas.id " .
        " 		inner join comodatoitems items on items.comodato_id = comodatos.id " .
        " 		inner join produtos on items.produto_id = produtos.id " .
        " 		AND empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " 		AND items.quantidade <> 0 " .
        " 		AND comodatos.DATACONTRATO <= to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') " .
        " 		AND comodatos.ativo = 1 " .
        " 		group by produtos.id, produtos.descricao " .
        " 		UNION ALL " .
        " 		SELECT est.PRODUTO_ID AS produto_id, '' AS produto, max(est.CUSTOMEDIO) AS customedio, 0 AS quantidade " .
        " 		FROM ( " .
        " 			SELECT max(id) AS id, max(updated_at) AS UPDATED_AT  " .
        " 			FROM ESTOQUEFECHAMENTOS fec " .
        " 			WHERE trunc(fec.DATAHORAFECHAMENTO) = trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')) " .
        " 		) fec " .
        " 		INNER JOIN ESTOQUEFECHAMENTOSETORS est ON est.ESTOQUEFECHAMENTO_ID = fec.id " .
        " 		inner join empresas on est.empresa_id = empresas.id " .
        " 		WHERE empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " 		GROUP BY est.PRODUTO_ID  " .
        " 	) prods " .
        " 	GROUP BY produto_id " .
        " ) cust " .
        " WHERE produto IS NOT NULL " .
        " UNION ALL  " .
        " SELECT   " .
        " sum(customedio * quantidade) as valorant, 0 AS valor " .
        " FROM ( " .
        " 	SELECT produto_id, max(produto) AS produto, sum(customedio) AS customedio, sum(quantidade) AS quantidade " .
        " 	from( " .
        " 		select produtos.id AS produto_id, produtos.descricao as produto, " .
        " 		0 AS customedio, sum(CASE WHEN tipo = 2 THEN quantidade*-1 ELSE quantidade end) as quantidade " .
        " 		from comodatos " .
        " 		inner join empresas on comodatos.empresa_id = empresas.id " .
        " 		inner join comodatoitems items on items.comodato_id = comodatos.id " .
        " 		inner join produtos on items.produto_id = produtos.id " .
        " 		where empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " 		AND items.quantidade <> 0 " .
        " 		AND comodatos.DATACONTRATO <= LAST_DAY(ADD_MONTHS(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),-1)) " .
        " 		AND comodatos.ativo = 1 " .
        " 		group by produtos.id, produtos.descricao " .
        " 		UNION ALL " .
        " 		SELECT est.PRODUTO_ID AS produto_id, '' AS produto, max(est.CUSTOMEDIO) AS customedio, 0 AS quantidade " .
        " 		FROM ( " .
        " 			SELECT max(id) AS id, max(updated_at) AS UPDATED_AT  " .
        " 			FROM ESTOQUEFECHAMENTOS fec " .
        " 			WHERE trunc(fec.DATAHORAFECHAMENTO) = LAST_DAY(ADD_MONTHS(trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')),-1)) " .
        " 		) fec " .
        " 		INNER JOIN ESTOQUEFECHAMENTOSETORS est ON est.ESTOQUEFECHAMENTO_ID = fec.id " .
        " 		inner join empresas on est.empresa_id = empresas.id " .
        " 		WHERE empresas.id in (".Session::get("empresa_padrao")->id.") " .
        " 		GROUP BY est.PRODUTO_ID  " .
        " 	) prods " .
        " 	GROUP BY produto_id " .
        " ) cust " .
        " WHERE produto IS NOT null " .
        " ) " .
        " ) " .
        " ) qq " .
        "   where empresas.id = ".Session::get("empresa_padrao")->id." and " .
        "   pedidos.datahoraprevisaoentrega between " .
        "     trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
        "     to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss') and " .
        "   pedidos.pedidosituacao_id in (select id from pedidosituacaos where empresa_id = ".Session::get("empresa_padrao")->id." and fechadoconcluido = 1) and " .
        "  produtos.ativo = 1 " .
        " GROUP BY produtos.descricao " .
        " ) ";        $result = [];
        $rec = (object) ['descricao'=>'Custos Variáveis', 'cabecalho'=> 2, 'clicavel' => 0, 'quantidade'=>null, 'custo'=> null, 'valor'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['descricao' => 'Descrição', 'cabecalho'=> 1, 'clicavel' => 0, 'quantidade'=>'Qtde', 'custo'=> 'Custo Médio', 'valor'=>$dtaux];
        array_push($result, $rec);
        $data = DB::select($query);
        $result = array_merge($result, $data);
        $totais = array_reduce($data, function ($acc, $item) {
            if($item->descricao != 'Total'){
                return [$acc[0] + $item->valor, $acc[1] + $item->quantidade];
            } else {
                return $acc;
            }
        }, [0, 0]);
        $total = $totais[0];
        $totalqtde = $totais[1];
        $rec = (object) ['descricao' => 'Total', 'cabecalho'=> 1, 'clicavel' => 0, 'quantidade'=>$totalqtde, 'custo'=> null, 'valor'=>$total];
        array_push($result, $rec);
        foreach($result as $row){
            $row->percentual = '';
            if($row->cabecalho == 2){
                $row->percentual = '';
            } elseif($row->cabecalho == 1 && $row->descricao != 'Total'){
                $row->percentual = '%';
            } else{
                if($row->valor != null && is_numeric($row->valor)){
                    $row->valor = floatval($row->valor);
                    $row->percentual = $total == 0 ? 0 : $row->valor/$total*100;
                }
                if($row->quantidade != null && is_numeric($row->quantidade)){
                    $row->quantidade = floatval($row->quantidade);
                }
                if($row->custo != null && is_numeric($row->custo)){
                    $row->custo = floatval($row->custo);
                }
            }
        }
        return $result;
    }

    public static function getDataDetalhesCustoFixo($dataReferencia, $plano_id, $juros){
        $query = 
         "select 0 as cabecalho, (case when juros = 1 then 0 else 1 end) as clicavel, juros as juros, " .
            "(case when " . $juros . " = 1 then planojuros_id else plano_id end) as plano_id, " .
            "(case when ". $juros ." = 1 then planojuros_desc else plano end) as descricao, " .
            "sum((case when ". $juros ." = 1 then planojuros_valor else valor end)) as valor " .
            "from(" .
                "select juros, plano_id, plano, sum(valor) as valor,  " .
                "planojuros_id, planojuros_desc, sum(planojuros_valor) as planojuros_valor " .
                "from( " .
                    "select juros, plano_id, plano, sum(valor) as valor, " .
                    "0 as planojuros_id, '' as planojuros_desc, 0 as planojuros_valor " .
                    "from( " .
                    
                    " SELECT 0 as juros, " .
                    " 	rato.planoconta_id as plano_id, plano.descricao as plano," .
                    " 	abs(sum(CASE WHEN rato.id IS NULL THEN " .
                    " 	   CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR end " .
                    " 	ELSE (CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR END)*rato.valor/fi.valor end)) AS valor " .
                    " 	FROM FINANCEIROPARCELAS parc  " .
                    " 		  left join financeiros fi on fi.id = parc.financeiro_id " .
                    " 		  left join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
                    " 		  left join planocontas plano on rato.planoconta_id = plano.id " .
                    " 	WHERE  " .
                    " 		parc.AGRUPAMENTO_STATUS < 2 and " .
                    " 		parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
                    " 		parc.datacompetencia between  " .
                    " 		  trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
                    " 		  to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  and plano.investimento = 0 and " .
                    " 		  parc.pagarreceber = 'P' and plano.custosvariaveis = 0 and " .
                    " 		  rato.planoconta_id in  " .
                    " 			(  " .
                    " 				select id  " .
                    " 				from planocontas  " .
                    " 				start with id = " . $plano_id . "  " .
                    " 				connect by prior id = paiplanoconta_id  " .
                    " 			)  " .
                    " 		GROUP BY rato.planoconta_id, plano.descricao " .
                    ") normais " .
                    "group by plano_id, plano, juros " .
                    
                    "union all " .
                    
                    "select juros as juros, 0 as plano_id, '' as plano, 0 as valor, " .
                    "plano_id as planojuros_id, plano as planojuros_desc, sum(valor) as planojuros_valor " .
                    "from( " .

                    " SELECT 0 as juros, " .
                    " 	rato.planoconta_id as plano_id, plano.descricao as plano," .
                    " 	abs(sum(CASE WHEN rato.id IS NULL THEN " .
                    " 	   CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR end " .
                    " 	ELSE (CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR END)*rato.valor/fi.valor end)) AS valor " .
                    " 	FROM FINANCEIROPARCELAS parc  " .
                    " 		  left join financeiros fi on fi.id = parc.financeiro_id " .
                    " 		  left join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
                    " 		  left join planocontas plano on rato.planoconta_id = plano.id " .
                    " 	WHERE  " .
                    " 		parc.AGRUPAMENTO_STATUS < 2 and " .
                    " 		parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
                    " 		parc.DATACOMPETENCIA between  " .
                    " 		  trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
                    " 		  to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  and plano.investimento = 0 and " .
                    " 		  parc.pagarreceber = 'P' and plano.custosvariaveis = 0 and " .
                    " 		  rato.planoconta_id in  " .
                    " 			(  " .
                    " 				select id  " .
                    " 				from planocontas  " .
                    " 				start with id = " . $plano_id . "  " .
                    " 				connect by prior id = paiplanoconta_id  " .
                    " 			)  " .
                    " 		GROUP BY rato.planoconta_id, plano.descricao " .			
                        
                        "union all " .
                        
                        "select 1 as juros, plano_id, descricao as plano, " .
                        "sum(valor) as valor " .
                        "from( " .
                            "select plano_id, string_agg(codigo, '' order by codigo) as codigo, " .
                            "string_agg(descricao, '' order by descricao) as descricao, sum(nivel) as nivel, " .
                            "string_agg(finalizador, '' order by finalizador) as finalizador, " .
                            "sum(juros + multa) as valor " .
                            "from( " .
                                "select id as plano_id, codigo, descricao, nivel, 0 as juros, 0 as multa, finalizador  " .
                                "from planocontas  " .
                                "where id in (  " .
                                    "select pcdespesasjuro_id  " .
                                    "from empresaconfigs config " .
                                    "where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
                                ") " .
                                
                                "union all  " .
                                
                                "select sum(plano_id) as plano_id, '' as codigo, '' as descricao, " .
                                "0 as nivel, sum(juros) as juros, sum(multa) as multa, '' as finalizador  " .
                                "from(  " .
                                    "select pcdespesasjuro_id as plano_id, " .
                                    "pcdespesasjuro_id as plano_desconto, 0 as juros, " .
                                    "0 as multa, 0 as desconto  " .
                                    "from empresaconfigs config " .
                                    "where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1  " .
                                    
                                    "union all  " .
                                    
                                    "select 0 as plano_id, 0 as plano_desconto, sum(juros), sum(multa), 0 as desconto  " .
                                    "from( " .
                                    "SELECT " .
                                    "	abs(sum(CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.JUROS ELSE parc.juros END)) AS juros, " .
                                    "	abs(sum(CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.multa ELSE parc.multa END)) AS multa " .
                                    "	FROM FINANCEIROPARCELAS parc  " .
                                    "	WHERE  " .
                                    "		parc.AGRUPAMENTO_STATUS < 2 and " .
                                    "		parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
                                    "		parc.DATACOMPETENCIA between  " .
                                    "		  trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
                                    "		  to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  and " .
                                    "		  parc.pagarreceber = 'P' and " .
                                    "			  (parc.juros <> 0 or parc.multa <> 0) " .
                                    ") juros_multa " .
                                ") juros_multas  " .
                            ") recurssive  " .
                            "group by plano_id " .
                            
                            "union all " .
                            
                            "select plano_desconto, " .
                            "string_agg(codigo, '' order by codigo) as codigo,  " .
                            "string_agg(descricao, '' order by descricao) as descricao, " .
                            "sum(nivel) as nivel, " .
                            "string_agg(finalizador, '' order by finalizador) as finalizador, sum(desconto) as valor  " .
                            "from( " .
                                "select id as plano_desconto, codigo, descricao, nivel, 0 as desconto, finalizador  " .
                                "from planocontas  " .
                                "where id in (  " .
                                    "select pcdespesasdesconto_id  " .
                                    "from empresaconfigs config " .
                                    "where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
                                ") " .
                                
                                "union all  " .
                                
                                "select sum(plano_desconto) as plano_desconto, '' as codigo, " .
                                "'' as descricao, 0 as nivel, sum(desconto) as desconto,  " .
                                "'' as finalizador  " .
                                "from(  " .
                                    "select pcdespesasdesconto_id as plano_desconto, 0 as desconto " .
                                    "from empresaconfigs config " .
                                    "where empresa_id = ".Session::get("empresa_padrao")->id." and rownum <= 1 " .
                                    
                                    "union all " .
                                    
                                    "select 0 as plano_desconto, sum(desconto) " .
                                    "from( " .
                                    " SELECT " .
                                    " 	abs(sum(CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.desconto ELSE parc.desconto END)) AS desconto " .
                                    " 	FROM FINANCEIROPARCELAS parc  " .
                                    " 	WHERE  " .
                                    " 		parc.AGRUPAMENTO_STATUS < 2 and " .
                                    " 		parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
                                    " 		parc.DATACOMPETENCIA between  " .
                                    " 		  trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
                                    " 		  to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  and " .
                                    " 		  parc.pagarreceber = 'R' and parc.desconto <> 0 " .
                                    ") descon         " .
                                ") descontos " .
                            ") query_2  " .
                            "group by plano_desconto " .
                        ") principal  " .
                        "group by descricao, plano_id " .
                    ") valores " .
                    "group by plano_id, plano, juros " .
                ") planos  " .
                "group by plano_id, planojuros_id, plano, planojuros_desc, juros " .
            ") planos_gerias " .
            "where (case when ". $juros ." = 1 then planojuros_id else plano_id end) is not null and " .
            "(case when ". $juros ." = 1 then planojuros_desc else plano end) is not null " .
            "group by (case when ". $juros ." = 1 then planojuros_id else plano_id end), " .
            "(case when ". $juros ." = 1 then planojuros_desc else plano end), juros " .
            "order by valor desc";
        $result = [];
        $planocontas = Planoconta::find($plano_id);
        $rec = (object) ['descricao'=>$planocontas?$planocontas->descricao:'Plano Contas', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null];
        array_push($result, $rec);
        $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
        $rec = (object) ['descricao' => 'Descrição', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$dtaux];
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
        $rec = (object) ['descricao' => 'Total', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total];
        array_push($result, $rec);
        foreach($result as $row){
            $row->percentual = '';
            if($row->cabecalho == 2){
                $row->percentual = '';
            } elseif($row->cabecalho == 1 && $row->descricao != 'Total'){
                $row->percentual = '%';
            } elseif($row->valor != null && is_numeric($row->valor)){
                $row->valor = floatval($row->valor);
                $row->percentual = $total == 0 ? 0 : $row->valor/$total*100;
            }
        }
        return $result;
    }

    public static function getDataCentroCustos($plano_id, $dataReferencia)
    {
            $query = 
            " SELECT  " .
            " centro.descricao as descricao,  " .
            " abs(sum(CASE WHEN rato.id IS NULL THEN  " .
            " CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR end " .
            " ELSE (CASE WHEN parc.PAGARRECEBER = 'P' THEN -parc.VALOR ELSE parc.VALOR END)*rato.valor/fi.valor end)) AS valor, 0 as cabecalho, 0 as clicavel " .
            " FROM FINANCEIROPARCELAS parc  " .
            " left join financeiros fi on fi.id = parc.financeiro_id " .
            " left join financeirorateios rato on parc.financeiro_id = rato.financeiro_id " .
            " left join planocontas plano on rato.planoconta_id = plano.id " .
            " left join centrocustos centro on rato.centrocusto_id = centro.id " .
            " WHERE  " .
            " parc.agrupamento_status < 2 and " .
            " parc.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
            " parc.datacompetencia between  " .
            " trunc(to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss'),'month') and " .
            " to_date('".  $dataReferencia ."','yyyy-mm-dd hh24:mi:ss')  " .
            " and rato.planoconta_id = " . $plano_id . "  " .
            " GROUP BY centro.descricao ";

            $result = [];
            $planocontas = Planoconta::find($plano_id);
            $rec = (object) ['descricao'=>$planocontas?$planocontas->descricao:'Centros de Custos', 'cabecalho'=> 2, 'clicavel' => 0, 'valor'=>null];
            array_push($result, $rec);
            $dtaux = Carbon::createFromFormat('Y-m-d H:i:s', $dataReferencia)->format('m/Y');
            $rec = (object) ['descricao' => 'Descrição', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$dtaux];
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
            $rec = (object) ['descricao' => 'Total', 'cabecalho'=> 1, 'clicavel' => 0, 'valor'=>$total];
            array_push($result, $rec);
            foreach($result as $row){
                $row->percentual = '';
                if($row->cabecalho == 2){
                    $row->percentual = '';
                } elseif($row->cabecalho == 1 && $row->descricao != 'Total'){
                    $row->percentual = '%';
                } elseif($row->valor != null && is_numeric($row->valor)){
                    $row->valor = floatval($row->valor);
                    $row->percentual = $total == 0 ? 0 : $row->valor/$total*100;
                }
            }
            return $result;
    }

}
