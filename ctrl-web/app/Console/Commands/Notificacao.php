<?php

namespace App\Console\Commands;

use DB;
use Carbon\Carbon;
use App\Notificacoes;
use App\Notificacaouser;
use Illuminate\Console\Command;

class Notificacao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:alertas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get all notifications;';

    /**
     * Variable for errors
     *
     * @var array
     */
    protected $errors = [];

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
     * Add an error to the array.
     *
     * @param string $error
     * @return void
     */
    protected function addError($error)
    {
        array_push($this->errors, $error);
    }

    /**
     * Return errors added.
     *
     * @return string $error
     */
    protected function getErrors()
    {
        return implode(', ', $this->errors);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $query = $this->getQuery("notificacoes");
        $notificacion = collect([]);
        $date = new \DateTime();
        $date = $date->format('d/m/Y H:i:s');
        DB::beginTransaction();
        try {
            $dbnot = DB::select($query);
            $notificacao = new Notificacoes();
            foreach ($dbnot as $not) {
                $noti = new Notificacoes();
                $noti->grupo_id = $not->grupo_id;
                $noti->empresa_id = $not->empresa_id;
                $noti->descricao = $not->descricao;
                $noti->identificador = $not->identificador;
                $noti->tela = $not->tela;
                $noti->dangerlevel = 1;
                $noti->created_at = Carbon::now()->format('Y-m-d H:m:s');
                $noti->updated_at = Carbon::now()->format('Y-m-d H:m:s');
                $notificacion->push($noti);
            }
            $inserted = $notificacao->insert($notificacion->toArray());
            if ($inserted) {
                $countuser = $this->insertAlertasUsers();
            }
            if (isset($countuser) && is_bool($countuser)) {
                throw new \Exception($this->getErrors());
            }
        } catch(\Exception $e) {
            DB::rollback();
            $this->comment("Error: " . $e->getMessage() . " line: " . $e->getLine() . " on: " . $e->getFile() . " date: $date");
            return false;
        }
        DB::commit();
        $count = count($dbnot);
        $this->info("Inserted $count record(s) on the Table: Notificacoes, and $countuser on the table: Notificacoesuser. Date: $date");
    }


    private function insertAlertasUsers()
    {
        $countuser = 0;
        $usuarios = collect([]);
        $notifyusers = new Notificacaouser();
        $queryUser = $notifyusers->getUsersQuery();
        $dbusers = collect(DB::select($queryUser));
        DB::beginTransaction();
        try {
            foreach ($dbusers as $user) {
                $notify = new Notificacaouser();
                $notify->user_id = $user->user_id;
                $notify->empresa_id = $user->empresa_id;
                $notify->notificacao_id = $user->notificacao_id;
                $notify->tela = $user->tela;
                $notify->status = $user->status;
                $notify->created_at = Carbon::now()->format('Y-m-d H:m:s');
                $notify->updated_at = Carbon::now()->format('Y-m-d H:m:s');
                $usuarios->push($notify);
                $countuser++;
            }
            $notifyusers->insert($usuarios->toArray());
        } catch (\Exception $e) {
            DB::rollback();
            $error = getErrorsException($e);
            $this->addError($error);
            return false;
        }
        DB::commit();
        return $countuser;
    }

    /**
     * Return queries
     *
     * @param string $which
     * @return string $query
     */
    private function getQuery()
    {
        $query = "select grupo_id, empresa as empresa_id, descricao, identificador, tela, 'N' as status ".
        "from( ".
            "select grupo_id, empresa_id as empresa, ('O veículo ' || placa || ' está com o óleo para vencer!') as descricao, ".
            "oleo_id as identificador, 'oleo' as tela ".
            "from( ".
                "select empresas.grupo_id, empresas.id as empresa_id, veiculos.placa, veiculos.kmatual, oleo.oleoproximatroca, oleo.alertaantes, ".
                "rank () over (partition by veiculos.id order by oleo.created_at desc) as id_rank, oleo.kmalertaantes, oleo.id as oleo_id ".
                "from veiculotrocaoleos oleo ".
                "inner join empresas on oleo.empresa_id = empresas.id ".
                "inner join veiculos on oleo.veiculo_id = veiculos.id ".
            ") oleos ".
            "where id_rank = 1 and alertaantes = 1 and ".
            "kmatual between oleoproximatroca - kmalertaantes and oleoproximatroca and ".
            "oleo_id not in (select identificador from notificacoes where tela = 'oleo' and empresa_id = oleos.empresa_id) ".

            "union all ".

            "select grupo_id, empresa_id as empresa, ('O veículo ' || placa || ' está com a vida útil de ' || quantidade || ' pneu(s) ' || 'esgotando!') as descricao, ".
            "pneu_id as identificador, 'pneu' as tela ".
            "from( ".
                "select empresas.grupo_id, empresas.id as empresa_id, veiculos.placa, veiculos.kmatual, pneu.vidautilkm as vida_util, pneu.km as troca_km, pneu.id as pneu_id, ".
                "rank () over (partition by veiculos.id order by pneu.created_at desc) as id_rank, pneu.alertaantes as alerta, pneu.kmalertaantes alerta_km, pneu.quantidade ".
                "from veiculopneus pneu ".
                "inner join veiculos on pneu.veiculo_id = veiculos.id ".
                "inner join empresas on pneu.empresa_id = empresas.id ".
            ") pneus ".
            "where id_rank = 1 and alerta = 1 and ".
            "kmatual between ((vida_util + troca_km) - alerta_km) and vida_util + troca_km and ".
            "pneu_id not in (select identificador from notificacoes where tela = 'pneu' and empresa_id = pneus.empresa_id) ".

            "union all ".

            "select grupo_id, empresa_id as empresa, ('O documento: ' || tipo || ' do veículo ' || placa || ' vence em: ' || to_char(vencimento,'dd/mm/yyyy')) as descricao, ".
            "documento_id as identificador, 'veiculo' as tela ".
            "from( ".
                "select empresas.grupo_id, empresas.id as empresa_id, doc.id as documento_id, ".
                "veiculos.placa, doc.vencimento, doc.descricao as doc, tipo.descricao as tipo, doc.alerta ".
                "from veiculos ".
                "inner join empresas on veiculos.empresa_id = empresas.id ".
                "inner join veiculodocumentos doc on doc.veiculo_id = veiculos.id ".
                "inner join tipodocumentos tipo on doc.tipodocumento_id = tipo.id ".
                "left  join notificacoes noti on noti.empresa_id = veiculos.empresa_id and noti.empresa_id = empresas.id and ".
                    "veiculos.empresa_id = noti.empresa_id and noti.identificador = doc.id and ".
                    "noti.tela = 'veiculo' ".
                "where alerta = 1 and (doc.vencimento - veiculos.alertasdiasantes) between trunc(sysdate) and ".
                "to_date(to_char(sysdate,'yyyy-mm-dd') || '23:59:59', 'yyyy-mm-dd hh24:mi:ss') and noti.id is null ".
            ") documentos ".

            "union all ".

            "select grupo_id, empresa_id, ('Checklist: ' || pesquisa_id || ' ' || pergunta || ' vence: ' || to_char(resposta,'dd/mm/yyyy')) as descricao, ".
            "resposta_id as identificador, 'checklist' as tela ".
            "from( ".
                "select empresas.grupo_id, empresas.id as empresa_id, pesquisas.id as pesquisa_id, ".
                "presp.id as resposta_id, perguntas.descricao as pergunta, to_date(presp.resposta,'yyyy-mm-dd') as resposta ".
                "from checklistforms form ".
                "inner join checklists chec on chec.checklistform_id = form.id ".
                "inner join checklistperguntas perguntas on perguntas.checklist_id = chec.id ".
                "inner join checklistrespostas respostas on respostas.checklistpergunta_id = perguntas.id ".
                "inner join checklistpesquisas pesquisas on pesquisas.checklistform_id = form.id ".
                "left join checklistpesquisarespostas presp on presp.checklistpesquisa_id = pesquisas.id and ".
                "presp.checklistpergunta_id = perguntas.id and presp.checklistresposta_id = respostas.id ".
                "inner join empresas on pesquisas.empresa_id = empresas.id and pesquisas.empresa_id = empresas.id ".
                "left join notificacoes noti on noti.empresa_id = pesquisas.empresa_id and noti.identificador = presp.id and ".
                    "noti.tela = 'checklist' and noti.empresa_id = empresas.id  ".
                "where respostas.alerta = 1 and presp.resposta is not null and ".
                "presp.resposta = to_char(sysdate,'yyyy-mm-dd') and noti.id is null ".
                "order by pesquisa_id ".
            ") checks ".

            "union all ".

            "select grupo_id, empresa_id, ('Exame ' || exame || ' do(a) colaborador(a) ' || nome || ' vence: ' || to_char(datavencimento,'dd/mm/yyyy')) as descricao, ".
            "exame_id as identificador, 'colaborador' as tela ".
            "from( ".
                "select empresas.grupo_id, empresas.id as empresa_id, colab.nome, tipo.descricao as exame, exame.id as exame_id, exame.datavencimento  ".
                "from colaboradors colab ".
                "inner join colaboradorexames exame on exame.colaborador_id = colab.id ".
                "inner join tipoexames tipo on exame.tipoexame_id = tipo.id ".
                "inner join empresas on colab.empresa_id = empresas.id ".
                "left  join notificacoes noti on noti.empresa_id = colab.empresa_id and noti.empresa_id = empresas.id and ".
                    "noti.identificador = exame.id and noti.tela = 'colaborador' ".
                "where exame.alerta = 1 and exame.datavencimento between trunc(sysdate) and ".
                "to_date(to_char(sysdate,'yyyy-mm-dd') || '23:59:59', 'yyyy-mm-dd hh24:mi:ss') and noti.id is null ".
            ") colaboradores ".
        ") alertas";

        return $query;
    }
}
