<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * PIE P2-D-06 — `whereKey` não existe no Query Builder.
 *
 * Em `DB::table(...)`, o método cai no `dynamicWhere` e vira `where "key" = ?`:
 * o Postgres responde "column key does not exist". Estava em três pontos (número
 * de sorteio, pergunta de checklist, configuração financeira da empresa) e só
 * explodia quando `tenant_account_id` vinha nulo — caminho que a suíte não
 * percorria. O seeder de demonstração abortava no CRM.
 */
class SemWhereKeyNoQueryBuilderTest extends TestCase
{
    public function test_db_table_nunca_usa_where_key(): void
    {
        $raiz = dirname(__DIR__, 2).'/app';
        $varridos = 0;
        $ocorrencias = [];

        $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz));
        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }
            $varridos++;
            foreach (file($arquivo->getPathname()) as $n => $linha) {
                if (preg_match('/DB::table\([^;]*->whereKey\(/', $linha)) {
                    $ocorrencias[] = str_replace($raiz, 'app', $arquivo->getPathname()).':'.($n + 1);
                }
            }
        }

        $this->assertGreaterThan(500, $varridos, 'O guardião não varreu o app.');
        $this->assertSame([], $ocorrencias, 'DB::table()->whereKey() vira `where "key" = ?`; use where(\'id\', ...).');
    }
}
