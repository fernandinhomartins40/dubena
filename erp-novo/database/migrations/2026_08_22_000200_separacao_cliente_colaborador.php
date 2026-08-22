<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separação explícita entre CLIENTE e COLABORADOR.
 *
 * Hoje os dois papéis apontam para a mesma tabela `users` sem nada que diga
 * qual é qual, e o app descobre o perfil por ausência: `AppAuthController::
 * vinculoDe()` faz fallback para FUNCIONARIO quando não acha colaborador — ou
 * seja, quem não tem cadastro de colaborador recebe perfil de funcionário.
 * Isso é fail-OPEN num ponto de identidade, o oposto do que o resto do sistema
 * faz ("sem credencial, não autentica").
 *
 * Duas mudanças:
 *
 * 1. `users.tipo_principal` — quem é este login: cliente | colaborador. Sem
 *    inferência, sem fallback.
 *
 * 2. `colaboradores.cliente_id` — a mesma PESSOA com DOIS PAPÉIS. Medido: 36
 *    colaboradores têm cadastro de cliente com o mesmo CPF. Hoje são dois
 *    registros soltos e o histórico de compra do funcionário fica partido;
 *    com o vínculo, ele compra como cliente e trabalha como colaborador sem
 *    duplicar a identidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Nullable de propósito: os usuários já existentes são classificados
            // logo abaixo, e um valor default fixo mentiria sobre quem não se
            // consegue classificar.
            $t->string('tipo_principal', 20)->nullable()->index();
        });

        Schema::table('colaboradores', function (Blueprint $t) {
            // nullOnDelete: o cadastro de cliente pode ser consolidado noutro;
            // perder o vínculo é aceitável, perder o colaborador não.
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
        });

        $this->classificarExistentes();
    }

    public function down(): void
    {
        Schema::table('colaboradores', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cliente_id');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('tipo_principal');
        });
    }

    /**
     * Classifica os logins que já existem pelo vínculo que eles têm hoje.
     *
     * Quem está em `colaboradores.user_id` é colaborador; quem está em
     * `clientes.user_id` é cliente. Quem não está em nenhum dos dois fica NULO
     * — e o código trata nulo como "não identificado", nunca como funcionário.
     */
    private function classificarExistentes(): void
    {
        DB::table('users')
            ->whereIn('id', fn ($q) => $q->select('user_id')->from('colaboradores')->whereNotNull('user_id'))
            ->update(['tipo_principal' => 'colaborador']);

        DB::table('users')
            ->whereNull('tipo_principal')
            ->whereIn('id', fn ($q) => $q->select('user_id')->from('clientes')->whereNotNull('user_id'))
            ->update(['tipo_principal' => 'cliente']);
    }
};
