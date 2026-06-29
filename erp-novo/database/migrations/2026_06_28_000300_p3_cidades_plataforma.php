<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3 — Cidade da PLATAFORMA (geolocalização-first).
 *
 * "Multi-cidade" da visão SaaS SEM criar um 4º nível rígido de tenancy: cidade é
 * uma dimensão de DESCOBERTA / AGRUPAMENTO / RELATÓRIO, não de isolamento. O sigilo
 * entre empresas continua 100% garantido por Grupo→Empresa + RLS (inalterado).
 *
 *  - cidades_plataforma (GLOBAL): catálogo de cidades atendidas pela plataforma
 *    (nome/uf/cod_ibge/centro). DISTINTO de `cidades` (N2, geográfico por GRUPO,
 *    usado no endereço do cliente) — esta é o catálogo da PLATAFORMA, administrado
 *    pelo SuperAdmin (P4). Sem empresa_id/grupo_id → fora da RLS por natureza
 *    (catálogo público de descoberta).
 *  - empresa_cidade (TENANT): em quais cidades da plataforma a empresa atua
 *    (filtro/relatório/SuperAdmin). empresa_id → entra na RLS (aplicada aqui, pois
 *    a ..._000300 já rodou). A COBERTURA real continua resolvida por geofence/raio
 *    (MarketplaceService); este vínculo é declarativo/administrativo.
 *
 * NO-OP de RLS fora do PostgreSQL (sqlite em teste). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catálogo GLOBAL de cidades da plataforma.
        Schema::create('cidades_plataforma', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->char('uf', 2);
            $t->unsignedInteger('cod_ibge')->nullable();
            $t->decimal('centro_lat', 10, 7)->nullable();
            $t->decimal('centro_lng', 10, 7)->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['nome', 'uf']);
            $t->index(['ativo', 'uf']);
        });

        // Vínculo empresa ↔ cidade (TENANT).
        Schema::create('empresa_cidade', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('cidade_plataforma_id')->constrained('cidades_plataforma')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['empresa_id', 'cidade_plataforma_id']);
        });

        $this->aplicarRls(['empresa_cidade']);
    }

    public function down(): void
    {
        $this->removerRls(['empresa_cidade']);
        Schema::dropIfExists('empresa_cidade');
        Schema::dropIfExists('cidades_plataforma');
    }

    /** @param  list<string>  $tabelas */
    private function aplicarRls(array $tabelas): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tabelas as $tabela) {
            DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$tabela}
                 USING (
                     nullif(current_setting('app.empresa_id', true), '') IS NULL
                     OR empresa_id = current_setting('app.empresa_id', true)::int
                 )
                 WITH CHECK (
                     nullif(current_setting('app.empresa_id', true), '') IS NULL
                     OR empresa_id = current_setting('app.empresa_id', true)::int
                 )"
            );
        }
    }

    /** @param  list<string>  $tabelas */
    private function removerRls(array $tabelas): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tabelas as $tabela) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
            DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
        }
    }
};
