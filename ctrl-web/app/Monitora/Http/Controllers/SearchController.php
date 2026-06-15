<?php

namespace App\Monitora\Http\Controllers;
/**
 * ApiSearchController is used for the "smart" search throughout the site.
 * it returns and array of items (with type and icon specified) so that the selectize.js plugin can render the search results properly
 * */
use Illuminate\Http\Request;
use App\Monitora\Models\Empresa;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use Session;
use Illuminate\Support\Facades\Input;
use Illuminate\Http\Response;
use Carbon\Carbon;
use App\Monitora\Models\User;
use App\Monitora\Models\Ultimaposicao;
use App\Monitora\Models\Position;
use App\Monitora\Models\Veiculo;
use App\Monitora\Models\Setor;
use App\Monitora\Models\Cerca;
use DB;
use Illuminate\Support\Collection;

class SearchController extends Controller
{

    public function appendValue($data, $type, $element)
    {
        // operate on the item passed by reference, adding the element and type
        foreach ($data as $key => & $item) {
            $item[$element] = $type;
        }
        return $data;
    }

    public function appendURL($data, $prefix)
    {
        // operate on the item passed by reference, adding the url based on slug
        foreach ($data as $key => & $item) {
            $item['url'] = url($prefix . '/' . $item['slug']);
        }
        return $data;
    }

    public function getPosicaoAtual()
    {
        $grupo_id = e(Input::get('grupo_id', ''));
        if (!$grupo_id && $grupo_id == '')
            return Response::json(array(), 400);

        $ultima_posicaoscoll = new Collection();
        foreach (\Auth::guard('monitora')->user()->empresas as $empresa) {
            $ultima_posicaos = $empresa->ultimaposicaos; //Ultimaposicao::where('empresa_id', $empresa->id)->get();
            //$ultima_posicaoscoll->merge($ultima_posicaos);
        }
        $ultima_posicaoscoll = Ultimaposicao::whereIn('empresa_id', \Auth::guard('monitora')->user()->empresas->pluck('id')->toArray())->get();

        return response()->json([
                    'data' => $ultima_posicaoscoll,
                        ]
        );
    }

