<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Processors\financeiroProcessor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;

/**
 * F6 — BASELINE (caracterização) do financeiroProcessor ANTES de expô-lo na API.
 * Trava o comportamento de gravar um lançamento (financeiro + parcela + rateio),
 * SEM baixa. Não altera o motor.
 */
class FinanceiroProcessorBaselineTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;
    private $grupo;
    private $empresaId;
    private $clienteId;
    private $planoId;
    private $centroId;

    private function preparar()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        $this->admin = \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
        \Auth::login($this->admin);
        $this->grupo = optional($this->admin->empresa)->grupo_id ?? 1;
        $this->empresaId = $this->admin->empresa_id;
        Session::put('empresa_padrao', \App\Empresa::find($this->empresaId));

        $this->clienteId = \DB::table('clientes')->insertGetId([
            'grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'nome' => 'Cli Fin', 'numero' => '1',
            'cidade_id' => 1, 'observacoes' => '', 'conveniolimite' => 0, 'latitude' => 0, 'longitude' => 0,
            'locationtype' => 'APPROXIMATE', 'cliente' => 1, 'fornecedor' => 0, 'transportador' => 0,
            'nfemite' => 0, 'convenio' => 0, 'consumidor_final' => 0, 'simples' => 0, 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->planoId = \DB::table('planocontas')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'PC Teste', 'pagarreceber' => 'R', 'codigo' => '1', 'nivel' => 1, 'insumo_valor' => 0, 'provisao' => 0, 'investimento' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->centroId = \DB::table('centrocustos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'CC Teste', 'codigo' => '1', 'nivel' => 1, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->condicaoId = \DB::table('condicaopagamentos')->insertGetId(['grupo_id' => $this->grupo, 'empresa_id' => $this->empresaId, 'descricao' => 'À vista', 'tipo' => 1, 'num_parcelas' => 1, 'dias_primeira' => 0, 'intervalo' => 0, 'ativo' => 1, 'taxa' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private $condicaoId;

    public function test_grava_lancamento_simples_sem_baixa()
    {
        $this->preparar();

        $req = new Request([
            'cliente_id' => $this->clienteId,
            'dataemissao' => '01/06/2026',
            'datacompetencia' => '01/06/2026',
            'datavencimento' => '10/06/2026',
            'planoconta_id' => $this->planoId,
            'centrocusto_id' => $this->centroId,
            'pagarreceber' => 'R',
            'descricao' => 'Lançamento baseline',
            'documento' => 'DOC1',
            'valor' => '150,00',
            'contamovimentotipo_id' => null,
            'baixar' => false,
            'datahorabaixa' => '',
            'conta_id' => null,
            'contafechamento_id' => -1,
            'condicaopagamento_id' => $this->condicaoId,
            'cartaonsu' => null,
            'cartaoautorizacao' => null,
            'origemAgrupar' => null,
            'parcelasOrigem' => null,
        ]);

        $proc = new financeiroProcessor();
        $proc->setFinanceiroRequest($req);
        $proc->setRateiosRequest(json_encode([[$this->centroId, '', $this->planoId, '', '150,00']]));
        $proc->setParcelasRequest(json_encode(['data' => [['10/06/2026', 150, 1]]]));

        // validar() exige condicaopagamento quando !fromCaixa; usamos fromCaixa=true p/ baseline simples
        $this->assertTrue($proc->validar($req, true), implode(';', $proc->getErrors()));
        $ret = $proc->gravar();
        $this->assertTrue($ret === 'OK|' || $ret === true, implode(';', $proc->getErrors()));

        // persistiu financeiro + 1 parcela + rateio
        $fin = \DB::table('financeiros')->where(['cliente_id' => $this->clienteId, 'documento' => 'DOC1'])->first();
        $this->assertNotNull($fin);
        $this->assertEquals(1, \DB::table('financeiroparcelas')->where('financeiro_id', $fin->id)->count());
        $this->assertEquals(1, \DB::table('financeirorateios')->where('financeiro_id', $fin->id)->count());
        $this->assertEquals(150, (float) \DB::table('financeiroparcelas')->where('financeiro_id', $fin->id)->value('valor'));
    }
}
