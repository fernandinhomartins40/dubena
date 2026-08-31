<?php

namespace Tests\Feature;

use App\Domain\Relatorio\RelatorioService;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F5-11 — a semântica temporal dos marcos financeiros, confirmada no schema
 * EFETIVO e não em comentário.
 *
 * ## O defeito que esta tarefa persegue
 *
 * `whereBetween('coluna', ['2026-08-01', '2026-08-31'])` **perde o dia 31
 * inteiro** quando o valor guardado tem hora: o banco compara
 * `'2026-08-31 00:00:00' > '2026-08-31'` como texto e descarta. O relatório
 * fecha com um dia a menos e ninguém percebe, porque o número sai plausível — é
 * a diferença de um dia num mês.
 *
 * ## O que a medição revelou, e não era o esperado
 *
 * `vencimento` é declarada `date` na migration. Isso sugeria que o
 * `whereBetween` por texto era seguro — não há hora para sobrar.
 *
 * Só que **o cast do Eloquent serializa `date` como `'AAAA-MM-DD 00:00:00'`**.
 * O Postgres trunca a hora ao gravar numa coluna `date`; o **sqlite não trunca
 * nada**, porque não tem tipo de data — guarda o texto como veio.
 *
 * Consequência: o mesmo relatório **perdia o último dia em sqlite e funcionava
 * em produção**. É a pior forma da divergência, porque a suíte é justamente
 * onde se confia. O caminho contrário também vale: um defeito que só aparece em
 * Postgres passa verde aqui.
 *
 * Por isso a correção não foi mexer no cast (mudaria semântica na base toda),
 * e sim tornar os dois consumidores **indiferentes ao tipo**, com `whereDate` —
 * que é literalmente o que a tarefa pede ao falar em "uniformizar limites
 * inclusivos e comparações date/datetime".
 *
 * Corrigi também um comentário em `fluxoCaixa` que afirmava que `vencimento`
 * guarda datetime. O código estava certo, mas a premissa errada escrita ao lado
 * é o que leva o próximo leitor à conclusão errada.
 */
class SemanticaTemporalTest extends TestCase
{
    use RefreshDatabase;

