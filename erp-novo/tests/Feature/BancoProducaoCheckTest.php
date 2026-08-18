<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * T6.2 — portão do banco de produção.
 *
 * As cinco verificações da tarefa estavam no plano como queries SQL para
 * copiar e colar **na janela do cutover**, uma por uma. Passo manual em janela
 * de madrugada é passo que se erra: cola-se no banco errado, lê-se `0` de uma
 * tabela que não é a esperada, pula-se a última porque as anteriores passaram.
 *
 * ⚠️ Estes testes rodam em sqlite, onde o comando sai cedo por não ser
 * PostgreSQL. Eles fixam o **contrato** (existe, tem a flag, é read-only) — a
 * verificação de comportamento real foi feita contra o Postgres local, nos dois
 * modos: sem `--pos-etl` reprova o banco carregado (6 falhas, correto), com
 * `--pos-etl` aprova as contagens.
 */
class BancoProducaoCheckTest extends TestCase
{
    public function test_comando_existe_e_roda(): void
    {
        $this->assertSame(0, Artisan::call('banco:producao-check'));
    }

    public function test_fora_do_postgres_o_portao_nao_opina(): void
    {
        Artisan::call('banco:producao-check');

        // Em sqlite (CI/dev) o portão não faz sentido: avisa e libera, em vez
        // de reprovar um ambiente que nunca será produção.
        $this->assertStringContainsString('não é PostgreSQL', Artisan::output());
    }

    public function test_tem_a_flag_pos_etl_documentada(): void
    {
        $definicao = Artisan::all()['banco:producao-check']->getDefinition();

        $this->assertTrue($definicao->hasOption('pos-etl'));
        $this->assertStringContainsString(
            'depois da carga',
            $definicao->getOption('pos-etl')->getDescription(),
        );
    }

    public function test_e_read_only(): void
    {
        // O portão inspeciona; quem cria banco é o operador. Um comando de
        // verificação que escreve é um comando em que não se confia rodar duas
        // vezes — e na janela ele será rodado várias.
        $fonte = file_get_contents(
            base_path('app/Console/Commands/BancoProducaoCheck.php'),
        );

        foreach (['->insert(', '->update(', '->delete(', 'DB::statement('] as $escrita) {
            $this->assertStringNotContainsString($escrita, $fonte, "comando deveria ser read-only: achou {$escrita}");
        }
    }
}
