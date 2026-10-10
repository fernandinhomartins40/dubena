<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Tenant\TenantContext;
use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueFechamento;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Trava de período do estoque.
 *
 * O fechamento era só um retrato: gravava o saldo de um produto num setor numa
 * data e não impedia nada. Um movimento lançado depois, no mesmo dia, fazia o
 * retrato deixar de corresponder ao que afirmava — sem aviso.
 *
 * A regra espelha a do legado (`isSetorestoqueFechadoData`): fechamento vigente
 * com data igual ou posterior à do movimento recusa o movimento. Como o ledger
 * novo não tem data de competência, o movimento é sempre de hoje.
 */
class EstoquePeriodoFechadoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Produto $produto;

    private Produto $outroProduto;

    private Setor $deposito;

    private Setor $loja;

    private EstoqueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $base = ['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id];
        $this->produto = Produto::factory()->create($base);
        $this->outroProduto = Produto::factory()->create($base);
        $this->deposito = Setor::factory()->create($base);
        $this->loja = Setor::factory()->create($base);
        $this->service = app(EstoqueService::class);
        $this->service->entrada($this->deposito->id, $this->produto->id, 100, 90.0);
        $this->service->entrada($this->deposito->id, $this->outroProduto->id, 50, 10.0);
    }

    private function saldo(Setor $setor, Produto $produto): float
    {
        return (float) EstoqueSaldo::withoutGlobalScopes()
            ->where('setor_id', $setor->id)->where('produto_id', $produto->id)->value('quantidade');
    }

    private function fecharHoje(): EstoqueFechamento
    {
        return $this->service->fechar($this->deposito->id, $this->produto->id, now()->toDateString());
    }

    public function test_movimento_em_periodo_fechado_e_recusado_e_o_saldo_nao_muda(): void
    {
        $this->fecharHoje();
        $movimentos = EstoqueHistorico::query()->count();

        try {
            $this->service->saida($this->deposito->id, $this->produto->id, 10);
            $this->fail('a saída deveria ter sido recusada');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Estoque fechado', $e->errors()['estoque'][0]);
        }

        // O efeito observável: nem saldo nem ledger mudaram.
        $this->assertEqualsWithDelta(100.0, $this->saldo($this->deposito, $this->produto), 0.001);
        $this->assertSame($movimentos, EstoqueHistorico::query()->count());
    }

    /** A trava é do PAR: outro produto e outro setor seguem movimentando. */
    public function test_trava_nao_vaza_para_outro_produto_nem_outro_setor(): void
    {
        $this->fecharHoje();

        $this->service->saida($this->deposito->id, $this->outroProduto->id, 5);
        $this->service->entrada($this->loja->id, $this->produto->id, 3);

        $this->assertEqualsWithDelta(45.0, $this->saldo($this->deposito, $this->outroProduto), 0.001);
        $this->assertEqualsWithDelta(3.0, $this->saldo($this->loja, $this->produto), 0.001);
    }

    /**
     * Transferência tem duas pernas. Se a origem está fechada, a entrada no
     * destino não pode acontecer sozinha — a mercadoria apareceria em dois
     * lugares.
     */
    public function test_transferencia_com_origem_fechada_nao_move_nenhuma_perna(): void
    {
        $this->fecharHoje();

        try {
            $this->service->transferir($this->deposito->id, $this->loja->id, $this->produto->id, 10);
            $this->fail('a transferência deveria ter sido recusada');
        } catch (ValidationException) {
        }

        $this->assertEqualsWithDelta(100.0, $this->saldo($this->deposito, $this->produto), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->saldo($this->loja, $this->produto), 0.001);
    }

    /** Destino fechado: a saída da origem, que vem antes, tem de ser desfeita. */
    public function test_transferencia_com_destino_fechado_desfaz_a_saida(): void
    {
        $this->service->entrada($this->loja->id, $this->produto->id, 1);
        $this->service->fechar($this->loja->id, $this->produto->id, now()->toDateString());

        try {
            $this->service->transferir($this->deposito->id, $this->loja->id, $this->produto->id, 10);
            $this->fail('a transferência deveria ter sido recusada');
        } catch (ValidationException) {
        }

        $this->assertEqualsWithDelta(100.0, $this->saldo($this->deposito, $this->produto), 0.001);
        $this->assertEqualsWithDelta(1.0, $this->saldo($this->loja, $this->produto), 0.001);
    }

    /**
     * Fechamento de data passada não trava nada: o passado já não recebe
     * movimento. É o que garante que os fechamentos migrados do legado não
     * bloqueiem a operação no dia do cutover.
     */
    public function test_fechamento_de_data_passada_nao_trava_o_presente(): void
    {
        $this->service->fechar($this->deposito->id, $this->produto->id, now()->subDay()->toDateString());

        $this->service->saida($this->deposito->id, $this->produto->id, 10);

        $this->assertEqualsWithDelta(90.0, $this->saldo($this->deposito, $this->produto), 0.001);
    }

    /** No dia seguinte ao fechamento o estoque volta a movimentar sozinho. */
    public function test_trava_termina_quando_a_data_do_fechamento_passa(): void
    {
        $this->fecharHoje();

        Carbon::setTestNow(now()->addDay());
        try {
            $this->service->saida($this->deposito->id, $this->produto->id, 10);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertEqualsWithDelta(90.0, $this->saldo($this->deposito, $this->produto), 0.001);
    }

    public function test_nao_fecha_em_data_futura(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->fechar($this->deposito->id, $this->produto->id, now()->addDay()->toDateString());
    }

    /** Regra do legado: não se fecha "para trás" de um fechamento que já vale. */
    public function test_nao_fecha_antes_de_um_fechamento_vigente(): void
    {
        $this->fecharHoje();

        foreach ([now()->toDateString(), now()->subDays(3)->toDateString()] as $data) {
            try {
                $this->service->fechar($this->deposito->id, $this->produto->id, $data);
                $this->fail("fechar em {$data} deveria ter sido recusado");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('data_fechamento', $e->errors());
            }
        }

        $this->assertSame(1, EstoqueFechamento::query()->count());
    }

    public function test_reabrir_exige_motivo_destrava_e_fica_na_trilha(): void
    {
        $fech = $this->fecharHoje();
        $rota = "/api/admin/estoque/fechamentos/{$fech->id}/reabrir";

        // Sem motivo: continua fechado.
        $this->actingAs($this->user, 'sanctum')->postJson($rota, [])->assertUnprocessable();
        $this->assertFalse($fech->fresh()->aberto);

        $this->actingAs($this->user, 'sanctum')
            ->postJson($rota, ['motivo' => 'Nota de entrada lançada depois do fechamento'])
            ->assertOk()->assertJsonPath('data.aberto', true);

        // A linha fica — reaberta, com o retrato que tinha.
        $this->assertTrue($fech->fresh()->aberto);
        $this->assertEqualsWithDelta(100.0, (float) $fech->fresh()->saldo_final, 0.001);

        $linha = AuditLog::query()->where('acao', 'reabriu_fechamento')->firstOrFail();
        $this->assertSame('Nota de entrada lançada depois do fechamento', $linha->motivo);
        $this->assertSame($fech->id, (int) $linha->entidade_id);

        // E o estoque volta a movimentar.
        $this->service->saida($this->deposito->id, $this->produto->id, 10);
        $this->assertEqualsWithDelta(90.0, $this->saldo($this->deposito, $this->produto), 0.001);
    }

    public function test_reabrir_duas_vezes_e_recusado(): void
    {
        $fech = $this->fecharHoje();
        $rota = "/api/admin/estoque/fechamentos/{$fech->id}/reabrir";

        $this->actingAs($this->user, 'sanctum')->postJson($rota, ['motivo' => 'Primeira'])->assertOk();
        $this->actingAs($this->user, 'sanctum')->postJson($rota, ['motivo' => 'Segunda'])->assertUnprocessable();

        $this->assertSame(1, AuditLog::query()->where('acao', 'reabriu_fechamento')->count());
    }

    /** Depois de reaberto pode-se fechar de novo, e o novo retrato é o vigente. */
    public function test_fechamento_reaberto_nao_impede_novo_fechamento_nem_serve_de_saldo_inicial(): void
    {
        $primeiro = $this->fecharHoje();
        $this->service->reabrirFechamento($primeiro);
        $this->service->saida($this->deposito->id, $this->produto->id, 30);

        $segundo = $this->fecharHoje();

        $this->assertEqualsWithDelta(70.0, (float) $segundo->saldo_final, 0.001);
        // O reaberto deixou de valer: não é dele que o saldo inicial vem.
        $this->assertEqualsWithDelta(0.0, (float) $segundo->saldo_inicial, 0.001);
    }

    public function test_fechamento_de_outra_empresa_nao_e_reaberto(): void
    {
        $outra = Empresa::factory()->create();
        $base = ['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id];
        $setor = Setor::factory()->create($base);
        $produto = Produto::factory()->create($base);
        $alheio = EstoqueFechamento::withoutGlobalScopes()->create([
            'empresa_id' => $outra->id, 'setor_id' => $setor->id, 'produto_id' => $produto->id,
            'data_fechamento' => now()->toDateString(), 'saldo_inicial' => 0, 'saldo_final' => 0, 'aberto' => false,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/estoque/fechamentos/{$alheio->id}/reabrir", ['motivo' => 'Tentativa'])
            ->assertNotFound();

        $this->assertFalse((bool) EstoqueFechamento::withoutGlobalScopes()->find($alheio->id)->aberto);
    }

    /** A porta HTTP devolve 422 com a mensagem — a tela precisa dizer o porquê. */
    public function test_api_recusa_lancamento_em_periodo_fechado_com_mensagem(): void
    {
        $fech = $this->fecharHoje();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/saida', [
                'setor_id' => $this->deposito->id, 'produto_id' => $this->produto->id, 'quantidade' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estoque')
            ->assertJsonFragment(['message' => 'Estoque fechado até '.now()->format('d/m/Y')." para este produto neste setor. Reabra o fechamento #{$fech->id} para movimentar."]);
    }
}
