<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Cobranca\BoletoService;
use App\Domain\Cobranca\PixService;
use App\Domain\Cobranca\SituacaoPix;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Shared\NumeroSequencialService;
use App\Models\Caixa\Conta;
use App\Models\Cliente\Cliente;
use App\Models\Cobranca\PixCobranca;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PIE Rodada 3 — Agente B (caixa e financeiro). Cada teste reproduz um defeito
 * visto no navegador (jornadas em e2e/caixa) e falha sem a correção.
 */
class PieR3CaixaFinanceiroTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $e;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e = Empresa::factory()->create();
        $this->user = User::factory()->create(['empresa_id' => $this->e->id, 'grupo_id' => $this->e->grupo_id]);
    }

    private function conta(): Conta
    {
        $conta = app(CaixaService::class)->criarConta([
            'empresa_id' => $this->e->id, 'grupo_id' => $this->e->grupo_id, 'descricao' => 'Caixa', 'saldo_inicial' => 0,
        ]);
        app(CaixaService::class)->abrir($conta->id, $this->e->id);

        return $conta->refresh();
    }

    private function titulo(float $valor, ?int $clienteId = null): Financeiro
    {
        return app(FinanceiroService::class)->criar([
            'empresa_id' => $this->e->id, 'grupo_id' => $this->e->grupo_id, 'pagarreceber' => 'R',
            'valor' => $valor, 'cliente_id' => $clienteId,
        ]);
    }

    // ---- P2-D-16: recebimento parcial ----

    public function test_p2_d_16_parcial_baixa_o_recebido_e_deixa_o_restante_em_aberto(): void
    {
        $conta = $this->conta();
        $t = $this->titulo(120);
        $p = $t->parcelas->first();

        $this->actingAs($this->user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar", [
            'parcela_id' => $p->id, 'valor_recebido' => 50,
        ])->assertCreated()->assertJsonPath('data.valor', '50.00');

        $parcelas = FinanceiroParcela::query()->where('financeiro_id', $t->id)->orderBy('numero')->get();
        $this->assertCount(2, $parcelas);
        $this->assertTrue($parcelas[0]->baixado);
        $this->assertEqualsWithDelta(50, (float) $parcelas[0]->valor, 0.001);
        $this->assertEqualsWithDelta(50, (float) $parcelas[0]->valor_efetivado, 0.001);
        $this->assertFalse($parcelas[1]->baixado);
        $this->assertEqualsWithDelta(70, (float) $parcelas[1]->valor, 0.001);
        $this->assertSame($parcelas[0]->vencimento->toDateString(), $parcelas[1]->vencimento->toDateString());
        // O título continua valendo o mesmo: a soma das parcelas não muda.
        $this->assertEqualsWithDelta(120, (float) $parcelas->sum('valor'), 0.001);
        $this->assertEqualsWithDelta(50, (float) $conta->refresh()->saldo_atual, 0.001);
    }

    public function test_parcial_imputa_primeiro_nos_juros_e_multa(): void
    {
        $conta = $this->conta();
        $t = $this->titulo(100);
        $p = $t->parcelas->first();

        // Recebe 50 de 100 + 10 de juros: 10 quitam os juros, 40 abatem o principal.
        app(CaixaService::class)->baixarParcela($conta->id, $p->id, $this->e->id, juros: 10, valorRecebido: 50);

        $parcelas = FinanceiroParcela::query()->where('financeiro_id', $t->id)->orderBy('numero')->get();
        $this->assertEqualsWithDelta(40, (float) $parcelas[0]->valor, 0.001);
        $this->assertEqualsWithDelta(50, (float) $parcelas[0]->valor_efetivado, 0.001);
        $this->assertEqualsWithDelta(60, (float) $parcelas[1]->valor, 0.001);
    }

    public function test_parcial_recusa_valores_invalidos_sem_gravar(): void
    {
        $conta = $this->conta();
        $p = $this->titulo(100)->parcelas->first();
        $svc = app(CaixaService::class);

        foreach ([
            fn () => $svc->baixarParcela($conta->id, $p->id, $this->e->id, valorRecebido: 100.01), // maior que o total
            fn () => $svc->baixarParcela($conta->id, $p->id, $this->e->id, valorRecebido: 0),       // zero
            fn () => $svc->baixarParcela($conta->id, $p->id, $this->e->id, juros: 30, valorRecebido: 20), // não cobre os juros
        ] as $i => $chamada) {
            try {
                $chamada();
                $this->fail("caso {$i} deveria ser recusado");
            } catch (ValidationException) {
            }
        }

        $this->assertSame(1, FinanceiroParcela::query()->where('financeiro_id', $p->financeiro_id)->count());
        $this->assertFalse($p->refresh()->baixado);
        $this->assertEqualsWithDelta(0, (float) $conta->refresh()->saldo_atual, 0.001);
    }

    public function test_parcial_recusada_com_boleto_ou_pix_em_aberto_na_parcela(): void
    {
        $conta = $this->conta();
        $comBoleto = $this->titulo(100)->parcelas->first();
        app(BoletoService::class)->gerarParaParcela($comBoleto);
        $comPix = $this->titulo(100)->parcelas->first();
        app(PixService::class)->criarCobranca($comPix);

        foreach ([$comBoleto, $comPix] as $p) {
            try {
                app(CaixaService::class)->baixarParcela($conta->id, $p->id, $this->e->id, valorRecebido: 40);
                $this->fail('parcial com cobrança bancária viva deveria ser recusada');
            } catch (ValidationException $ex) {
                $this->assertStringContainsString('boleto ou PIX', json_encode($ex->errors(), JSON_UNESCAPED_UNICODE));
            }
        }
        // O recebimento integral continua possível.
        app(CaixaService::class)->baixarParcela($conta->id, $comBoleto->id, $this->e->id);
        $this->assertTrue($comBoleto->refresh()->baixado);
    }

    public function test_estorno_de_parcial_reabre_e_o_titulo_volta_ao_total(): void
    {
        $conta = $this->conta();
        $t = $this->titulo(120);
        $mov = app(CaixaService::class)->baixarParcela($conta->id, $t->parcelas->first()->id, $this->e->id, valorRecebido: 50);
        app(CaixaService::class)->estornar($mov->id, $this->e->id);

        $abertas = FinanceiroParcela::query()->where('financeiro_id', $t->id)->where('baixado', false)->sum('valor');
        $this->assertEqualsWithDelta(120, (float) $abertas, 0.001);
        $this->assertEqualsWithDelta(0, (float) $conta->refresh()->saldo_atual, 0.001);
    }

    // ---- P2-D-09: estado da conta ----

    public function test_p2_d_09_conta_sem_abertura_tem_estado_proprio_e_caminho_para_fechar(): void
    {
        $conta = app(CaixaService::class)->criarConta([
            'empresa_id' => $this->e->id, 'grupo_id' => $this->e->grupo_id, 'descricao' => 'Banco', 'saldo_inicial' => 0,
        ]);
        $estado = fn () => collect($this->actingAs($this->user, 'sanctum')->getJson('/api/admin/caixa/contas')->assertOk()->json('data'))
            ->firstWhere('id', $conta->id)['estado'];

        $this->assertSame('SEM_ABERTURA', $estado());
        $this->actingAs($this->user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/abrir", [])->assertCreated();
        $this->assertSame('ABERTO', $estado());
        $this->actingAs($this->user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/fechar", [])->assertOk();
        $this->assertSame('FECHADO', $estado());
    }

    // ---- Painel de movimentos e lista de títulos ----

    public function test_movimentos_do_caixa_devolvem_o_saldo_que_a_tela_mostra(): void
    {
        $conta = $this->conta();
        app(CaixaService::class)->baixarParcela($conta->id, $this->titulo(80)->parcelas->first()->id, $this->e->id);

        $this->actingAs($this->user, 'sanctum')->getJson("/api/admin/caixa/{$conta->id}/movimentos")
            ->assertOk()
            ->assertJsonPath('saldo', 80);
    }

    public function test_lancamentos_trazem_o_nome_do_cliente(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->e->id, 'grupo_id' => $this->e->grupo_id, 'nome' => 'Fulana de Tal']);
        $this->titulo(80, $cliente->id);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/financeiro/lancamentos')
            ->assertOk()
            ->assertJsonPath('data.0.cliente', 'Fulana de Tal');
    }

    // ---- PIE-25: numeração de remessa CNAB ----

    public function test_pie_25_numero_da_remessa_sai_da_sequencia_travada(): void
    {
        $svc = app(BoletoService::class);
        $b1 = $svc->gerarParaParcela($this->titulo(100)->parcelas->first());
        $this->assertSame(1, $svc->gerarRemessa([$b1], $this->e->id)->numero_remessa);

        // Outra transação já reservou o 2 (concorrência simulada): com max+1 a
        // segunda remessa repetiria o número reservado e sobrescreveria o arquivo.
        app(NumeroSequencialService::class)->proximo("boleto:empresa:{$this->e->id}:remessa");
        $b2 = $svc->gerarParaParcela($this->titulo(100)->parcelas->first());
        $this->assertSame(3, $svc->gerarRemessa([$b2], $this->e->id)->numero_remessa);
    }

    // ---- PIE-24: webhook PIX depois da expiração local ----

    public function test_pie_24_pix_pago_depois_de_expirar_fica_registrado_sem_baixa_automatica(): void
    {
        $parcela = $this->titulo(75)->parcelas->first();
        $cobranca = app(PixService::class)->criarCobranca($parcela);
        $cobranca->update(['situacao' => SituacaoPix::EXPIRADA->value]);
        config()->set('services.pix.webhook_secret', 'segredo-de-teste');

        $payload = ['txid' => $cobranca->txid, 'valor' => 75.00, 'e2eid' => 'E123'];
        $this->postJson('/api/pix/webhook', $payload, ['X-Webhook-Token' => 'segredo-de-teste'])
            ->assertOk()
            ->assertJsonPath('situacao', 'PAGA_APOS_EXPIRAR');

        $cobranca->refresh();
        $this->assertSame(SituacaoPix::PAGA_APOS_EXPIRAR, $cobranca->situacao);
        $this->assertSame('E123', $cobranca->e2eid);
        $this->assertNotNull($cobranca->pago_em);
        // Fail-closed: a parcela pode ter sido recebida por outro meio enquanto a
        // cobrança estava expirada. A baixa espera a conciliação (decisão pendente).
        $this->assertFalse($parcela->refresh()->baixado);

        // Reentrega idempotente.
        $this->postJson('/api/pix/webhook', $payload, ['X-Webhook-Token' => 'segredo-de-teste'])->assertOk();
        $this->assertSame(1, PixCobranca::query()->where('situacao', SituacaoPix::PAGA_APOS_EXPIRAR->value)->count());
    }

    public function test_pie_24_pix_expirado_com_valor_divergente_continua_recusado(): void
    {
        $cobranca = app(PixService::class)->criarCobranca($this->titulo(75)->parcelas->first());
        $cobranca->update(['situacao' => SituacaoPix::EXPIRADA->value]);
        config()->set('services.pix.webhook_secret', 'segredo-de-teste');

        $this->postJson('/api/pix/webhook', ['txid' => $cobranca->txid, 'valor' => 10], ['X-Webhook-Token' => 'segredo-de-teste'])
            ->assertStatus(422);
        $this->assertSame(SituacaoPix::EXPIRADA, $cobranca->refresh()->situacao);
    }
}