    public function getPedidosPendentes()
    {
        $grupo_id = e(Input::get('grupo_id', ''));
        if (!$grupo_id && $grupo_id == '')
            return Response::json(array(), 400);

        $qry =  "select " .
                "	p.codigo_pedido " .
                // MySQL->PG: date_format -> to_char; TIMESTAMPDIFF(MINUTE,a,b) -> EXTRACT(EPOCH...)/60
                "   ,to_char(p.data_hora_entrega, 'DD/MM/YYYY HH24:MI:SS') as data_hora_entrega " .
                "   ,to_char(p.data_hora_envio, 'DD/MM/YYYY HH24:MI:SS') as data_hora_envio " .
                "   ,(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - p.data_hora_envio)) / 60)::int dif " .
                "   ,ruas.desc_rua " .
                "   ,c.razao_social " .
                "   ,p.numero_entrega " .
                "   ,p.bairro_entrega " .
                "   ,cid.desc_cidade " .
                "   ,est.uf " .
                "   ,p.entrega_urgente " .
                "   ,s.desc_setores " .
                "   ,co.nome_colaborador " .
                // MySQL IF(cond,a,b) -> Postgres CASE WHEN cond THEN a ELSE b END
                "   ,CASE WHEN p.latitude IS NULL OR p.latitude = 0 THEN c.latitude ELSE p.latitude END AS latitude " .
                "   ,CASE WHEN p.longitude IS NULL OR p.longitude = 0 THEN c.longitude ELSE p.longitude END AS longitude " .
                "   ,CONCAT(COALESCE(ruas.desc_rua, '') , ', ', " .
                "		   COALESCE(p.numero_entrega,''), ' - ', " .
                "		   COALESCE(p.bairro_entrega,''), ' - ', " .
                "		   COALESCE(cid.desc_cidade,''), ' - ', " .
                "		   COALESCE(est.uf,'')) AS endereco " .
                "   ,CONCAT(COALESCE(ruas.desc_rua, '') , ', ', " .
                "		   COALESCE(p.numero_entrega,''), ' - ', " .
                "		   COALESCE(p.bairro_entrega,'')) AS endereco1 " .
                "   ,c.codigo_clientes " .
                "   , (select CONCAT(tempo_entrega, '|', tempo_entrega_urgente) from tbl_config limit 1) as tempos " .
                " from " .
                "   tbl_pedidos p " .
                " INNER JOIN tbl_ruas ruas ON ruas.codigo_rua = p.cod_rua_entrega " .
                " INNER JOIN tbl_cidades cid ON cid.codigo_cidade = p.cod_cidade " .
                " INNER JOIN tbl_estados est ON est.codigo_estado = cid.cod_estado " .
                " INNER JOIN tbl_clientes c ON c.codigo_clientes = p.cod_cliente " .
                " INNER JOIN tbl_setores s ON (s.codigo_setores = p.cod_setores) " .
                " INNER JOIN tbl_setores_colaborador sc ON (sc.cod_setores = s.codigo_setores) " .
                " INNER JOIN tbl_colaborador co ON (co.codigo_colaborador = sc.cod_colaborador and p.cod_colaborador = co.codigo_colaborador) " .
                " " .
                " where " .
                "   p.data_hora_envio is not null and " .
                "   p.cod_pedidos_status in (8,1001,1002,1012,2003,2004) and " .
                "   p.cod_setores in (1,2,4,6,7,9,12,13,14,27) " .
                "   and " . Session::get('empresa_padrao')->id . " = 1 ".
                " order by " .
                "   p.codigo_pedido ";
        //dd($qry);
        //$results = DB::connection('mysql2')->select($qry, []);
        $qry = " SELECT  " .
        " ped.ID AS codigo_pedido " .
        " ,TO_CHAR(ped.ENTREGADATAHORA, 'dd/mm/yyyy') AS data_hora_entrega " .
        " ,TO_CHAR(ped.DATAHORA, 'dd/mm/yyyy HH24:mi:ss') AS data_hora_envio " .
        " ,ROUND(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - ped.DATAHORA)) / 60) AS dif " .
        " ,ruas.DESCRICAO AS desc_rua " .
        " ,cli.NOME AS razao_social " .
        " ,ped.ENTREGANUMERO AS numero_entrega " .
        " ,bai.DESCRICAO AS bairro_entrega " .
        " ,cid.DESCRICAO AS desc_cidade " .
        " ,cid.UF AS UF " .
        " ,CASE WHEN ped.ENTREGAURGENTE=1 THEN 'S' ELSE 'N' END AS entrega_urgente " .
        " ,se.DESCRICAO AS desc_setores " .
        " ,co.NOME AS nome_colaborador " .
        " ,ped.LATITUDE AS latitude " .
        " ,ped.LONGITUDE AS longitude " .
        " ,COALESCE(ruas.DESCRICAO, '')||', '||COALESCE(TO_CHAR(ped.ENTREGANUMERO), '')||' - '||COALESCE(bai.DESCRICAO,'')||' - '||COALESCE(cid.DESCRICAO,'')||' - '||COALESCE(cid.UF,'') AS  endereco " .
        " ,COALESCE(ruas.DESCRICAO, '')||', '||COALESCE(TO_CHAR(ped.ENTREGANUMERO), '')||' - '||COALESCE(bai.DESCRICAO,'') AS  endereco1 " .
        " ,ped.CLIENTE_ID " .
        " ,con.TEMPOENTREGA||'|'||con.TEMPOURGENTE AS tempos " .
        " FROM pedidos ped " .
        " INNER JOIN ruas ruas ON ruas.ID = ped.ENTREGARUA_ID " .
        " INNER JOIN clientes cli ON cli.ID = ped.CLIENTE_ID " .
        " INNER JOIN bairros bai ON bai.ID = ped.ENTREGABAIRRO_ID " .
        " INNER JOIN cidades cid ON cid.ID = ped.ENTREGACIDADE_ID " .
        " INNER JOIN setors se ON se.ID = ped.ENTREGASETOR_ID " .
        " INNER JOIN colaboradors co ON co.ID = ped.COLABORADOR_ID " .
        " INNER JOIN empresaconfigs con ON con.EMPRESA_ID = ped.EMPRESA_ID " .
        " WHERE ped.PEDIDOSITUACAO_ID IN (SELECT ID FROM pedidosituacaos WHERE fechadoconcluido <> 1 and fechadocancelado <> 1 and entregafinalizada <> 1 and entregacancelada <> 1) " .
        " AND ped.GRUPO_ID = 2 AND ped.EMPRESA_ID = 2";
        //dd($qry);
        // UNIFICAÇÃO: lê o ERP no MESMO Postgres (schema public), não mais via
        // conexão Oracle externa (oracle3). Query já em sintaxe Postgres.
        $results = DB::connection('pgsql')->select($qry, []);
        $response = ["status" => 'OK', "dados" => $results];

        //$response = buscarDadosRastreamento('getRastreamentoPedidos');
        //$registros = json_decode($response);

        return response()->json([
                    'data' => $response,
                        ]
        );
    }


