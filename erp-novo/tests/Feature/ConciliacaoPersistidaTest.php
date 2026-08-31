<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\ConciliacaoService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Financeiro\ConciliacaoLancamento;
use App\Models\Financeiro\OrigemMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F5-04 — a conciliação deixa de ser efêmera.
 *
 * ## O que se descobriu ao medir
 *
 * `ConciliacaoService` calculava tudo certo e **não gravava nada**. O parser já
 * extraía o `FITID` — o identificador único que o banco dá a cada lançamento — e
 * o campo morria na resposta HTTP.
 *
 * Três consequências, todas silenciosas:
 *
 *  - subir o mesmo extrato de novo reconciliava do zero, sem saber que já tinha
 *    visto aqueles lançamentos (e reprocessar o OFX do mês é rotina);
 *  - não havia histórico: nenhuma forma de responder por que aquela transação
 *    casou com aquele movimento, nem com que tolerância;
 *  - a exceção manual não deixava rastro — e é justamente a decisão humana que
 *    alguém vai querer revisar quando o mês não fechar.
 *
 * ## A regra que o teste protege
 *
 * O algoritmo pode recalcular o quanto quiser o que ele mesmo decidiu. O que
 * uma **pessoa** decidiu, ele não desfaz: um par feito à mão que voltasse a ser
 * desfeito na rodada seguinte deixaria o operador vendo seu trabalho sumir sem
 * explicação.
 */
class ConciliacaoPersistidaTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<array<string,string>>  $trns */
    private function ofx(array $trns): string
    {
        $blocos = '';
        foreach ($trns as $t) {
            $blocos .= "<STMTTRN><TRNTYPE>{$t['tipo']}</TRNTYPE><DTPOSTED>{$t['data']}</DTPOSTED>"
                ."<TRNAMT>{$t['valor']}</TRNAMT><FITID>{$t['id']}</FITID><MEMO>{$t['memo']}</MEMO></STMTTRN>";
        }

        return "OFXHEADER:100\n<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>{$blocos}</BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>";
    }

    /** @return array{Empresa, int, string} empresa, conta, ofx com um par e um pendente */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $caixa = app(CaixaService::class);
        $conta = $caixa->criarConta([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Banco', 'saldo_inicial' => 0,
        ]);
        $caixa->movimentar($conta->id, 150.00, CaixaService::AJUSTE, $empresa->id, [
            'origem' => 't', 'datahora' => '2026-06-10 09:00:00',
        ]);

        $ofx = $this->ofx([
            ['tipo' => 'CREDIT', 'data' => '20260610', 'valor' => '150.00', 'id' => 'B1', 'memo' => 'Dep'],
            ['tipo' => 'CREDIT', 'data' => '20260615', 'valor' => '999.00', 'id' => 'B2', 'memo' => 'Sem par'],
        ]);

        return [$empresa, $conta->id, $ofx];
    }

    /** @return array<string,mixed> */
    private function conciliar(Empresa $e, int $contaId, string $ofx): array
    {
        return app(ConciliacaoService::class)->conciliar($contaId, $e->id, $ofx, '2026-06-01', '2026-06-30');
    }

    public function test_a_rodada_persiste_os_dois_lados(): void
    {
        [$empresa, $contaId, $ofx] = $this->cenario();
        $this->conciliar($empresa, $contaId, $ofx);

        $casado = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B1')->firstOrFail();
        $pendente = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B2')->firstOrFail();

        $this->assertSame(OrigemMatch::AUTOMATICO->value, $casado->origem_match);
        $this->assertNotNull($casado->conta_movimento_id, 'o par tem de apontar para o movimento');
        $this->assertSame('150.00', (string) $casado->valor_banco, 'o lado do BANCO fica congelado');
        $this->assertSame(2, $casado->tolerancia_dias, 'com que folga casou faz parte da explicação');

        $this->assertSame(OrigemMatch::PENDENTE->value, $pendente->origem_match);
        $this->assertNull($pendente->conta_movimento_id, 'pendente é o que o operador precisa resolver');
    }

    /** Reprocessar o mesmo extrato é rotina — não pode duplicar. */
    public function test_reprocessar_o_mesmo_ofx_nao_duplica(): void
    {
        [$empresa, $contaId, $ofx] = $this->cenario();

        $this->conciliar($empresa, $contaId, $ofx);
        $this->conciliar($empresa, $contaId, $ofx);
        $this->conciliar($empresa, $contaId, $ofx);

        $this->assertSame(2, ConciliacaoLancamento::withoutTenant()->count());
        $this->assertSame(1, ConciliacaoLancamento::withoutTenant()->where('fitid', 'B1')->count());
    }

    /**
     * O coração da tarefa: o algoritmo não desfaz o que uma pessoa decidiu.
     *
     * Sem esta regra, o operador casa um lançamento à mão, o gestor sobe o
     * extrato de novo no dia seguinte, e o trabalho dele desaparece — sem erro,
     * sem aviso, sem forma de descobrir por quê.
     */
    public function test_rodada_nova_nao_reverte_decisao_manual(): void
    {
        [$empresa, $contaId, $ofx] = $this->cenario();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->conciliar($empresa, $contaId, $ofx);

        // O operador casa o pendente à mão com o movimento que existe.
        $pendente = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B2')->firstOrFail();
        $movimentoId = (int) ConciliacaoLancamento::withoutTenant()
            ->where('fitid', 'B1')->value('conta_movimento_id');

        app(ConciliacaoService::class)->casarManualmente(
            $pendente->id, $movimentoId, $empresa->id, $user->id, 'tarifa lançada com outro valor',
        );

        // Rodada nova, mesmo extrato: o algoritmo diria PENDENTE de novo.
        $this->conciliar($empresa, $contaId, $ofx);

        $depois = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B2')->firstOrFail();

        $this->assertSame(OrigemMatch::MANUAL->value, $depois->origem_match, 'a decisão humana sobrevive à rodada');
        $this->assertSame($movimentoId, (int) $depois->conta_movimento_id);
        $this->assertSame('tarifa lançada com outro valor', $depois->motivo);
        $this->assertSame($user->id, (int) $depois->decidido_por, 'quem decidiu fica registrado');
    }

    /** Desfazer também é decisão: não volta a casar sozinho na rodada seguinte. */
    public function test_par_desfeito_nao_volta_a_casar_sozinho(): void
    {
        [$empresa, $contaId, $ofx] = $this->cenario();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->conciliar($empresa, $contaId, $ofx);
        $casado = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B1')->firstOrFail();

        app(ConciliacaoService::class)->desfazer($casado->id, $empresa->id, $user->id, 'não é este lançamento');

        $this->conciliar($empresa, $contaId, $ofx);

        $depois = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B1')->firstOrFail();

        $this->assertSame(OrigemMatch::DESFEITO->value, $depois->origem_match);
        $this->assertNull($depois->conta_movimento_id);
        $this->assertSame('não é este lançamento', $depois->motivo);
    }

    /**
     * DESFEITO não é PENDENTE: "nunca casou" e "casou e alguém desfez" são fatos
     * diferentes, e o segundo é o que se quer investigar.
     */
    public function test_desfeito_e_estado_proprio_e_nao_se_confunde_com_pendente(): void
    {
        [$empresa, $contaId, $ofx] = $this->cenario();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->conciliar($empresa, $contaId, $ofx);
        $casado = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B1')->firstOrFail();
        app(ConciliacaoService::class)->desfazer($casado->id, $empresa->id, $user->id, 'x');

        $this->assertSame(1, ConciliacaoLancamento::withoutTenant()
            ->where('origem_match', OrigemMatch::DESFEITO->value)->count());
        $this->assertSame(1, ConciliacaoLancamento::withoutTenant()
            ->where('origem_match', OrigemMatch::PENDENTE->value)->count());
    }

    /**
     * A fronteira: um movimento de OUTRA conta não pode ser casado aqui.
     *
     * Casar um débito da conta A com um movimento da conta B fecharia as duas
     * erradas — e o erro só apareceria no fim do mês, nas duas ao mesmo tempo.
     */
    public function test_nao_casa_com_movimento_de_outra_conta(): void
    {
        [$empresa, $contaId, $ofx] = $this->cenario();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $caixa = app(CaixaService::class);
        $outra = $caixa->criarConta([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Outra conta', 'saldo_inicial' => 0,
        ]);
        $movOutra = $caixa->movimentar($outra->id, 999.00, CaixaService::AJUSTE, $empresa->id, [
            'origem' => 't', 'datahora' => '2026-06-15 09:00:00',
        ]);

        $this->conciliar($empresa, $contaId, $ofx);
        $pendente = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B2')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(ConciliacaoService::class)->casarManualmente(
            $pendente->id, $movOutra->id, $empresa->id, $user->id, 'tentativa indevida',
        );
    }

    /** E outra EMPRESA, muito menos — é a fronteira que o SaaS inteiro protege. */
    public function test_nao_concilia_lancamento_de_outra_empresa(): void
    {
        [$empresa, $contaId, $ofx] = $this->cenario();
        $this->conciliar($empresa, $contaId, $ofx);
        $lancamento = ConciliacaoLancamento::withoutTenant()->where('fitid', 'B1')->firstOrFail();

        $intrusa = Empresa::factory()->create();

        $this->expectException(ValidationException::class);
        app(ConciliacaoService::class)->desfazer($lancamento->id, $intrusa->id, null, 'nem deveria enxergar');
    }
}
