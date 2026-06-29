<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Saas\AuditoriaPlataforma;
use App\Domain\Seguranca\Totp;
use App\Http\Controllers\Controller;
use App\Models\Saas\PlatformAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Auth do SuperAdmin (P4) — guard 'platform'. 2FA OBRIGATÓRIO: se o admin tem 2FA
 * habilitado e não envia OTP válido, o login responde 423 sem emitir token. Toda
 * tentativa (sucesso/falha) é auditada em platform_audit_logs.
 */
class AuthController extends Controller
{
    public function __construct(
        private Totp $totp,
        private AuditoriaPlataforma $auditoria,
    ) {}

    /** POST /superadmin/login */
    public function login(Request $request): JsonResponse
    {
        $d = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'otp' => 'nullable|string',
        ]);

        $admin = PlatformAdmin::query()->where('email', $d['email'])->where('ativo', true)->first();
        if (! $admin || ! Hash::check($d['password'], $admin->password)) {
            $this->auditoria->registrar('login.falha', null, 'platform_admins', $admin?->id, null, ['motivo' => 'credenciais']);

            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        // 2FA obrigatório quando habilitado (recomendado para todo SuperAdmin).
        if ($admin->twofa_habilitado) {
            $otp = (string) ($d['otp'] ?? '');
            if ($otp === '' || ! $this->verificar2fa($admin, $otp)) {
                $this->auditoria->registrar('login.falha', null, 'platform_admins', $admin->id, null, ['motivo' => '2fa']);

                return response()->json(['message' => 'Código de verificação necessário.', 'two_factor_required' => true], 423);
            }
        }

        $token = $admin->createToken('superadmin')->plainTextToken;
        $this->auditoria->registrar('login.ok', null, 'platform_admins', $admin->id);

        return response()->json([
            'token' => $token,
            'admin' => ['id' => $admin->id, 'nome' => $admin->nome, 'email' => $admin->email],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    /** GET /superadmin/me */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json(['admin' => [
            'id' => $admin->id, 'nome' => $admin->nome, 'email' => $admin->email,
            'twofa_habilitado' => (bool) $admin->twofa_habilitado,
        ]]);
    }

    private function verificar2fa(PlatformAdmin $admin, string $otp): bool
    {
        if ($admin->twofa_secret && $this->totp->verificar($admin->twofa_secret, $otp)) {
            return true;
        }

        $codes = $admin->twofa_recovery_codes ?? [];
        $idx = array_search(strtoupper(trim($otp)), array_map('strtoupper', $codes), true);
        if ($idx !== false) {
            unset($codes[$idx]);
            $admin->update(['twofa_recovery_codes' => array_values($codes)]);

            return true;
        }

        return false;
    }
}
