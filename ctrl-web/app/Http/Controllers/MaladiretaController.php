<?php

namespace App\Http\Controllers;

use DB;
use Mail;
use Session;
use App\Setor;
use App\Cidade;
use App\Estado;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;
use App\Services\CarbonCustom as Carbon;

class MaladiretaController extends Controller
{
    public function index()
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $repositorio = new SelectRepository($empresa_id);
        $uf = Estado::where('uf', Session::get("empresa_padrao")->uf)->select('uf')->pluck('uf')->first();
        $cidades = $repositorio->getCities();
        $cidade_id = Session::get('empresa_padrao')->cidade_id;
        $cidade_id_tab_2 = $cidade_id;
        $cidade_id_tab_3 = $cidade_id;
        $setores = Setor::where('ativo', 1)->where('empresa_id', $empresa_id)->select('descricao', 'id')->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');

        if(count($_GET) > 0)
            return $this->getFiltersMalaDireta($_GET, $empresa_id, $uf, $setores, $cidades);

        return view('reports.clientes.maladireta.maladireta', compact('uf', 'cidade_id', 'setores', 'cidade_id_tab_2', 'cidade_id_tab_3', 'cidades'));
    }

    public function getFiltersMalaDireta($filtros, $empresa_id, $uf, $setores, $cidades)
    {
        $tab = $filtros['tab'];

        $compram = empty($filtros['compram']) ? '' : $filtros['compram'];
        $naocompram = empty($filtros['naocompram']) ? '' : $filtros['naocompram'];
        $cidade_id = empty($filtros['cidade']) ? 0 : $filtros['cidade'];
        $bairro_id = empty($filtros['bairro']) ? 0 : $filtros['bairro'];
        $rua_id = empty($filtros['rua']) ? 0 : $filtros['rua'];
        $setor_id = empty($filtros['setor']) ? 0 : $filtros['setor'];

        $clientes = $this->searchClientes($setor_id, $cidade_id, $bairro_id, $rua_id, $empresa_id, $tab, $compram, $naocompram, $filtros);
        switch ($tab) {
            case '_tab_2':
                $cidade_id_tab_3 = Session::get('empresa_padrao')->cidade_id;
                $cidade_id_tab_2 = $cidade_id;
                $rua_id_tab_2 = $rua_id;
                $bairro_id_tab_2 = $bairro_id;
                $setor_id_tab_2 = $setor_id;
                break;
            case '_tab_3':
                $cidade_id_tab_2 = $cidade_id;
                $cidade_id_tab_3 = $cidade_id;
                $rua_id_tab_3 = $rua_id;
                $bairro_id_tab_3 = $bairro_id;
                $setor_id_tab_3 = $setor_id;
                break;
            default:
                $cidade_id = Session::get('empresa_padrao')->cidade_id;
                $cidade_id_tab_3 = $cidade_id;
                $cidade_id_tab_2 = $cidade_id;
                break;
        }
        if ($tab) {
            $cidade_id = "";
            $bairro_id = "";
            $rua_id = "";
            $setor_id = "";
        }

        return view('reports.clientes.maladireta.maladireta', 
                        compact('uf', 'cidade_id', 'bairro_id', 'rua_id', 'setor_id', 
                            'cidade_id_tab_2', 'bairro_id_tab_2', 'rua_id_tab_2', 'setor_id_tab_2',
                            'cidade_id_tab_3', 'bairro_id_tab_3', 'rua_id_tab_3', 'setor_id_tab_3',
                             'setores', 'clientes', 'tab', 'compram', 'naocompram', 'cidades'));
    }

    private function searchClientes($setor_id, $cidade_id, $bairro_id, $rua_id, $empresa_id, $tab, $compram, $naocompram, $filtros)
    {
        $clientes = DB::table('clientes as cliente')
                        ->leftJoin('setors as setor', 'cliente.setor_id', 'setor.id')
                        ->join('bairros', 'bairros.id', 'cliente.bairro_id')
                        ->join('cidades', 'cidades.id', 'cliente.cidade_id')
                        ->join('estados', 'estados.uf', 'cliente.uf')
                        ->join('ruas', 'ruas.id', 'cliente.rua_id')
                        ->where('cliente.empresa_id', $empresa_id)
                        ->where("cliente.cliente", 1);
        if($setor_id > 0)
            $clientes->where('cliente.setor_id', $setor_id);

        if($cidade_id > 0)
            $clientes->where('cliente.cidade_id', $cidade_id);
        
        if($bairro_id > 0)
            $clientes->where('cliente.bairro_id', $bairro_id);
        
        if($rua_id > 0)
            $clientes->where('cliente.rua_id', $rua_id);

        if($tab == '') {
            if(empty($tab)){
                $datainicio = explode('/', $filtros['datainicio']);
                $diaInicio = $datainicio[0];
                $mesInicio = $datainicio[1];
                $anoInicio = $datainicio[2];

                $datafim = explode('/', $filtros['datafim']);
                $diaFim = $datafim[0];
                $mesFim = $datafim[1];
                $anoFim = $datafim[2];
            
                $datainicio = insertDataOracle($filtros['datainicio']);
                $datafim = insertDataOracle($filtros['datafim']);
            }
            if($anoFim > $anoInicio) {
                $clientes->where([
                    [DB::raw("TO_CHAR(cliente.datanascimento, 'YYYY-MM-DD')"), '>=', array('0001' . $mesInicio . '-' . $diaInicio)],
                    [DB::raw("TO_CHAR(cliente.datanascimento, 'YYYY-MM-DD')"), '<=', array(Carbon::now()->year . '-' . $mesFim . '-' .$diaFim)]
                    ]);
            } else {
                $clientes->whereBetween(DB::raw("TO_CHAR(cliente.datanascimento, 'MM-DD')"), 
                    [array($mesInicio . '-' . $diaInicio), array( $mesFim . '-' . $diaFim)]);
            }
        } else if ($tab == '_tab_3') {
            $now = Carbon::now()->endOfDay();
            $dataCompram = Carbon::today()->subDays($compram)->startOfDay();
            $dataNaoCompram = Carbon::today()->subDays($naocompram)->endOfDay();
            $selectPedidoSituacao = "SELECT ID FROM PEDIDOSITUACAOS WHERE FECHADOCONCLUIDO = 1";
            $selectJoin = "SELECT cliente_id FROM pedidos WHERE pedidosituacao_id IN ($selectPedidoSituacao) "
                                        . " AND datahoraprevisaoentrega BETWEEN"
                                        . " TO_DATE('$dataCompram', 'YYYY-MM-DD HH24:MI:SS') AND TO_DATE('$dataNaoCompram', 'YYYY-MM-DD HH24:MI:SS')"
                                        . " AND datahoraprevisaoentrega NOT BETWEEN "
                                        . "TO_DATE('" . $dataNaoCompram->copy()->startOfDay() . "', 'YYYY-MM-DD HH24:MI:SS') AND TO_DATE('$now', 'YYYY-MM-DD HH24:MI:SS')"
                                        ." GROUP by cliente_id";
            $selectJoinUltimaCompra = "SELECT cliente_id, MAX(datahoraprevisaoentrega) AS datacompra FROM pedidos WHERE pedidosituacao_id IN ($selectPedidoSituacao) GROUP BY cliente_id";
            $functionJoin = function($join) use ($dataNaoCompram) 
                                {
                                    $join->on('ultimacompra.cliente_id', 'cliente.id')->whereRaw("datacompra < TO_DATE('$dataNaoCompram', 'YYYY-MM-DD HH24:MI:SS')");
                                };

            $clientes->rightJoin(DB::raw("($selectJoin) pedido"), 'pedido.cliente_id', 'cliente.id');
            $clientes->rightJoin(DB::raw("($selectJoinUltimaCompra) ultimacompra"), $functionJoin);
        }

        $clientes = $clientes->select('setor.descricao as setor', 'cliente.id', 'cliente.datanascimento', 'cliente.email', 'cliente.nome',
                                                    DB::raw("(bairros.descricao || ' - ' || ruas.descricao || ', ' || cliente.numero) as endereco"),
                                                    DB::raw("(SELECT string_agg(tel.telefone, ', ' order by tel.telefone) FROM clientetelefones tel 
                                                        WHERE tel.cliente_id = cliente.id AND ROWNUM <= 2 and telefonetipo_id IN 
                                                            (SELECT id FROM telefonetipos WHERE celular = 1)
                                                        ) as telefone")
                                                )->orderBy('nome')->get();

        return $clientes;
    }

    public function csv(Request $request)
    {
        $data = $request->all();
        $clientes = json_decode($data['clientes']);

        // unlink($filename);
        $filename = Session::get('empresa_padrao')->id . '.csv';

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $handle = fopen($filename, 'w+');
        fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $text = 'CLIENTE;TELEFONE' . PHP_EOL;
        foreach($clientes as $cliente) {
            $phones = explode(',', $cliente[1]);
            $phones = str_replace('(', '', $phones);
            $phones = str_replace(')', '', $phones);
            $phones = str_replace('-', '', $phones);
            $phones = str_replace(' ', '', $phones);
            $cliente[1] = ';' . implode(', ', $phones) . PHP_EOL;
            $text .= $cliente[0] . $cliente[1];
        }

        fwrite($handle, $text);

        fclose($handle);

        return response()->download($filename, 'clientes.csv', $headers)->deleteFileAfterSend(true);

    }

    public function deleteTempFileCSV($filename)
    {
        unlink($filename . '.csv');
        return "OK|";
    }

    public function sendMail(Request $request)
    {
        $data = $request->all();
        try {
            $clientes = collect(json_decode($data['clientes']));

            sendMail([
                'content' => $data['conteudo'],
                'subject' => $data['assunto'],
                'to' => $clientes->pluck('email')->toArray()
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();    
        }
        return 'OK|';
    }

    public function etiquetas()
    {
        $arrayClientes_id = explode(';', $_GET['clientes']);
        $apartir = $_GET['apartir'];

        $dadosParaImpressao = [];

        $clientes = DB::table('clientes as cliente')
                        ->join('bairros', 'bairros.id', 'cliente.bairro_id')
                        ->join('cidades', 'cidades.id', 'cliente.cidade_id')
                        ->join('estados', 'estados.uf', 'cliente.uf')
                        ->join('ruas', 'ruas.id', 'cliente.rua_id')
                        ->whereIn('cliente.id', $arrayClientes_id)
                        ->select(DB::raw("(ruas.descricao || ', ' || cliente.numero) as rua_numero, 
                            (bairros.descricao || ' - ' || cidades.descricao || '/' || UPPER(estados.uf) ) as bairro_cidade"), 'cliente.nome', 
                            'cliente.cep')->get();

        $clientes = $clientes->sortBy('nome');
        if ($apartir > 1) {
            for ($i = 1; $i < $apartir; $i++) {
                $dados = (object) [];
                $dados->rua_numero = '&nbsp;';
                $dados->bairro_cidade = '';
                $dados->nome = '';
                $dados->cep = '';
                $clientes->push($dados);
            }
        }
        $clientes = $clientes->reverse();
        $qdeArray = count($clientes);
        if($qdeArray < 82) {
            if($qdeArray < 22 )
                $extras = 22 - $qdeArray;
            else if($qdeArray < 52 )
                $extras = 52 - $qdeArray;
            else
                $extras = 82 - $qdeArray;
            for ($i = 1; $i < $extras; $i++) {
                $dados = (object) [];
                $dados->rua_numero = '';
                $dados->bairro_cidade = '';
                $dados->nome = '';
                $dados->cep = '';
                $clientes->push($dados);
            }
        }

        $dados = [];
        foreach ($clientes as $cliente) {
            array_push($dados, $cliente);
        }
        $clientes = $dados;
        return view('reports.clientes.maladireta.etiquetas', compact('clientes'));
    }
}
