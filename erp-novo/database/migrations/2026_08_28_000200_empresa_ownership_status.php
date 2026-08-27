<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F0-01: não inferir titularidade jurídica da rede/grupo atual.
 *
 * Toda empresa preexistente entra explicitamente como não classificada até a
 * decisão documental. F1 substitui esta marca por TenantAccount e vínculos
 * aprovados; até lá, a ausência da decisão fica observável e não vira acesso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('ownership_status', 32)
                ->default('OWNERSHIP_UNRESOLVED')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex(['ownership_status']);
            $table->dropColumn('ownership_status');
        });
    }
};
