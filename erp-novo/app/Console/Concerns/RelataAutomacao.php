<?php

namespace App\Console\Concerns;

use App\Domain\Tenant\ResultadoDaAutomacao;

/**
 * Como um comando agendado conta o que fez — e, principalmente, o que NÃO fez.
 *
 * Antes da identidade de automação estes comandos terminavam com `DONE` tendo
 * processado zero empresas, e nada distinguia isso de "não havia trabalho".
 */
trait RelataAutomacao
{
    /** Imprime o resumo e devolve o código de saída do comando. */
    protected function relatar(ResultadoDaAutomacao $resultado): int
    {
        if ($resultado->semIdentidade !== []) {
            $this->warn(sprintf(
                '%d empresa(s) PULADA(S) por falta de identidade de automação: #%s. Rode `saas:automacao:provisionar`.',
                count($resultado->semIdentidade),
                implode(', #', $resultado->semIdentidade),
            ));
        }

        foreach ($resultado->falhas as $empresaId => $mensagem) {
            $this->error("Empresa #{$empresaId} falhou: {$mensagem}");
        }

        // Falha de uma empresa reprova a execução; as outras já foram
        // processadas. Empresa pulada NÃO reprova: o agendador roda a cada 30
        // segundos, e o lugar de cobrar a configuração é o `golive:check`.
        return $resultado->falhas === [] ? self::SUCCESS : self::FAILURE;
    }
}
