<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Item 5 do gate F1: snapshot e rollback da fronteira de titularidade.
 *
 * O importador documental escreve cinco tabelas de fronteira numa transacao e,
 * como efeito colateral, promove `empresas.ownership_status`. Se o mapeamento
 * aprovado estiver errado, `migrate:rollback` nao desfaz nada disso — sao dados,
 * nao schema — e o `down()` das migrations de protecao se recusa, de proposito,
 * a restaurar policy fail-open.
 *
 * Este comando grava o estado ANTES da decisao, num arquivo fora do banco que
 * ele restaura. Nao vai para `tenant_staging_artifacts`: aquela tabela exige um
 * `tenant_account_id` (o snapshot precisa cobrir todos, inclusive o "nenhum") e
 * tem TTL/purge, que apagaria justamente a evidencia de rollback.
 *
 * Somente leitura por padrao. `--restore` e a unica escrita, e ela recusa
 * qualquer estado que nao seja exatamente o capturado.
 */
class SaasSnapshotGrants extends Command
{
    protected $signature = 'saas:tenant:snapshot-grants
        {arquivo : Caminho do JSON de snapshot (gravado, ou lido com --restore)}
        {--connection=pgsql_owner : Conexao PostgreSQL que enxerga a fronteira}
        {--restore : Restaura a fronteira exatamente como estava no snapshot}';

    protected $description = 'Registra (ou restaura) o snapshot de grants e mapeamentos da fronteira SaaS.';

    /**
     * Ordem importa: na captura e a ordem de leitura; na restauracao, o insert
     * segue esta ordem e o delete segue a inversa, por causa das FKs.
     *
     * @var list<string>
     */
    private const TABELAS = [
        'tenant_accounts',
        'tenant_companies',
        'tenant_legacy_group_scopes',
        'tenant_memberships',
        'tenant_company_grants',
    ];

    public function handle(): int
    {
        $connection = DB::connection((string) $this->option('connection'));
        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('O snapshot de fronteira exige PostgreSQL efetivo.');

            return self::FAILURE;
        }

        $arquivo = (string) $this->argument('arquivo');

        return $this->option('restore')
            ? $this->restaurar($connection, $arquivo)
            : $this->capturar($connection, $arquivo);
    }

    private function capturar($connection, string $arquivo): int
    {
        $snapshot = [
            'captured_at' => now()->toIso8601String(),
            'database' => $connection->getDatabaseName(),
            'tables' => [],
            // O importador promove este campo fora das cinco tabelas; sem ele o
            // rollback deixaria empresas marcadas como aprovadas sem vinculo.
            'empresa_ownership_status' => $connection->table('empresas')
                ->orderBy('id')->pluck('ownership_status', 'id')->all(),
        ];

        foreach (self::TABELAS as $tabela) {
            $snapshot['tables'][$tabela] = array_map(
                fn (object $linha): array => (array) $linha,
                $connection->table($tabela)->orderBy('id')->get()->all(),
            );
        }

        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($arquivo, $json) === false) {
            $this->error("Nao foi possivel gravar o snapshot em {$arquivo}.");

            return self::FAILURE;
        }

        foreach (self::TABELAS as $tabela) {
            $this->line(sprintf('  %-30s %d linha(s)', $tabela, count($snapshot['tables'][$tabela])));
        }
        $this->info("Snapshot gravado em {$arquivo}.");

        return self::SUCCESS;
    }

    private function restaurar($connection, string $arquivo): int
    {
        if (! is_file($arquivo)) {
            $this->error("Snapshot inexistente: {$arquivo}.");

            return self::FAILURE;
        }

        $snapshot = json_decode((string) file_get_contents($arquivo), true);
        if (! is_array($snapshot) || ! isset($snapshot['tables'], $snapshot['empresa_ownership_status'])) {
            $this->error('Snapshot invalido: faltam as chaves `tables` e `empresa_ownership_status`.');

            return self::FAILURE;
        }

        // Restaurar num banco diferente do capturado apagaria a fronteira de
        // outro ambiente. O nome do banco e a checagem minima possivel.
        $destino = $connection->getDatabaseName();
        if (($snapshot['database'] ?? null) !== $destino) {
            $this->error(sprintf(
                'Snapshot foi capturado em [%s] e o destino e [%s]. Recusado.',
                $snapshot['database'] ?? '(desconhecido)',
                $destino,
            ));

            return self::FAILURE;
        }

        $connection->transaction(function () use ($connection, $snapshot): void {
            foreach (array_reverse(self::TABELAS) as $tabela) {
                $connection->table($tabela)->delete();
            }
            foreach (self::TABELAS as $tabela) {
                $linhas = $snapshot['tables'][$tabela] ?? [];
                if ($linhas !== []) {
                    $connection->table($tabela)->insert($linhas);
                }
            }
            foreach ($snapshot['empresa_ownership_status'] as $empresaId => $status) {
                $connection->table('empresas')->where('id', (int) $empresaId)
                    ->update(['ownership_status' => $status]);
            }
        });

        $this->info(sprintf('Fronteira restaurada ao snapshot de %s.', $snapshot['captured_at'] ?? '(sem data)'));

        return self::SUCCESS;
    }
}
