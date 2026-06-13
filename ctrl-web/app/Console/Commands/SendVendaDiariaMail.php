<?php

namespace App\Console\Commands;

use App\Configuracoesgerais;
use App\Empresaconfig;
use App\Estoquerequisicao;
use App\Estoquerequisicaoitem;
use App\Http\Controllers\ReportResumoVendasController;
use App\Produto;
use Config;
use App\Services\CarbonCustom;
use App\Setor;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendVendaDiariaMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vendadiaria:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia emails para a Diretoria/Comercial com a Venda diária dos setores';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dataReferencia = Carbon::now()->subDay()->startOfDay();
        $dias = explode('_', 'Domingo_Segunda-Feira_Terça-Feira_Quarta-Feira_Quinta-Feira_Sexta-Feira_Sábado');
        $empresaconfigs = Empresaconfig::all();
        foreach($empresaconfigs as $config){
            if($config->empresa->ativo==1){
                if($config->emailcomercial || $config->emaildiretoria){
                    $produto = Produto::where('empresa_id', $config->empresa_id)
                                      ->where('tipo_glp',3)
                                      ->where('pesoliquido', 13)->first();
                    if($produto){
                        $empresa = $config->empresa;
                        $cont = new ReportResumoVendasController();
                        $setor_id =  Setor::where(['empresa_id' => $config->empresa_id, 'ativo' => 1, 'estoqueproprio'=>1])->orderby('descricao')->pluck('id')->toArray();
                        array_push($setor_id, -1);
                        $vendas = $cont->getResumoVendas($dataReferencia, $produto->id, $produto->empresa_id, $setor_id);
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
                        $pdf = \App::make("dompdf.wrapper");
                        $pdf->loadView("reports.vendas.resumo.vendas_diasetor_pdf", compact("titulo", "filtro", "vendas", "totais", "empresa",  "seta_cima", "seta_baixo"));
                        $pdfContent = $pdf->output();
                        $files = [];
                        array_push($files, (object) ['name'=>"VendaDiaria_".$dataReferencia->format('d-m-Y').".pdf", 'content'=>$pdfContent]);
                        $emails = explode(',', $config->emaildiretoria);
                        $emails =  array_unique(array_merge($emails, explode(',', $config->emailcomercial)));
                        foreach($emails as $email){
                            if(trim($email)){
                                $this->sendMailApi($files, trim($email), $dataReferencia, $empresa);
                            }
                        }


                    }
                }
            }
        }
        return true;
       
    }

    public function info($string, $verbosity = null)
    {
        parent::info(CarbonCustom::now()->toDateTimeString() . " | " . $string, $verbosity);
    }

    public function sendMailApi($files, $email, $dataReferencia, $empresa){
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
                        "contents" =>  "Relatório de Venda Diária - ".$dataReferencia->format('d/m/Y'),
                    ],
                    [
                        "name" => "content",
                        "contents" => 'Olá! Segue em anexo o Relatório de Venda Diária referente ao dia '.$dataReferencia->format('d/m/Y').' da empresa '.$empresa->razao_social,
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
}
