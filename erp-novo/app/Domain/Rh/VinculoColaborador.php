<?php

namespace App\Domain\Rh;

/**
 * Sob qual relação a pessoa trabalha para a rede — F1.
 *
 * Enum, e não string solta, porque o vínculo decide autorização (que papel o
 * token recebe) e dinheiro (folha × comissão/repasse). É máquina de estados de
 * negócio, e o CLAUDE.md manda enum para isso.
 */
enum VinculoColaborador: string
{
    /** CLT: entregador com vínculo empregatício, remunerado por salário. */
    case FUNCIONARIO = 'funcionario';

    /** PJ sem vínculo: entrega e vende, remunerado por comissão ou repasse. */
    case FRANQUEADO = 'franqueado';

    /** Vendedor da rede para empresa/indústria: emite nota e negocia preço. */
    case INDUSTRIAL = 'industrial';

    /**
     * Ability de papel que o token do app recebe.
     * O `AppRole` (Middleware/AppRole.php:16) compara contra `role:<papel>`.
     */
    public function papelDoApp(): string
    {
        return match ($this) {
            self::FUNCIONARIO => 'entregador',
            self::FRANQUEADO => 'franqueado',
            self::INDUSTRIAL => 'industrial',
        };
    }

    /** Entra em folha de pagamento? Só o CLT — o resto recebe por comissão/repasse. */
    public function entraEmFolha(): bool
    {
        return $this === self::FUNCIONARIO;
    }

    /** Pode pedir desconto acima da própria alçada para a Central aprovar (F2/F3)? */
    public function podeSolicitarDesconto(): bool
    {
        return $this !== self::FUNCIONARIO;
    }

    /** Emite NF-e em campo? Só o industrial (F6). */
    public function emiteNotaEmCampo(): bool
    {
        return $this === self::INDUSTRIAL;
    }
}
