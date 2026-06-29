<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4 — Painel SuperAdmin: administradores da PLATAFORMA + trilha cross-tenant.
 *
 * O SuperAdmin é a ÚNICA camada autorizada a cruzar tenants (administrar todas as
 * empresas/planos/cidades). Por isso é deliberadamente SEPARADO do tenant:
 *
 *  - platform_admins (GLOBAL, fora de qualquer empresa): identidade própria do
 *    operador da plataforma, com guard 'platform' (token Sanctum dedicado). NÃO é
 *    um `users` com flag — é uma tabela à parte, para que um vazamento de credencial
 *    de tenant nunca conceda acesso de plataforma, e vice-versa. Sem empresa_id/
 *    grupo_id → fora da RLS por natureza.
 *  - platform_audit_logs (GLOBAL, append-only): registra TODA ação do SuperAdmin
 *    (quem, quando, sobre qual tenant/entidade, antes/depois). É a prestação de
 *    contas do acesso cross-tenant — requisito de sigilo: nada cruza empresas sem
 *    ficar gravado aqui.
 *
 * Tokens do guard 'platform' usam a mesma tabela personal_access_tokens (morph),
 * apontando para PlatformAdmin — não há colisão com os tokens de `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('ativo')->default(true);
            // 2FA obrigatório para o SuperAdmin (P4): segredo TOTP cifrado.
            $t->text('twofa_secret')->nullable();          // encrypted
            $t->boolean('twofa_habilitado')->default(false);
            $t->text('twofa_recovery_codes')->nullable();  // encrypted (json)
            $t->timestamp('twofa_confirmado_em')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('platform_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $t->string('acao', 60);                 // ex.: 'empresa.suspensa', 'assinatura.alterada'
            $t->unsignedBigInteger('empresa_id')->nullable(); // tenant alvo (sem FK: trilha sobrevive à exclusão)
            $t->string('entidade', 60)->nullable(); // tabela/recurso afetado
            $t->unsignedBigInteger('entidade_id')->nullable();
            $t->json('antes')->nullable();
            $t->json('depois')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('criado_em')->useCurrent();
            $t->index(['empresa_id', 'criado_em']);
            $t->index(['acao', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_admins');
    }
};
