<?php

namespace Tests\Feature;

use App\Domain\Satelite\ComodatoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoContrato;
use App\Models\Satelite\ComodatoMovimento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Devolução PARCIAL com contrato reemitido.
 *
 * **O que estava errado.** O saldo aceitava devolução parcial, mas o papel não:
 * o contrato era gerado do estado atual, então depois de receber 2 de 5 o
 * documento simplesmente mudava — a via que o cliente tinha assinado deixava de
 * existir. Sem documento que descrevesse a posse nova, o operador dava baixa
 * total na mão para "acertar o papel", e o registro de que 3 continuavam com o
 * cliente se perdia.
 *
 * **O que estes testes fixam.**
 *
 * 1. Devolver parcialmente REEMITE o contrato numa versão nova, com o saldo já
 *    descontado, e a versão anterior continua existindo com os números que
 *    tinha quando foi assinada.
 * 2. Devolução TOTAL encerra e não emite versão nova — não há posse a
 *    descrever.
 * 3. Devolução errada se corrige por ESTORNO, não por edição: o saldo volta, o
 *    estoque baixa de novo, e a entrega original permanece no extrato.
 * 4. `quantidade_devolvida` é DERIVADA do extrato, não uma segunda verdade.
 */
class ComodatoDevolucaoParcialTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Comodato} */
    private function cenario(float $quantidade = 5): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Padaria do Zé',
            'cpf' => '12345678909',
            'cliente' => true,
        ]);
        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Vasilhame P45',
            'vasilhame_retornavel' => true,
            'ativo' => true,
        ]);

        $comodato = app(ComodatoService::class)->emprestar([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'produto_id' => $produto->id,
            'quantidade' => $quantidade,
        ], $user->id);

        return [$user, $comodato];
    }

    public function test_emprestimo_registra_movimento_e_contrato_v1(): void
    {
        [, $comodato] = $this->cenario();

        $mov = ComodatoMovimento::where('comodato_id', $comodato->id)->sole();
        $this->assertSame(ComodatoMovimento::EMPRESTIMO, $mov->tipo);
        $this->assertEquals(5, (float) $mov->saldo_apos);

        $versao = ComodatoContrato::where('comodato_id', $comodato->id)->sole();
        $this->assertSame(1, (int) $versao->versao);
        $this->assertEquals(5, (float) $versao->quantidade_em_posse);
    }

    public function test_devolucao_parcial_reemite_contrato_com_saldo_descontado(): void
    {
        [$user, $comodato] = $this->cenario();

        app(ComodatoService::class)->devolver($comodato, 2, $user->id);

        $comodato->refresh();
        $this->assertSame('PARCIAL', $comodato->situacao);
        $this->assertEquals(3, app(ComodatoService::class)->emPosse($comodato));

        // O ponto da mudança: existe uma versão 2 do contrato descrevendo os 3
        // que continuam com o cliente.
        $v2 = ComodatoContrato::where('comodato_id', $comodato->id)->where('versao', 2)->sole();
        $this->assertSame(ComodatoContrato::DEVOLUCAO_PARCIAL, $v2->motivo);
        $this->assertEquals(3, (float) $v2->quantidade_em_posse);
        $this->assertEquals(2, (float) $v2->quantidade_devolvida);

        // E a versão 1 — a que foi assinada — continua dizendo o que dizia.
        $v1 = ComodatoContrato::where('comodato_id', $comodato->id)->where('versao', 1)->sole();
        $this->assertEquals(5, (float) $v1->quantidade_em_posse);
        $this->assertEquals(0, (float) $v1->quantidade_devolvida);
    }

    public function test_devolucao_total_encerra_e_nao_emite_versao_nova(): void
    {
        [$user, $comodato] = $this->cenario();

        app(ComodatoService::class)->devolver($comodato, 5, $user->id);

        $comodato->refresh();
        $this->assertSame('DEVOLVIDO', $comodato->situacao);
        $this->assertNotNull($comodato->data_devolucao);

        // Nada a contratar: emitir uma versão 2 seria papel afirmando posse
        // que não existe.
        $this->assertSame(1, ComodatoContrato::where('comodato_id', $comodato->id)->count());
    }

    public function test_devolucoes_sucessivas_ate_zerar(): void
    {
        [$user, $comodato] = $this->cenario();
        $service = app(ComodatoService::class);

        $service->devolver($comodato, 2, $user->id);
        $service->devolver($comodato->refresh(), 2, $user->id);
        $service->devolver($comodato->refresh(), 1, $user->id);

        $comodato->refresh();
        $this->assertSame('DEVOLVIDO', $comodato->situacao);
        $this->assertEquals(0, $service->emPosse($comodato));

        // v1 (empréstimo) + v2 e v3 (as duas parciais). A terceira devolução
        // zerou, então não gerou versão.
        $this->assertSame(3, ComodatoContrato::where('comodato_id', $comodato->id)->count());
    }

    public function test_devolver_mais_que_o_pendente_bloqueia(): void
    {
        [$user, $comodato] = $this->cenario();
        $service = app(ComodatoService::class);

        $service->devolver($comodato, 4, $user->id);

        $this->expectException(ValidationException::class);
        $service->devolver($comodato->refresh(), 2, $user->id);
    }

    public function test_estorno_devolve_o_saldo_sem_apagar_a_entrega(): void
    {
        [$user, $comodato] = $this->cenario();
        $service = app(ComodatoService::class);

        $service->devolver($comodato, 2, $user->id);
        $devolucao = ComodatoMovimento::where('comodato_id', $comodato->id)
            ->where('tipo', ComodatoMovimento::DEVOLUCAO)->sole();

        $service->estornar($devolucao, $user->id);

        $comodato->refresh();
        $this->assertEquals(5, $service->emPosse($comodato));
        $this->assertSame('ATIVO', $comodato->situacao);

        // A devolução original NÃO some: ela aconteceu, e o extrato é a prova.
        $this->assertDatabaseHas('comodato_movimentos', [
            'id' => $devolucao->id,
            'tipo' => ComodatoMovimento::DEVOLUCAO,
        ]);
        $this->assertDatabaseHas('comodato_movimentos', [
            'estorna_id' => $devolucao->id,
            'tipo' => ComodatoMovimento::ESTORNO,
        ]);
    }

    public function test_estornar_duas_vezes_a_mesma_devolucao_bloqueia(): void
    {
        [$user, $comodato] = $this->cenario();
        $service = app(ComodatoService::class);

        $service->devolver($comodato, 2, $user->id);
        $devolucao = ComodatoMovimento::where('comodato_id', $comodato->id)
            ->where('tipo', ComodatoMovimento::DEVOLUCAO)->sole();

        $service->estornar($devolucao, $user->id);

        // Sem esta trava o saldo subiria acima do emprestado.
        $this->expectException(ValidationException::class);
        $service->estornar($devolucao->refresh(), $user->id);
    }

    public function test_estornar_emprestimo_bloqueia(): void
    {
        [$user, $comodato] = $this->cenario();

        $emprestimo = ComodatoMovimento::where('comodato_id', $comodato->id)
            ->where('tipo', ComodatoMovimento::EMPRESTIMO)->sole();

        $this->expectException(ValidationException::class);
        app(ComodatoService::class)->estornar($emprestimo, $user->id);
    }

    public function test_quantidade_devolvida_e_derivada_do_extrato(): void
    {
        [$user, $comodato] = $this->cenario();
        $service = app(ComodatoService::class);

        $service->devolver($comodato, 2, $user->id);

        // Alguém escreve direto no acumulado, fora do serviço.
        $comodato->refresh()->forceFill(['quantidade_devolvida' => 4])->save();

        $r = $service->recalcular($comodato->refresh());

        $this->assertTrue($r['divergiu']);
        $this->assertEquals(2, $r['depois']);
        $this->assertEquals(2, (float) $comodato->refresh()->quantidade_devolvida);
    }

    public function test_recalcular_nao_zera_comodato_do_legado(): void
    {
        [, $comodato] = $this->cenario();

        // O legado trouxe 975 comodatos sem extrato nenhum. Reconstruir o
        // acumulado a partir de "nenhum movimento" daria zero, apagando a
        // devolução que já estava registrada lá.
        ComodatoMovimento::where('comodato_id', $comodato->id)->delete();
        $comodato->forceFill(['quantidade_devolvida' => 3])->save();

        $r = app(ComodatoService::class)->recalcular($comodato->refresh());

        $this->assertFalse($r['divergiu']);
        $this->assertEquals(3, (float) $comodato->refresh()->quantidade_devolvida);
    }

    public function test_comodato_cancelado_nao_recebe_devolucao(): void
    {
        [$user, $comodato] = $this->cenario();
        $comodato->forceFill(['situacao' => 'CANCELADO'])->save();

        $this->expectException(ValidationException::class);
        app(ComodatoService::class)->devolver($comodato->refresh(), 1, $user->id);
    }

    /**
     * O ETL marca o comodato inativo do legado como `ENCERRADO` sem preencher
     * `quantidade_devolvida` — o saldo aparenta estar todo com o cliente.
     * Aceitar devolução aqui daria ENTRADA em estoque de vasilhame que voltou
     * anos atrás, inflando o saldo real.
     */
    public function test_comodato_encerrado_do_legado_nao_recebe_devolucao(): void
    {
        [$user, $comodato] = $this->cenario();
        $comodato->forceFill(['situacao' => 'ENCERRADO'])->save();

        $this->expectException(ValidationException::class);
        app(ComodatoService::class)->devolver($comodato->refresh(), 1, $user->id);
    }
}
