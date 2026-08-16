<?php

namespace App\Console\Commands;

use App\Etl\MigratorRegistry;
use App\Etl\Support\MigrationContext;
use Illuminate\Console\Command;

/**
 * Runner do ETL (migração legado → novo).
 *
 *   php artisan etl:run                  # roda todos os migrators (ordem de dependência)
 *   php artisan etl:run estados          # roda só um
 *   php artisan etl:run --dry-run        # simula (não grava)
 *   php artisan etl:run --check          # roda e VALIDA invariantes (portão do cutover)
 *
 * É o esqueleto do Bloco 2/3 do plano: carga + validação de invariantes.
 */
class EtlRun extends Command
{
    protected $signature = 'etl:run {migrator? : nome do migrador (vazio = todos)}
                                    {--dry-run : não grava, apenas simula}
                                    {--check : valida invariantes após a carga}';

    protected $description = 'Executa a migração de dados do banco legado para o schema novo (ETL).';

    public function handle(): int
    {
        // Carga de migração legítima: os migradores materializam mapas de FK do
        // dump inteiro (443 mil títulos, 475 mil parcelas, 442 mil rateios) —
        // o limite padrão de 512M do CLI derruba o financeiro no meio.
        ini_set('memory_limit', '3G');

        $ctx = new MigrationContext(dryRun: (bool) $this->option('dry-run'));
        $alvo = $this->argument('migrator');

        $migrators = MigratorRegistry::resolved();
        if ($alvo) {
            $migrators = array_values(array_filter($migrators, fn ($m) => $m->nome() === $alvo));
            if ($migrators === []) {
                $this->error("Migrador '{$alvo}' não encontrado.");

                return self::FAILURE;
            }
        }

        $falhou = false;
        /** @var list<string> $todosAvisos */
        $todosAvisos = [];

        foreach ($migrators as $m) {
            $this->info("→ {$m->nome()}".($ctx->dryRun ? ' (dry-run)' : ''));
            $res = $m->migrar($ctx);
            $this->line('  '.$res->resumo());
            foreach ($res->avisos as $aviso) {
                $this->warn('  ! '.$aviso);
                $todosAvisos[] = "{$m->nome()}: {$aviso}";
            }

            if ($this->option('check')) {
                foreach ($m->invariantes() as $inv) {
                    $r = $inv->verificar();
                    $r->ok ? $this->line('  '.$r->resumo()) : $this->error('  '.$r->resumo());
                    $falhou = $falhou || ! $r->ok;
                }
            }
        }

        // Bloco consolidado (T2.6): com 28 migrators, um aviso isolado no meio
        // do log rola para fora da tela e ninguém vê. O resumo final é o que
        // torna o aviso acionável.
        $this->newLine();
        $this->line('Avisos:');
        if ($todosAvisos === []) {
            $this->line('  (nenhum)');
        } else {
            foreach ($todosAvisos as $aviso) {
                $this->warn('  ! '.$aviso);
            }
        }

        if ($falhou) {
            $this->error('ETL concluído COM FALHA de invariante (portão NÃO liberado).');

            return self::FAILURE;
        }

        // "Origem indisponível" é diferente de "origem vazia": a primeira
        // significa que a carga rodou com dado FALTANDO e não deve ser tratada
        // como sucesso por um script de deploy.
        $indisponiveis = array_values(array_filter(
            $todosAvisos,
            fn (string $a) => str_contains($a, 'leitura falhou'),
        ));

        if ($indisponiveis !== []) {
            $this->error(sprintf(
                'ETL concluído com %d falha(s) de LEITURA da origem — a carga está incompleta.',
                count($indisponiveis),
            ));

            return self::FAILURE;
        }

        $this->info('ETL concluído.');

        return self::SUCCESS;
    }
}
