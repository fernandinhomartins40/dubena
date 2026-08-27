<?php

namespace Tests\Feature;

use App\Domain\Financeiro\ConciliacaoService;
use App\Domain\Financeiro\ContaExtratoAcao;
use App\Domain\Financeiro\RegraExtratoService;
use App\Models\Caixa\Conta;
use App\Models\Empresa;
use App\Models\Financeiro\ContaExtratoRegra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da T4.2 — classificação automática do extrato bancário.
 *
 * A importação OFX existia, mas devolvia uma lista crua: cada linha precisava
 * ser classificada à mão (plano de contas, centro de custo, tipo de movimento).
 * Com `contamovimentos` em 410.417 linhas isso não é inconveniência, é
 * impedimento — a auditoria chama de "a importação existe mas não é produtiva".
 *
 * O cenário central é o do plano: cadastrar a regra "PIX RECEBIDO" →
 * LANCAR_BAIXAR, importar um OFX com essa descrição, e a linha voltar
 * pré-classificada com os ids preenchidos.
 */
class RegraExtratoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa,2:Conta} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        $conta = Conta::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Banco do Brasil c/c 1234',
            'tipo' => 'BANCO',
            'saldo_inicial' => 0,
            'saldo_atual' => 0,
            'fechado' => false,
            'ativo' => true,
        ]);

        return [$user, $empresa, $conta];
    }

    private function regra(Conta $conta, string $descricao, ContaExtratoAcao $acao, array $extra = []): ContaExtratoRegra
    {
        return ContaExtratoRegra::create(array_merge([
            'empresa_id' => $conta->empresa_id,
            'conta_id' => $conta->id,
            'descricao' => $descricao,
            'acao' => $acao->value,
            'condicaopagamento_id' => 1,
            'contamovimentotipo_id' => 2,
            'plano_conta_id' => 3,
            'centro_custo_id' => 4,
            'ativo' => true,
            'prioridade' => 0,
        ], $extra));
    }

    /** OFX mínimo com uma transação. */
    private function ofx(string $memo, string $valor = '150.00', string $data = '20260817'): string
    {
        return <<<OFX
        <OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>CREDIT</TRNTYPE>
            <DTPOSTED>{$data}120000</DTPOSTED>
            <TRNAMT>{$valor}</TRNAMT>
            <FITID>TX001</FITID>
            <MEMO>{$memo}</MEMO>
          </STMTTRN>
        </BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>
        OFX;
    }

    // ─────────────────── O cenário do plano ───────────────────

    public function test_linha_do_ofx_volta_pre_classificada(): void
    {
        [, , $conta] = $this->cenario();
        $this->regra($conta, 'PIX RECEBIDO', ContaExtratoAcao::LANCAR_BAIXAR);

        $resultado = app(ConciliacaoService::class)->conciliar(
            $conta->id,
            (int) $conta->empresa_id,
            $this->ofx('PIX RECEBIDO DE JOAO DA SILVA'),
            '2026-08-01',
            '2026-08-31',
        );

        $pendentes = $resultado['ofx_pendentes'];
        $this->assertCount(1, $pendentes);

        $sugestao = $pendentes[0]['sugestao'];
        $this->assertNotNull($sugestao, 'a linha tinha de vir classificada');
        $this->assertSame('LANCAR_BAIXAR', $sugestao['acao']);
        $this->assertSame(3, $sugestao['plano_conta_id']);
        $this->assertSame(4, $sugestao['centro_custo_id']);
        // LANCAR_BAIXAR: o dinheiro já consta no extrato, então o título nasce quitado.
        $this->assertTrue($sugestao['baixar']);
    }

    public function test_sem_regra_a_linha_volta_intacta(): void
    {
        [, , $conta] = $this->cenario();

        $resultado = app(ConciliacaoService::class)->conciliar(
            $conta->id, (int) $conta->empresa_id, $this->ofx('TARIFA BANCARIA'), '2026-08-01', '2026-08-31',
        );

        // Sem regras, a tela segue funcionando exatamente como antes.
        $this->assertNull($resultado['ofx_pendentes'][0]['sugestao'] ?? null);
    }

    // ─────────────────── Casamento ───────────────────

    public function test_casamento_ignora_acento_caixa_e_pontuacao(): void
    {
        [, , $conta] = $this->cenario();
        $this->regra($conta, 'PIX RECEBIDO', ContaExtratoAcao::LANCAR);

        $service = app(RegraExtratoService::class);
        $regras = $service->regrasDaConta($conta->id, (int) $conta->empresa_id);

        // O texto do MEMO varia entre bancos: comparar cru faria a mesma regra
        // falhar em metade dos extratos.
        foreach (['Pix recebido de Fulano', 'PIX  RECEBIDO-123', 'pix recebído'] as $memo) {
            $this->assertNotNull($service->casar($memo, $regras), "deveria casar: {$memo}");
        }

        $this->assertNull($service->casar('TARIFA MENSALIDADE', $regras));
    }

    public function test_regra_mais_especifica_vence_a_generica(): void
    {
        [, , $conta] = $this->cenario();
        $this->regra($conta, 'PIX', ContaExtratoAcao::LANCAR, ['plano_conta_id' => 10]);
        $this->regra($conta, 'PIX RECEBIDO ALUGUEL', ContaExtratoAcao::LANCAR, ['plano_conta_id' => 20]);

        $service = app(RegraExtratoService::class);
        $casada = $service->casar('PIX RECEBIDO ALUGUEL SALA 3', $service->regrasDaConta($conta->id, (int) $conta->empresa_id));

        // Sem o desempate por comprimento, a regra genérica engoliria todas as
        // específicas e classificaria tudo no mesmo plano de contas.
        $this->assertSame(20, $casada?->plano_conta_id);
    }

    public function test_prioridade_vence_o_comprimento(): void
    {
        [, , $conta] = $this->cenario();
        $this->regra($conta, 'PAGAMENTO FORNECEDOR XYZ', ContaExtratoAcao::LANCAR, ['plano_conta_id' => 10]);
        $this->regra($conta, 'PAGAMENTO', ContaExtratoAcao::LANCAR, ['plano_conta_id' => 99, 'prioridade' => 5]);

        $service = app(RegraExtratoService::class);
        $casada = $service->casar('PAGAMENTO FORNECEDOR XYZ LTDA', $service->regrasDaConta($conta->id, (int) $conta->empresa_id));

        $this->assertSame(99, $casada?->plano_conta_id, 'prioridade explícita tem de vencer');
    }

    public function test_regra_de_outra_conta_nao_e_usada(): void
    {
        [, $empresa, $conta] = $this->cenario();
        $outra = Conta::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Caixa interno', 'tipo' => 'CAIXA',
            'saldo_inicial' => 0, 'saldo_atual' => 0, 'fechado' => false, 'ativo' => true,
        ]);
        $this->regra($outra, 'PIX RECEBIDO', ContaExtratoAcao::LANCAR);

        $service = app(RegraExtratoService::class);

        // "PIX RECEBIDO" significa coisas diferentes em contas diferentes.
        $this->assertNull($service->casar('PIX RECEBIDO', $service->regrasDaConta($conta->id, (int) $conta->empresa_id)));
    }

    public function test_conta_de_outra_empresa_nao_fornece_regras_ao_owner_esperado(): void
    {
        [, $empresa, $conta] = $this->cenario();
        $this->regra($conta, 'PIX RECEBIDO', ContaExtratoAcao::LANCAR);
        $outra = Empresa::factory()->create();

        $this->assertSame([], app(RegraExtratoService::class)->regrasDaConta($conta->id, $outra->id));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ConciliacaoService::class)->conciliar(
            $conta->id,
            $outra->id,
            $this->ofx('PIX RECEBIDO'),
            '2026-08-01',
            '2026-08-31',
        );
    }

    public function test_regra_inativa_e_ignorada(): void
    {
        [, , $conta] = $this->cenario();
        $this->regra($conta, 'PIX RECEBIDO', ContaExtratoAcao::LANCAR, ['ativo' => false]);

        $service = app(RegraExtratoService::class);
        $this->assertNull($service->casar('PIX RECEBIDO', $service->regrasDaConta($conta->id, (int) $conta->empresa_id)));
    }

    public function test_sinal_vem_do_extrato_e_nao_da_regra(): void
    {
        [, , $conta] = $this->cenario();
        $this->regra($conta, 'TARIFA', ContaExtratoAcao::LANCAR_BAIXAR);

        $resultado = app(ConciliacaoService::class)->conciliar(
            $conta->id, (int) $conta->empresa_id, $this->ofx('TARIFA DE MANUTENCAO', '-45.90'), '2026-08-01', '2026-08-31',
        );

        // Valor negativo é SAÍDA, independentemente do que a regra diga.
        // Inverter isso lançaria despesa como receita.
        $sugestao = $resultado['ofx_pendentes'][0]['sugestao'];
        $this->assertSame('P', $sugestao['pagarreceber']);
        $this->assertEqualsWithDelta(45.90, $sugestao['valor'], 0.001);
    }

    // ─────────────────── CRUD e validação condicional ───────────────────

    public function test_crud_completo(): void
    {
        [$user, , $conta] = $this->cenario();
        $base = "/api/admin/financeiro/contas/{$conta->id}/extrato-regras";

        $criada = $this->actingAs($user, 'sanctum')->postJson($base, [
            'descricao' => 'PIX RECEBIDO',
            'acao' => 'LANCAR_BAIXAR',
            'condicaopagamento_id' => 1,
            'contamovimentotipo_id' => 2,
            'plano_conta_id' => 3,
            'centro_custo_id' => 4,
        ])->assertCreated()->json('data');

        $this->actingAs($user, 'sanctum')->getJson($base)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // O catálogo de ações vai junto: a tela não precisa hardcodar.
            ->assertJsonCount(3, 'acoes');

        $this->actingAs($user, 'sanctum')->putJson("{$base}/{$criada['id']}", [
            'descricao' => 'PIX RECEBIDO CLIENTE',
            'acao' => 'LANCAR_BAIXAR',
            'condicaopagamento_id' => 1,
            'contamovimentotipo_id' => 2,
            'plano_conta_id' => 3,
            'centro_custo_id' => 4,
        ])->assertOk()->assertJsonPath('data.descricao', 'PIX RECEBIDO CLIENTE');

        $this->actingAs($user, 'sanctum')->deleteJson("{$base}/{$criada['id']}")->assertOk();
        $this->actingAs($user, 'sanctum')->getJson($base)->assertJsonCount(0, 'data');
    }

    public function test_lancar_exige_plano_de_contas_e_centro_de_custo(): void
    {
        [$user, , $conta] = $this->cenario();

        // A validação condicional é o que dá sentido à regra: uma de LANÇAR sem
        // plano de contas não classifica nada.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/financeiro/contas/{$conta->id}/extrato-regras", [
                'descricao' => 'ALGUMA COISA',
                'acao' => 'LANCAR',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plano_conta_id', 'centro_custo_id']);
    }

    public function test_transferir_exige_conta_de_origem_mas_nao_plano(): void
    {
        [$user, , $conta] = $this->cenario();
        $base = "/api/admin/financeiro/contas/{$conta->id}/extrato-regras";

        $this->actingAs($user, 'sanctum')->postJson($base, [
            'descricao' => 'TRANSF ENTRE CONTAS',
            'acao' => 'TRANSFERIR',
        ])->assertStatus(422)->assertJsonValidationErrors(['conta_origem_id']);

        // Com a conta de origem, passa — e NÃO exige plano/centro, que não fazem
        // sentido numa transferência entre contas próprias.
        $this->actingAs($user, 'sanctum')->postJson($base, [
            'descricao' => 'TRANSF ENTRE CONTAS',
            'acao' => 'TRANSFERIR',
            'conta_origem_id' => $conta->id,
        ])->assertCreated();
    }

    public function test_escrita_exige_permissao(): void
    {
        [, $empresa, $conta] = $this->cenario();
        $leitor = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);

        $this->actingAs($leitor, 'sanctum')
            ->postJson("/api/admin/financeiro/contas/{$conta->id}/extrato-regras", [
                'descricao' => 'X', 'acao' => 'TRANSFERIR', 'conta_origem_id' => $conta->id,
            ])
            ->assertStatus(403);
    }
}
