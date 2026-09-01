<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O `etl:run` não pode dizer "concluído" sem ter convertido nada.
 *
 * ## Os dois furos que estes testes fecham
 *
 * **1. A origem inteira ilegível saía como sucesso.** O comando reprovava
 * quando o aviso continha a frase `leitura falhou` — mas quando a TABELA não
 * existe no espelho, os migradores dizem `ausente no espelho`, e quando o schema
 * está inacessível dizem `legado indisponível`. Nenhuma das duas casava.
 *
 * Reproduzi por acidente em 2026-09-01, apontando o ETL para um banco onde a
 * role não tinha permissão no schema `legado`: os 27 migradores leram **zero** e
 * o comando saiu com **exit 0**.
 *
 * **2. Ler zero em tudo, sem aviso nenhum.** Um migrador pode ler zero e não
 * avisar. Aí nenhum filtro de texto pega — o que pega é a soma: uma conversão
 * que não leu uma linha sequer não é uma conversão bem-sucedida, é uma conversão
 * que não aconteceu.
 *
 * ## Por que isso importa no cutover deste cliente
 *
 * O modelo de virada é: o dono manda um **dump** do legado (que roda noutro
 * servidor), e a conversão acontece aqui. Se eu apontar para o banco errado, o
 * schema errado, ou com credencial trocada — e o comando disser "concluído" —
 * ninguém descobre até alguém abrir o sistema e não achar os pedidos.
 *
 * ## Por que isto NÃO é o portão de descarte
 *
 * O portão de descarte mede quanto do lido foi jogado fora. Aqui o problema é
 * anterior: **não se leu nada**. Zero descartado de zero lido é 0% — abaixo de
 * qualquer limiar. São buracos vizinhos, e é preciso os dois.
 */
class EtlNaoDizConcluidoAToaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sem conexão com o legado, todos os migradores leem zero.
     *
     * A suíte já aponta o legado para uma porta inválida (`phpunit.xml`), então
     * este é exatamente o cenário: origem inacessível.
     *
     * Antes: `ETL concluído`, exit 0.
     * Agora: reprova dizendo o que conferir.
     */
    public function test_origem_ilegivel_reprova_em_vez_de_dizer_concluido(): void
    {
        $this->artisan('etl:run', ['--dry-run' => true])
            ->doesntExpectOutputToContain('ETL concluído.')
            ->assertFailed();
    }

    /**
     * A mensagem tem de dizer o que fazer.
     *
     * Um erro que não aponta o caminho vira ticket de suporte. As três causas
     * reais são banco, schema e permissão — e foi a terceira que me pegou.
     */
    public function test_a_mensagem_aponta_o_que_conferir(): void
    {
        $this->artisan('etl:run', ['--dry-run' => true])
            ->expectsOutputToContain('LEGADO_DB_SCHEMA')
            ->assertFailed();
    }
}
