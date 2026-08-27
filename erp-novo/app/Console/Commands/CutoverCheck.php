<?php

namespace App\Console\Commands;

use App\Etl\MigratorRegistry;
use App\Etl\Support\MigrationContext;
use Illuminate\Console\Command;

/**
 * cutover:check (N12) — o PORTÃO FINAL do cutover. Roda TODAS as invariantes de
 * todos os migrators (sem re-migrar) e devolve um placar consolidado. Falha
 * (exit≠0) se qualquer invariante divergir — bloqueia a virada.
 */
class CutoverCheck extends Command
{
    protected $signature = 'cutover:check';

    protected $description = 'Valida todas as invariantes do ETL (portão final do cutover).';

    public function handle(): int
    {
        $ctx = new MigrationContext;
        $ok = 0;
        $falhas = 0;

        foreach (MigratorRegistry::resolved() as $migrator) {
            $invariantes = $migrator->invariantes();
            if ($invariantes === []) {
                continue;
            }

            $this->info("→ {$migrator->nome()}");
            foreach ($invariantes as $inv) {
                $r = $inv->verificar();
                if ($r->ok) {
                    $this->line('  '.$r->resumo());
                    $ok++;
                } else {
                    $this->error('  '.$r->resumo());
                    $falhas++;
                }
            }
        }

        $this->newLine();
        $this->line("Invariantes: {$ok} OK, {$falhas} falha(s).");

        if ($ok + $falhas === 0) {
            $this->error('PORTÃO FECHADO — nenhuma invariante obrigatória foi executada.');

            return self::FAILURE;
        }

        if ($falhas > 0) {
            $this->error('PORTÃO FECHADO — não faça o cutover até zerar as falhas.');

            return self::FAILURE;
        }

        $this->info('PORTÃO LIBERADO — invariantes 100% verdes.');

        return self::SUCCESS;
    }
}
