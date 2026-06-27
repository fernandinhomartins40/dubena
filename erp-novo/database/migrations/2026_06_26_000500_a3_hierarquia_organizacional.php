<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A3 — Hierarquia organizacional.
 *
 * Adiciona a árvore Empresa → Unidade(filial) → Departamento → Setor e estende
 * `role_user` com o ESCOPO da atribuição (a partir de qual nó da árvore o papel
 * vale, e se desce para os filhos).
 *
 * Cargo↔papel padrão: a tabela `cargos` JÁ EXISTE (RH, grupo-scoped). Em vez de
 * duplicá-la, só acrescentamos `role_id` (papel padrão sugerido para o cargo) —
 * a administração do cargo continua no cadastro de apoio existente.
 *
 * Todas as tabelas novas são escopadas por empresa_id → entram na 1ª barreira
 * (global scope BelongsToTenant) e na 2ª (RLS). Como a migração de RLS
 * (..._000300) já rodou, aplicamos a policy tenant_isolation AQUI para as novas
 * tabelas (mesma regra/idempotente). NO-OP de RLS fora do pgsql (sqlite em teste).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Unidades (filiais) — árvore por empresa (parent_id self-FK).
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->string('tipo')->default('filial'); // 'matriz' | 'filial'
            $table->string('nome');
            $table->string('cnpj', 18)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'parent_id']);
        });

        // Departamentos — pertencem a uma unidade.
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'unidade_id']);
        });

        // Setores/Equipes — pertencem a um departamento. Nome de tabela `setores_org`
        // para não colidir com `setores` (estoque) já existente.
        Schema::create('setores_org', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'departamento_id']);
        });

        // Cargo↔papel padrão: a tabela `cargos` (RH) já existe — só acrescenta o
        // vínculo opcional com um papel RBAC.
        Schema::table('cargos', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('descricao')->constrained('roles')->nullOnDelete();
        });

        // Escopo hierárquico na atribuição papel↔usuário.
        Schema::table('role_user', function (Blueprint $table) {
            $table->foreignId('unidade_id')->nullable()->after('empresa_id')->constrained('unidades')->nullOnDelete();
            $table->foreignId('departamento_id')->nullable()->after('unidade_id')->constrained('departamentos')->nullOnDelete();
            $table->foreignId('setor_id')->nullable()->after('departamento_id')->constrained('setores_org')->nullOnDelete();
            // Se o escopo desce para os nós-filhos (default: sim — comportamento intuitivo).
            $table->boolean('herda_filhos')->default(true)->after('setor_id');
            $table->index(['user_id', 'empresa_id']);
        });

        $this->aplicarRls(['unidades', 'departamentos', 'setores_org']);
    }

    public function down(): void
    {
        $this->removerRls(['unidades', 'departamentos', 'setores_org']);

        Schema::table('cargos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidade_id');
            $table->dropConstrainedForeignId('departamento_id');
            $table->dropConstrainedForeignId('setor_id');
            $table->dropColumn('herda_filhos');
            $table->dropIndex(['user_id', 'empresa_id']);
        });

        Schema::dropIfExists('setores_org');
        Schema::dropIfExists('departamentos');
        Schema::dropIfExists('unidades');
    }

    /**
     * Aplica RLS + policy tenant_isolation por empresa_id (mesma regra da
     * migração ..._000300). NO-OP fora do pgsql. Idempotente.
     *
     * @param  list<string>  $tabelas
     */
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
