<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS: a policy de empresa passa a aceitar o CONJUNTO de empresas visíveis.
 *
 * Contexto: numa rede com matriz + filiais, as listagens mostram as empresas do
 * usuário (o `empresas_permitidas` + `whereIn` do ctrl-web), não só a empresa
 * ativa. A 1ª barreira (global scope do Eloquent) já faz isso; esta migration
 * alinha a 2ª (RLS) — senão o banco barraria exatamente o que a aplicação
 * liberou, e as filiais sumiriam de novo, agora sem erro visível.
 *
 * A policy passa a ser:
 *
 *   sem GUC          → não filtra (CLI/ETL, como antes);
 *   com lista        → empresa_id ∈ app.empresas_visiveis;
 *   só empresa ativa → empresa_id = app.empresa_id (compatibilidade).
 *
 * O isolamento entre REDES não muda: `empresas_visiveis` é montado a partir das
 * empresas do usuário DENTRO do grupo (ver User::empresasVisiveis), então nunca
 * contém empresa de outra rede. E `WITH CHECK` continua amarrado à empresa
 * ATIVA: ver várias é uma coisa; ESCREVER é sempre na empresa em que se está.
 */
return new class extends Migration
{
    private const VAR_ATIVA = 'app.empresa_id';

    private const VAR_VISIVEIS = 'app.empresas_visiveis';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tabelasComPolicyDeEmpresa() as $tabela) {
            $this->aplicar($tabela);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Volta ao filtro pela empresa ativa apenas.
        foreach ($this->tabelasComPolicyDeEmpresa() as $tabela) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$tabela}
                 USING (
                     nullif(current_setting('".self::VAR_ATIVA."', true), '') IS NULL
                     OR empresa_id = nullif(current_setting('".self::VAR_ATIVA."', true), '')::int
                 )
                 WITH CHECK (
                     nullif(current_setting('".self::VAR_ATIVA."', true), '') IS NULL
                     OR empresa_id = nullif(current_setting('".self::VAR_ATIVA."', true), '')::int
                 )"
            );
        }
    }

    private function aplicar(string $tabela): void
    {
        $ativa = self::VAR_ATIVA;
        $visiveis = self::VAR_VISIVEIS;

        // LEITURA: sem GUC não filtra; com lista, pertence à lista; senão, a ativa.
        // `string_to_array(...)::int[]` transforma o CSV da GUC num array.
        $leitura = "
            nullif(current_setting('{$ativa}', true), '') IS NULL
            OR (
                nullif(current_setting('{$visiveis}', true), '') IS NOT NULL
                AND empresa_id = ANY (
                    string_to_array(current_setting('{$visiveis}', true), ',')::int[]
                )
            )
            OR (
                nullif(current_setting('{$visiveis}', true), '') IS NULL
                AND empresa_id = nullif(current_setting('{$ativa}', true), '')::int
            )";

        // ESCRITA: sempre na empresa ATIVA. Ver a rede inteira não autoriza
        // gravar numa filial em que não se está posicionado.
        $escrita = "
            nullif(current_setting('{$ativa}', true), '') IS NULL
            OR empresa_id = nullif(current_setting('{$ativa}', true), '')::int";

        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING ({$leitura})
             WITH CHECK ({$escrita})"
        );
    }

    /**
     * Tabelas que hoje têm a policy `tenant_isolation` sobre `empresa_id`.
     *
     * Descoberto em runtime (não hardcoded): novas tabelas escopadas por
     * empresa entram automaticamente quando esta migration roda num ambiente
     * que já as tem.
     *
     * @return list<string>
     */
    private function tabelasComPolicyDeEmpresa(): array
    {
        $linhas = DB::select(
            "SELECT c.relname AS tabela
               FROM pg_policy p
               JOIN pg_class c ON c.oid = p.polrelid
               JOIN pg_namespace n ON n.oid = c.relnamespace
              WHERE n.nspname = 'public'
                AND p.polname = 'tenant_isolation'
                AND EXISTS (
                    SELECT 1 FROM information_schema.columns col
                     WHERE col.table_schema = 'public'
                       AND col.table_name = c.relname
                       AND col.column_name = 'empresa_id'
                )
              ORDER BY c.relname"
        );

        return array_map(fn ($l) => $l->tabela, $linhas);
    }
};
