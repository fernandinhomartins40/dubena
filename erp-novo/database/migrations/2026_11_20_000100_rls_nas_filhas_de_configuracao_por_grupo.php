<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P2-E-13 — `checklist_perguntas` e `sorteio_numeros` só ganhavam RLS pelo
 * comando pós-carga `saas:tenant:proteger-configuracao-grupo`. Revenda nova, que
 * nunca roda esse comando, ficava com as filhas sem barreira no banco.
 *
 * A policy aqui é "a filha é visível se o PAI for visível": a subconsulta em
 * `checklists`/`sorteios` passa pela RLS do pai (o Postgres aplica a policy das
 * tabelas citadas na expressão). Assim ela vale com o pai ainda na policy legada
 * por `grupo_id` E depois que o pós-carga o converte para a canônica — sem
 * repetir aqui uma regra de tenant que só o pai decide.
 *
 * Não há `empresa_id` nestas tabelas, então a armadilha do `empresa_id` nulo
 * legítimo não se aplica: a filha sem pai visível simplesmente não existe para
 * a sessão. O `tenant_account_id` da filha tem de ser o do pai (o model copia;
 * `IS NOT DISTINCT FROM` aceita os dois nulos do estado anterior ao pós-carga).
 *
 * Idempotente e não destrutiva: tabela que já tem `tenant_isolation` (o
 * pós-carga já rodou, como na homologação) não é tocada — a policy dele é mais
 * estrita e substituí-la seria afrouxar. Nenhuma linha é alterada.
 */
return new class extends Migration
{
    /** @var array<string, array{0:string, 1:string}> filha => [pai, fk] */
    private const FILHAS = [
        'checklist_perguntas' => ['checklists', 'checklist_id'],
        'sorteio_numeros' => ['sorteios', 'sorteio_id'],
    ];

    /** Marca a policy desta migration, para o down não remover a do pós-carga. */
    private const MARCA = 'P2-E-13: filha visivel pelo pai';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::FILHAS as $filha => [$pai, $fk]) {
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$filha} TO erp_app");

            if ($this->temPolicy($filha)) {
                continue;
            }

            DB::statement("ALTER TABLE {$filha} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$filha} FORCE ROW LEVEL SECURITY");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$filha}
                USING (EXISTS (SELECT 1 FROM {$pai} pai WHERE pai.id = {$filha}.{$fk}))
                WITH CHECK (EXISTS (
                    SELECT 1 FROM {$pai} pai
                     WHERE pai.id = {$filha}.{$fk}
                       AND pai.tenant_account_id IS NOT DISTINCT FROM {$filha}.tenant_account_id
                ))
            SQL);
            DB::statement("COMMENT ON POLICY tenant_isolation ON {$filha} IS '".self::MARCA."'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(self::FILHAS) as $filha) {
            // Só desfaz o que esta migration criou: a policy do pós-carga fica.
            $nossa = DB::scalar(
                "SELECT count(*) FROM pg_policy p JOIN pg_class c ON c.oid = p.polrelid
                  WHERE c.relname = ? AND p.polname = 'tenant_isolation'
                    AND obj_description(p.oid, 'pg_policy') = ?",
                [$filha, self::MARCA],
            );
            if ((int) $nossa === 0) {
                continue;
            }

            DB::statement("DROP POLICY tenant_isolation ON {$filha}");
            DB::statement("ALTER TABLE {$filha} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$filha} DISABLE ROW LEVEL SECURITY");
        }
    }

    private function temPolicy(string $tabela): bool
    {
        return (int) DB::scalar(
            "SELECT count(*) FROM pg_policies WHERE schemaname = 'public' AND tablename = ? AND policyname = 'tenant_isolation'",
            [$tabela],
        ) > 0;
    }
};
