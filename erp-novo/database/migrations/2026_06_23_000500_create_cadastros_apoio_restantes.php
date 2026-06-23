<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C12 — cadastros de apoio restantes do legado, no mesmo padrão (grupo_id,
 * descricao, ativo + extras): agências (de banco), transportadoras, feriados,
 * profissões e estados civis. Entram no CadastroApoioRegistry (sem novo controller).
 */
return new class extends Migration
{
    public function up(): void
    {
        $apoio = function (string $tabela, ?Closure $extras = null): void {
            Schema::create($tabela, function (Blueprint $t) use ($extras) {
                $t->id();
                $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
                $t->string('descricao');
                $t->boolean('ativo')->default(true);
                if ($extras) {
                    $extras($t);
                }
                $t->timestamps();
            });
        };

        // Agência bancária (vinculada a um banco).
        $apoio('agencias', function (Blueprint $t) {
            $t->foreignId('banco_id')->nullable()->constrained('bancos')->nullOnDelete();
            $t->string('numero', 20)->nullable();
            $t->string('digito', 2)->nullable();
        });

        // Transportadora (cnpj/contato).
        $apoio('transportadoras', function (Blueprint $t) {
            $t->string('cnpj', 14)->nullable();
            $t->string('telefone', 20)->nullable();
        });

        // Feriado (data + se é recorrente todo ano).
        $apoio('feriados', function (Blueprint $t) {
            $t->date('data');
            $t->boolean('recorrente')->default(false);
        });

        // Profissão e estado civil (apoio de cliente/colaborador).
        $apoio('profissoes');
        $apoio('estados_civis');
    }

    public function down(): void
    {
        foreach (['estados_civis', 'profissoes', 'feriados', 'transportadoras', 'agencias'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
