<?php

namespace App\Console\Commands;

use App\Configuracoesgerais;
use Config;
use App\Services\CarbonCustom;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SendRememberMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remembermail:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia emails para a equipe de suporte configuradas';

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
        if (! $configG->remembermails) {
            $this->info("Nenhum email configurado");
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

            $data = \DB::table("produtoleiimpostos")
                ->select("versao", "fim")
                ->whereBetween("fim", [CarbonCustom::now()->subMonth()->toDateString(), CarbonCustom::now()->addMonth()->toDateString()])
                ->orderBy("fim")
                ->groupBy("fim", "versao")->get();

            $count = $data->count();
            if (! $count) {
                $this->info("Nenhum lembrete");
                return false;
            }
            $data = $data->each(function ($el) {
                $el->fim = requestDataOracleSemHora($el->fim);
            });
            $content = "Estamos lhe enviando este e-mail para lembra-lo que ";
            if ($count > 1) {
                $content .= "as versões [" .  $data->pluck("versao")->implode(", ") . "] " .
                    "da Lei de Olho no Imposto terminam em [" . $data->pluck("fim")->implode(", ") . "]";
            } else {
                $content .= "a versão " .  $data->first()->versao . " da Lei de Olho no Imposto terminam em " . $data->first()->fim;
            }
            $send = [
                "subject"   => $configG->emailassunto,
                "content"   => $content,
                "to"        => explode(";", $configG->remembermails),
            ];
            sendMail($send);
            $this->info("E-mail(s) enviado(s) com sucesso");
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
}
