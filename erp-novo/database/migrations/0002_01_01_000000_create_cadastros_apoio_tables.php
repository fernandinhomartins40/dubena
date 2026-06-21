<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N1 — Cadastros de apoio (descricao + ativo + extras), escopados por GRUPO.
 *
 * Espelha o legado consolidado (CadastroApoioController::TIPOS): cada cadastro é
 * uma tabela simples com unicidade de `descricao` no escopo do grupo. Campos
 * extras são tipados (booleanos nativos, não 0/1 string como no legado).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Helper local: cria uma tabela de apoio padrão (id, grupo_id, descricao,
        // ativo, timestamps) e deixa espaço para colunas extras via callback.
        $apoio = function (string $tabela, ?\Closure $extras = null): void {
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

        // ── Clientes ──
        $apoio('segmentos');
        $apoio('tipopessoas', function (Blueprint $t) {
            // legado: tipopessoacadastro (F=Física, J=Jurídica, etc.)
            $t->string('tipopessoacadastro', 1)->nullable();
        });
        $apoio('telefonetipos', function (Blueprint $t) {
            $t->boolean('celular')->default(false);
        });
        $apoio('clientecontatotipos');
        $apoio('clientecontatosituacoes');

        // ── Financeiro ──
        $apoio('bancos', function (Blueprint $t) {
            $t->string('codigo', 10)->nullable();
            $t->string('site')->nullable();
        });
        $apoio('contamovimentotipos', function (Blueprint $t) {
            $t->string('pagarreceber', 1)->nullable(); // P=pagar, R=receber
            $t->boolean('cheque')->default(false);
            $t->boolean('cartao')->default(false);
            $t->boolean('valegas')->default(false);
            $t->boolean('convenio')->default(false);
        });
    }

    public function down(): void
    {
        foreach ([
            'contamovimentotipos', 'bancos', 'clientecontatosituacoes',
            'clientecontatotipos', 'telefonetipos', 'tipopessoas', 'segmentos',
        ] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
