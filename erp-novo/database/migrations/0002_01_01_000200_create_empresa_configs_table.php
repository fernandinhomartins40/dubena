<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N1 — Configuração operacional/fiscal da empresa (1:1 com empresas).
 *
 * O legado tem ~106 colunas planas em `empresas`, muitas referenciando entidades
 * que só existem em fases posteriores (setor → N3, conta → N6, certificado → fiscal
 * N9). Em vez de replicar 106 colunas cegamente (princípio: schema limpo), aqui:
 *   - colunas ESTRUTURAIS já tipadas para o que faz sentido em N1;
 *   - `dados` (JSON) guarda o restante das chaves de config de forma flexível,
 *     promovidas a coluna tipada quando o módulo dono chegar.
 *
 * A senha-mestra é hash (nunca texto), separada do JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_configs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();

            // Pedido/entrega (inteiros).
            $t->integer('tempoentrega')->nullable();
            $t->integer('tempourgente')->nullable();
            $t->integer('maximoparcelas')->nullable();

            // E-mail (SMTP) — senha guardada encriptada (cast 'encrypted').
            $t->string('email_host')->nullable();
            $t->integer('email_port')->nullable();
            $t->string('email_username')->nullable();
            $t->text('email_password')->nullable();
            $t->string('email_from_address')->nullable();
            $t->string('email_from_name')->nullable();
            $t->string('email_encryption', 10)->nullable();

            // Senha-mestra (hash, nunca texto claro).
            $t->string('senha_mestra')->nullable();

            // Demais chaves de config (flags/percentuais/refs futuras) como JSON.
            $t->json('dados')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_configs');
    }
};
