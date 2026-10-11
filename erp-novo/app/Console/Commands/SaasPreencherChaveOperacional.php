<?php

namespace App\Console\Commands;

use App\Domain\Tenant\ChaveDeTenantOperacional;
use App\Domain\Tenant\ConexaoDeOwner;
use Illuminate\Console\Command;
use LogicException;

/**
 * Passo pós-carga: sem ele o dado migrado existe e ninguém o enxerga.
 *
 * Ordem depois do `etl:run`: este comando PRIMEIRO, depois
 * `saas:tenant:proteger-configuracao-grupo --apply` — o protetor valida filhos
 * de tabelas operacionais (`produto_operacao_fiscal` ← `produtos`) e recusa se o
 * pai ainda estiver sem chave.
 */
class SaasPreencherChaveOperacional extends Command
{
    protected $signature = 'saas:tenant:preencher-chave-operacional {--apply : Grava; sem a opção, só mostra o que faria}';

    protected $description = 'Preenche tenant_account_id nas tabelas operacionais a partir do vínculo APROVADO da empresa (pós-ETL). Não decide titularidade.';

    public function handle(ChaveDeTenantOperacional $chave): int
    {
        try {
            // Owner: pelo runtime sem envelope a RLS devolve zero linhas, e o
            // preview diria "nada a preencher" com milhões de linhas pendentes.
            return ConexaoDeOwner::executar(fn (): int => $this->executar($chave));
        } catch (LogicException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function executar(ChaveDeTenantOperacional $chave): int
    {
        $antes = $chave->preview();
        $pendentes = array_filter($antes, fn (array $s): bool => $s['sem_chave'] > 0 || $s['divergentes'] > 0);

        $this->line(count($antes).' tabela(s) operacional(is) conferida(s); '.count($pendentes).' com pendência.');

        if ($pendentes !== []) {
            $this->table(
                ['tabela', 'sem chave', 'preenchíveis', 'sem vínculo aprovado', 'divergentes'],
                array_map(
                    fn (string $t, array $s): array => [$t, $s['sem_chave'], $s['preenchiveis'], $s['sem_vinculo'], $s['divergentes']],
                    array_keys($pendentes),
                    $pendentes,
                ),
            );
        }

        if (! $this->option('apply')) {
            $this->info('Preview: nada foi gravado. Rode com --apply para preencher.');

            return self::SUCCESS;
        }

        $gravadas = $chave->aplicar();
        $this->info(array_sum($gravadas).' linha(s) preenchida(s) em '.count($gravadas).' tabela(s).');

        // O que sobra sem chave depois de aplicar é linha de empresa sem vínculo
        // aprovado: segue invisível de propósito, mas tem de aparecer aqui.
        $sobra = array_sum(array_column($chave->preview(), 'sem_chave'));

        if ($sobra > 0) {
            $this->warn("{$sobra} linha(s) continuam sem chave: a empresa delas não tem vínculo APROVADO em tenant_companies.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
