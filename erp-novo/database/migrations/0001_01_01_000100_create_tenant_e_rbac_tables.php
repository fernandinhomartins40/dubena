<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Acesso multi-empresa do usuário (empresa_user) + RBAC unificado.
 *
 * Decisão N0: UM modelo de permissão (roles + permissions), substituindo a
 * coexistência legada de `menuusers` (menu-no-banco) + spatie. Permissões são
 * strings "modulo.acao" (ex.: cliente.view, financeiro.baixar), aplicadas via
 * Policies/Gate. A navegação no front passa a ser declarativa (sem menu no banco).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Empresas que cada usuário pode acessar (multi-empresa).
        Schema::create('empresa_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'empresa_id']);
        });

        // Papéis (por grupo — uma rede pode ter papéis próprios).
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->cascadeOnDelete();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->timestamps();
            $table->unique(['grupo_id', 'nome']);
        });

        // Permissões: "modulo.acao" (granularidade fina, ex.: caixa.estornar).
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique(); // ex.: 'cliente.view'
            $table->string('descricao')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // Papel do usuário POR empresa (permissão pode variar por empresa do grupo).
        // PK própria (id) em vez de PK composta: `empresa_id` é NULLABLE (papel
        // GLOBAL, lido por wherePivotNull em User::temPermissao) e coluna de
        // PRIMARY KEY não aceita NULL no PostgreSQL — com a PK composta, atribuir
        // um papel global falhava com not-null violation (achado DB-1 da auditoria).
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
        });

        // Unicidade em DUAS parciais (num unique comum, NULL não colide com NULL):
        // uma para a atribuição por empresa, outra para a global. pgsql e sqlite
        // suportam índice parcial com a mesma sintaxe.
        DB::statement('CREATE UNIQUE INDEX role_user_empresa_unique ON role_user (user_id, role_id, empresa_id) WHERE empresa_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX role_user_global_unique ON role_user (user_id, role_id) WHERE empresa_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('empresa_user');
    }
};
