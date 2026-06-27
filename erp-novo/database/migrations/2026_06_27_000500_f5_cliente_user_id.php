<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5 — Vínculo opcional usuário-do-app ↔ cliente. Permite notificar o cliente por
 * push (FCM) na mudança de status do pedido, mirando os devices do usuário ligado
 * ao cliente. Nullable: clientes de balcão/legado seguem sem usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->after('grupo_id')->constrained('users')->nullOnDelete();
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
        });
    }
};
