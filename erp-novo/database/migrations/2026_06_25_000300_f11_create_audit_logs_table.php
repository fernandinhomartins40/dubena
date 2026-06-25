<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F11 — trilha de auditoria UNIFICADA (moderniza os `revisions`/Logsenha/Logcerca do
 * legado num só lugar). Registra quem mudou o quê: entidade, id, ação, usuário,
 * empresa (tenant), valores antes/depois (JSON) e IP. Escopada por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('empresa_id')->nullable()->index();
            $t->string('entidade', 80)->index();      // tabela/model auditado
            $t->unsignedBigInteger('entidade_id')->nullable();
            $t->string('acao', 20);                    // criado | atualizado | excluido
            $t->unsignedBigInteger('user_id')->nullable();
            $t->json('antes')->nullable();
            $t->json('depois')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('criado_em')->useCurrent();
            $t->index(['entidade', 'entidade_id']);
            $t->index(['empresa_id', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
