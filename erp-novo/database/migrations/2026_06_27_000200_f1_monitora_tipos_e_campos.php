<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1 — Paridade operacional do Monitora.
 *
 * Legado (app/Monitora): veículo tem TIPO (com ícone e velocidade-máxima, base do
 * relatório de excesso), além de motorista/km_atual/deviceid. O erp-novo nascera só
 * com placa/descricao/imei/ativo. Aqui:
 *  - monitora_veiculo_tipos (descricao, icone, velocidade_maxima) por grupo;
 *  - monitora_veiculos ganha tipo_id, motorista, km_atual, deviceid.
 *
 * tipo_id é nullable (veículos existentes seguem sem tipo). RLS: veiculo_tipos é por
 * grupo_id (cadastro de apoio compartilhado na rede) — a migration rls_tenant_completa
 * já isola por grupo_id automaticamente; aqui aplicamos a policy explicitamente p/ a
 * tabela nova (NO-OP fora do pg).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitora_veiculo_tipos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $t->string('descricao');
            $t->string('icone')->nullable();                  // URL/nome do ícone no mapa
            $t->unsignedSmallInteger('velocidade_maxima')->nullable(); // km/h (relatório de excesso)
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['grupo_id', 'ativo']);
        });

        Schema::table('monitora_veiculos', function (Blueprint $t) {
            $t->foreignId('tipo_id')->nullable()->after('descricao')->constrained('monitora_veiculo_tipos')->nullOnDelete();
            $t->string('motorista')->nullable()->after('tipo_id');
            $t->unsignedInteger('km_atual')->nullable()->after('motorista');
            $t->string('deviceid', 50)->nullable()->after('imei'); // id do rastreador no provedor
            $t->index('deviceid');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Isola monitora_veiculo_tipos por grupo (cadastro de apoio da rede).
        DB::statement('ALTER TABLE monitora_veiculo_tipos ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE monitora_veiculo_tipos FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON monitora_veiculo_tipos');
        DB::statement(
            "CREATE POLICY tenant_isolation ON monitora_veiculo_tipos
             USING (
                 nullif(current_setting('app.grupo_id', true), '') IS NULL
                 OR grupo_id = current_setting('app.grupo_id', true)::int
             )
             WITH CHECK (
                 nullif(current_setting('app.grupo_id', true), '') IS NULL
                 OR grupo_id = current_setting('app.grupo_id', true)::int
             )"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON monitora_veiculo_tipos');
        }

        Schema::table('monitora_veiculos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tipo_id');
            $t->dropColumn(['motorista', 'km_atual', 'deviceid']);
        });

        Schema::dropIfExists('monitora_veiculo_tipos');
    }
};
