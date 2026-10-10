<?php

namespace App\Console\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Comando de conferência que precisa ler dado de TODAS as empresas.
 *
 * O runtime conecta como `erp_app`, sob RLS forçada e sem envelope de tenant no
 * console: toda tabela de empresa lê ZERO linhas num banco cheio. Um portão que
 * conta por essa conexão não reprova — ele informa "nenhuma empresa" e passa.
 * Medido na homologação em 10/10/2026: `golive:check` dizia "Empresas
 * cadastradas (0)" e `saas:licenca:status` dizia "nenhuma empresa com vínculo
 * aprovado", com 7 empresas aprovadas no banco.
 *
 * É o mesmo defeito que `cutover:pos-check` teve ("reprovava por não ENXERGAR"),
 * só que do lado pior: aqui o resultado cego é o que LIBERA.
 */
trait EnxergaAtravesDaRls
{
    /**
     * Executa a leitura pela conexão de owner e devolve a conexão padrão ao
     * estado anterior — o resto do comando pode depender de ser o runtime
     * (o `golive:check` confere justamente a role dele).
     *
     * Só em PostgreSQL: em sqlite não há RLS, e `pgsql_owner` apontaria para
     * outro banco. Sem credencial de owner, lê pelo runtime mesmo: o resultado
     * pode ser cego, mas derrubar o comando não ajudaria ninguém.
     *
     * @template T
     *
     * @param  callable(): T  $leitura
     * @return T
     */
    protected function comoOwner(callable $leitura): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $leitura();
        }

        try {
            DB::connection('pgsql_owner')->getPdo();
        } catch (\Throwable) {
            return $leitura();
        }

        $padrao = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_owner');

        try {
            return $leitura();
        } finally {
            DB::setDefaultConnection($padrao);
        }
    }
}
