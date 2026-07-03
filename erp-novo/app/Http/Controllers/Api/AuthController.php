<?php

namespace App\Http\Controllers\Api;

use App\Domain\Seguranca\AuditoriaSeguranca;
use App\Domain\Seguranca\LoginSeguranca;
use App\Domain\Seguranca\VerificadorDoisFatores;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autenticação (Sanctum) — suporta os DOIS modos no mesmo endpoint:
 *  - SPA (cookie): se a requisição é stateful (mesmo domínio + csrf-cookie), o
 *    login fixa a sessão (Auth::attempt + regenerate) — o cookie autentica as
 *    próximas requisições.
 *  - Apps/integrações (token): sempre devolve um token Bearer no corpo.
 *
 * A5 — segurança: trilha em login_logs, LOCKOUT por falhas recentes (além do
 * rate-limit) e 2FA (TOTP). Se o usuário tem 2FA habilitado e não envia um `otp`
 * válido, o login responde 423 com `two_factor_required` (sem emitir token).
 */
class AuthController extends Controller
{
    public function __construct(
        private LoginSeguranca $seguranca,
        private VerificadorDoisFatores $doisFatores,
        private AuditoriaSeguranca $auditoria,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $cred = $request->validated();
        $email = (string) $cred['email'];

        // Lockout: barra antes mesmo de tentar autenticar.
        if ($this->seguranca->bloqueado($email, $request->ip())) {
            $this->seguranca->registrar($request, $email, false, 'lockout');

            return response()->json(['message' => 'Muitas tentativas. Tente novamente em alguns minutos.'], 429);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $cred['password']], remember: true)) {
            $this->seguranca->registrar($request, $email, false, 'credenciais');

            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->ativo) {
            Auth::logout();
            $this->seguranca->registrar($request, $email, false, 'inativo', $user->id, $user->empresa_id);

            return response()->json(['message' => 'Usuário inativo.'], 403);
        }

        // 2FA: se habilitado, exige um OTP válido (ou um recovery code).
        $twofa = $user->twoFactor;
        if ($twofa && $twofa->habilitado) {
            $otp = (string) ($request->input('otp', ''));
            if ($otp === '' || ! $this->doisFatores->verificar($twofa, $otp)) {
                Auth::logout();
                $this->seguranca->registrar($request, $email, false, '2fa', $user->id, $user->empresa_id);

                return response()->json([
                    'message' => 'Código de verificação necessário.',
                    'two_factor_required' => true,
                ], 423);
            }
        }

        // SPA cookie-based: fixa a sessão (o cookie autentica as próximas requisições).
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $this->seguranca->registrar($request, $email, true, 'ok', $user->id, $user->empresa_id);

        // S-7: o usuário `support` ignora TODA verificação de permissão (Gate::before).
        // Logar cada gate seria ruído; logamos o LOGIN de suporte — evento raro e
        // acionável — para haver trilha de quando o bypass total entrou em uso.
        if ($user->support) {
            $this->auditoria->registrar('login.suporte', $email, ['user_id' => $user->id]);
        }

        // Token Bearer para apps/integrações (a SPA pode ignorar e usar o cookie).
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            // Shape único (inclui roles/permissions) — o RBAC da SPA depende disto.
            'user' => $user->payloadAuth(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Token: revoga o access token atual. Cookie: invalida a sessão.
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
