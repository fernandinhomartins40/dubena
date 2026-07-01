<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L0 (Logística — Fase 0) — Fundação de dados logísticos.
 *
 * 1. `jornadas`: o TURNO do entregador. É AQUI que o entregador↔veículo se ligam
 *    (o `monitora_veiculos.motorista` era texto livre; a jornada é o vínculo real).
 *    Guarda o veículo escolhido no início do expediente, o checklist e o km. A
 *    partir de uma jornada ativa, o ERP passa a considerar o entregador "em campo"
 *    e a usar a posição dele (celular — decisão de produto) para distribuir.
 * 2. `pedidos.veiculo_id`: em qual veículo a entrega saiu (nullable; preenchido na
 *    atribuição — L1). Fecha a rastreabilidade pedido→veículo→Monitora.
 *
 * Tenant-scoped (empresa_id) → RLS inline (padrão P6). NO-OP fora do PostgreSQL
 * (sqlite em teste). Colunas novas nullable → nenhuma migração destrutiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornadas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->unsignedBigInteger('grupo_id')->nullable();
            $t->foreignId('entregador_user_id')->constrained('users')->cascadeOnDelete();
            // Veículo do Monitora escolhido no início da jornada (a "identidade" real).
            $t->foreignId('veiculo_id')->nullable()->constrained('monitora_veiculos')->nullOnDelete();
            $t->dateTime('iniciada_em');
            $t->dateTime('encerrada_em')->nullable();
            $t->unsignedInteger('km_inicial')->nullable();
            $t->unsignedInteger('km_final')->nullable();
            // Checklist do veículo (pneus/gás/documentos/avarias) — estrutura livre.
            $t->json('checklist')->nullable();
            // 'ativa' | 'encerrada'. Uma ativa por entregador (garantido no serviço +
            // índice parcial no PostgreSQL abaixo).
            $t->string('status', 20)->default('ativa');
            $t->timestamps();

            $t->index(['empresa_id', 'status']);
            $t->index(['entregador_user_id', 'status']);
        });

        // Em qual veículo a entrega saiu (preenchido na atribuição — L1).
        Schema::table('pedidos', function (Blueprint $t) {
            $t->foreignId('veiculo_id')->nullable()->after('entregador_user_id')
                ->constrained('monitora_veiculos')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Garante 1 jornada ATIVA por entregador no nível do banco (defense-in-depth).
        DB::statement(
            "CREATE UNIQUE INDEX jornadas_uma_ativa_por_entregador
             ON jornadas (entregador_user_id) WHERE status = 'ativa'"
        );

        $this->aplicarRls('jornadas');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS jornadas_uma_ativa_por_entregador');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON jornadas');
        }

        Schema::table('pedidos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('veiculo_id');
        });
        Schema::dropIfExists('jornadas');
    }

    private function aplicarRls(string $tabela): void
    {
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
};
