<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4-01 — o ledger de estoque não duplica quando reprocessado.
 *
 * `estoquehistorico` já era um ledger, e um bom: quantidade assinada, tipo,
 * evento causal, ator e saldo resultante. O que faltava era a garantia que o
 * gate da F4 exige — **"rerun não duplica"**.
 *
 * A proteção existia, mas POR CASO DE USO: o pedido tem a flag
 * `estoque_movimentado`. Transferência, acerto e carga do franqueado não tinham
 * nada — reprocessar um job, ou o operador clicando duas vezes, gravava o
 * movimento de novo.
 *
 * E movimento de estoque duplicado **não dá erro**: dá um saldo que não bate,
 * descoberto no inventário, quando ninguém mais liga o sintoma à causa.
 */
class LedgerIdempotenteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, Setor, Produto} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $setor, $produto];
    }

    private function saldo(Setor $setor, Produto $produto): float
    {
        return (float) EstoqueSaldo::withoutTenant()
            ->where('setor_id', $setor->id)->where('produto_id', $produto->id)
            ->value('quantidade');
    }

    /** O caso que o gate exige: reprocessar não move o saldo de novo. */
    public function test_reprocessar_com_a_mesma_chave_nao_duplica(): void
    {
        [, $setor, $produto] = $this->cenario();
        $servico = app(EstoqueService::class);

        $primeiro = $servico->entrada($setor->id, $produto->id, 10, 5, 'carga', 1, null, null, 'carga:1');
        $segundo = $servico->entrada($setor->id, $produto->id, 10, 5, 'carga', 1, null, null, 'carga:1');

        $this->assertSame($primeiro->id, $segundo->id, 'a segunda chamada devolve o movimento já gravado');
        $this->assertSame(10.0, $this->saldo($setor, $produto), 'o saldo não pode ter dobrado');
        $this->assertSame(1, EstoqueHistorico::withoutTenant()->count());
    }

    /** Sem chave, o comportamento antigo continua — dois movimentos legítimos. */
    public function test_sem_chave_o_movimento_continua_sendo_gravado(): void
    {
        [, $setor, $produto] = $this->cenario();
        $servico = app(EstoqueService::class);

        $servico->entrada($setor->id, $produto->id, 10, 5);
        $servico->entrada($setor->id, $produto->id, 10, 5);

        $this->assertSame(20.0, $this->saldo($setor, $produto));
        $this->assertSame(2, EstoqueHistorico::withoutTenant()->count());
    }

    /** Chaves diferentes são movimentos diferentes — a garantia não pode travar o normal. */
    public function test_chaves_diferentes_gravam_movimentos_diferentes(): void
    {
        [, $setor, $produto] = $this->cenario();
        $servico = app(EstoqueService::class);

        $servico->entrada($setor->id, $produto->id, 10, 5, 'carga', 1, null, null, 'carga:1');
        $servico->entrada($setor->id, $produto->id, 10, 5, 'carga', 2, null, null, 'carga:2');

        $this->assertSame(20.0, $this->saldo($setor, $produto));
        $this->assertSame(2, EstoqueHistorico::withoutTenant()->count());
    }

    /**
     * A chave é escopada por empresa.
     *
     * Duas revendas podem legitimamente usar o mesmo identificador de origem —
     * o número do pedido reinicia por empresa. Uma unicidade global faria a
     * segunda revenda **perder o movimento**, que é pior que duplicá-lo.
     */
    public function test_a_mesma_chave_em_empresas_diferentes_nao_colide(): void
    {
        [, $setorA, $produtoA] = $this->cenario();
        [, $setorB, $produtoB] = $this->cenario();
        $servico = app(EstoqueService::class);

        $a = $servico->entrada($setorA->id, $produtoA->id, 10, 5, 'pedido', 1, null, null, 'pedido:1');
        $b = $servico->entrada($setorB->id, $produtoB->id, 7, 5, 'pedido', 1, null, null, 'pedido:1');

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(10.0, $this->saldo($setorA, $produtoA));
        $this->assertSame(7.0, $this->saldo($setorB, $produtoB));
    }

    /** A saída também é protegida — o efeito de duplicar ali é perder mercadoria. */
    public function test_saida_reprocessada_nao_baixa_duas_vezes(): void
    {
        [, $setor, $produto] = $this->cenario();
        $servico = app(EstoqueService::class);

        $servico->entrada($setor->id, $produto->id, 10, 5);
        $servico->saida($setor->id, $produto->id, 4, 'venda', 9, null, null, 'venda:9');
        $servico->saida($setor->id, $produto->id, 4, 'venda', 9, null, null, 'venda:9');

        $this->assertSame(6.0, $this->saldo($setor, $produto));
    }

    /** O ledger entra na fronteira SaaS: sem o tenant, ficaria fora da policy canônica. */
    public function test_movimento_herda_o_tenant_da_empresa(): void
    {
        [$empresa, $setor, $produto] = $this->cenario();

        $mov = app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 5);

        $this->assertSame(
            $empresa->tenant_account_id,
            $mov->tenant_account_id,
            'o movimento pertence ao mesmo tenant da empresa',
        );
    }
}
