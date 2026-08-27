<?php

namespace App\Console\Commands;

use App\Domain\Saas\TransformationFreeze;
use App\Domain\Saas\TransformationFrozenException;
use App\Etl\MigratorRegistry;
use App\Etl\Support\MigrationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                                    {--check : valida invariantes após a carga}
                                    {--eu-sei-o-que-estou-fazendo : libera a recarga DEPOIS do cutover (T6.6.5)}';

    protected $description = 'Executa a migração de dados do banco legado para o schema novo (ETL).';

    public function handle(TransformationFreeze $freeze): int
    {
        if (! $this->option('dry-run')) {
            try {
                $freeze->assertMigrationWritesAllowed();
            } catch (TransformationFrozenException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            if (app()->isProduction() && ! $this->option('check')) {
                $this->error('ETL de producao exige --check; carga sem invariantes e bloqueada.');

                return self::FAILURE;
            }
        }

        if (($erro = $this->travaPosCutover()) !== null) {
            $this->error($erro);

            return self::FAILURE;
        }

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

    /**
     * T6.6.5 — impede a recarga depois que o sistema novo virou fonte da verdade.
     *
     * **O perigo concreto.** A recarga é idempotente por upsert preservando id
     * (`PreservaIdsDoLegado`): re-rodar o ETL **sobrescreve** qualquer linha de
     * id legado que tenha sido editada no sistema novo. Antes do cutover isso é
     * a característica desejada — é o que torna a recarga final possível. Depois
     * dele, é destruição silenciosa de trabalho real: o cliente cujo endereço o
     * atendente corrigiu ontem volta ao endereço errado do legado, sem erro,
     * sem log, sem ninguém perceber até a entrega falhar.
     *
     * **Como detecta.** Não por flag de configuração que alguém precisa lembrar
     * de ligar, mas por evidência no próprio banco: existe pedido criado no
     * sistema novo (id acima da faixa preservada do legado). Se a operação já
     * está gerando pedidos aqui, o cutover aconteceu — independentemente do que
     * qualquer arquivo diga.
     *
     * `--dry-run` passa livre: simular não grava nada.
     *
     * @return string|null mensagem de erro, ou null se pode prosseguir
     */
    private function travaPosCutover(): ?string
    {
        if ($this->option('dry-run')) {
            return null;
        }

        if ($this->option('eu-sei-o-que-estou-fazendo')) {
            Log::critical('Override manual da trava pos-cutover solicitado no ETL.', [
                'ambiente' => app()->environment(),
                'migrador' => $this->argument('migrator'),
            ]);

            return null;
        }

        try {
            $novo = DB::connection();

            if (! $novo->getSchemaBuilder()->hasTable('pedidos')) {
                return null;   // banco ainda não migrado: nada a proteger
            }

            $maxLegado = $this->maiorIdDoLegado('pedidos');

            if ($maxLegado === null) {
                return 'RECARGA BLOQUEADA: origem legado/tabela pedidos indisponível; '
                    .'não é possível provar que o cutover ainda não ocorreu.';
            }

            $nascidosAqui = (int) $novo->table('pedidos')->where('id', '>', $maxLegado)->count();

            if ($nascidosAqui === 0) {
                return null;
            }

            return "RECARGA BLOQUEADA: existem {$nascidosAqui} pedido(s) criados NESTE sistema "
                ."(id > {$maxLegado}, a faixa do legado).
"
                .'  O cutover ja aconteceu. Re-rodar o ETL sobrescreveria, via upsert por id, '
                ."toda edicao feita aqui sobre linhas herdadas — sem erro e sem log.\n"
                .'  Se ainda assim for necessario, use --eu-sei-o-que-estou-fazendo (e tenha backup).';
        } catch (\Throwable $e) {
            return 'RECARGA BLOQUEADA: falha ao inspecionar o estado de cutover: '.$e->getMessage();
        }
    }

    /** Maior id da tabela no legado, ou null se a origem não estiver acessível. */
    private function maiorIdDoLegado(string $tabela): ?int
    {
        try {
            $legado = DB::connection('legado');

            if (! $legado->getSchemaBuilder()->hasTable($tabela)) {
                return null;
            }

            $max = (int) ($legado->table($tabela)->max('id') ?? 0);

            return $max > 0 ? $max : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
