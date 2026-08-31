<?php

namespace Tests\Feature;

use App\Domain\Estoque\ConferenciaDeSaldo;
use App\Domain\Estoque\EstoqueService;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4-02 — a projeção de saldo é recalculável, e a divergência é auditável.
 *
 * `estoquesaldos` é a projeção, mantida na mesma transação do movimento. Isso
 * está certo e é o que dá o saldo em O(1) na tela.
 *
 * O que faltava é a outra metade: **provar que ela bate com o ledger**. Uma
 * projeção sem conferência é uma afirmação sem prova — ela pode divergir por um
 * bug corrigido meses atrás, por um `UPDATE` manual em produção, por uma
 * migração de dados, e ninguém descobre.
 *
 * O serviço **não ajusta**, e isso é o ponto. Se a projeção diz 10 e o ledger
 * soma 8, há duas hipóteses: a projeção está errada, ou falta um movimento no
 * ledger (mercadoria que entrou sem registro). Sobrescrever resolve a tela e
 * apaga a pergunta — e se a resposta era a segunda, a mercadoria some da
 * contabilidade sem deixar rastro.
 */
class ConferenciaDeSaldoTest extends TestCase
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

    /** O caminho normal fecha: a projeção é mantida na transação do movimento. */
    public function test_operacao_normal_fecha_com_o_ledger(): void
    {
        [$empresa, $setor, $produto] = $this->cenario();
        $servico = app(EstoqueService::class);

        $servico->entrada($setor->id, $produto->id, 10, 5);
        $servico->saida($setor->id, $produto->id, 3);
        $servico->entrada($setor->id, $produto->id, 7, 5);

        $this->assertTrue(app(ConferenciaDeSaldo::class)->fecha($empresa->id));
    }

    /**
     * O caso que a conferência existe para achar: alguém mexeu na projeção sem
     * passar pelo ledger — um `UPDATE` manual em produção, uma migração.
     */
    public function test_projecao_adulterada_e_detectada(): void
    {
        [$empresa, $setor, $produto] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 5);

        EstoqueSaldo::withoutTenant()
            ->where('setor_id', $setor->id)->where('produto_id', $produto->id)
            ->update(['quantidade' => 99]);

        $divergencias = app(ConferenciaDeSaldo::class)->divergencias($empresa->id);

        $this->assertCount(1, $divergencias);
        $this->assertSame(99.0, $divergencias[0]['projetado']);
        $this->assertSame(10.0, $divergencias[0]['ledger']);
        $this->assertSame(89.0, $divergencias[0]['diferenca']);
    }

    /**
     * O caso MAIS grave: movimento no ledger sem linha de projeção.
     *
     * É mercadoria movimentada que não aparece em lugar nenhum na tela — e por
     * isso a comparação percorre a UNIÃO das duas chaves, não só as da projeção.
     */
    public function test_movimento_sem_projecao_tambem_e_detectado(): void
    {
        [$empresa, $setor, $produto] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 5);

        EstoqueSaldo::withoutTenant()
            ->where('setor_id', $setor->id)->where('produto_id', $produto->id)
            ->delete();

        $divergencias = app(ConferenciaDeSaldo::class)->divergencias($empresa->id);

        $this->assertCount(1, $divergencias);
        $this->assertSame(0.0, $divergencias[0]['projetado']);
        $this->assertSame(10.0, $divergencias[0]['ledger']);
    }

    /** A conferência NÃO ajusta — é o gate da fase. */
    public function test_conferir_nao_altera_a_projecao(): void
    {
        [$empresa, $setor, $produto] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 5);

        EstoqueSaldo::withoutTenant()
            ->where('setor_id', $setor->id)->where('produto_id', $produto->id)
            ->update(['quantidade' => 99]);

        app(ConferenciaDeSaldo::class)->divergencias($empresa->id);

        $this->assertSame(
            99.0,
            (float) EstoqueSaldo::withoutTenant()
                ->where('setor_id', $setor->id)->where('produto_id', $produto->id)
                ->value('quantidade'),
            'a divergência continua lá: quem decide é gente, com o relatório na mão',
        );
    }

    /** A conferência é por empresa: divergência de uma não polui a outra. */
    public function test_conferencia_nao_atravessa_empresa(): void
    {
        [$empresaA, $setorA, $produtoA] = $this->cenario();
        [$empresaB, $setorB, $produtoB] = $this->cenario();
        $servico = app(EstoqueService::class);

        $servico->entrada($setorA->id, $produtoA->id, 10, 5);
        $servico->entrada($setorB->id, $produtoB->id, 10, 5);

        EstoqueSaldo::withoutTenant()
            ->where('setor_id', $setorA->id)->update(['quantidade' => 99]);

        $conferencia = app(ConferenciaDeSaldo::class);

        $this->assertFalse($conferencia->fecha($empresaA->id));
        $this->assertTrue($conferencia->fecha($empresaB->id), 'a empresa B não tem nada a ver com isso');
    }

    /** Arredondamento de 3 casas não é divergência de estoque. */
    public function test_diferenca_abaixo_da_tolerancia_nao_e_divergencia(): void
    {
        [$empresa, $setor, $produto] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 5);

        EstoqueSaldo::withoutTenant()
            ->where('setor_id', $setor->id)->where('produto_id', $produto->id)
            ->update(['quantidade' => 10.0004]);

        $this->assertTrue(app(ConferenciaDeSaldo::class)->fecha($empresa->id));
    }

    /** O comando reprova quando há divergência — serve de portão, não só de relatório. */
    public function test_comando_reprova_com_divergencia(): void
    {
        [$empresa, $setor, $produto] = $this->cenario();
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 10, 5);

        $this->artisan('estoque:conferir --empresa='.$empresa->id)->assertSuccessful();

        EstoqueSaldo::withoutTenant()
            ->where('setor_id', $setor->id)->update(['quantidade' => 99]);

        $this->artisan('estoque:conferir --empresa='.$empresa->id)->assertFailed();
    }

    /** Empresa sem movimento nenhum fecha — vazio bate com vazio. */
    public function test_empresa_sem_movimento_fecha(): void
    {
        [$empresa] = $this->cenario();

        $this->assertTrue(app(ConferenciaDeSaldo::class)->fecha($empresa->id));
        $this->assertSame(0, EstoqueHistorico::withoutTenant()->count());
    }
}
