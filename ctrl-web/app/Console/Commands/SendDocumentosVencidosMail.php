<?php

namespace App\Console\Commands;

use App\Configuracoesgerais;
use Config;
use App\Services\CarbonCustom;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendDocumentosVencidosMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documentosvencidosmail:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia emails para os responsáveis de documentos cuja versão esteja dentro dos alertas';

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
        $configG = Configuracoesgerais::first();
        if (! $configG) {
            $this->info("Configurações gerais não definidas");
            return false;
        }
        try {
            $config = $this->setConfig($configG);

            if (is_bool($config)) {
                $msg = 'Por favor, faça o cadastro das informações do e-mail nas Configurações Gerais.';
                $this->info($msg);
                return false;

            } else if (is_string($config)) {
                $msg = 'Porta incorreta, as portas suportadas são: 465 e 587';
                $this->info($msg);
                return false;
            }

            Config::set('mail', $config);

            $data = $this->getDocumentosVencidos();

            $count = count($data);
            if (! $count) {
                $this->info("Nenhum lembrete");
                return false;
            }
            foreach($data as $colaborador_id=>$content){
                $send = [
                    "subject"   => "Documentos vencidos/próximos ao vencimento",
                    "content"   => $content,
                    "to"        => $content["email"],
                ];
                $this->sendMail($send);
                $this->info("E-mail para ".$content["nome"]." (". $content["email"] .") enviado com sucesso");
            }
            return true;
        } catch (\Exception $e) {
            $this->info($e->getMessage());
        }
        return true;
    }

    public function info($string, $verbosity = null)
    {
        parent::info(CarbonCustom::now()->toDateTimeString() . " | " . $string, $verbosity);
    }

    /**
     * @param Configuracoesgerais $config
     * @return array|bool|object|string
     * @throws \Exception
     */
    private function setConfig(Configuracoesgerais $config)
    {
        $request = new Request();
        $request->replace(["configGeneral" => $config]);
        return setMailConfig($request);
    }

    private function getDocumentosVencidos(){
        $query = 
        "  SELECT qry.* FROM ( " .
        "  SELECT " .
        "  d.DOCUMENTOTIPO_ID, t.DESCRICAO AS tipodescricao,d.DESCRICAO AS documentodescricao, v.DATAVENCIMENTO, v.NUMEROVERSAO, v.id, " .
        "  (datavencimento)::date - current_date AS qtdiasvencer, t.diasalerta, " .
        "  c.nome, c.email, d.colaborador_id " .
        "  FROM DOCUMENTOS d  " .
        "  INNER JOIN DOCUMENTOVERSAOS v ON v.DOCUMENTO_ID = d.ID  " .
        "  INNER JOIN DOCUMENTOTIPOS t ON t.id = d.DOCUMENTOTIPO_ID  " .
        "  INNER JOIN COLABORADORS c ON c.ID = d.COLABORADOR_ID " .
        "  WHERE v.ATIVO = 1 " .
        "  ) qry WHERE QTDIASVENCER <=diasalerta  " .
        "  ORDER BY QTDIASVENCER ";
        $data = collect(DB::select($query));
        $result = [];
        foreach($data as $doc){
            if(!$doc->email){
                $this->info("Documento ".$doc->tipodescricao." ".$doc->documentodescricao." - Colaborador: ".$doc->nome." não tem email cadastrado.");
                continue;
            }
            if(!isset($result[$doc->colaborador_id])){
                $result[$doc->colaborador_id] = ['colaborador_id'=>$doc->colaborador_id, 'nome'=>$doc->nome, 'email'=>$doc->email, 'documentos'=>[]];
            }
            $doc->datavencimento = requestDataOracleSemHora($doc->datavencimento);
            $doc->msg = "A versão <strong>" .$doc->numeroversao. "</strong> do documento <strong>".$doc->documentodescricao."</strong> em <strong>".$doc->tipodescricao.($doc->qtdiasvencer < 0?"</strong> está vencido a ":"</strong> irá vencer em ").abs($doc->qtdiasvencer)." dia(s).";
            array_push($result[$doc->colaborador_id]['documentos'], $doc);
        }
        return $result;
    }

    private function sendMail(array $data)
    {
        Mail::send('layouts.maildocumentosvencidos', array('content' => $data['content']), function ($mail) use ($data) {
            $mail->to($data['to'])->subject($data['subject']);
            if (isset($data['files'])) {
                foreach ($data['files'] as $file) {
                    $mail->attach($file);
                }
            }
        });
    }
}
