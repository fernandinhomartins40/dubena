<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cache das viagens apuradas por veículo e período.
 *
 * Segmentar um dia de rota varre todas as posições daquele dia — e são 16
 * milhões no histórico, com centenas por veículo por dia. A tela de rota é a
 * que o operador reabre o tempo todo (trocando de veículo, voltando ao mesmo
 * dia), e refazer a apuração a cada visita é desperdício puro.
 *
 * Só entra aqui período JÁ ENCERRADO: enquanto o dia corre o veículo ainda
 * está rodando, e servir o cache congelaria o trajeto no meio da tarde. Isso é
 * decidido no `ViagensService`, não aqui.
 *
 * Diferente de `rotas_cache` (que é global, porque um trajeto A→B pelas ruas é
 * fato geográfico público), este guarda por onde um veículo de uma empresa
 * andou — dado de tenant, com RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitora_viagens_cache', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('veiculo_id')->constrained('monitora_veiculos')->cascadeOnDelete();
            $t->date('de');
            $t->date('ate');
            // As viagens já montadas (trechos, horários, caminho reduzido).
            $t->json('conteudo');
            $t->unsignedInteger('hits')->default(0);
            $t->timestamps();

            $t->unique(['veiculo_id', 'de', 'ate']);
        });

        $this->aplicarRls('monitora_viagens_cache');
    }

    public function down(): void
    {
        Schema::dropIfExists('monitora_viagens_cache');
    }

    /** RLS por empresa + GRANT para a role de runtime. */
    private function aplicarRls(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
             )
             WITH CHECK (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = nullif(current_setting('app.empresa_id', true), '')::int
             )"
        );

        $role = 'erp_app';
        if (DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]) === null) {
            return;
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE {$tabela}_id_seq TO {$role}");
    }
};
