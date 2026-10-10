<?php

namespace App\Domain\Tenant;

use Illuminate\Support\Facades\DB;

/**
 * Leitura (ou manutenção) que precisa enxergar TODAS as empresas.
 *
 * O runtime conecta como `erp_app`, sob RLS forçada. Fora de uma requisição não
 * há envelope de tenant, e toda tabela de empresa lê ZERO linhas num banco
 * cheio — `withoutGlobalScopes()` não ajuda, a barreira é do Postgres.
 *
 * Quem pode usar: conferência de plataforma (portões, relatórios de licença) e
 * o passo que DESCOBRE por quais empresas uma automação vai passar. O trabalho
 * dentro de cada empresa não roda aqui: roda pelo runtime, com o envelope da
 * identidade de automação, para a RLS continuar valendo.
 */
final class ConexaoDeOwner
{
    /**
     * Executa pela conexão de owner e devolve a conexão padrão ao estado
     * anterior — quem chama pode depender de ser o runtime depois.
     *
     * Só em PostgreSQL: em sqlite não há RLS, e `pgsql_owner` apontaria para
     * outro banco. Sem credencial de owner, executa pelo runtime mesmo: o
     * resultado pode ser cego, mas derrubar o processo não ajudaria ninguém.
     *
     * @template T
     *
     * @param  callable(): T  $trabalho
     * @return T
     */
    public static function executar(callable $trabalho): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $trabalho();
        }

        try {
            DB::connection('pgsql_owner')->getPdo();
        } catch (\Throwable) {
            return $trabalho();
        }

        $padrao = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_owner');

        try {
            return $trabalho();
        } finally {
            DB::setDefaultConnection($padrao);
        }
    }
}
