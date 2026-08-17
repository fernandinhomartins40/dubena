<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pares de rua/bairro marcados como NÃO-duplicados (T4.1 do PLANO_PRODUCAO).
 *
 * O detector de inconsistências foi portado do legado, mas a AÇÃO QUE FECHA O
 * CICLO não: no legado o operador resolve o par com `ignorarRua`/`ignorarBairro`
 * (`InconsistenciaController.php:48-91`), gravando-o numa tabela de ignorados.
 * Sem isso, os mesmos falsos positivos reaparecem a cada consulta e a fila nunca
 * esvazia — a tela existe, parece pronta, e não substitui a do legado.
 *
 * Polimórfica como no legado (`ignorable_type`), para servir a ruas e bairros
 * com uma tabela só. Diferença: aqui há `empresa_id`/`grupo_id`, porque o
 * sistema novo é multi-tenant e a tabela precisa de RLS — uma tabela nova sem
 * policy é vazamento cross-tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_pares_ignorados', function (Blueprint $tabela) {
            $tabela->id();

            // O par, sempre gravado com o MENOR id primeiro (normalizado no
            // service): sem isso (A,B) e (B,A) seriam linhas distintas e o par
            // voltaria à fila pela ordem inversa.
            $tabela->string('tipo', 20);            // 'rua' | 'bairro'
            $tabela->unsignedBigInteger('item_id');
            $tabela->unsignedBigInteger('item_ignorado_id');

            $tabela->unsignedBigInteger('empresa_id')->nullable();
            $tabela->unsignedBigInteger('grupo_id');

            // Quem decidiu e por quê: a decisão de "não é duplicata" é um juízo
            // humano, e daqui a um ano alguém vai querer saber de quem foi.
            $tabela->unsignedBigInteger('user_id')->nullable();
            $tabela->string('motivo', 255)->nullable();

            $tabela->timestamps();

            $tabela->unique(
                ['grupo_id', 'tipo', 'item_id', 'item_ignorado_id'],
                'geo_pares_ignorados_par_unique'
            );
            $tabela->index(['grupo_id', 'tipo']);
        });

        $this->aplicarRls();
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_pares_ignorados');
    }

    /**
     * Cobre a tabela com a policy de isolamento por tenant.
     *
     * A migration de auto-descoberta (`…_rls_cobertura_tabelas_novas`) varre as
     * colunas UMA VEZ, no momento em que roda — ela não alcança tabelas criadas
     * depois. Por isso a policy é aplicada aqui, no mesmo formato (cast-safe com
     * `nullif`, senão a GUC vazia do fim de requisição estoura
     * "invalid input syntax for integer").
     */
    private function aplicarRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tabela = 'geo_pares_ignorados';
        $var = 'app.grupo_id';
        $coluna = 'grupo_id';

        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('{$var}', true), '') IS NULL
                 OR {$coluna} = nullif(current_setting('{$var}', true), '')::int
             )
             WITH CHECK (
                 nullif(current_setting('{$var}', true), '') IS NULL
                 OR {$coluna} = nullif(current_setting('{$var}', true), '')::int
             )"
        );

        // A role de runtime não é dona da tabela: sem GRANT explícito ela recebe
        // "permission denied" na primeira escrita — sintoma tardio, porque só
        // aparece quando alguém usa a funcionalidade. Mesma role e mesmo padrão
        // de `2026_08_14_000200_grants_runtime_role`, incluindo o UPDATE na
        // sequence (não basta USAGE/SELECT).
        $role = 'erp_app';
        $existe = DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]);
        if ($existe === null) {
            return; // dev/CI de uma role só
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE {$tabela}_id_seq TO {$role}");
    }
};
