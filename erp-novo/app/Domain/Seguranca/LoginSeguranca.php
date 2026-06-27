<?php

namespace App\Domain\Seguranca;

use App\Models\LoginLog;
use Illuminate\Http\Request;

/**
 * Regras de segurança do login (A5): trilha em login_logs e LOCKOUT por falhas
 * recentes (além do rate-limit por IP do throttle). O lockout conta falhas por
 * e-mail E por IP numa janela curta — barra brute-force direcionado a uma conta
 * mesmo que o atacante rode de vários IPs (por e-mail) ou contra vários e-mails
 * de um IP (por IP).
 */
class LoginSeguranca
{
    /** Falhas toleradas na janela antes de bloquear. */
    private const MAX_FALHAS = 5;

    /** Janela de contagem das falhas (minutos). */
    private const JANELA_MIN = 15;

    /** O e-mail/IP está bloqueado por excesso de falhas recentes? */
    public function bloqueado(string $email, string $ip): bool
    {
        $desde = now()->subMinutes(self::JANELA_MIN);

        $porEmail = LoginLog::query()
            ->where('email', $email)->where('sucesso', false)
            ->where('criado_em', '>=', $desde)->count();

        $porIp = LoginLog::query()
            ->where('ip', $ip)->where('sucesso', false)
            ->where('criado_em', '>=', $desde)->count();

        return $porEmail >= self::MAX_FALHAS || $porIp >= self::MAX_FALHAS;
    }

    /** Registra uma tentativa (sucesso/falha) com o motivo. */
    public function registrar(Request $request, string $email, bool $sucesso, string $motivo, ?int $userId = null, ?int $empresaId = null): void
    {
        LoginLog::create([
            'user_id' => $userId,
            'email' => $email,
            'empresa_id' => $empresaId,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'sucesso' => $sucesso,
            'motivo' => $motivo,
        ]);
    }
}
