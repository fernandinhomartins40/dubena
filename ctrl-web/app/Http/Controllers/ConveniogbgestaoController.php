<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ConveniogbgestaoController extends Controller
{
    public function index()
    {
        return view("reports.vendas.conveniogb.index");
    }

    public function getDashboard()
    {
        try {
            $dataChartConvenio = $this->getDataChartConvenio();
            $dataChartConvenioClientes = $this->getDataChartConvenioClientes();
            $dataChartprodutos = $this->getDataChartprodutos();
            $dataTabelaConvenio = $this->getDataTabelaConvenio();
            $dataChartValegas = $this->getDataChartValegas();
            $dataTabelaValegas = $this->getDataTabelaValegas();
            return responseSuccess(["dataChartConvenio" => $dataChartConvenio, 
                                    'dataChartprodutos'=>$dataChartprodutos, 
                                    'dataChartConvenioClientes'=>$dataChartConvenioClientes,
                                    'dataTabelaConvenio'=>$dataTabelaConvenio,
                                    'dataChartValegas'=>$dataChartValegas,
                                    'dataTabelaValegas'=>$dataTabelaValegas]);
        } catch(Exception $e){
            return getErrorsException($e);
        }
    }

    public function getConvenioValegasMes(){
        try {
            $produto = \Input::get("produto", "");
            $mes = \Input::get("mes", "");
            $tipo = \Input::get("tipo", "");
            $dataTabela = [];
            if($tipo=='valegas'){
                $dataTabela = $this->getDataTabelaValegas($produto, $mes);
            } else {
                $dataTabela = $this->getDataTabelaConvenio($produto, $mes);
            } 

            return responseSuccess(["dataTabela" => $dataTabela]);
        } catch(Exception $e){
            return getErrorsException($e);
        }
    }

    public function getDataChartConvenio(){
        $query = 
        " SELECT  " .
        "     p.DESCRICAO, mes_seq, COALESCE(qryvenda.quantidade, 0) AS quantidade, COALESCE(qryvenda.precomedio, 0) AS precomedio " .
        " FROM ( " .
        // Oracle CONNECT BY gerava os últimos 12 meses (yyyymm). Postgres:
        // generate_series sobre meses a partir de (mês atual - 11).
        "     SELECT to_char(date_trunc('month', current_timestamp) - g.lvl * interval '1 month', 'yyyymm') AS mes_seq " .
        "     FROM generate_series(11,0,-1) AS g(lvl) " .
        " ) seq CROSS JOIN produtos p " .
        "     LEFT JOIN ( " .
        "         select produtos.id AS produto_id,  " .
        "                 to_char(datahoraprevisaoentrega,'yyyymm') as mes, " .
        "                 sum(items.quantidade) AS quantidade,  " .
        "                 sum(items.precovendaunitario*items.quantidade)/sum(items.quantidade) as precomedio " .
        "         from pedidos " .
        "         inner join pedidoitems items on items.pedido_id = pedidos.id " .
        "         inner join produtos on items.produto_id = produtos.id " .
        "         inner join condicaopagamentos cond on pedidos.condicaopagamento_id = cond.id " .
        "         where pedidos.empresa_id = ".Session::get("empresa_padrao")->id." and " .
        "         cond.tipo = '4' and  " .
        "         pedidos.datahoraprevisaoentrega BETWEEN date_trunc('month', (CURRENT_TIMESTAMP + (-11) * interval '1 month')) AND CURRENT_TIMESTAMP and " .
        "         pedidos.pedidosituacao_id in (select id from pedidosituacaos where empresa_id = ".Session::get("empresa_padrao")->id." and fechadoconcluido = 1) and " .
        "         produtos.tipo_glp IN (3,4,5) AND produtos.PESOLIQUIDO IN (13,20,45)  " .
        "         GROUP BY produtos.id, to_char(datahoraprevisaoentrega,'yyyymm') " .
        "     )  qryvenda ON qryvenda.produto_id = p.id AND qryvenda.mes = mes_seq " .
        "     WHERE p.TIPO_GLP IN (3,4,5) AND p.PESOLIQUIDO IN (13,20,45) AND  " .
        "         p.EMPRESA_ID = ".Session::get("empresa_padrao")->id." " .
        "     ORDER BY p.DESCRICAO, MES_SEQ  ";
        return DB::select($query);
    }

    public function getDataChartProdutos(){
        $query = "select id, descricao from produtos ". 
                 " where produtos.tipo_glp IN (3,4,5) AND produtos.PESOLIQUIDO IN (13,20,45) and empresa_id = ".Session::get("empresa_padrao")->id;
        return DB::select($query);
    }

    public function getDataChartConvenioClientes(){
        $query = 
        " SELECT   " .
        "     p.DESCRICAO, mes_seq, COALESCE(qryvenda.qtvenda, 0) AS quantidade " .
        " FROM (  " .
        // Oracle CONNECT BY -> generate_series (últimos 12 meses, yyyymm).
        "     SELECT to_char(date_trunc('month', current_timestamp) - g.lvl * interval '1 month', 'yyyymm') AS mes_seq  " .
        "     FROM generate_series(11,0,-1) AS g(lvl)  " .
        " ) seq CROSS JOIN produtos p  " .
        "     LEFT JOIN (  " .
        "         select produtos.id AS produto_id,   " .
        "                 to_char(datahoraprevisaoentrega,'yyyymm') as mes,  " .
        "                 sum(items.quantidade) AS qtvenda, 0 AS qtclientes " .
        "         from pedidos  " .
        "         inner join pedidoitems items on items.pedido_id = pedidos.id  " .
        "         inner join produtos on items.produto_id = produtos.id  " .
        "         inner join condicaopagamentos cond on pedidos.condicaopagamento_id = cond.id  " .
        "         where pedidos.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "         cond.tipo = '4' and   " .
        "         pedidos.datahoraprevisaoentrega BETWEEN date_trunc('month', (CURRENT_TIMESTAMP + (-11) * interval '1 month')) AND CURRENT_TIMESTAMP and  " .
        "         pedidos.pedidosituacao_id in (select id from pedidosituacaos where empresa_id = ".Session::get("empresa_padrao")->id." and fechadoconcluido = 1) and  " .
        "         produtos.tipo_glp IN (3,4,5) AND produtos.PESOLIQUIDO IN (13,20,45)   " .
        "         GROUP BY produtos.id, to_char(datahoraprevisaoentrega,'yyyymm')  " .
        "     )  qryvenda ON qryvenda.produto_id = p.id AND qryvenda.mes = mes_seq  " .
        "     WHERE p.TIPO_GLP IN (3,4,5) AND p.PESOLIQUIDO IN (13,20,45) AND   " .
        "         p.EMPRESA_ID = ".Session::get("empresa_padrao")->id."  " .
        "     UNION ALL " .
        "     SELECT * FROM ( " .
        "     WITH meses_ref AS ( " .
        // Oracle CONNECT BY LEVEL<=12 (últimos 12 meses) -> generate_series.
        "         SELECT TO_CHAR(date_trunc('month', current_date) - g.lvl * interval '1 month', 'YYYYMM') AS mes " .
        "         FROM generate_series(11,0,-1) AS g(lvl) " .
        "     ), " .
        "     clientes_por_mes AS ( " .
        "         SELECT " .
        "         CASE " .
        "             WHEN clientes.CREATED_AT < date_trunc('month', (CURRENT_DATE + (-11) * interval '1 month')) " .
        "             THEN TO_CHAR(date_trunc('month', (CURRENT_DATE + (-11) * interval '1 month')), 'YYYYMM') " .
        "             ELSE TO_CHAR(clientes.CREATED_AT, 'YYYYMM') " .
        "         END AS mes, " .
        "         COUNT(clientes.id) AS quantclientes " .
        "         FROM clientes " .
        "         INNER JOIN empresas ON clientes.empresa_id = empresas.id " .
        "         WHERE " .
        "         empresas.id = ".Session::get("empresa_padrao")->id." " .
        "         AND clientes.ativo = 1 " .
        "         AND clientes.convenio_id IS NOT NULL " .
        "         AND clientes.CREATED_AT IS NOT NULL " .
        "         GROUP BY " .
        "         CASE " .
        "             WHEN clientes.CREATED_AT < date_trunc('month', (CURRENT_DATE + (-11) * interval '1 month')) " .
        "             THEN TO_CHAR(date_trunc('month', (CURRENT_DATE + (-11) * interval '1 month')), 'YYYYMM') " .
        "             ELSE TO_CHAR(clientes.CREATED_AT, 'YYYYMM') " .
        "         END " .
        "     ) " .
        "     SELECT " .
        "         'Clientes' AS descricao, " .
        "         m.mes AS mes_seq, " .
        "         SUM(COALESCE(c.quantclientes, 0)) OVER ( " .
        "         ORDER BY m.mes " .
        "         ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW " .
        "         ) AS qtvenda " .
        "     FROM meses_ref m " .
        "     LEFT JOIN clientes_por_mes c ON c.mes = m.mes " .
        "     ORDER BY m.mes " .
        "     ) qrycli " .
        "     ORDER BY DESCRICAO, MES_SEQ   ";
        return DB::select($query); 
    }

    public function getDataTabelaConvenio($produto = 'GLP P13', $mes = ''){
        $diainicial = '';
        $diafinal = '';
        if($mes != ''){
            $diainicial = '20'.(explode('/', $mes)[1])."-".explode('/', $mes)[0]."-01 00:00:00";
            $diafinal = Carbon::createFromFormat('Y-m-d H:i:s', $diainicial)->lastOfMonth()->format('Y-m-d')." 23:59:59";
        }
        $query = 
        " SELECT convenio_id, nome, sum(QUANTCLIENTES) AS QUANTCLIENTES, sum(QUANTVENDIDA) AS QUANTVENDIDA FROM ( " .
        "     SELECT  " .
        "     clientes.convenio_id, " .
        "     convenios.nome, " .
        "     count(convenios.id) AS quantclientes, " .
        "     0 AS quantvendida " .
        "     FROM clientes " .
        "     INNER JOIN clientes convenios ON convenios.id = clientes.CONVENIO_ID  " .
        "     WHERE " .
        "         clientes.empresa_id = ".Session::get("empresa_padrao")->id." " .
        "         AND clientes.ativo = 1 " .
        "         AND clientes.convenio_id IS NOT NULL " .
        "     GROUP BY clientes.convenio_id, convenios.id, convenios.nome " .
        "     UNION ALL  " .
        "     SELECT " .
        "         CONVENIADO.ID AS CONVENIO_ID, " .
        "         CONVENIADO.NOME, " .
        "         0 AS QUANTCLIENTES, " .
        "         sum(COALESCE(ITEMS.QUANTIDADE, 0)) AS quantvendida " .
        "     FROM CLIENTECONVENIOS convenio " .
        "     INNER JOIN CLIENTES ON CONVENIO.CLIENTE_ID = CLIENTES.CONVENIO_ID " .
        "     INNER JOIN CLIENTES conveniado ON CONVENIO.CLIENTE_ID = CONVENIADO.ID " .
        "     LEFT JOIN PEDIDOS ON PEDIDOS.CLIENTE_ID = CLIENTES.ID " .
        "     INNER JOIN SETORS setor ON PEDIDOS.ENTREGASETOR_ID = SETOR.ID " .
        "     INNER JOIN PEDIDOITEMS items ON ITEMS.PEDIDO_ID = PEDIDOS.ID " .
        "     INNER JOIN PRODUTOS ON ITEMS.PRODUTO_ID = PRODUTOS.ID and UPPER(produtos.descricao) like UPPER('%' || ? || '%') "  .
        "     LEFT JOIN CLIENTEPRODUTOSCONVENIOS prodcon ON PRODCON.CLIENTE_ID = CLIENTES.CONVENIO_ID " .
        "         AND PRODCON.CLIENTE_ID = CONVENIO.CLIENTE_ID AND PRODCON.PRODUTO_ID = ITEMS.PRODUTO_ID AND PRODCON.PRODUTO_ID = PRODUTOS.ID " .
        "     WHERE " .
        "         PEDIDOS.DATAHORAPREVISAOENTREGA BETWEEN " .
        ($mes == ''?
        "      date_trunc('month', CURRENT_TIMESTAMP) AND CURRENT_TIMESTAMP  ":
        "       to_date(?, 'yyyy-mm-dd hh24:mi:ss') and to_date(?, 'yyyy-mm-dd hh24:mi:ss') "
        ) .
        "         AND PEDIDOS.PEDIDOSITUACAO_ID IN ( " .
        "             SELECT id FROM PEDIDOSITUACAOS p WHERE p.EMPRESA_ID = ".Session::get("empresa_padrao")->id." AND (p.ENTREGAFINALIZADA=1 OR p.FECHADOCONCLUIDO=1) " .
        "         ) " .
        "         AND PEDIDOS.CONDICAOPAGAMENTO_ID IN ( " .
        "             SELECT id FROM CONDICAOPAGAMENTOS c WHERE c.EMPRESA_ID = ".Session::get("empresa_padrao")->id." AND c.ATIVO = 1 AND c.TIPO = '4' " .
        "         ) " .
        "         AND (CLIENTES.EMPRESA_ID = ".Session::get("empresa_padrao")->id." " .
        "         AND ITEMS.PRECOVENDAUNITARIO > 0) " .
        "     GROUP BY conveniado.id, conveniado.nome " .
        " ) qrytotal  " .
        " GROUP BY convenio_id, nome " .
        " ORDER BY QUANTCLIENTES desc ";
        $bindings = [$produto];
        if ($mes != '') { $bindings[] = $diainicial; $bindings[] = $diafinal; }
        return DB::select($query, $bindings);
    }

        public function getDataChartValegas(){
        $query = 
         " SELECT   " .
         "        p.DESCRICAO, mes_seq, COALESCE(qryvenda.quantidade, 0) AS quantidade, COALESCE(qryvenda.precomedio, 0) AS precomedio  " .
         "    FROM (  " .
         // Oracle CONNECT BY -> generate_series (últimos 12 meses, yyyymm).
         "        SELECT to_char(date_trunc('month', current_timestamp) - g.lvl * interval '1 month', 'yyyymm') AS mes_seq  " .
         "        FROM generate_series(11,0,-1) AS g(lvl)  " .
         "    ) seq CROSS JOIN produtos p  " .
         "        LEFT JOIN (  " .
         "                SELECT produtos.id AS produto_id, " .
         "                to_char(datavenda,'yyyymm') as mes, " .
         "                count(valegas.id) AS quantidade, " .
         "                sum(venda.valorunitario)/count(valegas.id) AS precomedio " .
         "                from valegas  " .
         "                inner join valegasvendas venda on valegas.valegasvenda_id = venda.id " .
         "                inner join empresas on valegas.empresa_id = empresas.id " .
         "                inner join produtos on valegas.produto_id = produtos.id " .
         "                where empresas.id = ".Session::get("empresa_padrao")->id." AND  " .
         "                    valegas.valegassituacao_id in (select id from valegassituacaos where lower(descricao) like 'baixado' or  " .
         "                                                    lower(descricao) like 'impresso' or lower(descricao) like 'vendido') and " .
         "                    venda.datavenda BETWEEN date_trunc('month', (CURRENT_TIMESTAMP + (-11) * interval '1 month')) AND CURRENT_TIMESTAMP and  " .
         "                    produtos.tipo_glp IN (3,4,5) AND produtos.PESOLIQUIDO IN (13,20,45)   " .
         "                GROUP BY produtos.id, to_char(venda.datavenda,'yyyymm')  " .
         "        )  qryvenda ON qryvenda.produto_id = p.id AND qryvenda.mes = mes_seq  " .
         "        WHERE p.TIPO_GLP IN (3,4,5) AND p.PESOLIQUIDO IN (13,20,45) AND   " .
         "            p.EMPRESA_ID = ".Session::get("empresa_padrao")->id."  " .
         "        ORDER BY p.DESCRICAO, MES_SEQ   ";
        return DB::select($query);
    }

    public function getDataTabelaValegas($produto = 'GLP P13', $mes = ''){
        $diainicial = '';
        $diafinal = '';
        if($mes != ''){
            $diainicial = '20'.(explode('/', $mes)[1])."-".explode('/', $mes)[0]."-01 00:00:00";
            $diafinal = Carbon::createFromFormat('Y-m-d H:i:s', $diainicial)->lastOfMonth()->format('Y-m-d')." 23:59:59";
        }
        $query = 
        " SELECT  " .
        "    cli.id, cli.nome, " .
        "    sum(venda.QUANTIDADE) AS quantidade, " .
        "    AVG(venda.valorunitario) AS precomedio, " .
        "    SUM(venda.valortotal) AS valortotal " .
        "    FROM valegasvendas venda " .
        "    INNER JOIN clientes cli ON cli.id = venda.cliente_id " .
        "    INNER JOIN PRODUTOS ON produtos.id = venda.PRODUTO_ID  " .
        "    WHERE venda.datavenda BETWEEN " .
        ($mes == ''?
        "   date_trunc('month', CURRENT_TIMESTAMP) AND CURRENT_TIMESTAMP  ":
        "    to_date(?, 'yyyy-mm-dd hh24:mi:ss') and to_date(?, 'yyyy-mm-dd hh24:mi:ss') "
        ) .
        "    AND produtos.tipo_glp IN (3,4,5) AND produtos.PESOLIQUIDO IN (13,20,45)    " .
        "    and UPPER(produtos.descricao) like UPPER('%' || ? || '%') "  .
        "    AND EXISTS ( " .
        "        SELECT 1 FROM valegas  " .
        "        WHERE valegas.valegasvenda_id = venda.id " .
        "        AND valegas.valegassituacao_id IN ( " .
        "            SELECT id FROM valegassituacaos  " .
        "            WHERE LOWER(descricao) IN ('baixado', 'impresso', 'vendido') " .
        "        ) " .
        "        AND valegas.empresa_id = ".Session::get("empresa_padrao")->id."  " .
        "    ) " .
        "    GROUP BY cli.id, cli.nome order by quantidade desc ";
        $bindings = [];
        if ($mes != '') { $bindings[] = $diainicial; $bindings[] = $diafinal; }
        $bindings[] = $produto;
        return DB::select($query, $bindings);
    }
}
