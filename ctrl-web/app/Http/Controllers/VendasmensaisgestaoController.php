<?php

namespace App\Http\Controllers;

use App\Configuracoesgerais;
use App\Empresaconfig;
use App\Setor;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpPresentation\PhpPresentation;
    use PhpOffice\PhpPresentation\DocumentLayout;
    use PhpOffice\PhpPresentation\Slide;
    use PhpOffice\PhpPresentation\Shape\Chart;
    use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar;
    use PhpOffice\PhpPresentation\Shape\Chart\Type\Line;
    use PhpOffice\PhpPresentation\Shape\Chart\Series;
    use PhpOffice\PhpPresentation\Style\Color;
    use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Alignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpPresentation\Shape\Drawing\Base64;
use PhpOffice\PhpPresentation\Shape\Drawing\File;
use PhpOffice\PhpPresentation\Style\Fill;

class VendasmensaisgestaoController extends Controller
{
    public function index()
    {
        $years = $this->getTime('ano');
		$meses = $this->getTime('mes');
        $keygooglemaps = $this->getKey();
        return view("reports.vendas.vendasmensais.index")->with(compact('years', 'meses', 'keygooglemaps'));
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

    public function getDashboard()
    {
        try {
            $ano = Input::get("ano", "");
            $mes = Input::get("mes", "");
            $dataChartVendaGeral = $this->getDataChartVendaGeral($ano);
            $dataChartVendaSetores = $this->getDataChartVendaSetores($ano, $mes);
            $directory = 'presentation/'.$ano.str_pad($mes, 2, '0', STR_PAD_LEFT);
            $gerado = (Storage::disk('local')->exists($directory));
            
            return responseSuccess(["dataChartVendaGeral" => $dataChartVendaGeral,
                                    "dataChartVendaSetores" => $dataChartVendaSetores, 
                                    "gerado" => $gerado]);
        } catch(Exception $e){
            return getErrorsException($e);
        }
    }

    public function getDataChartVendaGeral($ano){
        $query = 
        " SELECT   " .
        "     p.DESCRICAO, mes_seq, COALESCE(qryvenda.quantidade, 0) AS quantidade, COALESCE(qryvenda.precomedio, 0) AS precomedio  " .
        " FROM (  " .
        "     SELECT " .
		// Oracle CONNECT BY: meses do ano $ano (até o mês atual se ano corrente,
		// senão 12). Postgres: generate_series com limite dinâmico via CASE.
		"       TO_CHAR(date_trunc('year', TO_DATE('".$ano."' || '-01-01', 'YYYY-MM-DD')) + (g.lvl - 1) * interval '1 month', 'YYYYMM') AS mes_seq " .
		"     FROM generate_series(1, CASE WHEN '".$ano."' = TO_CHAR(now(), 'YYYY') THEN EXTRACT(MONTH FROM now())::int ELSE 12 END) AS g(lvl) " .
        " ) CROSS JOIN ( " .
        "     SELECT 998 AS id, 'Realizado' AS descricao FROM dual " .
        "       UNION ALL SELECT 999 AS id, 'Meta' AS descricao FROM dual) p  " .
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
        "         inner join setors se on pedidos.entregasetor_id = se.id " .
        "         where pedidos.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
        "         extract(year from pedidos.datahoraprevisaoentrega) = ".$ano." AND   " .
        "         pedidos.pedidosituacao_id in (select id from pedidosituacaos where empresa_id = ".Session::get("empresa_padrao")->id." and fechadoconcluido = 1) and  " .
        "         produtos.tipo_glp IN (3) AND produtos.PESOLIQUIDO IN (13) and " .
        "         (op.disk = 1 or op.vendadireta = 1) and  " .
        "         se.estoqueproprio = 1 " .
        "         GROUP BY produtos.id, to_char(datahoraprevisaoentrega,'yyyymm')  " .
        " " .
        "         UNION ALL  " .
        "          " .
        "         select 999 AS produto_id, to_char(meta.datameta,'yyyymm') as mes, sum(meta.quantidade) as quantidade, 0 AS precomedio " .
        "	    from metavendas meta " .
        "	    inner join produtos on meta.produto_id = produtos.id " .
        "	    inner join empresas on meta.empresa_id = empresas.id " .
        "	    where empresas.id = ".Session::get("empresa_padrao")->id."  and " .
        "	    extract(year from datameta) = ".$ano." and produtos.tipo_glp = 3 " .
        "	    group by to_char(meta.datameta,'yyyymm') " .
        "     )  qryvenda ON qryvenda.produto_id = p.id AND qryvenda.mes = mes_seq  " .
        "     " .
        "     ORDER BY p.DESCRICAO, MES_SEQ   ";
        return DB::select($query);
    }

    public function getDataChartVendaSetores($ano, $mes){
        $setors = Setor::where('empresa_id', Session::get("empresa_padrao")->id)
                       ->where('ativo', true)
                       ->where('estoqueproprio', true)
                       ->orderBy('descricao')
                       ->get();

        $result = [];

        $config = Empresaconfig::where('empresa_id', Session::get('empresa_padrao')->id)->select('fatorpotencialvenda')->get()->first();

        foreach($setors as $setor){
            $row = ['id'=>$setor->id, 'descricao'=>$setor->descricao, 'potencialvendas'=>$setor->qtderesidencias?$setor->qtderesidencias*$config->fatorpotencialvenda/13:0, 'dados'=>[], 'markers'=>[], 'cerca'=>null];
            $query = 
            " SELECT   " .
            "     p.DESCRICAO, mes_seq, COALESCE(qryvenda.quantidade, 0) AS quantidade, COALESCE(qryvenda.precomedio, 0) AS precomedio  " .
            " FROM (  " .
            "     SELECT " .
            // Oracle CONNECT BY: meses do ano (até mês atual se ano corrente). -> generate_series.
            "       TO_CHAR(date_trunc('year', TO_DATE('".$ano."' || '-01-01', 'YYYY-MM-DD')) + (g.lvl - 1) * interval '1 month', 'YYYYMM') AS mes_seq " .
            "     FROM generate_series(1, CASE WHEN '".$ano."' = TO_CHAR(now(), 'YYYY') THEN EXTRACT(MONTH FROM now())::int ELSE 12 END) AS g(lvl) " .
            " ) CROSS JOIN ( " .
            "     SELECT 998 AS id, 'Realizado' AS descricao FROM dual " .
            "       UNION ALL SELECT 999 AS id, 'Meta' AS descricao FROM dual) p  " .
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
            "         where pedidos.empresa_id = ".Session::get("empresa_padrao")->id." and  " .
            "         extract(year from pedidos.datahoraprevisaoentrega) = ".$ano." AND   " .
            "         pedidos.pedidosituacao_id in (select id from pedidosituacaos where empresa_id = ".Session::get("empresa_padrao")->id." and fechadoconcluido = 1) and  " .
            "         produtos.tipo_glp IN (3) AND produtos.PESOLIQUIDO IN (13) and " .
            "         pedidos.ENTREGASETOR_ID = ".$setor->id." and " .
            "        (op.disk = 1 or op.vendadireta = 1)  " .
            "         GROUP BY produtos.id, to_char(datahoraprevisaoentrega,'yyyymm')  " .
            " " .
            "         UNION ALL  " .
            "          " .
            "         select 999 AS produto_id, to_char(meta.datameta,'yyyymm') as mes, sum(meta.quantidade) as quantidade, 0 AS precomedio " .
            "	    from metavendas meta " .
            "	    inner join produtos on meta.produto_id = produtos.id " .
            "	    inner join empresas on meta.empresa_id = empresas.id " .
            "	    where empresas.id = ".Session::get("empresa_padrao")->id."  and " .
            "       meta.SETOR_ID = ".$setor->id." AND  " .
            "	    extract(year from datameta) = ".$ano." and produtos.tipo_glp = 3 " .
            "	    group by to_char(meta.datameta,'yyyymm') " .
            "     )  qryvenda ON qryvenda.produto_id = p.id AND qryvenda.mes = mes_seq  " .
            "     " .
            "     ORDER BY p.DESCRICAO, MES_SEQ   ";
            $row['dados'] = DB::select($query);
            $row['markers'] = $this->getPedidosMapa($ano, $mes, $setor->id);
            $row['cerca'] = $this->getCercas($setor->id);
            array_push($result, $row);
        }
        return $result;
    }

    private function getPedidosMapa($ano, $mes, $setor_id)
    {
        $datainicio = Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, '0', STR_PAD_LEFT).'01')->startOfDay();
        $datafim = Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, '0', STR_PAD_LEFT).'01')->endOfMonth()->endOfDay();
        $pedidos = DB::table('pedidos as pedido')
            ->join('clientes as cliente', 'cliente.id', 'pedido.cliente_id')
            ->join('bairros', 'bairros.id', 'pedido.entregabairro_id')
            ->join('ruas', 'ruas.id', 'pedido.entregarua_id')
            ->whereRaw("pedidosituacao_id IN (SELECT id FROM pedidosituacaos WHERE grupo_id = " . Session::get('empresa_padrao')->grupo_id . " AND (fechadoconcluido = 1 or entregafinalizada = 1) )")
            ->whereBetween("datahoraenvioentregador", [$datainicio, $datafim])
            ->where('pedido.empresa_id', Session::get('empresa_padrao')->id)
            ->selectRaw(
                "pedido.id, pedido.latitude as entregalatitude, " .
                    "pedido.longitude as entregalongitude, cliente.nome, " .
                    "pedido.latitude || pedido.longitude as latlng, (ruas.descricao || ', ' || pedido.entreganumero) as endereco, " .
                    "round((entregadatahora - datahoraenvioentregador) * 24 * 60) as tempoentrega, " .
                    "TO_CHAR(entregadatahora, 'dd/mm/yyyy hh24:mi:ss') as entrega, " .
                    "TO_CHAR(datahoraenvioentregador, 'dd/mm/yyyy hh24:mi:ss') as previsao"
            )->orderby('latlng');

        if ($setor_id > 0)
            $pedidos->where('entregasetor_id', $setor_id);

        return $pedidos->get()->toArray();
    }

    public function getCercas($setor_id){
        $cercasAll = \DB::connection("monitora")
            ->table('cercapoligonos as pol')
            ->join('cercas as cer', 'pol.cerca_id', 'cer.id')
            ->join('setors as set', 'cer.setor_id', 'set.id')
            ->selectRaw('pol.latitude as lat, pol.longitude as lng')
            ->where('setor_ctrlmais',  $setor_id)
            ->get();
        return $cercasAll;
    }


    private function getKey()
	{
		$config = Empresaconfig::where('empresa_id', Session::get('empresa_padrao')->id)->select('keygooglemaps')->get()->first();

		if(is_null($config) || (!is_null($config) && is_null($config->keygooglemaps)))
			$config = Configuracoesgerais::select('keygooglemaps')->first();

		if(!is_null($config) && !is_null($config->keygooglemaps)){
			$key = $config->keygooglemaps;
			return $key;
		} 
		return null;
	}


    public function generatePowerPoint(Request $request)
    {
        try {
            $data = $request->all();
            $directory = 'presentation/'.$data['ano'].str_pad($data['mes'], 2, '0', STR_PAD_LEFT);
            $gerado = (Storage::disk('local')->exists($directory));
            throwIf(!$gerado, 'Favor atualizar os dados no servidor antes de realizar o download');
            $objPHPPresentation = new PhpPresentation();
            //Gera o slide de folha de rosto;
            $this->generateSlideInicial($objPHPPresentation, $data);
            //Gera o slide de venda geral
            $this->generateSlideGeral($objPHPPresentation, $data, $directory);
            //Gera os slides por setor
            $this->generateSlidesSetores($objPHPPresentation, $data, $directory);
            //Gera o slide de venda geral
            $this->generateSlideParticipacaoGeral($objPHPPresentation, $data, $directory);
            //Gera o slide final
            $this->generateSlideFinal($objPHPPresentation, $data);
            $oWriter = IOFactory::createWriter($objPHPPresentation, 'PowerPoint2007');
            $filename = 'vendas_'.$data['ano'].str_pad($data['mes'], 2, '0', STR_PAD_LEFT).'.pptx';
            $oWriter->save(storage_path('app/' . $filename));
            return responseSuccess(['fileUrl' => route('vendasmensaisgestao.downloadPowerPoint', ['fileName' => $filename])]);
        } catch(Exception $e){
            return responseError($e->getMessage());
        }
    }

    public function downloadPowerPoint($fileName)
    {
        $filePath = storage_path('app/' . $fileName);

        if (file_exists($filePath)) {
            return response()->download($filePath, $fileName);
        } else {
            abort(404, 'File not found.');
        }
    }

    public function generateSlideInicial($objPHPPresentation, $data){
        $currentSlide = $objPHPPresentation->getActiveSlide();

        $img = $currentSlide->createDrawingShape();
        $img->setPath(public_path('img/imagem_venda.png'))
            ->setOffsetX(0)
            ->setOffsetY(0);

        $shape = $currentSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(900)
                ->setOffsetX(25)
                ->setOffsetY(25);


        $textRun2 = $shape->createTextRun('Vendas de '.getDescricaoMes($data['mes'])."/".$data['ano']);
        
        $textRun2->getFont()
            ->setBold(true)
            ->setSize(40)
            ->setName('Arial');

        $logo = $currentSlide->createDrawingShape();
        $logo->setPath(public_path('img/logo_dubena.png'))
            ->setOffsetX(200)
            ->setOffsetY(530)
            ->setWidth(500)
            ->setResizeProportional(true);
    }

    public function generateSlideGeral($objPHPPresentation, $data, $directory){
        $currentSlide = $objPHPPresentation->createSlide();

        $imgtblvendageral = Storage::disk('local')->path($directory."/999999_tbl_vendageral.png");
        $imgchartvendageral = Storage::disk('local')->path($directory."/999999_chartVendaGeral.png");

        $this->generateTextBoxTitle($currentSlide, 'Venda Geral');
        $this->generateTextBoxFooter($currentSlide, $data);
        $currentSlide->addShape($this->generateImgGeral($imgchartvendageral, 20, 80, 920));
        $currentSlide->addShape($this->generateImgGeral($imgtblvendageral, 20, 420, 920));
    }

    public function generateSlidesSetores($objPHPPresentation, $data, $directory){
        $setors = Setor::where('empresa_id', Session::get("empresa_padrao")->id)
                       ->where('ativo', true)
                       ->where('estoqueproprio', true)
                       ->orderBy('descricao')
                       ->get();

        foreach($setors as $setor){
            $imgtblvendasetor = Storage::disk('local')->path($directory."/".$setor->id."_tbl_vendasetor_".$setor->id.".png");
            $imgchartvendasetor = Storage::disk('local')->path($directory."/".$setor->id."_chartSetor.png");
            $imgmapasetor = Storage::disk('local')->path($directory."/".$setor->id."_divMapa_".$setor->id.".png");
            $imgcharttemposetor = Storage::disk('local')->path($directory."/".$setor->id."_chartTempo.png");
            $imgpartsetor = Storage::disk('local')->path($directory."/".$setor->id."_divtblparticip_".$setor->id.".png");
            // Criar slide para vendas mensais no ano
            $currentSlide = $objPHPPresentation->createSlide();
            $this->generateTextBoxTitle($currentSlide, $setor->descricao);
            $this->generateTextBoxFooter($currentSlide, $data);
            if(file_exists($imgchartvendasetor)){
                $currentSlide->addShape($this->generateImgGeral($imgchartvendasetor, 20, 80, 920));
            }
            if(file_exists($imgtblvendasetor)){
                $currentSlide->addShape($this->generateImgGeral($imgtblvendasetor, 20, 420, 920));
            }
            //Criar slide para vendas no mapa
            if(file_exists($imgmapasetor) || file_exists($imgcharttemposetor) || file_exists($imgpartsetor) ){
            $currentSlide = $objPHPPresentation->createSlide();
            if(file_exists($imgmapasetor)){
                $currentSlide->addShape($this->generateImgGeral($imgmapasetor, 20, 80, 660));
            }
            if(file_exists($imgpartsetor)){
                $currentSlide->addShape($this->generateImgGeral($imgpartsetor, 700, 80, 230));
            }
            if(file_exists($imgcharttemposetor) ){
                $currentSlide->addShape($this->generateImgGeral($imgcharttemposetor, 700, 400, 230));
            }
            $this->generateTextBoxTitle($currentSlide, $setor->descricao);
            $this->generateTextBoxFooter($currentSlide, $data);

            }
        }
    }

    public function generateSlideParticipacaoGeral($objPHPPresentation, $data, $directory){
        $currentSlide = $objPHPPresentation->createSlide();

        $imgtblpartgeral = Storage::disk('local')->path($directory."/999999_divtblparticip_999999.png");

        $this->generateTextBoxTitle($currentSlide, 'Participação de Mercado Total');
        $this->generateTextBoxFooter($currentSlide, $data);
        $currentSlide->addShape($this->generateImgGeral($imgtblpartgeral, 480, 150, 400));
        $logo = $currentSlide->createDrawingShape();
        $logo->setPath(public_path('img/imagem_participacao.png'))
            ->setOffsetX(0)
            ->setOffsetY(170)
            ->setWidth(400)
            ->setResizeProportional(true);
    }

    public function generateSlideFinal($objPHPPresentation, $data){
        $currentSlide = $objPHPPresentation->createSlide();

        $img = $currentSlide->createDrawingShape();
        $img->setPath(public_path('img/imagem_venda.png'))
            ->setOffsetX(0)
            ->setOffsetY(0);

        $shape = $currentSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(900)
                ->setOffsetX(25)
                ->setOffsetY(25);


        $textRun2 = $shape->createTextRun('Vendas de '.getDescricaoMes($data['mes'])."/".$data['ano']);
        
        $textRun2->getFont()
            ->setBold(true)
            ->setSize(40)
            ->setName('Arial');

        $logo = $currentSlide->createDrawingShape();
        $logo->setPath(public_path('img/logo_dubena.png'))
            ->setOffsetX(200)
            ->setOffsetY(530)
            ->setWidth(500)
            ->setResizeProportional(true);
    }



    public function generateTextBoxTitle($currentSlide, $titulo){
        $shape = $currentSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(900)
                ->setOffsetX(25)
                ->setOffsetY(25);

        $textRun = $shape->createTextRun($titulo);

        $textRun->getFont()
            ->setBold(true)
            ->setSize(25)
            ->setName('Arial');
    }

    public function generateTextBoxFooter($currentSlide, $data){
        $shape = $currentSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(900)
                ->setOffsetX(25)
                ->setOffsetY(670);

        $textRun = $shape->createTextRun('Apresentação de Vendas | ');
        $textRun->getFont()
            ->setBold(false)
            ->setSize(16)
            ->setName('Arial');

        $textRun2 = $shape->createTextRun(getDescricaoMes($data['mes'])."/".$data['ano']);
        $textRun2->getFont()
            ->setBold(true)
            ->setSize(16)
            ->setName('Arial');


        $logo = $currentSlide->createDrawingShape();
        $logo->setPath(public_path('img/logo_dubena.png'))
            ->setOffsetX(830)
            ->setOffsetY(670)
            ->setWidth(115)
            ->setResizeProportional(true);            

        $currentSlide->createLineShape(5, 665, 955, 665);
    }


    public function generateImgGeral($imgurl, $offsetX = 20, $offsetY = 25, $width = 900){
        $shape = new File();
        $shape->setPath($imgurl);
        $shape->setOffsetX($offsetX)
                ->setOffsetY($offsetY)
                ->setWidth($width)
                ->setResizeProportional(true);

        return $shape;
    }

    public function uploadImagem(Request $request)
    {
        // Verifica se o arquivo foi enviado corretamente
        if ($request->hasFile('imagem')) {
            $data = $request->all();
            // 2. Obtém o arquivo da requisição
            $file = $request->file('imagem');

            // 3. Define o nome do arquivo
            // O Laravel pode gerar um nome único para você
            $fileName = $data['setor_id'].'_'.$data['input_id'] . '.' . $file->getClientOriginalExtension();
            $directory = 'presentation/'.$data['ano'].str_pad($data['mes'], 2, '0', STR_PAD_LEFT); // Nome da pasta de destino

            try {
                if (!Storage::disk('local')->exists($directory)) {
                    Storage::disk('local')->makeDirectory($directory);
                }
                // 4. Salva o arquivo no disco (Storage)
                // O método `putFileAs` é seguro e simples

                $path = Storage::disk('local')->putFileAs(
                    $directory, // Onde o arquivo será salvo dentro de 'storage/app/public'
                    $file,
                    $fileName
                );

                // 5. Retorna uma resposta de sucesso
                return responseSuccess([
                    'success' => true,
                    'message' => 'Imagem salva com sucesso!',
                    'path' => $path, // O caminho do arquivo salvo
                ]);

            } catch (\Exception $e) {
                // Em caso de erro
                return responseError([
                    'success' => false,
                    'message' => 'Erro ao salvar a imagem: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Se o arquivo não foi encontrado na requisição
        return response()->json([
            'success' => false,
            'message' => 'Nenhum arquivo de imagem foi enviado.',
        ], 400);
    }
}