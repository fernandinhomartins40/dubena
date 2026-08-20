<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cache do encaixe de trechos nas ruas (economia da Roads API).
 *
 * Parte da frota reporta a cada 2 minutos — quase 1 km entre posições. Ligar
 * dois pontos assim em reta atravessa quarteirão, e só a Roads API sabe por
 * onde a rua passa entre eles. Como a chamada é paga, cada trecho aprendido
 * fica guardado: a revenda repete as mesmas ruas todo dia, e o custo despenca
 * depois dos primeiros dias de uso.
 *
 * GLOBAL (sem empresa_id/RLS de propósito), pelo mesmo motivo de `rotas_cache`:
 * o traçado de uma rua é fato geográfico público, não dado de tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitora_vias_cache', function (Blueprint $t) {
            $t->id();
            // Hash da sequência de células (~100 m) do trecho. Hash e não a
            // lista crua porque 100 pontos dariam 1,7 kB — não caberia no
            // índice B-tree do Postgres, que tem limite por entrada.
            $t->string('trecho', 32)->unique();
            $t->json('pontos');
            $t->unsignedInteger('hits')->default(0);
            $t->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $role = 'erp_app';
        if (DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]) === null) {
            return;
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON monitora_vias_cache TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE monitora_vias_cache_id_seq TO {$role}");
    }

    public function down(): void
    {
        Schema::dropIfExists('monitora_vias_cache');
    }
};
