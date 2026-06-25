<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F01 — configuração GLOBAL por grupo (equivalente modernizado do
 * `configuracoesgerais` do legado, que era uma única linha global). Aqui é uma
 * linha por GRUPO (rede), pois o sistema é multi-tenant por grupo:
 *   - Responsável Técnico (RT) + CSRT (obrigatório para NF-e/NFC-e);
 *   - SMTP global (fallback do envio de e-mail);
 *   - SAT (CNPJs/signAC de produção e homologação);
 *   - chave Google Maps + link de monitoramento.
 *
 * Segredos (SMTP password, CSRT, signAC) ficam criptografados (cast 'encrypted').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('config_globais')) {
            return;
        }

        Schema::create('config_globais', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grupo_id')->unique()->constrained('grupos')->cascadeOnDelete();

            // Responsável Técnico (NT 2018.005) + CSRT.
            $t->string('rt_cnpj', 14)->nullable();
            $t->string('rt_contato', 120)->nullable();
            $t->string('rt_email', 120)->nullable();
            $t->string('rt_telefone', 20)->nullable();
            $t->string('rt_id_csrt', 10)->nullable();
            $t->text('rt_csrt')->nullable();          // criptografado

            // SMTP global (fallback).
            $t->string('email_remetente', 120)->nullable();
            $t->string('email_nome_remetente', 120)->nullable();
            $t->string('email_host', 120)->nullable();
            $t->unsignedSmallInteger('email_porta')->nullable();
            $t->string('email_usuario', 120)->nullable();
            $t->text('email_senha')->nullable();      // criptografado
            $t->boolean('email_tls')->default(true);

            // SAT (CF-e).
            $t->string('sat_cnpj_prod', 14)->nullable();
            $t->string('sat_cnpj_homolog', 14)->nullable();
            $t->text('sat_signac_prod')->nullable();  // criptografado
            $t->text('sat_signac_homolog')->nullable();

            // Geo.
            $t->string('google_maps_key', 120)->nullable();
            $t->string('link_monitoramento', 255)->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_globais');
    }
};
