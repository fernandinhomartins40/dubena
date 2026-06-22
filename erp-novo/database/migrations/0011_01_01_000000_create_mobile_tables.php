<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N10 — Mobile / API externa: devices (push FCM) + transações de pagamento online.
 *
 * Auth do app reusa o token Sanctum por usuário/colaborador (sem usuário-mestre
 * via env — a falha de segurança do legado). Pagamento online (Rede) é GATE,
 * isolado em service/driver; aqui ficam os registros rastreáveis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $t->string('plataforma', 12)->nullable();    // android, ios
            $t->string('push_token', 255)->nullable();    // token FCM
            $t->string('device_id', 120)->nullable();
            $t->string('app_versao', 20)->nullable();
            $t->boolean('ativo')->default(true);
            $t->dateTime('ultimo_acesso')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'device_id']);
            $t->index('push_token');
        });

        Schema::create('pagamentos_online', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->string('gateway', 20)->default('rede');   // adquirente
            $t->string('tid', 64)->nullable();             // id da transação no gateway
            $t->string('nsu', 30)->nullable();
            $t->string('autorizacao', 30)->nullable();
            $t->string('bandeira', 20)->nullable();
            $t->decimal('valor', 12, 2);
            $t->unsignedSmallInteger('parcelas')->default(1);
            // PENDENTE, AUTORIZADO, CAPTURADO, NEGADO, CANCELADO, ESTORNADO.
            $t->string('situacao', 20)->default('PENDENTE');
            $t->text('mensagem')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'situacao']);
            $t->index('tid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos_online');
        Schema::dropIfExists('app_devices');
    }
};
