<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use Tests\Caracterizacao\Support\FixturesFiscais;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Processors\EstoqueProcessor;
use App\Estoquesetorhistorico;
use App\Estoquefisico;
use App\Estoquefisicosetor;
use App\Estoquesetor;
use App\Services\CarbonCustom as Carbon;

/**
 * Caracterização (golden master) — FASE 1, leva 5.
 *
 * Exercita EstoqueProcessor::efetivarEstoquefisico: ajusta o estoque do sistema
 * à contagem física (gera ENTRADA/SAÍDA pela diferença) e marca efetivado=1.
 * Fixa o ajuste que o motor faz hoje, para a refatoração (Fase 4) não alterar.
 *
 * Desliga o event dispatcher (lib de auditoria chama Event::fire(), removido).
 *
 * PHPUnit 7.5 / Laravel 5.4 / PHP 7.4.
 */
class EstoqueFisicoTest extends TestCase
{
    use DatabaseTransactions;
    use FixturesFiscais;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
    }

    /**
     * Sistema com 10; contagem física 8 → SAÍDA de 2; estoque final 8 e
     * estoque físico marcado como efetivado.
     */
    public function testEfetivarAjustaEstoqueAContagemFisica()
    {
        $this->criarCenarioFiscal();
        $user = $this->criarUsuarioLogado();

        // Estoque inicial: ENTRADA 10.
        $proc = new EstoqueProcessor();
        $hist = new Estoquesetorhistorico();
        $hist->user_id = $user->id;
        $hist->setor_id = $this->setor->id;
        $hist->produto_id = $this->produto->id;
        $hist->movimentacao = 'ENTRADA';
        $hist->quantidade = 10;
        $hist->motivo = 'Carga inicial';
        $hist->datahora = Carbon::now();
        $hist->datahoracompetencia = Carbon::now();
        $hist->entidade = 'Teste';
        $hist->entidade_id = 1;
        $hist->grupo_id = $this->empresa->grupo_id;
        $hist->empresa_id = $this->empresa->id;
        $this->assertTrue($proc->movimentarEstoque([$hist]), implode('; ', $proc->getErrors()));

        // Estoque físico: contagem 8 (diferença 2 a menos → SAÍDA).
        $ef = new Estoquefisico();
        $ef->grupo_id = $this->empresa->grupo_id;
        $ef->empresa_id = $this->empresa->id;
        $ef->datacompetencia = Carbon::now()->toDateString();
        $ef->user_id = $user->id;
        $ef->efetivado = 0;
        $ef->save();

        $efs = new Estoquefisicosetor();
        $efs->grupo_id = $this->empresa->grupo_id;
        $efs->empresa_id = $this->empresa->id;
        $efs->setor_id = $this->setor->id;
        $efs->produto_id = $this->produto->id;
        $efs->estoquefisico_id = $ef->id;
        $efs->quantidadesistema = 10;
        $efs->quantidadefisica = 8;
        $efs->quantidadediferenca = 2;
        $efs->estoquezerar = 0;
        $efs->save();

        $procEf = new EstoqueProcessor();
        $ok = $procEf->efetivarEstoquefisico($ef);
        $this->assertTrue($ok, 'efetivar falhou: ' . implode('; ', $procEf->getErrors()));

        // Estoque do setor ajustado para 8.
        $setor = Estoquesetor::where('produto_id', $this->produto->id)
            ->where('setor_id', $this->setor->id)->first();
        $this->assertNotNull($setor);
        $this->assertEqualsWithDelta(8, (float) $setor->quantidade, 0.0001);

        // Estoque físico marcado como efetivado.
        $ef->refresh();
        $this->assertEquals(1, (int) $ef->efetivado);
    }

    /**
     * Efetivar duas vezes falha (já efetivado) — regra de idempotência do motor.
     */
    public function testEfetivarJaEfetivadoFalha()
    {
        $this->criarCenarioFiscal();
        $user = $this->criarUsuarioLogado();

        $ef = new Estoquefisico();
        $ef->grupo_id = $this->empresa->grupo_id;
        $ef->empresa_id = $this->empresa->id;
        $ef->datacompetencia = Carbon::now()->toDateString();
        $ef->user_id = $user->id;
        $ef->efetivado = 1; // já efetivado
        $ef->save();

        $proc = new EstoqueProcessor();
        $ok = $proc->efetivarEstoquefisico($ef);

        $this->assertFalse($ok);
        $this->assertNotEmpty($proc->getErrors());
    }
}
