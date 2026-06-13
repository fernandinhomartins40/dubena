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

class FechamentomensalgestaoController extends Controller
{
    public function index()
    {
        $years = $this->getTime('ano');
		$meses = $this->getTime('mes');

        return view("financeiro.fechamentomensal.index")->with(compact('years', 'meses'));
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
            $dataDreMensal = $this->getDataDreMensal('SCREEN');
            $dataBalanco = $this->getDataBalanco('SCREEN');
            return responseSuccess(["dataDreMensal" => $dataDreMensal,
                                    "dataBalanco" => $dataBalanco,
                                    ]);
        } catch(Exception $e){
            return responseError(getErrorsException($e));
        }
    }

    public function sendMail()
    {
        try {
            $tipoExport = \Input::get("tipoExport", "");
            $mes = \Input::get("mes", "");
            $ano = \Input::get("ano", "");
            $nomearquivoDRE = 'DRE_'.Session::get('empresa_padrao')->id."_".$ano.str_pad($mes, 2, "0", STR_PAD_LEFT);
            $nomearquivoBalanco = 'Balanco_'.Session::get('empresa_padrao')->id."_".$ano.str_pad($mes, 2, "0", STR_PAD_LEFT);
            $config = Empresaconfig::where('empresa_id', Session::get('empresa_padrao')->id)->first();
            throwIf(!$config, 'Configuração de empresa não encontrada');
            $emails = explode(',', $config->emaildiretoria);
            throwIf(count($emails)==0, 'E-mails da diretoria não estão configurados');
            $dataDreMensal = $this->getDataDreMensal($tipoExport);
            $files = [];
            $arquivoDRE = $this->geraArquivoDRE($tipoExport, $dataDreMensal, $nomearquivoDRE);
            if($arquivoDRE){
                array_push($files, (object) ['name'=>$nomearquivoDRE.".".$tipoExport, 'content'=>file_get_contents($arquivoDRE)]);
            }
            $dataBalanco = $this->getDataBalanco($tipoExport);
            $arquivoBalanco = $this->geraArquivoBalanco($tipoExport, $dataBalanco, $nomearquivoBalanco);
            if($arquivoBalanco){
                array_push($files, (object) ['name'=>$nomearquivoBalanco.".".$tipoExport, 'content'=>file_get_contents($arquivoBalanco)]);
            }
            throwIf(count($files)==0, 'Não foi possível gerar os arquivos.');
            foreach($emails as $email){
                $this->sendMailApi($files, $mes, $ano, $email);
            }
            return responseSuccess();
            return responseSuccess();
        } catch(Exception $e){
            return responseError(getErrorsException($e));
        }
    }

    public function sendMailApi($files, $mes, $ano, $email){
        $config = setMailConfigApi();

        $payload = [
                    [
                        "name" => "server",
                        "contents" => $config["host"],
                    ],
                    [
                        "name" => "port",
                        "contents" =>  $config["port"],
                    ],
                    [
                        "name" => "username",
                        "contents" => $config["username"],
                    ],
                    [
                        "name" => "password",
                        "contents" => $config["password"],
                    ],
                    [
                        "name" => "from_name",
                        "contents" => isset($data["sender_name"])?$data["sender_name"]:$config["from"]["name"],
                    ],
                    [
                        "name" => "from_address",
                        "contents" => $config["from"]["address"],
                    ],
                    [
                        "name" => "to",
                        "contents" => trim($email),
                    ],
                    [
                        "name" => "subject",
                        "contents" =>  "Fechamento do mês " . str_pad($mes, 2, "0", STR_PAD_LEFT)."/".$ano." da empresa ".Session::get('empresa_padrao')->razao_social,
                    ],
                    [
                        "name" => "content",
                        "contents" => 'Olá! Seguem em anexo os arquivos referentes ao fechamento gerencial do mês '.str_pad($mes, 2, "0", STR_PAD_LEFT)."/".$ano." da empresa ".Session::get('empresa_padrao')->razao_social,
                    ],
                ];

        foreach ($files as $file) {
            array_push($payload,  [
                "name" => "attachments",
                "contents" => $file->content,
                "filename" => $file->name
            ]);
        }

        $httpClient = new Client();

        $resp = $httpClient->request("POST", env("MAIL_SENDER_URL"), [
            "header" => [
                "Content-Type" => "multipart/form-data",
                "Accept" => "application/json",
            ],
            "multipart" => $payload,
        ]);
    }


    public function geraArquivoDRE($tipoExport, $dataDreMensal, $nomearquivo)
    {
        Excel::create($nomearquivo, function($excel) use ($dataDreMensal, $tipoExport) {
            $excel->sheet('DRE', function($sheet) use($dataDreMensal, $tipoExport) {
                $sheet->setAutoSize(false);
                $sheet->setOrientation('portrait');
                $sheet->setPageMargin(array(
                    0.1, 0.1, 0.1, 0.1
                ));
                $dados = $dataDreMensal->data;
                $sheet->fromArray($dados, null, 'A1', true, false);
                $fulldata = $dataDreMensal->fulldata;
                for($i=1;$i<=5;$i++){
                    $sheet->mergeCells('B'.($i).':D'.($i));
                }
                for($i=count($fulldata)-2;$i<=count($fulldata);$i++){
                    $sheet->mergeCells('B'.($i).':D'.($i));
                }
                $this->setStyle($sheet, 'B2', false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 8, false, false, false);
                $this->setStyle($sheet, 'B3', false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_CENTER, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 12, true, false, false);
                $this->setStyle($sheet, 'B5', false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_CENTER, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, true, false, false);
                for($i=5;$i<count($fulldata)-3;$i++){
                    $row = $fulldata[$i];
                    if($row->cabecalho == 2){
                            $sheet->mergeCells('B'.($i+1).':D'.($i+1));
                            $this->setStyle($sheet, 'B'.($i+1), false, 'thin', false, \PHPExcel_Style_Alignment::VERTICAL_BOTTOM, 'Helvetica', 11, true, false, false);
                            if($row->plano != ''){
                            $sheet->setSize('B'.($i+1), 20, 25);
                            } elseif($tipoExport=='pdf') {
                            $sheet->setSize('B'.($i+1), 1, 1);
                            }
                    } elseif($row->cabecalho == 1){
                        $this->setStyle($sheet, 'B'.($i+1), false, 'thin', false, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, true, false, 'C0C0C0');
                        $this->setStyle($sheet, 'C'.($i+1).':D'.($i+1), false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, true, false, 'C0C0C0');
                    } else {
                        $this->setStyle($sheet, 'B'.($i+1).':B'.($i+1), false, 'thin', false, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, false, false, false);
                        $this->setStyle($sheet, 'C'.($i+1).':D'.($i+1), false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, false, false, false);
                    }
                }
                $sheet->setColumnFormat(array(
                    'C8:D'.(count($fulldata)-3) => '#,##0.00_ ;-#,##0.00 ',
                ));
                $sheet->setWidth(array(
                    'A' => $this->colWidth(5), 'B' => $this->colWidth(50), 'C' => $this->colWidth(12), 'D' => $this->colWidth(10), 'E' => $this->colWidth(5)
                ));
                if($tipoExport=='xlsx'){
                    $image = imagecreatefromstring(base64_decode(Session::get('empresa_padrao')->logo));
                    imagesavealpha($image, true);
                    $drawing = new PHPExcel_Worksheet_MemoryDrawing();
                    $drawing->setName('teste');
                    $drawing->setImageResource($image);
                    $drawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_PNG);
                    $drawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                    $drawing->setWidthAndHeight(150, 74);
                    $drawing->setResizeProportional(true);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                } else {
                    $sheet->cells('A'.(count($fulldata)+1).':E'.(count($fulldata)+1), function($cells) {
                        $cells->setBorder('thin', 'none', 'none', 'none');
                        $cells->setFontSize(9);
                    }); 
                }
            });
        })->save($tipoExport);
        $fullpath = storage_path('exports') . "/" . $nomearquivo. "." .$tipoExport;
        return file_exists($fullpath)?$fullpath:null;
    }

    public function geraArquivoBalanco($tipoExport, $dataBalanco, $nomearquivo)
    {
        Excel::create($nomearquivo, function($excel) use ($dataBalanco, $tipoExport) {
            $excel->sheet('Balanço', function($sheet) use($dataBalanco, $tipoExport) {
                $sheet->setAutoSize(false);
                $sheet->setOrientation('portrait');
                $sheet->setPageMargin(array(
                    0.1, 0.1, 0.1, 0.3
                ));
                $dados = $dataBalanco->data;
                $sheet->fromArray($dados, null, 'A1', true, false);
                $fulldata = $dataBalanco->fulldata;
                for($i=1;$i<=5;$i++){
                    $sheet->mergeCells('B'.($i).':E'.($i));
                }
                for($i=count($fulldata)-2;$i<=count($fulldata);$i++){
                    $sheet->mergeCells('B'.($i).':E'.($i));
                }
                $this->setStyle($sheet, 'B2', false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 8, false, false, false);
                $this->setStyle($sheet, 'B3', false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_CENTER, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 12, true, false, false);
                $this->setStyle($sheet, 'B5', false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_CENTER, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, true, false, false);
                for($i=5;$i<count($fulldata)-3;$i++){
                    $row = $fulldata[$i];
                    if($row->cabecalho == 2){
                            $sheet->mergeCells('B'.($i+1).':E'.($i+1));
                            $this->setStyle($sheet, 'B'.($i+1), false, 'thin', false, \PHPExcel_Style_Alignment::VERTICAL_BOTTOM, 'Helvetica', 11, true, false, false);
                            if($row->descricao != ''){
                            $sheet->setSize('B'.($i+1), 20, 25);
                            } elseif($tipoExport=='pdf') {
                            $sheet->setSize('B'.($i+1), 1, 1);
                            }
                    } elseif($row->cabecalho == 1){
                        $this->setStyle($sheet, 'B'.($i+1), false, 'thin', false, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, true, false, 'C0C0C0');
                        if($row->descricao == 'Conta'){
                            $this->setStyle($sheet, 'C'.($i+1), false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_LEFT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, true, false, 'C0C0C0');
                        } else {
                            $this->setStyle($sheet, 'C'.($i+1), false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, true, false, 'C0C0C0');
                        }
                        $this->setStyle($sheet, 'D'.($i+1).':E'.($i+1), false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, true, false, 'C0C0C0');
                    } else {
                        $this->setStyle($sheet, 'B'.($i+1).':B'.($i+1), false, 'thin', false, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, false, false, false);
                        if(is_numeric($row->descricao) || $row->descricao == 'Quantidade'){
                            $this->setStyle($sheet, 'C'.($i+1).':E'.($i+1), false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, false, false, false);
                        } else {
                            $this->setStyle($sheet, 'C'.($i+1), false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_LEFT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, false, false, false);
                            $this->setStyle($sheet, 'D'.($i+1).':E'.($i+1), false, 'thin', \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT, \PHPExcel_Style_Alignment::VERTICAL_CENTER, 'Helvetica', 10, false, false, false);
                        }
                    }
                }
                $sheet->setColumnFormat(array(
                    'C8:C'.(count($fulldata)-3) => '#,##0_ ;-#,##0 ',
                    'D8:E'.(count($fulldata)-3) => '#,##0.00_ ;-#,##0.00 ',
                ));
                $sheet->setWidth(array(
                    'A' => $this->colWidth(5), 'B' => $this->colWidth(30), 'C' => $this->colWidth(20), 'D' => $this->colWidth(14), 'E' => $this->colWidth(14), 'F' => $this->colWidth(5)
                ));
                if($tipoExport=='xlsx'){
                    $image = imagecreatefromstring(base64_decode(Session::get('empresa_padrao')->logo));
                    imagesavealpha($image, true);
                    $drawing = new PHPExcel_Worksheet_MemoryDrawing();
                    $drawing->setName('teste');
                    $drawing->setImageResource($image);
                    $drawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_PNG);
                    $drawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                    $drawing->setWidthAndHeight(150, 74);
                    $drawing->setResizeProportional(true);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                } else {
                    $sheet->cells('A'.(count($fulldata)+1).':F'.(count($fulldata)+1), function($cells) {
                        $cells->setBorder('thin', 'none', 'none', 'none');
                        $cells->setFontSize(9);
                    }); 
                }
            });
        })->save($tipoExport);
        $fullpath = storage_path('exports') . "/" . $nomearquivo. "." .$tipoExport;
        return file_exists($fullpath)?$fullpath:null;
    }    

    public function colWidth($num){
        return $num+0.71;
    }

    public function getDataDreMensal($tipo = 'SCREEN'){
        $mes = \Input::get("mes", "");
        $ano = \Input::get("ano", "");
        $dataReferencia = Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, "0", STR_PAD_LEFT)."01")->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');
        $dataTabela = [];
        $dataTabela =  FechamentomensalDreRepository::getDataDreReceita($dataReferencia);
        $dataTabela = array_merge($dataTabela, FechamentomensalDreRepository::getDataDreCustosVariaveis($dataReferencia));
        $dataTabela = array_merge($dataTabela, FechamentomensalDreRepository::getDataDreCustosFixos($dataReferencia));
        $data = FechamentomensalDreRepository::getDataDreResultado($dataTabela, $dataReferencia);
        $dataTabela = array_merge($dataTabela, $data['result']);
        $resultado = $data['resultado'];
        $dataTabela = array_merge($dataTabela, FechamentomensalDreRepository::getDataDreInvestimentos($dataReferencia, $resultado));
        if($tipo!='SCREEN'){
            $dataTabela = $this->formatDataDreFile($dataTabela, $ano, $mes, $tipo);
        }

        return $dataTabela;
    }

    public function getDataBalanco($tipo = 'SCREEN'){
        $mes = \Input::get("mes", "");
        $ano = \Input::get("ano", "");
        $dataReferencia = Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, "0", STR_PAD_LEFT)."01")->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');
        $dataTabela = [];
        $total = 0;
        $data = FechamentomensalBalancoRepository::getDataBalancoContas($dataReferencia);
        $dataTabela =  $data->result;
        $total += $data->total;
        $data = FechamentomensalBalancoRepository::getDataBalancoEstoque($dataReferencia);
        $dataTabela = array_merge($dataTabela, $data->result);
        $total += $data->total;
        $data = FechamentomensalBalancoRepository::getDataBalancoContasReceber($dataReferencia);
        $dataTabela = array_merge($dataTabela, $data->result);
        $total += $data->total;
        $data = FechamentomensalBalancoRepository::getDataBalancoComodatoCliente($dataReferencia);
        $dataTabela = array_merge($dataTabela, $data->result);
        $total += $data->total;
        $data = FechamentomensalBalancoRepository::getDataBalancoComodatoDistribuidora($dataReferencia);
        $dataTabela = array_merge($dataTabela, $data->result);
        $total -= $data->total;
        $data = FechamentomensalBalancoRepository::getDataBalancoContasPagar($dataReferencia);
        $dataTabela = array_merge($dataTabela, $data->result);
        $total -= $data->total;
        $dataTabela = array_merge($dataTabela, FechamentomensalBalancoRepository::getDataBalancoPatrimonio($dataReferencia, $total));
        if($tipo!='SCREEN'){
            $dataTabela = $this->formatDataBalancoFile($dataTabela, $ano, $mes, $tipo);
        }
        return $dataTabela;
    }


    public function formatDataDreFile($dataTabela, $ano, $mes, $tipo){
        $header = [];
        array_push($header, [' ', '', '', '', ' ']);
        array_push($header, [' ', 'Emitido em '. Carbon::now()->format('d/m/Y H:i:s'), '', '', ' ']);
        array_push($header, [' ', 'Demonstrativo do Resultado do Exercício', '', '', ' ']);
        array_push($header, [' ', '', '', '', ' ']);
        array_push($header, [' ', 'Mês: ' . Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, "0", STR_PAD_LEFT)."01")->endOfMonth()->endOfDay()->format('m/Y')."  Empresa: ".Session::get('empresa_padrao')->razao_social, '', '', ' ']);

        $data = array_merge($header, []);
        foreach($dataTabela as $row){
            array_push($data, [' ', $row->plano, $tipo=='pdf' && is_numeric($row->valor)?number_format($row->valor, 2, ',', '.'):$row->valor, $tipo=='pdf' && is_numeric($row->percentual)?number_format($row->percentual, 2, ',', '.'):$row->percentual, ' ']);
        }
        $dataTabela = array_merge($header, $dataTabela);
        for($i=0; $i<3; $i++){
            array_push($dataTabela, [' ', '', '', '', '']);
            array_push($data, [' ', '', '', '', '']);
        }
        return (object) ['fulldata'=>$dataTabela, 'data'=>$data];

    }

    public function formatDataBalancoFile($dataTabela, $ano, $mes, $tipo){
        $header = [];
        array_push($header, [' ', '', '', '', ' ', ' ']);
        array_push($header, [' ', 'Emitido em '. Carbon::now()->format('d/m/Y H:i:s'), '', '', ' ', ' ']);
        array_push($header, [' ', 'Balanço Patrimonial', '', '', ' ', ' ']);
        array_push($header, [' ', '', '', '', ' ',' ']);
        array_push($header, [' ', 'Mês: ' . Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, "0", STR_PAD_LEFT)."01")->endOfMonth()->endOfDay()->format('m/Y')."  Empresa: ".Session::get('empresa_padrao')->razao_social, '', '', ' ', ' ']);

        $data = array_merge($header, []);
        foreach($dataTabela as $row){
            array_push($data, [' ', $row->tipodescricao, $tipo=='pdf' && is_numeric($row->descricao)?number_format($row->descricao, 0, ',', '.'):$row->descricao, $tipo=='pdf' && is_numeric($row->custo)?number_format($row->custo, 2, ',', '.'):$row->custo, $tipo=='pdf' && is_numeric($row->valor)?number_format($row->valor, 2, ',', '.'):$row->valor, ' ']);
        }
        $dataTabela = array_merge($header, $dataTabela);
        for($i=0; $i<3; $i++){
            array_push($dataTabela, [' ', '', '', '', '', '']);
            array_push($data, [' ', '', '', '', '', '']);
        }
        return (object) ['fulldata'=>$dataTabela, 'data'=>$data];

    }

    public function getDetalhes()
    {
        try {
            $mes = \Input::get("mes", "");
            $ano = \Input::get("ano", "");
            $plano_id = \Input::get("plano_id", "");
            $juros = \Input::get("juros", "");
            $dataReferencia = Carbon::createFromFormat('Ymd', $ano.str_pad($mes, 2, "0", STR_PAD_LEFT)."01")->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');
            $data = [];
            if($plano_id == -1){
                $data = FechamentomensalDreRepository::getDataDetalhesFaturamento($dataReferencia, 2);
            } elseif($plano_id == -2){
                $data = FechamentomensalDreRepository::getDataDetalhesCustoVariavel($dataReferencia);
            } elseif($plano_id > 0){
                $data = FechamentomensalDreRepository::getDataDetalhesCustoFixo($dataReferencia, $plano_id, $juros);
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
            $result = FechamentomensalDreRepository::getDataCentroCustos($plano_id, $dataReferencia);
            return responseSuccess($result);
        } catch(Exception $e){
            return responseError(getErrorsException($e));
        }
    }

    public function setStyle($sheet, $cell, $border, $border_size, $alignment_h, $alignment_v, $font_family, $font_size, $font_bold, $font_color, $background){
        $styleArray = Array();
        if($border){
            $styleArray['borders'] = array(
                'allborders' => array(
                'style' => $border_size=='medium'?\PHPExcel_Style_Border::BORDER_MEDIUM:\PHPExcel_Style_Border::BORDER_THIN
                )
            );
        }
        if($font_family){
            $styleArray['font'] = array(
                'bold'  => $font_bold,
                'size'  => $font_size,
                'name'  => $font_family
            );
            if($font_color){
                $styleArray['font']['color'] = ['rgb'=>$font_color];
            }
        }
        if($alignment_h || $alignment_v){
            $alignment = Array();
            if($alignment_h){
                $alignment['horizontal'] = $alignment_h;
            }
            if($alignment_v){
                $alignment['vertical'] = $alignment_v;
            }
            $styleArray['alignment'] = $alignment;
        }
        if($background){
            $sheet->cells($cell, function($cells) use ($background) {
                $cells->setBackground($background);
            });
        }
        $sheet->getStyle($cell)->applyFromArray($styleArray);

    }

    public function export(Request $request)
    {
         try {
            $tipoExport = \Input::get("tipoExport", "");
            $tipoDocumento = \Input::get("tipoDocumento", "");
            $mes = \Input::get("mes", "");
            $ano = \Input::get("ano", "");
            if($tipoDocumento=='dre'){
                $nomearquivo = 'DRE_'.Session::get('empresa_padrao')->id."_".$ano.str_pad($mes, 2, "0", STR_PAD_LEFT);
                $dataDreMensal = $this->getDataDreMensal($tipoExport);
                $arquivoDRE = $this->geraArquivoDRE($tipoExport, $dataDreMensal, $nomearquivo);
                return responseSuccess(['fileUrl' => route('fechamentomensalgestao.download', ['fileName' => $nomearquivo.".".$tipoExport])]);
            } elseif($tipoDocumento=='balanco'){
                $nomearquivo = 'Balanco_'.Session::get('empresa_padrao')->id."_".$ano.str_pad($mes, 2, "0", STR_PAD_LEFT);
                $dataBalanco = $this->getDataBalanco($tipoExport);
                $arquivoBalanco = $this->geraArquivoBalanco($tipoExport, $dataBalanco, $nomearquivo);
                return responseSuccess(['fileUrl' => route('fechamentomensalgestao.download', ['fileName' => $nomearquivo.".".$tipoExport])]);
            }
        } catch(Exception $e){
            return getErrorsException($e);
        }

    }

     public function download($fileName)
    {
         $filePath = storage_path('exports/' . $fileName);

        if (file_exists($filePath)) {
            return response()->download($filePath, $fileName);
        } else {
            abort(500, 'File not found.');
        }

    }
}
