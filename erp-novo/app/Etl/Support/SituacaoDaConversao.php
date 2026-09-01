<?php

namespace App\Etl\Support;

/**
 * F7-02 — os estados de uma execução de conversão.
 *
 * ## Por que enum, e não string solta
 *
 * `encerrar('CONCLUIDA')` aceitava qualquer texto. Um erro de digitação
 * (`'CONCLUÍDA'`, com acento) gravaria um estado que nenhuma consulta encontra,
 * e a execução ficaria invisível para o gate de cutover — que procura por
 * `CONCLUIDA` exato.
 *
 * É a mesma razão pela qual `SituacaoNota` e `EfeitoPedido` são enums nesta
 * base: máquina de estados em string é máquina de estados sem máquina.
 *
 * ## Feliz × bloqueante
 *
 * O plano F7-02 pede "estados felizes e bloqueantes". A distinção não é
 * decorativa: é ela que um script de deploy consulta para decidir se pode
 * seguir. `FALHOU` e `INTERROMPIDA` são desfechos legítimos — não são erros do
 * registro —, mas nenhum deles libera o cutover.
 */
enum SituacaoDaConversao: string
{
    case EM_ANDAMENTO = 'EM_ANDAMENTO';

    /** O único estado feliz. */
    case CONCLUIDA = 'CONCLUIDA';

    /** Invariante reprovada, ou origem indisponível: a carga está incompleta. */
    case FALHOU = 'FALHOU';

    /**
     * Morreu no meio — OOM, sessão fechada, processo morto.
     *
     * Existe porque linha eternamente `EM_ANDAMENTO` é indistinguível de carga
     * rodando agora, e alguém acabaria esperando por um processo que já morreu.
     */
    case INTERROMPIDA = 'INTERROMPIDA';

    /** Estados finais: uma execução que chegou aqui não volta atrás. */
    public function final(): bool
    {
        return $this !== self::EM_ANDAMENTO;
    }

    /** Só `CONCLUIDA` libera o que depende da conversão ter dado certo. */
    public function liberaCutover(): bool
    {
        return $this === self::CONCLUIDA;
    }

    /**
     * As transições que a máquina aceita.
     *
     * Só de `EM_ANDAMENTO`, e só para um estado final. Duas consequências
     * deliberadas:
     *
     *  - **não se reabre execução encerrada.** Reabrir apagaria o registro do
     *    que aconteceu, e o registro é a evidência;
     *  - **não se sobrescreve desfecho.** Uma execução que FALHOU não vira
     *    CONCLUIDA por uma segunda chamada — que é exatamente o que o CAS em
     *    `RegistroDaConversao::encerrar()` impede na corrida entre dois
     *    processos.
     */
    public function podeIrPara(self $destino): bool
    {
        return $this === self::EM_ANDAMENTO && $destino->final();
    }
}
