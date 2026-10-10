<?php

namespace App\Console\Concerns;

use App\Domain\Tenant\ConexaoDeOwner;

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
     * @template T
     *
     * @param  callable(): T  $leitura
     * @return T
     */
    protected function comoOwner(callable $leitura): mixed
    {
        return ConexaoDeOwner::executar($leitura);
    }
}
