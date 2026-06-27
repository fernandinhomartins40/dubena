<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A5 — Segurança avançada: histórico de login, 2FA (TOTP) e política de senha.
 *
 * - login_logs: trilha de TODA tentativa (sucesso/falha) — NÃO é tenant-scoped
 *   (o login ocorre antes de resolver o tenant; o usuário pode nem existir). Por
 *   isso fica FORA da RLS e guarda o e-mail tentado + motivo. Base do lockout.
 * - user_2fa: segredo TOTP por usuário (cifrado). Usuário é global (sem empresa).
 * - password_policies: política por empresa → entra na RLS (aplicada aqui).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();           // e-mail tentado (mesmo que não exista)
            $table->foreignId('empresa_id')->nullable();   // sem FK: pode ser nulo no pré-login
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('sucesso')->default(false);
            $table->string('motivo')->nullable();          // 'ok' | 'credenciais' | 'inativo' | '2fa' | 'lockout'
            $table->timestamp('criado_em')->useCurrent();
            $table->index(['email', 'criado_em']);
            $table->index(['ip', 'criado_em']);
        });

        Schema::create('user_2fa', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->text('secret');                         // cifrado (cast encrypted no model)
            $table->boolean('habilitado')->default(false);
            $table->timestamp('confirmado_em')->nullable();
            $table->text('recovery_codes')->nullable();     // cifrado (json)
            $table->timestamps();
        });

        Schema::create('password_policies', function (Blueprint $table) {
            $table->foreignId('empresa_id')->primary()->constrained('empresas')->cascadeOnDelete();
            $table->unsignedSmallInteger('min_len')->default(8);
            $table->boolean('exige_complexidade')->default(false); // maiúscula+minúscula+dígito
            $table->unsignedSmallInteger('expira_dias')->default(0); // 0 = nunca
            $table->timestamps();
        });

        $this->aplicarRls('password_policies');
    }

    public function down(): void
    {
        $this->removerRls('password_policies');
        Schema::dropIfExists('password_policies');
        Schema::dropIfExists('user_2fa');
        Schema::dropIfExists('login_logs');
    }

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
                 OR empresa_id = current_setting('app.empresa_id', true)::int
             )
             WITH CHECK (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id = current_setting('app.empresa_id', true)::int
             )"
        );
    }

    private function removerRls(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
    }
};