    /** Título com uma parcela — mesmo padrão dos demais testes de relatório. */
    private function parcela(Empresa $e, string $venc, float $valor, ?string $baixaEm = null): void
    {
        $titulo = Financeiro::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'pagarreceber' => 'R', 'descricao' => 'T', 'valor' => $valor,
            'data_emissao' => now(), 'cancelado' => false,
        ]);

        FinanceiroParcela::create([
            'financeiro_id' => $titulo->id, 'numero' => 1,
            'vencimento' => $venc, 'valor' => $valor,
            'baixado' => $baixaEm !== null,
            'valor_efetivado' => $baixaEm !== null ? $valor : 0,
            'datahora_baixa' => $baixaEm,
        ]);
    }

    /**
     * Marcos comparados por texto em algum relatório. Enquanto forem `date`,
     * `whereBetween` com 'AAAA-MM-DD' é seguro.
     *
     * @return array<string, array{string, string}>
     */
    public static function marcosComparadosPorTexto(): array
    {
        return [
            'vencimento da parcela' => ['financeiroparcelas', 'vencimento'],
            'vencimento do boleto' => ['boletos', 'vencimento'],
        ];
    }

    /**
     * @dataProvider marcosComparadosPorTexto
     */
    public function test_marco_comparado_por_texto_continua_sendo_data_pura(string $tabela, string $coluna): void
    {
        $tipo = Schema::getColumnType($tabela, $coluna);

        $this->assertSame('date', $tipo, sprintf(
            "%s.%s virou '%s'. Relatórios comparam essa coluna com 'AAAA-MM-DD' ".
            'por texto — com hora, o último dia do período some. Troque os '.
            'consumidores por whereDate() antes de mudar o tipo.',
            $tabela, $coluna, $tipo,
        ));
    }

    /**
     * O limite superior é INCLUSIVO: quem pede 01→31 espera o dia 31 dentro.
     *
     * É este teste que falharia se `vencimento` virasse datetime — ele é o que
     * dá sentido ao contrato de tipo acima.
     */
    public function test_ultimo_dia_do_periodo_entra_na_posicao_financeira(): void
    {
        $empresa = Empresa::factory()->create();

        // Vence exatamente no último dia pedido — a fronteira do defeito.
        $this->parcela($empresa, '2026-08-31', 250.00);

        $posicao = app(RelatorioService::class)->financeiro($empresa->id, '2026-08-01', '2026-08-31');

        $this->assertSame(250.00, $posicao['a_receber'], 'o último dia do período faz parte do período');
    }

    /** E o primeiro também — a fronteira de baixo é inclusiva do mesmo jeito. */
    public function test_primeiro_dia_do_periodo_entra(): void
    {
        $empresa = Empresa::factory()->create();
        $this->parcela($empresa, '2026-08-01', 130.00);

        $posicao = app(RelatorioService::class)->financeiro($empresa->id, '2026-08-01', '2026-08-31');

        $this->assertSame(130.00, $posicao['a_receber']);
    }

    /** O que está FORA continua fora: um limite inclusivo demais é o erro oposto. */
    public function test_dia_seguinte_ao_fim_fica_de_fora(): void
    {
        $empresa = Empresa::factory()->create();
        $this->parcela($empresa, '2026-09-01', 999.00);

        $posicao = app(RelatorioService::class)->financeiro($empresa->id, '2026-08-01', '2026-08-31');

        $this->assertSame(0.0, $posicao['a_receber']);
    }

    /**
     * O outro lado do F5-11: marcos que SÃO datetime têm de receber limites com
     * hora. `datahora_baixa` alimenta a DRE — é onde o dinheiro realizado
     * aparece, e perder o último dia ali some com um dia de faturamento.
     */
    public function test_dre_inclui_baixa_no_fim_do_ultimo_dia(): void
    {
        $this->assertSame('datetime', Schema::getColumnType('financeiroparcelas', 'datahora_baixa'));

        $empresa = Empresa::factory()->create();

        // 23:47 do último dia: só entra se o limite superior levar a hora.
        $this->parcela($empresa, '2026-08-31', 400.00, '2026-08-31 23:47:00');

        $dre = app(RelatorioService::class)->dre($empresa->id, '2026-08-01', '2026-08-31');

        $this->assertSame(400.00, round((float) $dre['total_receitas'], 2));
    }

    /**
     * Guardião: nenhum `whereBetween` volta a comparar marco temporal por texto.
     *
     * Corrigi os dois que existiam. Este teste é o que impede o terceiro — e a
     * reincidência é provável, porque `whereBetween` é a forma óbvia de escrever
     * "entre duas datas" e o defeito não aparece em nenhum teste de relatório
     * comum.
     *
     * O filtro só reprova quando o limite é uma **data pura**: `whereBetween`
     * com `Carbon` já em `startOfDay()`/`endOfDay()` é correto e comum no
     * código, e reprová-lo transformaria o guardião em ruído — que é como
     * guardião morre.
     */
    public function test_nenhum_where_between_compara_marco_temporal_por_texto(): void
    {
        $marcos = ['vencimento', 'datahora_baixa', 'emitida_em', 'data_emissao'];
        $achados = [];
        $varridos = 0;

        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }
            $varridos++;
            $conteudo = (string) file_get_contents($arquivo->getPathname());

            $linhas = explode('
', $conteudo);

            foreach ($linhas as $n => $linha) {
                if (! str_contains($linha, 'whereBetween(')) {
                    continue;
                }

                // Os limites costumam ser preparados nas linhas ACIMA
                // (`$di = ...->startOfDay()`), nao na propria chamada. Olhar so a
                // linha do whereBetween acusaria codigo correto — e um guardiao
                // que acusa o certo e desligado no primeiro incomodo.
                $contexto = implode('
', array_slice($linhas, max(0, $n - 8), 9));

                $liberado = str_contains($contexto, 'startOfDay')
                    || str_contains($contexto, 'endOfDay')
                    || str_contains($contexto, '00:00:00');

                foreach ($marcos as $marco) {
                    // Reprova por PADRAO e libera pela excecao visivel. A primeira
                    // versao fazia o contrario — so acusava limite literal — e nao
                    // pegou a regressao deliberada que plantei: o limite mais comum
                    // e uma VARIAVEL, e ai nao ha nada na linha para reconhecer.
                    // Provar que o guardiao detecta valeu mais que o teste verde.
                    if (str_contains($linha, $marco) && ! $liberado) {
                        $achados[] = basename($arquivo->getPathname()).':'.($n + 1);
                    }
                }
            }
        }

        $this->assertGreaterThan(200, $varridos, 'a varredura precisa ter varrido algo');
        $this->assertSame([], $achados, 'use whereDate(): whereBetween por texto perde o último dia');
    }
}
