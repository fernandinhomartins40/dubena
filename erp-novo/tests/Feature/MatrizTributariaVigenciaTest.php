<?php

namespace Tests\Feature;

use App\Domain\Fiscal\ResolucaoTributariaService;
use App\Models\Empresa;
use App\Models\Fiscal\NfImposto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F5-07 — a matriz tributária versionada por vigência.
 *
 * ## O que já estava certo, e é bastante
 *
 * A matriz é bem modelada: uma regra por (empresa, operação fiscal, grupo
 * fiscal), com `unique` — então **ambiguidade não acontece**, o banco não deixa.
 * E a ausência já bloqueava a emissão: `FiscalService` lança quando não há
 * regra, em vez de inventar um padrão de São Paulo.
 *
 * ## O que faltava
 *
 * **Alíquota não tinha data.** Editar a regra sobrescrevia a anterior, e com ela
 * sumia a informação de que antes era outra coisa.
 *
 * Não é hipótese: alíquota de ICMS muda por decreto estadual, com data certa, e
 * o GLP tem histórico disso. O que acontecia:
 *
 *  - a revenda edita a regra em 1º de janeiro — correto para as notas novas;
 *  - qualquer nota de dezembro montada de novo passa a calcular com a alíquota
 *    de janeiro. O XML diverge do autorizado;
 *  - a apuração de dezembro, se refeita, sai com o número de agora.
 *
 * Silencioso e plausível, como quase todo defeito que este plano encontrou: o
 * número sai, ninguém desconfia, e a divergência aparece na fiscalização.
 */
class MatrizTributariaVigenciaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, int} empresa e id da operação fiscal */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();

        // `operacoes_fiscais` é por GRUPO (sem `empresa_id`): a natureza da
        // operação é a mesma nas unidades de uma revenda; o que varia por
        // empresa é a tributação, e essa mora em `nf_impostos`.
        $operacaoId = DB::table('operacoes_fiscais')->insertGetId([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Venda de GLP '.uniqid(), 'ativo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$empresa, $operacaoId];
    }

    private function regra(Empresa $e, int $operacaoId, float $aliquota, ?string $inicio, ?string $fim = null): NfImposto
    {
        return NfImposto::query()->create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'operacao_fiscal_id' => $operacaoId, 'grupo_fiscal_id' => null,
            'vigencia_inicio' => $inicio, 'vigencia_fim' => $fim,
            'cst_icms' => '00', 'aliq_icms' => $aliquota,
        ]);
    }

    /**
     * O caso da tarefa: a nota antiga continua sendo calculada com a alíquota
     * que valia quando ela nasceu.
     */
    public function test_a_nota_antiga_usa_a_aliquota_da_epoca(): void
    {
        [$empresa, $operacaoId] = $this->cenario();

        $this->regra($empresa, $operacaoId, 12.0, '2000-01-01', '2025-12-31');
        $this->regra($empresa, $operacaoId, 18.0, '2026-01-01');

        $servico = app(ResolucaoTributariaService::class);

        $dezembro = $servico->regraPara($empresa->id, $operacaoId, null, '2025-12-15');
        $janeiro = $servico->regraPara($empresa->id, $operacaoId, null, '2026-01-15');

        $this->assertSame(12.0, (float) $dezembro->aliq_icms, 'dezembro usa a alíquota de dezembro');
        $this->assertSame(18.0, (float) $janeiro->aliq_icms, 'janeiro usa a nova');
    }

    /**
     * Cadastrar a alíquota nova ANTES de ela entrar em vigor é o uso normal:
     * o decreto sai em dezembro, valendo a partir de janeiro.
     */
    public function test_regra_com_inicio_no_futuro_ainda_nao_vale(): void
    {
        [$empresa, $operacaoId] = $this->cenario();

        $this->regra($empresa, $operacaoId, 12.0, '2000-01-01');
        $this->regra($empresa, $operacaoId, 18.0, now()->addMonth()->toDateString());

        $hoje = app(ResolucaoTributariaService::class)
            ->regraPara($empresa->id, $operacaoId, null, now()->toDateString());

        $this->assertSame(12.0, (float) $hoje->aliq_icms, 'o que ainda não começou não vale');
    }

    /** Entre versões que já começaram, vale a mais recente. */
    public function test_entre_versoes_vigentes_vale_a_mais_recente(): void
    {
        [$empresa, $operacaoId] = $this->cenario();

        $this->regra($empresa, $operacaoId, 7.0, '2020-01-01');
        $this->regra($empresa, $operacaoId, 12.0, '2023-01-01');
        $this->regra($empresa, $operacaoId, 18.0, '2026-01-01');

        $regra = app(ResolucaoTributariaService::class)
            ->regraPara($empresa->id, $operacaoId, null, '2026-06-01');

        $this->assertSame(18.0, (float) $regra->aliq_icms);
    }

    /**
     * Regra encerrada não é usada depois do fim — nem que seja a única.
     *
     * Devolver a regra vencida como consolo seria pior que devolver nada: a nota
     * sairia com alíquota que a legislação não reconhece mais, e o bloqueio que
     * o `FiscalService` já faz (fail-closed) é exatamente o comportamento certo.
     */
    public function test_regra_encerrada_nao_e_usada_depois_do_fim(): void
    {
        [$empresa, $operacaoId] = $this->cenario();

        $this->regra($empresa, $operacaoId, 12.0, '2020-01-01', '2025-12-31');

        $depois = app(ResolucaoTributariaService::class)
            ->regraPara($empresa->id, $operacaoId, null, '2026-03-01');

        $this->assertNull($depois, 'sem regra vigente, o chamador bloqueia a emissão');
    }

    /** O último dia da vigência ainda está dentro dela (F5-11 outra vez). */
    public function test_o_ultimo_dia_da_vigencia_ainda_vale(): void
    {
        [$empresa, $operacaoId] = $this->cenario();

        $this->regra($empresa, $operacaoId, 12.0, '2020-01-01', '2025-12-31');

        $regra = app(ResolucaoTributariaService::class)
            ->regraPara($empresa->id, $operacaoId, null, '2025-12-31');

        $this->assertNotNull($regra, 'o dia do fim é inclusivo');
        $this->assertSame(12.0, (float) $regra->aliq_icms);
    }

    /** E o primeiro também. */
    public function test_o_primeiro_dia_da_vigencia_ja_vale(): void
    {
        [$empresa, $operacaoId] = $this->cenario();

        $this->regra($empresa, $operacaoId, 18.0, '2026-01-01');

        $regra = app(ResolucaoTributariaService::class)
            ->regraPara($empresa->id, $operacaoId, null, '2026-01-01');

        $this->assertNotNull($regra);
        $this->assertSame(18.0, (float) $regra->aliq_icms);
    }

    /**
     * As regras que já existiam continuam valendo.
     *
     * O backfill dá a elas início em 2000-01-01. Escolher `hoje` teria deixado
     * todo o histórico sem regra aplicável — trocando um defeito silencioso por
     * uma parada geral, que é pior.
     */
    public function test_regra_sem_vigencia_declarada_vale_sempre(): void
    {
        [$empresa, $operacaoId] = $this->cenario();

        $this->regra($empresa, $operacaoId, 12.0, null);

        $servico = app(ResolucaoTributariaService::class);

        $this->assertNotNull($servico->regraPara($empresa->id, $operacaoId, null, '2015-05-05'));
        $this->assertNotNull($servico->regraPara($empresa->id, $operacaoId, null, '2030-05-05'));
    }

    /** A fronteira de sempre: a regra de uma revenda não serve à outra. */
    public function test_a_vigencia_nao_atravessa_empresas(): void
    {
        [$empresa, $operacaoId] = $this->cenario();
        $this->regra($empresa, $operacaoId, 18.0, '2000-01-01');

        $intrusa = Empresa::factory()->create();

        $this->assertNull(
            app(ResolucaoTributariaService::class)
                ->regraPara($intrusa->id, $operacaoId, null, now()->toDateString()),
        );
    }
}
