<?php

namespace App\Console\Commands;

use App\Domain\Identidade\ConsolidarClientes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * identidade:reparar-cadeias — conserta consolidações encadeadas.
 *
 * Quando A é absorvido por B e, depois, B é absorvido por C, tudo que apontava
 * para A ficou apontando para B — um cadastro que já não está mais na lista.
 * O registro some da vista do operador sem ter sido apagado.
 *
 * Medido na primeira execução em produção: 2.760 fusões geraram 20 cadeias, e
 * 18 pedidos + 18 títulos financeiros ficaram presos em cadastros absorvidos.
 * A causa (o `ConsolidarClientes` não checava se o PRINCIPAL já tinha sido
 * absorvido) está corrigida; este comando repara o que já foi gravado.
 *
 * Somente leitura por default.
 */
class IdentidadeRepararCadeias extends Command
{
    protected $signature = 'identidade:reparar-cadeias
        {--executar : Aplica o reparo. Sem esta flag, somente leitura.}';

    protected $description = 'Repara consolidações encadeadas (A→B→C), remapeando o que ficou preso em cadastros absorvidos.';

    public function handle(ConsolidarClientes $consolidador): int
    {
        $executar = (bool) $this->option('executar');

        $cadeias = DB::table('cliente_vinculos as a')
            ->join('cliente_vinculos as b', 'b.cliente_id', '=', 'a.principal_id')
            ->select('a.cliente_id', 'a.principal_id as intermediario', 'b.principal_id as final')
            ->get();

        if ($cadeias->isEmpty()) {
            $this->info('Nenhuma cadeia de consolidação. Nada a reparar.');

            return self::SUCCESS;
        }

        $this->warn($cadeias->count().' cadeia(s) encontrada(s).');

        // O que ainda aponta para um cadastro absorvido, tabela a tabela.
        $presos = $this->referenciasPresas();

        if ($presos === []) {
            $this->info('Nenhuma referência presa: as cadeias existem, mas nada aponta para o meio delas.');
        } else {
            $this->table(
                ['Tabela.coluna', 'Aponta para (absorvido)', 'Deveria apontar para', 'Linhas'],
                array_map(fn ($p) => [$p['ref'], $p['de'], $p['para'], $p['linhas']], $presos),
            );
        }

        if (! $executar) {
            $this->newLine();
            $this->warn('Somente leitura: nada foi alterado. Use --executar.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($presos as $p) {
            [$tabela, $coluna] = explode('.', $p['ref']);

            $n = DB::table($tabela)->where($coluna, $p['de'])->update([$coluna => $p['para']]);
            $total += $n;
        }

        $this->info("{$total} referência(s) remapeada(s) para o cadastro final.");

        // Reindexa os traços dos sobreviventes: eles absorveram telefones e
        // campos na cadeia, e o índice precisa refletir o cadastro atual.
        $finais = $cadeias->pluck('final')->unique();
        $this->info('Reindexando '.$finais->count().' cadastro(s) final(is)…');
        $this->call('identidade:reindexar');

        unset($consolidador);

        return self::SUCCESS;
    }

    /**
     * Referências que apontam para um cadastro absorvido.
     *
     * Descobre as FKs em `pg_constraint` (não em `information_schema`, que só
     * mostra objetos que a role POSSUI — o runtime é `erp_app`, que não é dona
     * das tabelas).
     *
     * @return list<array{ref: string, de: int, para: int, linhas: int}>
     */
    private function referenciasPresas(): array
    {
        $refs = $this->fksParaClientes();
        $saida = [];

        // cliente_id absorvido → principal FINAL da cadeia.
        $destino = [];
        foreach (DB::table('cliente_vinculos')->get(['cliente_id', 'principal_id']) as $v) {
            $destino[(int) $v->cliente_id] = $this->resolverFinal((int) $v->cliente_id);
        }

        foreach ($refs as $ref) {
            // As tabelas da própria identidade guardam o histórico da fusão e
            // não devem ser remapeadas: reescrevê-las apagaria o rastro.
            if (in_array($ref['tabela'], ['cliente_vinculos', 'cliente_revisoes', 'cliente_identidades'], true)) {
                continue;
            }

            $linhas = DB::table($ref['tabela'])
                ->whereIn($ref['coluna'], array_keys($destino))
                ->selectRaw($ref['coluna'].' as alvo, count(*) as total')
                ->groupBy($ref['coluna'])
                ->get();

            foreach ($linhas as $linha) {
                $de = (int) $linha->alvo;
                $para = $destino[$de] ?? null;

                if ($para === null || $para === $de) {
                    continue;
                }

                $saida[] = [
                    'ref' => $ref['tabela'].'.'.$ref['coluna'],
                    'de' => $de,
                    'para' => $para,
                    'linhas' => (int) $linha->total,
                ];
            }
        }

        return $saida;
    }

    /** Segue a cadeia de vínculos até o cadastro que sobreviveu. */
    private function resolverFinal(int $clienteId, int $limite = 10): int
    {
        $atual = $clienteId;

        for ($i = 0; $i < $limite; $i++) {
            $proximo = DB::table('cliente_vinculos')->where('cliente_id', $atual)->value('principal_id');

            if ($proximo === null) {
                return $atual;
            }

            $atual = (int) $proximo;
        }

        return $atual;
    }

    /** @return list<array{tabela: string, coluna: string}> */
    private function fksParaClientes(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [
                ['tabela' => 'pedidos', 'coluna' => 'cliente_id'],
                ['tabela' => 'financeiros', 'coluna' => 'cliente_id'],
                ['tabela' => 'clientetelefones', 'coluna' => 'cliente_id'],
            ];
        }

        $linhas = DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS tabela,
                   a.attname                  AS coluna
              FROM pg_constraint c
              JOIN pg_attribute a
                ON a.attrelid = c.conrelid
               AND a.attnum   = ANY (c.conkey)
             WHERE c.contype   = 'f'
               AND c.confrelid = 'public.clientes'::regclass
             ORDER BY 1, 2
        SQL);

        return array_map(fn ($l) => [
            'tabela' => str_replace('public.', '', $l->tabela),
            'coluna' => $l->coluna,
        ], $linhas);
    }
}
