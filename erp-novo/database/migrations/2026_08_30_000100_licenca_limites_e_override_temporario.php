<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2-03 (pendências) — limites numéricos por plano e override com prazo/motivo.
 *
 * Duas lacunas ficaram do microlote anterior:
 *
 * 1. Recurso era BOOLEANO. "Tem CRM" ou "não tem" — não existia "até 5 veículos
 *    rastreados" nem "até 10 usuários". Num SaaS o limite é metade da grade
 *    comercial: é o que separa a revenda de bairro da rede com 11 unidades.
 *
 * 2. `recurso_overrides` não tinha prazo nem motivo. O gate F2-03 pede
 *    "overrides temporários auditados"; cortesia sem validade vira permanente
 *    por esquecimento, que é como um piloto de 30 dias custa dois anos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Limite por plano: NULL = ilimitado. Sem linha = sem limite declarado,
        // que tambem e ilimitado — nao inventamos teto para plano existente.
        Schema::create('plano_limites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $t->string('limite_chave', 60);
            $t->unsignedInteger('valor')->nullable();
            $t->timestamps();

            $t->unique(['plano_id', 'limite_chave']);
        });

        // Override de limite por empresa (cortesia/piloto), com o mesmo rito
        // temporal do override de recurso.
        Schema::create('limite_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('limite_chave', 60);
            $t->unsignedInteger('valor')->nullable();
            $t->string('motivo', 500);
            $t->timestamp('expira_em')->nullable();
            $t->timestamps();

            $t->unique(['empresa_id', 'limite_chave']);
        });

        Schema::table('recurso_overrides', function (Blueprint $t) {
            // `motivo` nasce nullable por causa das linhas ja existentes; o
            // servico exige motivo em toda escrita nova.
            $t->string('motivo', 500)->nullable()->after('habilitado');
            $t->timestamp('expira_em')->nullable()->after('motivo');
            $t->index('expira_em');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // `plano_limites` e catalogo de plataforma: leitura livre, escrita so
        // pelo owner.
        DB::statement('ALTER TABLE plano_limites ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE plano_limites FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON plano_limites');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON plano_limites
            USING (true)
            WITH CHECK (false)
        SQL);

        // `limite_overrides` tem `empresa_id`, entao o guardiao de RLS a cobra —
        // e com razao: e teto por empresa, dado de fronteira.
        //
        // A policy espelha exatamente a de `recurso_overrides`, a irma dela: le
        // pelas empresas visiveis e escreve so na empresa ativa. Inventar aqui
        // um `WITH CHECK (false)` "mais seguro" quebraria a porta do SuperAdmin,
        // que grava pela conexao de runtime.
        DB::statement('ALTER TABLE limite_overrides ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE limite_overrides FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON limite_overrides');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON limite_overrides
            USING (
                NULLIF(current_setting('app.empresa_id', true), '') IS NOT NULL
                AND (
                    (
                        NULLIF(current_setting('app.empresas_visiveis', true), '') IS NOT NULL
                        AND empresa_id = ANY ((string_to_array(current_setting('app.empresas_visiveis', true), ','))::integer[])
                    )
                    OR (
                        NULLIF(current_setting('app.empresas_visiveis', true), '') IS NULL
                        AND empresa_id = (NULLIF(current_setting('app.empresa_id', true), ''))::integer
                    )
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.empresa_id', true), '') IS NOT NULL
                AND empresa_id = (NULLIF(current_setting('app.empresa_id', true), ''))::integer
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('recurso_overrides', function (Blueprint $t) {
            $t->dropIndex(['expira_em']);
            $t->dropColumn(['motivo', 'expira_em']);
        });

        Schema::dropIfExists('limite_overrides');
        Schema::dropIfExists('plano_limites');
    }
};
