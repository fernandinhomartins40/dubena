<?php

namespace Tests\Domain;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F05 — endurecimento multi-tenant do estoque. A invariante Σ histórico = saldo
 * deve valer POR EMPRESA, os saldos/históricos de uma empresa não podem vazar para
 * outra (global scope), e transferência entre empresas é proibida.
 */
class EstoqueMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    private EstoqueService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(EstoqueService::class);
    }

    /** @return array{0:Empresa,1:Setor,2:Produto} */
    private function tenant(): array
    {
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        return [$empresa, $setor, $produto];
    }

    public function test_invariante_soma_historico_igual_saldo_por_empresa(): void
    {
        [$empA, $setorA, $prodA] = $this->tenant();
        [$empB, $setorB, $prodB] = $this->tenant();

        $this->svc->entrada($setorA->id, $prodA->id, 100, 10);
        $this->svc->saida($setorA->id, $prodA->id, 30);
        $this->svc->entrada($setorB->id, $prodB->id, 50, 8);

        // Σ histórico por empresa == saldo derivado por empresa.
        $histA = (float) EstoqueHistorico::withoutTenant()->where('empresa_id', $empA->id)->sum('quantidade');
        $histB = (float) EstoqueHistorico::withoutTenant()->where('empresa_id', $empB->id)->sum('quantidade');
        $this->assertSame(70.0, round($histA, 3));
        $this->assertSame(50.0, round($histB, 3));
        $this->assertSame(70.0, $this->svc->saldoDerivado($setorA->id, $prodA->id));
        $this->assertSame(50.0, $this->svc->saldoDerivado($setorB->id, $prodB->id));
    }

    public function test_saldos_nao_vazam_entre_empresas_sob_scope(): void
    {
        [$empA, $setorA, $prodA] = $this->tenant();
        [$empB, $setorB, $prodB] = $this->tenant();
        $this->svc->entrada($setorA->id, $prodA->id, 100, 10);
        $this->svc->entrada($setorB->id, $prodB->id, 40, 5);

        // Com o tenant B ativo, o global scope só enxerga os saldos de B.
        app(TenantContext::class)->set($empB->id, $empB->grupo_id);
        $this->assertSame(1, EstoqueSaldo::query()->count());
        $this->assertSame(40.0, (float) EstoqueSaldo::query()->sum('quantidade'));

        // withoutTenant enxerga os dois.
        $this->assertSame(2, EstoqueSaldo::withoutTenant()->count());
    }

    public function test_transferencia_entre_empresas_e_bloqueada(): void
    {
        [, $setorA, $prodA] = $this->tenant();
        [, $setorB] = $this->tenant();
        $this->svc->entrada($setorA->id, $prodA->id, 100, 10);

        $this->expectException(ValidationException::class);
        // setorB é de outra empresa → deve recusar.
        $this->svc->transferir($setorA->id, $setorB->id, $prodA->id, 10);
    }

    public function test_transferencia_dentro_da_empresa_preserva_total(): void
    {
        [$empresa, $setor1, $produto] = $this->tenant();
        $setor2 = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $this->svc->entrada($setor1->id, $produto->id, 100, 10);

        $this->svc->transferir($setor1->id, $setor2->id, $produto->id, 40);

        $this->assertSame(60.0, $this->svc->saldoDerivado($setor1->id, $produto->id));
        $this->assertSame(40.0, $this->svc->saldoDerivado($setor2->id, $produto->id));
        // Total da empresa preservado.
        $total = (float) EstoqueHistorico::withoutTenant()->where('empresa_id', $empresa->id)->sum('quantidade');
        $this->assertSame(100.0, round($total, 3));
    }
}
