<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNIQUE (empresa_id, api_id) em `clientes` — a trava definitiva da T2.1/T2.2.
 *
 * Separada da migration que cria a coluna (…000100) porque num banco já
 * carregado existem 4 linhas por `api_id`: a restrição só pode nascer depois da
 * deduplicação (`php artisan dados:dedup-clientes-app`).
 *
 * Com ela no lugar, a duplicação deixa de ser possível mesmo que alguém
 * reintroduza o bug no migrator — o banco recusa a segunda inserção em vez de
 * aceitá-la silenciosamente. É a diferença entre corrigir o sintoma e fechar
 * a porta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicados = $this->gruposDuplicados();

        if ($duplicados > 0) {
            throw new \RuntimeException(
                "Não é possível criar o UNIQUE (empresa_id, api_id): existem {$duplicados} "
                .'grupos de api_id duplicados em public.clientes. '
                .'Rode `php artisan dados:dedup-clientes-app` (T2.2) antes desta migration.'
            );
        }

        Schema::table('clientes', function (Blueprint $tabela) {
            $tabela->unique(['empresa_id', 'api_id'], 'clientes_empresa_api_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $tabela) {
            $tabela->dropUnique('clientes_empresa_api_unique');
        });
    }

    /**
     * Quantos api_id aparecem mais de uma vez na mesma empresa.
     *
     * Falhar aqui com mensagem clara é melhor que deixar o Postgres devolver
     * um "duplicate key value violates unique constraint" sem dizer o que fazer.
     */
    private function gruposDuplicados(): int
    {
        if (! Schema::hasColumn('clientes', 'api_id')) {
            return 0;
        }

        $linhas = DB::table('clientes')
            ->select('empresa_id', 'api_id')
            ->whereNotNull('api_id')
            ->groupBy('empresa_id', 'api_id')
            ->havingRaw('count(*) > 1')
            ->get();

        return $linhas->count();
    }
};
