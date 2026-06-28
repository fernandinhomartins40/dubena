<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3b — MÚLTIPLOS endereços por cliente (paridade com o legado `clienteenderecos`).
 *
 * O ERP-NOVO tinha só o endereço inline do cliente (1 só). O app legado permite
 * vários endereços de entrega com um favorito. Esta tabela porta essa capacidade
 * sem reduzir funcionalidade. Escopo por empresa (como o cliente). `favorito`
 * marca o endereço padrão de entrega (1 por cliente, garantido no service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_enderecos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->string('titulo', 100)->default('Casa');     // Casa, Trabalho, Outro
            $t->string('endereco', 255);
            $t->string('numero', 20)->nullable();
            $t->string('complemento', 120)->nullable();
            $t->string('ponto_referencia', 160)->nullable();
            $t->string('bairro', 120)->nullable();
            $t->string('cidade', 120)->nullable();
            $t->string('cep', 12)->nullable();
            $t->string('uf', 2)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('favorito')->default(false);
            $t->timestamps();

            $t->index(['empresa_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_enderecos');
    }
};
