<?php

namespace App\Domain\Seguranca;

use App\Models\User2fa;

/**
 * Verificação de 2FA (A5) — PONTO ÚNICO (Q-2 da auditoria).
 *
 * Antes o mesmo `verificar2fa` estava duplicado byte-a-byte no AuthController
 * (web) e no AppAuthController (app): verificar o TOTP OU consumir um recovery
 * code de uso único. Agora ambos delegam aqui — uma correção, um lugar.
 */
class VerificadorDoisFatores
{
    public function __construct(private Totp $totp) {}

    /**
     * Verifica o OTP contra o segredo TOTP; se não bater, tenta consumir um
     * recovery code (uso único — remove-o ao usar). Retorna true se qualquer um
     * autenticar. `$twofa` nulo → false.
     */
    public function verificar(?User2fa $twofa, string $otp): bool
    {
        if ($twofa === null) {
            return false;
        }

        if ($this->totp->verificar($twofa->secret, $otp)) {
            return true;
        }

        // Recovery code (one-time): se bater, consome e segue.
        $codes = $twofa->recovery_codes ?? [];
        $idx = array_search(strtoupper(trim($otp)), array_map('strtoupper', $codes), true);
        if ($idx !== false) {
            unset($codes[$idx]);
            $twofa->update(['recovery_codes' => array_values($codes)]);

            return true;
        }

        return false;
    }
}
