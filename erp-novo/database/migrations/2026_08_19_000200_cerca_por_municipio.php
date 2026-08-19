<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cerca pertence a um MUNICÍPIO; o setor é a divisão dentro dele.
 *
 * A lista plana não escala e já misturava dois níveis: das 19 cercas migradas,
 * "Colônia", "Turvo", "Goioxim" e "Boa Ventura do São Roque" são municípios
 * inteiros, enquanto "Setor 01" a "Setor 08" são zonas dentro de Guarapuava.
 * Quem opera em várias cidades não conseguia enxergar o que é de onde.
 *
 * `cidade_id` é NULLABLE de propósito: cerca desenhada antes desta mudança —
 * ou cujo polígono não cai em nenhum município cadastrado — continua válida e
 * aparece agrupada como "Sem município". Exigir a cidade tornaria inválido o
 * dado que já existe, e esconderia cerca em vez de mostrá-la para ser
 * classificada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitora_cercas', function (Blueprint $t) {
            // `nullOnDelete`: apagar uma cidade do cadastro não pode apagar a
            // área de entrega desenhada — ela vira "sem município" e continua
            // valendo como geofence.
            $t->foreignId('cidade_id')->nullable()->after('empresa_id')
                ->constrained('cidades')->nullOnDelete();
            $t->index(['empresa_id', 'cidade_id']);
        });
    }

    public function down(): void
    {
        Schema::table('monitora_cercas', function (Blueprint $t) {
            $t->dropIndex(['empresa_id', 'cidade_id']);
            $t->dropConstrainedForeignId('cidade_id');
        });
    }
};
