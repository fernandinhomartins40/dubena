<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2-05 — break-glass substitui o bypass permanente de `support`.
 *
 * Hoje `users.support` da bypass total de RBAC em quatro camadas
 * (`Gate::before`, `PolicyEvaluator::permite`, `podeAcessarEmpresa` e
 * `empresasVisiveis`). Provado em teste: a mesma rota devolve 403 sem o flag e
 * 200 com ele. Na copia real ha 12 usuarios ativos assim, e
 * `platform_audit_logs` esta zerado — ninguem sabe o que o suporte fez.
 *
 * Numa revenda so isso e "o pessoal do suporte". Num SaaS com revendas
 * concorrentes e um acesso permanente, irrestrito e sem trilha.
 *
 * Aqui o acesso passa a ser um EVENTO: tem motivo, alvo, validade e quem
 * concedeu. Expira sozinho. O flag continua existindo (o sistema depende dele
 * como mecanismo legitimo de suporte), mas deixa de ser autorizacao por si:
 * quem decide e a concessao vigente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('break_glass_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Alvo: sem empresa declarada nao ha acesso. O escopo e sempre
            // explicito — "pode tudo em qualquer lugar" e o que se esta removendo.
            $t->unsignedBigInteger('empresa_id');

            $t->string('motivo', 500);
            $t->string('ticket_ref', 120)->nullable();

            // Quem autorizou. Nulo so no lastro historico da conversao, que fica
            // marcado como tal no motivo.
            $t->foreignId('concedido_por_platform_admin_id')->nullable()
                ->constrained('platform_admins')->nullOnDelete();

            $t->timestamp('inicia_em')->useCurrent();
            $t->timestamp('expira_em');
            $t->timestamp('revogado_em')->nullable();
            $t->string('revogado_motivo', 500)->nullable();

            $t->timestamps();

            $t->index(['user_id', 'expira_em']);
            $t->index(['empresa_id', 'expira_em']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // A concessao e um ato de PLATAFORMA: quem opera no runtime nao pode
        // conceder acesso a si mesmo. Leitura liberada para a propria linha —
        // a app precisa saber se ha concessao vigente para decidir.
        DB::statement('ALTER TABLE break_glass_grants ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE break_glass_grants FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON break_glass_grants');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON break_glass_grants
            USING (true)
            WITH CHECK (false)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('break_glass_grants');
    }
};
