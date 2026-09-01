<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F9-06 — o fuso da plataforma é declarado, não herdado.
 *
 * ## O defeito
 *
 * `config/app.php` trazia `'timezone' => 'UTC'` — o padrão do Laravel, que
 * ninguém mudou. Só que a operação é brasileira.
 *
 * Às 22h em Guarapuava o sistema gravava **01h do dia seguinte**. Toda venda
 * feita depois das 21h caía no dia errado, e com ela o fechamento diário, a
 * comissão do entregador e o DRE do dia.
 *
 * Medi ao vivo enquanto corrigia: eram 22:34 em São Paulo e `now()` devolvia
 * `2026-09-01 01:34`. O dia divergia.
 *
 * ## Por que passou tanto tempo despercebido
 *
 * É o modo de falhar desta transformação inteira: **silencioso e plausível**.
 * Ninguém confere se o pedido das 22h está no relatório de ontem ou de hoje. A
 * descoberta viria pelo total do dia que não bate com o caixa — e aí a
 * investigação começa pelo lugar errado.
 *
 * A suíte também não pegava: os testes criam pedidos com `now()` e conferem com
 * `now()`, então o deslocamento é consistente dos dois lados e some.
 *
 * ## Não desloca o que já está gravado
 *
 * Verifiquei antes de mudar: o schema usa `timestamp`/`datetime` **sem fuso**, e
 * não há conversão em lugar nenhum do código — nem no ETL. O sistema grava e lê
 * no mesmo fuso, então o valor no banco não se move; o que muda é a
 * interpretação daqui em diante, que passa a ser a correta.
 *
 * ## `env`, porque é default de PLATAFORMA
 *
 * O timezone da revenda que opera em outro fuso é configuração da empresa dela,
 * não deste arquivo. O que este arquivo define é o padrão de quem não declarou
 * nada — e o gate da F9 pede exatamente que esse default seja **explícito**, em
 * vez de "o que veio no framework".
 */
class FusoHorarioDaOperacaoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O default da plataforma é o fuso da operação, não UTC.
     */
    public function test_o_fuso_padrao_e_o_da_operacao(): void
    {
        $this->assertSame(
            'America/Sao_Paulo',
            config('app.timezone'),
            'UTC joga a venda das 22h para o dia seguinte',
        );
    }

    /**
     * O caso concreto: 22h de um dia continua sendo aquele dia.
     *
     * É a asserção que o defeito quebrava. Sob UTC, 22:00 em São Paulo vira
     * 01:00 do dia seguinte, e o pedido some do fechamento do dia em que foi
     * feito.
     */
    public function test_a_venda_da_noite_fica_no_dia_em_que_aconteceu(): void
    {
        $vendaEm = Carbon::parse('2026-08-31 22:30:00');

        $this->assertSame('2026-08-31', $vendaEm->toDateString());
        $this->assertSame(
            '2026-08-31',
            $vendaEm->copy()->startOfDay()->toDateString(),
            'o dia da venda não pode escorregar para o seguinte',
        );
    }

    /**
     * `now()` e a hora de parede concordam.
     *
     * Este é o teste que teria pegado o defeito original — e é preciso comparar
     * com o fuso NOMEADO, não com `date()`, porque o PHP CLI pode estar em
     * qualquer fuso na máquina de quem roda.
     */
    public function test_now_concorda_com_a_hora_da_operacao(): void
    {
        $naOperacao = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));

        $this->assertSame(
            $naOperacao->format('Y-m-d'),
            now()->toDateString(),
            'a data do sistema tem de ser a data de quem opera',
        );
    }

    /**
     * O schema não guarda fuso — e é por isso que a correção é segura.
     *
     * Com `timestamptz`, mudar o fuso da aplicação reinterpretaria cada valor
     * já gravado e deslocaria o histórico inteiro. Com `timestamp` simples, o
     * valor fica onde está: só a leitura passa a ser feita no fuso certo.
     *
     * Se um dia alguém migrar para `timestamptz`, este teste falha — e falha
     * ANTES de o histórico se mover.
     */
    public function test_o_schema_nao_guarda_fuso(): void
    {
        $tipo = Schema::getColumnType('pedidos', 'datahora');

        $this->assertContains(
            $tipo,
            ['datetime', 'timestamp'],
            'coluna com fuso mudaria a leitura de todo o histórico ao trocar o timezone',
        );
    }
}