    public function getCercaRastreamento()
    {
        $empresa_id = e(Input::get('empresa_id', ''));
        if (empty($empresa_id) || $empresa_id == 0) {
            $empresa_id = Session::get('empresa_padrao')->id;
        }
        $cercas = Cerca::where('empresa_id', $empresa_id)->get();
        return response()->json($cercas);
    }

    public function getCoordenadasSetor()
    {
        $setor_id = e(Input::get('setor_id', ''));
        if (!$setor_id && $setor_id == '')
            return Response::json(array(), 400);
        $setor = Setor::findOrFail($setor_id);
        return response()->json([
                    'latitude' => $setor->latitude,
                    'longitude' => $setor->longitude,
        ]);
    }

    public function getCercaPoligono()
    {
        $pars = explode('|', e(Input::get('cerca_id', '')));
        $cerca_id = $pars[1];
        $empresa_id = $pars[0];

        $poligonos = array();
        if ($cerca_id > 0) {
            $cerca = Cerca::find($cerca_id);
            array_push($poligonos, ['id' => $cerca_id, 'cor' => $cerca->cor, 'poligono' => $cerca->coordenadas]);
        } else {
            $except = e(Input::get('cerca_id_except', ''));
            $cercas = Cerca::where('empresa_id', $empresa_id)->get();
            foreach ($cercas as $cerca) {
                if($cerca->id != $except){
                    array_push($poligonos, ['id' => $cerca_id, 'cor' => $cerca->cor, 'poligono' => $cerca->coordenadas]);
                }
            }
        }
        return response()->json([
                    'data' => $poligonos,
                        ]
        );
    }

    public function getRotas()
    {
        $veiculo_id = e(Input::get('veiculo_id', ''));
        $dataInicio = e(Input::get('datainicio', ''));
        $dataTermino = e(Input::get('datafim', ''));
        $dataini = Carbon::createFromFormat('d/m/Y H:i', $dataInicio);
        $datafin = Carbon::createFromFormat('d/m/Y H:i', $dataTermino);

        if (!$veiculo_id && $veiculo_id == '')
            return Response::json(array(), 400);

        $veiculo = Veiculo::find($veiculo_id);
        $veiculo->load("veiculotipo");
        $positions = Position::where('deviceid', $veiculo->deviceid)
                ->whereBetween('dhposition', [$dataini, $datafin])
                ->orderBy('dhposition', 'desc')
                ->get();

        return response()->json([
                    'data' => $positions,
                    'veiculo' => $veiculo,
                        ]
        );
    }

}
