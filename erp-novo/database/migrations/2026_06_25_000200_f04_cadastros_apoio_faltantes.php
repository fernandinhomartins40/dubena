<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F04 — tabelas de apoio que a SPA já referencia mas não existiam (parentescos,
 * tipos de exame). Padrão de apoio (grupo_id + descricao + ativo), escopadas por
 * grupo, unicidade de descricao no grupo. `cargos` já existe (RH) e será exposto
 * como cadastro de apoio sem nova tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        $apoio = function (string $tabela, ?\Closure $extras = null): void {
            if (Schema::hasTable($tabela)) {
                return;
            }
            Schema::create($tabela, function (Blueprint $t) use ($extras) {
                $t->id();
                $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
                $t->string('descricao');
                $t->boolean('ativo')->default(true);
                if ($extras) {
                    $extras($t);
                }
                $t->timestamps();
                $t->unique(['grupo_id', 'descricao']);
            });
        };

        $apoio('parentescos');
        $apoio('tipos_exame', function (Blueprint $t) {
            // exame admissional? (usado pelo RH ao classificar o ASO)
            $t->boolean('admissional')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_exame');
        Schema::dropIfExists('parentescos');
    }
};
