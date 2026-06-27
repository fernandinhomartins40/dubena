<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * A6 — Auditoria de segurança + histórico de permissões.
 *
 * - security_events: trilha de eventos SENSÍVEIS de acesso/segurança (criação/
 *   edição de papel, mudança de permissões, atribuição de papéis a usuário,
 *   2FA on/off, sessão revogada, autorização negada/403). Complementa o
 *   audit_logs (dados de negócio) e o login_logs (tentativas de login).
 * - role_versions: snapshot das permissões de um papel a cada alteração — base
 *   do "histórico de permissões" e de auditoria de quem mudou o quê.
 *
 * Ambas escopadas por empresa → entram na RLS (aplicada aqui, a ..._000300 já rodou).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // autor da ação
            $table->string('tipo');          // ex.: 'papel.criado', 'autorizacao.negada'
            $table->string('alvo')->nullable(); // ex.: 'role:12', 'user:34', 'pedido.aprovar'
            $table->json('detalhes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('criado_em')->useCurrent();
            $table->index(['empresa_id', 'criado_em']);
            $table->index(['tipo', 'criado_em']);
        });

        Schema::create('role_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->json('snapshot');        // { nome, descricao, permissoes: [...] }
            $table->foreignId('alterado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('criado_em')->useCurrent();
            $table->index(['role_id', 'criado_em']);
        });

        $this->aplicarRls('security_events');
        $this->aplicarRls('role_versions');
    }

    public function down(): void
    {
        $this->removerRls('security_events');
        $this->removerRls('role_versions');
        Schema::dropIfExists('role_versions');
        Schema::dropIfExists('security_events');
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
                 OR empresa_id IS NULL
                 OR empresa_id = current_setting('app.empresa_id', true)::int
             )
             WITH CHECK (
                 nullif(current_setting('app.empresa_id', true), '') IS NULL
                 OR empresa_id IS NULL
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
