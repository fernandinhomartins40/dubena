<?php

namespace App\Models\Financeiro;

/**
 * Como o par entre extrato e movimento do ERP foi formado.
 *
 * Enum e não string solta, pelo motivo de sempre neste repositório: é uma
 * máquina de estados, e string solta aceita 'automatico', 'AUTO' e 'Automático'
 * como coisas diferentes.
 *
 * A distinção entre AUTOMATICO e MANUAL é o que a tarefa F5-04 chama de
 * "exceções manuais auditadas". Sem ela, a decisão de uma pessoa fica
 * indistinguível do resultado do algoritmo — e a pergunta "quem decidiu que
 * esses dois são o mesmo lançamento?" deixa de ter resposta.
 */
enum OrigemMatch: string
{
    /** Casado pela regra (valor + data dentro da tolerância). */
    case AUTOMATICO = 'AUTOMATICO';

    /** Casado por uma pessoa, que assumiu a responsabilidade pelo par. */
    case MANUAL = 'MANUAL';

    /** Visto no extrato, sem par no ERP. É o que o operador precisa resolver. */
    case PENDENTE = 'PENDENTE';

    /**
     * Par desfeito por uma pessoa.
     *
     * Estado próprio, e não volta a PENDENTE, porque "nunca casou" e "casou e
     * alguém desfez" são fatos diferentes — e o segundo é o interessante.
     */
    case DESFEITO = 'DESFEITO';
}
