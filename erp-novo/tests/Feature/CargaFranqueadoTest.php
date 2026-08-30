<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Venda\CargaFranqueadoService;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F5 — mercadoria em poder do franqueado.
 *
 * A auditoria apontou que isso não existia: "carga" no CentralService é número
 * de pedidos, não botijão. O cliente usa os DOIS modelos (consignação e compra),
 * fixos por pessoa — e a diferença decide de quem é a mercadoria na rua.
 */
class CargaFranqueadoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Setor $deposito;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->deposito = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Deposito Central',
        ]);
        $this->produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'preco_venda' => 100, 'descricao' => 'Botijao 13kg',
        ]);

        app(EstoqueService::class)->entrada($this->deposito->id, $this->produto->id, 500, 70);
    }

    private function franqueado(?string $modo): Colaborador
    {
        return Colaborador::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Joao Franqueado', 'vinculo' => 'franqueado', 'modo_estoque' => $modo,
        ]);
    }

    private function servico(): CargaFranqueadoService
    {
        return app(CargaFranqueadoService::class);
    }

    private function saldoDeposito(): float
    {
        return app(EstoqueService::class)->saldoDerivado($this->deposito->id, $this->produto->id);
    }

    public function test_carga_tira_do_deposito_e_poe_em_poder_do_franqueado(): void
    {
        $f = $this->franqueado('consignacao');

        $r = $this->servico()->carregar($f, $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 20],
        ]);

        $this->assertSame(480.0, $this->saldoDeposito());

        $emPoder = $this->servico()->emPoder($f->fresh());
        $this->assertCount(1, $emPoder);
        $this->assertSame(20.0, $emPoder[0]['quantidade']);
        $this->assertSame('consignacao', $r['modo']);
    }

    public function test_setor_do_franqueado_nasce_na_primeira_carga(): void
    {
        $f = $this->franqueado('compra');
        $this->assertNull($f->setor_estoque_id);

        $this->servico()->carregar($f, $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 10],
        ]);

        // Criado uma vez e reaproveitado: a segunda carga não gera outro setor.
        $primeiro = $f->fresh()->setor_estoque_id;
        $this->assertNotNull($primeiro);

        $this->servico()->carregar($f->fresh(), $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 5],
        ]);

        $this->assertSame($primeiro, $f->fresh()->setor_estoque_id);
    }

    public function test_consignacao_aceita_devolucao_do_que_sobrou(): void
    {
        $f = $this->franqueado('consignacao');
        $this->servico()->carregar($f, $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 20],
        ]);

        // Vendeu 12, devolve 8.
        $this->servico()->devolver($f->fresh(), $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 8],
        ]);

        $this->assertSame(488.0, $this->saldoDeposito());
        $this->assertSame(12.0, $this->servico()->emPoder($f->fresh())[0]['quantidade']);
    }

    public function test_compra_nao_aceita_devolucao(): void
    {
        $f = $this->franqueado('compra');
        $this->servico()->carregar($f, $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 20],
        ]);

        // A mercadoria é dele: "devolver" seria compra de volta, outra operação
        // com efeito fiscal próprio.
        $this->expectException(\DomainException::class);
        $this->servico()->devolver($f->fresh(), $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 5],
        ]);
    }

    public function test_sem_modo_definido_nao_movimenta(): void
    {
        // Fail-closed: adivinhar erraria de quem é o botijão na rua.
        $this->expectException(\DomainException::class);
        $this->servico()->carregar($this->franqueado(null), $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 10],
        ]);
    }

    public function test_em_poder_vem_vazio_antes_da_primeira_carga(): void
    {
        $this->assertSame([], $this->servico()->emPoder($this->franqueado('consignacao')));
    }

    public function test_nao_carrega_mais_do_que_ha_no_deposito(): void
    {
        // Quem barra é o EstoqueService (saldo insuficiente) — a carga não
        // reimplementa essa regra.
        $this->expectException(ValidationException::class);
        $this->servico()->carregar($this->franqueado('consignacao'), $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 9999],
        ]);
    }

    public function test_rotas_da_central_carregam_e_devolvem(): void
    {
        $f = $this->franqueado('consignacao');
        $operador = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            // bypass de RBAC: o foco aqui é a rota, não a permissão
        ]);

        $this->actingAs($operador, 'sanctum')
            ->postJson("/api/admin/franqueados/{$f->id}/carga", [
                'setor_origem_id' => $this->deposito->id,
                'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 15]],
            ])
            ->assertCreated();

        $this->assertSame(485.0, $this->saldoDeposito());

        $this->actingAs($operador, 'sanctum')
            ->getJson("/api/admin/franqueados/{$f->id}/estoque")
            ->assertOk()
            ->assertJsonPath('data.modo_estoque', 'consignacao')
            ->assertJsonPath('data.itens.0.quantidade', 15);

        $this->actingAs($operador, 'sanctum')
            ->postJson("/api/admin/franqueados/{$f->id}/devolucao", [
                'setor_origem_id' => $this->deposito->id,
                'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 5]],
            ])
            ->assertOk();

        $this->assertSame(490.0, $this->saldoDeposito());
    }

    public function test_franqueado_ve_o_proprio_estoque_no_app(): void
    {
        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $f = Colaborador::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $user->id, 'vinculo' => 'franqueado', 'modo_estoque' => 'consignacao',
        ]);
        $this->servico()->carregar($f, $this->deposito->id, [
            ['produto_id' => $this->produto->id, 'quantidade' => 7],
        ]);

        $token = $user->createToken('app', ['role:entregador'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/app/v1/entregador/estoque')
            ->assertOk()
            ->assertJsonPath('data.itens.0.quantidade', 7);
    }
}
