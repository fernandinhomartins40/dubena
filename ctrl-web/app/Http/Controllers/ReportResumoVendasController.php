<?php

namespace App\Http\Controllers;

use App\Produto;
use App\Produtoclasse;
use App\Repository\SelectRepository;
use App\Setor;
use DB;
use Session;
use Carbon\Carbon;

class ReportResumoVendasController extends Controller
{
    protected $empresa_id;
    protected $grupo_id;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $class = Produtoclasse::where([["tipo", "G"], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->pluck('id')->toArray();
        $produtos = Produto::where(['ativo' => 1, 'empresa_id' => Session::get('empresa_padrao')->id])->whereIn('produtoclasse_id', $class)->orderby('descricao')->pluck('descricao', 'id')->prepend("Selecione", "");
        $setores =  Setor::where(['empresa_id' => Session::get('empresa_padrao')->id, 'ativo' => 1])->orderby('descricao')->pluck('descricao', 'id');
        $setores_sel =  Setor::where(['empresa_id' => Session::get('empresa_padrao')->id, 'ativo' => 1, 'estoqueproprio'=>1])->orderby('descricao')->pluck('id')->toArray();
        return view('reports.vendas.resumo.vendas_diasetor')->with(compact('produtos', 'setores', 'setores_sel'));

    }

    public function filtroResumoDia()
    {
        $dias = explode('_', 'Domingo_Segunda-Feira_Terça-Feira_Quarta-Feira_Quinta-Feira_Sexta-Feira_Sábado');
        $this->empresa_id = Session::get("empresa_padrao")->id;
        $this->grupo_id = Session::get("empresa_padrao")->grupo_id;

        $dataReferencia = Carbon::parse(request()->get("datareferencia"));
        $produto_id = request()->get("produto");
        $setor_id = explode(',', request()->get("setor"));
        array_push($setor_id, -1);
        $produto = Produto::find($produto_id);
        $tipo = request()->get("tipo");
        $vendas = $this->getResumoVendas($dataReferencia, $produto_id, Session::get('empresa_padrao')->id, $setor_id);
        $totais = $vendas->reduce(function ($carry, $item) {
            return [$carry[0] + $item->qtde, $carry[1] + $item->qtdemeta];
        }, [0, 0]);
        $titulo = "Resumo de Venda Diária por Setor";
        $filtro = [];
        array_push($filtro, "Referência " . requestDataOracle($dataReferencia, false));
        array_push($filtro, "Dia da Semana: ".$dias[$dataReferencia->dayOfWeek]);
        array_push($filtro, "Produto: ".$produto->descricao);

        $seta_cima = base64_encode(file_get_contents(public_path('img/seta_cima_verde.png')));
        $seta_baixo = base64_encode(file_get_contents(public_path('img/seta_baixo_vermelha.png')));

        $empresa = Session::get("empresa_padrao");

        if ($tipo == "1") return view("reports.vendas.resumo.vendas_diasetor_pdf")->with(compact("titulo", "filtro", "vendas", "totais", "empresa", "seta_cima", "seta_baixo"));

        $pdf = \App::make("dompdf.wrapper");

        $pdf->loadView("reports.vendas.resumo.vendas_diasetor_pdf", compact("titulo", "filtro", "vendas", "totais", "empresa", "seta_cima", "seta_baixo"));

        return $pdf->stream();
    }

    public function getResumoVendas($dataReferencia, $produto_id, $empresa_id, $setor_id)
    {
        $referencia = $dataReferencia->endOfDay();
        $query = 
        " select qrytotal.* " .
        " from ( " .
        "   select (case when length(setor) > 20 then substr(setor,0,20) || '...' else setor end) as setor, " .
        "   produto,  " .
        "   max((select substr(c.nome,1,instr(c.nome,' ')-1) from  " .
        "   setorcolaboradores sc  " .
        "   inner join colaboradors c on c.id = sc.colaborador_id " .
        "   where sc.setor_id = qry.id and rownum <= 1)) AS nome, " .
        "   sum(qtde) as qtde, round(sum(qtdemeta) / extract(day from last_day(to_date('".  $referencia ."','yyyy-mm-dd hh24:mi:ss'))),0) as qtdemeta  " .
        "   from ( " .
        "     select setor.id, setor.descricao as setor, produtos.descricao as produto, sum(items.quantidade) as qtde, 0 as qtdemeta " .
        "     from pedidos " .
        "     inner join pedidoitems items on items.pedido_id = pedidos.id  " .
        "     inner join produtos on items.produto_id = produtos.id  " .
        "     inner join setors setor on pedidos.entregasetor_id = setor.id  " .
        "     where pedidos.empresa_id = ".  $empresa_id ." and  " .
        "     setor.id in (".implode(',', $setor_id).") and " .
        "     pedidos.datahoraprevisaoentrega between trunc(to_date('".  $referencia ."','yyyy-mm-dd hh24:mi:ss')) and  " .
        "     to_date('".  $referencia ."','yyyy-mm-dd hh24:mi:ss') and items.produto_id = " . $produto_id . " " .
        "     and pedidos.pedidosituacao_id in (select id from pedidosituacaos where (fechadoconcluido = 1 or entregafinalizada = 1) and grupo_id = (select grupo_id from empresas where id = '".  $empresa_id ."')) " .
        "     group by setor.id, setor.descricao, produtos.descricao " .
        "    " .
        "     union all " .
        "      " .
        "     select setor.id, setor.descricao as setor, produtos.descricao as produto, 0 as qtde, sum(meta.quantidade) as qtdemeta  " .
        "     from metavendas meta " .
        "     join setors setor on meta.setor_id = setor.id " .
        "     join produtos on meta.produto_id = produtos.id " .
        "     where meta.empresa_id = ".  $empresa_id ." and  " .
        "     extract(month from meta.datameta) = extract(month from to_date('".  $referencia ."','yyyy-mm-dd hh24:mi:ss')) and  " .
        "     produtos.id = " . $produto_id . " and " .
        "     setor.id in (".implode(',', $setor_id).") " .
        "     group by setor.id, setor.descricao, produtos.descricao " .
        "   ) qry " .
        "   group by setor, produto " .
        "   order by setor " .
        " ) qrytotal " .
        " cross join empresas e  " .
        " where e.id = ".  $empresa_id ." and qtde <> 0 ";
        $vendas = collect(DB::select($query));
        return $vendas;
    }
}
