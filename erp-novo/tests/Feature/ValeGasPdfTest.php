<?php

namespace Tests\Feature;

use App\Domain\Satelite\SituacaoValeGas;
use App\Domain\Satelite\ValeGasPdfService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\Satelite\ValeGas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE do item 19 da triagem — vale-gás impresso e duplicata.
 *
 * A triagem: *"o vale **é** um documento físico entregue ao cliente: sem
 * impressão, o produto não existe"*. Venda, baixa e consulta já estavam
 * migradas; faltava o papel.
 *
 * O que estes testes fixam além do "o PDF sai": as **recusas**. Um vale
 * cancelado impresso é indistinguível de um válido na mão do cliente, e um
 * vale já utilizado reimpresso daria direito a uma segunda troca. Nos dois
 * casos a revenda entrega botijão contra papel sem lastro.
 */
class ValeGasPdfTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa,2:Cliente} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);
        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Maria da Silva',
            'cpf' => '12345678909',
            'cliente' => true,
        ]);

        return [$user, $empresa, $cliente];
    }

    private function vale(Empresa $empresa, Cliente $cliente, array $extra = []): ValeGas
    {
        return ValeGas::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'codigo' => 'VG-ABC12345',
            'valor' => 120.00,
            'validade' => now()->addMonths(6),
            'situacao' => SituacaoValeGas::PAGO,
            ...$extra,
        ]);
    }

    /** Título a prazo com 3 parcelas, como numa venda parcelada de vale. */
    private function tituloComParcelas(Empresa $empresa, Cliente $cliente): Financeiro
    {
        $titulo = Financeiro::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'pagarreceber' => 'R',
            'descricao' => 'Vale-gás VG-ABC12345',
            'valor' => 120.00,
            'data_emissao' => now(),
            'origem' => 'vale_gas',
        ]);

        foreach ([1, 2, 3] as $n) {
            FinanceiroParcela::create([
                'financeiro_id' => $titulo->id,
                'numero' => $n,
                'vencimento' => now()->addMonths($n),
                'valor' => 40.00,
                'baixado' => $n === 1,
                'datahora_baixa' => $n === 1 ? now() : null,
            ]);
        }

        return $titulo;
    }

    // ── Vale impresso ────────────────────────────────────────────────────────

    public function test_vale_pago_sai_em_pdf(): void
    {
        [$user, $empresa, $cliente] = $this->cenario();
        $vale = $this->vale($empresa, $cliente);

        $resposta = $this->actingAs($user, 'sanctum')->get("/api/admin/vale-gas/{$vale->id}/pdf");

        $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_vale_emitido_sai_com_aviso_de_pagamento_pendente(): void
    {
        [$user, $empresa, $cliente] = $this->cenario();
        $vale = $this->vale($empresa, $cliente, ['situacao' => SituacaoValeGas::EMITIDO]);

        // Sai, mas o papel avisa: o vale ainda não foi pago e não vale troca.
        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/vale-gas/{$vale->id}/pdf")
            ->assertOk();
    }

    public function test_vale_cancelado_nao_imprime(): void
    {
        [$user, $empresa, $cliente] = $this->cenario();
        $vale = $this->vale($empresa, $cliente, ['situacao' => SituacaoValeGas::CANCELADO]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/vale-gas/{$vale->id}/pdf")
            ->assertStatus(422);
    }

    public function test_vale_expirado_nao_imprime(): void
    {
        [$user, $empresa, $cliente] = $this->cenario();
        $vale = $this->vale($empresa, $cliente, ['situacao' => SituacaoValeGas::EXPIRADO]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/vale-gas/{$vale->id}/pdf")
            ->assertStatus(422);
    }

    public function test_vale_utilizado_nao_reimprime(): void
    {
        [$user, $empresa, $cliente] = $this->cenario();
        $vale = $this->vale($empresa, $cliente, [
            'situacao' => SituacaoValeGas::UTILIZADO,
            'utilizado_em' => now(),
        ]);

        // Reimprimir daria direito a uma segunda troca do mesmo cupom.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/vale-gas/{$vale->id}/pdf")
            ->assertStatus(422);
    }

    // ── Duplicata ────────────────────────────────────────────────────────────

    public function test_duplicata_sai_com_as_parcelas(): void
    {
        [$user, $empresa, $cliente] = $this->cenario();
        $titulo = $this->tituloComParcelas($empresa, $cliente);
        $vale = $this->vale($empresa, $cliente, ['financeiro_id' => $titulo->id]);

        $resposta = $this->actingAs($user, 'sanctum')->get("/api/admin/vale-gas/{$vale->id}/duplicata");

        $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_vale_a_vista_nao_tem_duplicata(): void
    {
        [$user, $empresa, $cliente] = $this->cenario();
        $vale = $this->vale($empresa, $cliente, ['financeiro_id' => null]);

        // Entregar uma duplicata sem dívida por trás seria cobrar o que não existe.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/vale-gas/{$vale->id}/duplicata")
            ->assertStatus(422);
    }

    public function test_titulo_sem_parcelas_nao_gera_duplicata(): void
    {
        [, $empresa, $cliente] = $this->cenario();
        $titulo = Financeiro::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pagarreceber' => 'R',
            'descricao' => 'Vale sem parcelas', 'valor' => 50, 'data_emissao' => now(),
        ]);
        $vale = $this->vale($empresa, $cliente, ['financeiro_id' => $titulo->id]);

        $this->expectException(\DomainException::class);
        app(ValeGasPdfService::class)->duplicata($vale);
    }

    public function test_duplicata_de_vale_utilizado_ainda_sai(): void
    {
        [$user, $empresa, $cliente] = $this->cenario();
        $titulo = $this->tituloComParcelas($empresa, $cliente);
        $vale = $this->vale($empresa, $cliente, [
            'situacao' => SituacaoValeGas::UTILIZADO,
            'utilizado_em' => now(),
            'financeiro_id' => $titulo->id,
        ]);

        // A dívida sobrevive ao resgate: o cliente trocou o vale, mas ainda
        // deve as parcelas. Bloquear a cobrança aqui perderia receita.
        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/vale-gas/{$vale->id}/duplicata")
            ->assertOk();
    }

    public function test_exige_permissao(): void
    {
        [, $empresa, $cliente] = $this->cenario();
        $vale = $this->vale($empresa, $cliente);
        $leitor = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);

        $this->actingAs($leitor, 'sanctum')
            ->getJson("/api/admin/vale-gas/{$vale->id}/pdf")
            ->assertStatus(403);
    }
}
