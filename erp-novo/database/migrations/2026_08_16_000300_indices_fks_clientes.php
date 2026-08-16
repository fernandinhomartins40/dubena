<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices nas FKs que apontam para `clientes` (achado da T2.2).
 *
 * O Postgres NÃO cria índice automático no lado que referencia — só no lado
 * referenciado (a PK). Descoberto ao executar a deduplicação: 23 das 24 FKs
 * para `clientes.id` estavam sem índice, e cada linha removida forçava uma
 * varredura sequencial em todas elas para validar a constraint — incluindo
 * `financeiros` (443 mil linhas) e `notas_fiscais` (241 mil).
 *
 * O impacto não é só do ETL: **qualquer** DELETE ou UPDATE de cliente na
 * operação normal paga esse custo, e as telas que listam dados por cliente
 * (ficha, histórico, títulos) varrem a tabela inteira em vez de usar índice.
 *
 * Usa CREATE INDEX (sem CONCURRENTLY) por rodar dentro da transação de
 * migration; num banco em produção com carga, considere aplicar
 * concorrentemente numa janela dedicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->fksSemIndice() as $fk) {
            $indice = "{$fk['tabela']}_{$fk['coluna']}_index";

            DB::statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON public.%s (%s)',
                $indice, $fk['tabela'], $fk['coluna'],
            ));
        }
    }

    public function down(): void
    {
        foreach ($this->fksParaClientes() as $fk) {
            DB::statement("DROP INDEX IF EXISTS public.{$fk['tabela']}_{$fk['coluna']}_index");
        }
    }

    /**
     * FKs para `clientes.id` que ainda não têm índice na coluna que referencia.
     *
     * @return list<array{tabela:string,coluna:string}>
     */
    private function fksSemIndice(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [];
        }

        $linhas = DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS tabela, a.attname AS coluna
              FROM pg_constraint c
              JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
             WHERE c.contype = 'f'
               AND c.confrelid = 'public.clientes'::regclass
               AND NOT EXISTS (
                     SELECT 1 FROM pg_index i
                      WHERE i.indrelid = c.conrelid
                        AND i.indkey[0] = c.conkey[1]
                   )
             ORDER BY 1
        SQL);

        return array_map(
            fn ($l) => [
                'tabela' => str_replace('public.', '', $l->tabela),
                'coluna' => $l->coluna,
            ],
            $linhas,
        );
    }

    /**
     * Todas as FKs para `clientes.id` (usado no rollback).
     *
     * @return list<array{tabela:string,coluna:string}>
     */
    private function fksParaClientes(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! Schema::hasTable('clientes')) {
            return [];
        }

        $linhas = DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS tabela, a.attname AS coluna
              FROM pg_constraint c
              JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
             WHERE c.contype = 'f' AND c.confrelid = 'public.clientes'::regclass
             ORDER BY 1
        SQL);

        return array_map(
            fn ($l) => [
                'tabela' => str_replace('public.', '', $l->tabela),
                'coluna' => $l->coluna,
            ],
            $linhas,
        );
    }
};
