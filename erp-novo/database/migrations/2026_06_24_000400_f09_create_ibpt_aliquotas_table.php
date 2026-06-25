<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F09 — tabela IBPT (Lei 12.741) por NCM. Alimentada pelo command `ibpt:atualizar`
 * (importa o CSV regional mensal). É GLOBAL (não tenant): a carga tributária por NCM
 * vale para todos. O IbptService lê daqui com fallback a alíquota padrão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ibpt_aliquotas')) {
            return;
        }

        Schema::create('ibpt_aliquotas', function (Blueprint $t) {
            $t->id();
            $t->string('ncm', 10)->index();
            $t->string('uf', 2)->nullable()->index();
            $t->string('ex', 3)->nullable();
            $t->decimal('nacional', 6, 2)->default(0);
            $t->decimal('importado', 6, 2)->default(0);
            $t->decimal('estadual', 6, 2)->default(0);
            $t->decimal('municipal', 6, 2)->default(0);
            $t->string('versao', 20)->nullable();
            $t->date('vigencia_inicio')->nullable();
            $t->date('vigencia_fim')->nullable();
            $t->timestamps();
            $t->unique(['ncm', 'uf', 'ex']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ibpt_aliquotas');
    }
};
