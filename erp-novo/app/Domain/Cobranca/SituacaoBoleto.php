<?php

namespace App\Domain\Cobranca;

/**
 * Situação do boleto (N7). Atualizada pelo retorno CNAB (ocorrências do banco).
 */
enum SituacaoBoleto: string
{
    case PENDENTE = 'PENDENTE';       // criado, ainda não registrado no banco
    case REGISTRADO = 'REGISTRADO';   // registrado no banco (remessa aceita)
    case LIQUIDADO = 'LIQUIDADO';     // pago
    case BAIXADO = 'BAIXADO';         // baixado sem pagamento
    case PROTESTADO = 'PROTESTADO';
    case REJEITADO = 'REJEITADO';
    case CANCELADO = 'CANCELADO';
}
