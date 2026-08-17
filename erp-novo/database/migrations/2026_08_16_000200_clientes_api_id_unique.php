<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNIQUE (empresa_id, api_id) em `clientes` — a trava definitiva da T2.1/T2.2.
 *
 * Separada da migration que cria a coluna (…000100) porque num banco já
 * carregado existem várias linhas por `api_id`: a restrição só pode nascer
 * depois da deduplicação.
 *
 * Com ela no lugar, a duplicação deixa de ser possível mesmo que alguém
 * reintroduza o bug no migrator — o banco recusa a segunda inserção em vez de
 * aceitá-la silenciosamente. É a diferença entre corrigir o sintoma e fechar
 * a porta.
 *
 * **Por que a dedup roda AQUI e não como passo separado do deploy.** O workflow
 * aplica `migrate --force` antes de qualquer comando artisan, então uma
 * migration que só abortasse deixaria o deploy travado até alguém entrar na
 * VPS à mão. Rodar a dedup na própria migration mantém a ordem correta
 * (deduplica → cria a restrição) sem passo manual.
 *
 * **A conexão importa.** A dedup precisa ler `pg_constraint` para descobrir as
 * FKs a remapear, e precisa poder apagar linhas. A conexão DEFAULT da aplicação
 * é `erp_app` — a role restrita `NOSUPERUSER NOBYPASSRLS` do multi-tenant, que
 * não é dona das tabelas e por isso enxerga ZERO FKs (verificado em produção:
 * o dry-run com ela reportou "0 tabelas referenciam clientes.id" enquanto havia
 * 40 mil linhas filhas apontando para as cópias). Por isso a conexão default é
 * trocada para a mesma com que a migration está rodando — `pgsql_owner` no
 * deploy — durante a chamada, e restaurada logo depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicados = $this->gruposDuplicados();

        if ($duplicados > 0) {
            // A dedup é transacional: remapeia as FKs, funde as filhas que
            // convergirem e só então remove as cópias. Se qualquer etapa falhar,
            // o rollback é limpo e a restrição não chega a ser criada.
            $this->comConexaoDaMigration(
                fn () => Artisan::call('dados:dedup-clientes-app', ['--executar' => true]),
            );

            $restantes = $this->gruposDuplicados();
            if ($restantes > 0) {
                throw new \RuntimeException(
                    "A deduplicação não zerou os duplicados: ainda há {$restantes} "
                    .'grupos de api_id repetidos em public.clientes. Investigue antes '
                    .'de criar o UNIQUE — a saída do comando está no log do deploy.'
                );
            }
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
     * Executa algo com a conexão default apontando para a MESMA que a migration
     * está usando (`--database=pgsql_owner` no deploy).
     *
     * `Artisan::call` resolve `DB::connection()` pela default do config, não pela
     * conexão da migration — sem isto, a dedup rodaria como `erp_app` e não
     * enxergaria FK nenhuma.
     *
     * @template T
     *
     * @param  callable():T  $acao
     * @return T
     */
    private function comConexaoDaMigration(callable $acao): mixed
    {
        $anterior = config('database.default');
        $daMigration = $this->getConnection() ?: $anterior;

        config(['database.default' => $daMigration]);

        try {
            return $acao();
        } finally {
            config(['database.default' => $anterior]);
        }
    }

    /** Quantos api_id aparecem mais de uma vez na mesma empresa. */
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
