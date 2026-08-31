<?php

namespace Tests\Feature;

use App\Domain\Satelite\ComodatoService;
use App\Domain\Satelite\ConferenciaDeCustodia;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4-04 — o saldo de custódia passa a ter prova.
 *
 * O ledger patrimonial já existia (`comodato_movimentos`, com tipo, quantidade,
 * `saldo_apos`, estorno explícito e ator) e o `sentido` já separa o concedido do
 * recebido — `fornecedor` não decide direção.
 *
 * O que faltava é o mesmo que faltava no estoque: **conferir a projeção contra o
 * ledger**. `comodatos.quantidade` menos `quantidade_devolvida` é a projeção, e
 * ninguém somava os movimentos para verificar.
 *
 * Aqui o que está em jogo é patrimônio em poder de terceiro: um saldo errado
 * significa vasilhame que a revenda acha que tem — ou que acha que emprestou e
 * não emprestou.
 */
class ConferenciaDeCustodiaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, Cliente, Produto} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $cliente, $produto];
    }

    private function emprestar(Empresa $e, Cliente $c, Produto $p, float $qtd): Comodato
    {
        return app(ComodatoService::class)->emprestar([
            'empresa_id' => $e->id,
            'grupo_id' => $e->grupo_id,
            'cliente_id' => $c->id,
            'produto_id' => $p->id,
            'quantidade' => $qtd,
        ]);
    }

    /** O caminho normal fecha: a projeção acompanha cada movimento. */
    public function test_emprestimo_e_devolucao_fecham_com_o_ledger(): void
    {
        [$empresa, $cliente, $produto] = $this->cenario();

        $comodato = $this->emprestar($empresa, $cliente, $produto, 10);
        app(ComodatoService::class)->devolver($comodato, 4);

        $this->assertTrue(app(ConferenciaDeCustodia::class)->fecha($empresa->id));
    }

    /**
     * O caso que a conferência existe para achar: a projeção foi mexida sem
     * passar pelo ledger.
     */
    public function test_projecao_adulterada_e_detectada(): void
    {
        [$empresa, $cliente, $produto] = $this->cenario();
        $comodato = $this->emprestar($empresa, $cliente, $produto, 10);

        Comodato::withoutTenant()->whereKey($comodato->id)->update(['quantidade' => 99]);

        $divergencias = app(ConferenciaDeCustodia::class)->divergencias($empresa->id);

        $this->assertCount(1, $divergencias);
        $this->assertSame(99.0, $divergencias[0]['projetado']);
        $this->assertSame(10.0, $divergencias[0]['ledger']);
    }

    /**
     * Estorno: o movimento estornado e o estorno se anulam.
     *
     * Somar os dois com o mesmo sinal contaria o empréstimo duas vezes — e é o
     * tipo de erro que faria a conferência acusar divergência onde não há,
     * transformando o relatório em ruído.
     */
    public function test_estorno_de_devolucao_nao_desequilibra_a_conta(): void
    {
        [$empresa, $cliente, $produto] = $this->cenario();
        $servico = app(ComodatoService::class);

        $comodato = $this->emprestar($empresa, $cliente, $produto, 10);
        $servico->devolver($comodato->refresh(), 4);

        // `estornar` recebe o MOVIMENTO a desfazer, nao o comodato.
        $devolucao = ComodatoMovimento::withoutTenant()
            ->where('comodato_id', $comodato->id)
            ->where('tipo', ComodatoMovimento::DEVOLUCAO)
            ->latest('id')->firstOrFail();

        $servico->estornar($devolucao);

        $this->assertTrue(
            app(ConferenciaDeCustodia::class)->fecha($empresa->id),
            'depois do estorno, projeção e ledger voltam a bater',
        );
    }

    /** A conferência NÃO ajusta — mesmo princípio da F4-02. */
    public function test_conferir_nao_altera_a_projecao(): void
    {
        [$empresa, $cliente, $produto] = $this->cenario();
        $comodato = $this->emprestar($empresa, $cliente, $produto, 10);

        Comodato::withoutTenant()->whereKey($comodato->id)->update(['quantidade' => 99]);
        app(ConferenciaDeCustodia::class)->divergencias($empresa->id);

        $this->assertSame(
            '99.000',
            (string) Comodato::withoutTenant()->whereKey($comodato->id)->value('quantidade'),
            'a divergência continua lá: quem decide é gente',
        );
    }

    /** A conferência é por empresa. */
    public function test_conferencia_nao_atravessa_empresa(): void
    {
        [$empresaA, $clienteA, $produtoA] = $this->cenario();
        [$empresaB, $clienteB, $produtoB] = $this->cenario();

        $comodatoA = $this->emprestar($empresaA, $clienteA, $produtoA, 10);
        $this->emprestar($empresaB, $clienteB, $produtoB, 10);

        Comodato::withoutTenant()->whereKey($comodatoA->id)->update(['quantidade' => 99]);

        $conferencia = app(ConferenciaDeCustodia::class);

        $this->assertFalse($conferencia->fecha($empresaA->id));
        $this->assertTrue($conferencia->fecha($empresaB->id));
    }

    /** O comando unico reprova tambem por divergencia de CUSTODIA. */
    public function test_comando_reprova_por_divergencia_de_custodia(): void
    {
        [$empresa, $cliente, $produto] = $this->cenario();
        $comodato = $this->emprestar($empresa, $cliente, $produto, 10);

        $this->artisan('estoque:conferir --empresa='.$empresa->id)->assertSuccessful();

        Comodato::withoutTenant()->whereKey($comodato->id)->update(['quantidade' => 99]);

        $this->artisan('estoque:conferir --empresa='.$empresa->id)->assertFailed();
    }

    /** Empresa sem comodato fecha — vazio bate com vazio. */
    public function test_empresa_sem_comodato_fecha(): void
    {
        [$empresa] = $this->cenario();

        $this->assertTrue(app(ConferenciaDeCustodia::class)->fecha($empresa->id));
    }
}
