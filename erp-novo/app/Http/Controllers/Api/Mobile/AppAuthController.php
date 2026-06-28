<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Mobile\ClienteAuthService;
use App\Domain\Mobile\Exceptions\FirebaseTokenInvalido;
use App\Domain\Seguranca\LoginSeguranca;
use App\Domain\Seguranca\Totp;
use App\Http\Controllers\Controller;
use App\Models\Mobile\AppDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Auth do app (cliente/entregador) — N10/F1. Token REAL por usuário (Sanctum),
 * eliminando o usuário-mestre via env do legado. Registra o device (push).
 *
 * Dois caminhos de login:
 *  - login()        → e-mail/senha (colaborador/entregador);
 *  - loginCliente() → phone-auth do Firebase (cliente do app), via ClienteAuthService.
 *
 * Hardening (P1): o login por e-mail/senha tem PARIDADE de segurança com o web
 * (AuthController) — trilha em login_logs, LOCKOUT por falhas recentes (e-mail/IP)
 * e 2FA (TOTP). O login do CLIENTE (Firebase) já tem 2º fator no SMS; aqui só
 * registramos a trilha. Tokens de app emitidos com expiração (config sanctum).
 */
class AppAuthController extends Controller
{
    public function __construct(
        private LoginSeguranca $seguranca,
        private Totp $totp,
    ) {}

    /**
     * POST /app/v1/cliente/login — login do CLIENTE pelo app (F1).
     * Recebe o ID token do Firebase (telefone verificado por SMS) + a empresa, resolve
     * o cliente/usuário e emite o token Sanctum. Sem token-mestre, sem app_key.
     */
    public function loginCliente(Request $request, ClienteAuthService $auth): JsonResponse
    {
        $d = $request->validate([
            'firebase_id_token' => 'required|string',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'device_id' => 'nullable|string|max:120',
            'push_token' => 'nullable|string|max:255',
            'plataforma' => 'nullable|string|max:12',
            'app_versao' => 'nullable|string|max:20',
        ]);

        try {
            $user = $auth->autenticar([
                'firebase_id_token' => $d['firebase_id_token'],
                'empresa_id' => (int) $d['empresa_id'],
            ]);
        } catch (FirebaseTokenInvalido $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $this->registrarDeviceDoLogin($user, $d);
        $token = $user->createToken('app-cliente-'.($d['device_id'] ?? 'mobile'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'empresa_id' => $user->empresa_id],
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $d = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'otp' => 'nullable|string',
            'device_id' => 'nullable|string|max:120',
            'push_token' => 'nullable|string|max:255',
            'plataforma' => 'nullable|string|max:12',
            'app_versao' => 'nullable|string|max:20',
        ]);

        $email = (string) $d['email'];

        // Lockout: barra antes de validar credenciais (paridade com o web).
        if ($this->seguranca->bloqueado($email, $request->ip())) {
            $this->seguranca->registrar($request, $email, false, 'lockout');

            return response()->json(['message' => 'Muitas tentativas. Tente novamente em alguns minutos.'], 429);
        }

        $user = User::query()->where('email', $email)->where('ativo', true)->first();
        if (! $user || ! Hash::check($d['password'], $user->password)) {
            $this->seguranca->registrar($request, $email, false, 'credenciais', $user?->id, $user?->empresa_id);
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        // 2FA (TOTP): se habilitado, exige OTP válido (ou recovery code).
        $twofa = $user->twoFactor;
        if ($twofa && $twofa->habilitado) {
            $otp = (string) ($d['otp'] ?? '');
            if ($otp === '' || ! $this->verificar2fa($user, $otp)) {
                $this->seguranca->registrar($request, $email, false, '2fa', $user->id, $user->empresa_id);

                return response()->json([
                    'message' => 'Código de verificação necessário.',
                    'two_factor_required' => true,
                ], 423);
            }
        }

        $this->seguranca->registrar($request, $email, true, 'ok', $user->id, $user->empresa_id);
        $this->registrarDeviceDoLogin($user, $d);

        $token = $user->createToken('app-'.($d['device_id'] ?? 'mobile'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'empresa_id' => $user->empresa_id],
        ]);
    }

    /** Verifica OTP TOTP ou consome um recovery code (uso único) — espelha AuthController. */
    private function verificar2fa(User $user, string $otp): bool
    {
        $twofa = $user->twoFactor;
        if ($twofa === null) {
            return false;
        }

        if ($this->totp->verificar($twofa->secret, $otp)) {
            return true;
        }

        $codes = $twofa->recovery_codes ?? [];
        $idx = array_search(strtoupper(trim($otp)), array_map('strtoupper', $codes), true);
        if ($idx !== false) {
            unset($codes[$idx]);
            $twofa->update(['recovery_codes' => array_values($codes)]);

            return true;
        }

        return false;
    }

    /**
     * Registra/atualiza o device do usuário para push, a partir do payload de login.
     *
     * @param  array<string,mixed>  $d
     */
    private function registrarDeviceDoLogin(User $user, array $d): void
    {
        if (empty($d['device_id'])) {
            return;
        }

        AppDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $d['device_id']],
            [
                'empresa_id' => $user->empresa_id,
                'plataforma' => $d['plataforma'] ?? null,
                'push_token' => $d['push_token'] ?? null,
                'app_versao' => $d['app_versao'] ?? null,
                'ativo' => true,
                'ultimo_acesso' => now(),
            ],
        );
    }

    /**
     * POST /app/v1/cliente/cadastro — cadastro do CLIENTE pelo app (F3b, fluxo newuser).
     * Verifica o ID token do Firebase, cria o cliente com o telefone verificado, vincula
     * o usuário e já emite o token Sanctum (mesma resposta do login).
     */
    public function cadastrarCliente(Request $request, ClienteAuthService $auth): JsonResponse
    {
        $d = $request->validate([
            'firebase_id_token' => 'required|string',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'nome' => 'required|string|max:160',
            'cpf' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:160',
            'datanascimento' => 'nullable|date',
            'device_id' => 'nullable|string|max:120',
            'push_token' => 'nullable|string|max:255',
            'plataforma' => 'nullable|string|max:12',
            'app_versao' => 'nullable|string|max:20',
        ]);

        try {
            $user = $auth->cadastrar($d);
        } catch (FirebaseTokenInvalido $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $this->registrarDeviceDoLogin($user, $d);
        $token = $user->createToken('app-cliente-'.($d['device_id'] ?? 'mobile'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'empresa_id' => $user->empresa_id],
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout efetuado.']);
    }

    /**
     * POST /app/v1/token/refresh — reemite o token do app (rotação) e revoga o atual.
     * Como os tokens passam a expirar (P1), o app troca o token vigente por um novo
     * sem refazer o login. O device é resolvido pelo nome do token atual.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $atual = $user->currentAccessToken();
        $nome = $atual->name ?? 'app-mobile';

        // Emite o novo ANTES de revogar o atual (não deixa o app sem token se algo falhar).
        $token = $user->createToken($nome)->plainTextToken;
        if (method_exists($atual, 'delete')) {
            $atual->delete();
        }

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'empresa_id' => $user->empresa_id],
        ]);
    }

    /** Atualiza só o push token do device (renovação FCM). */
    public function registrarDevice(Request $request): JsonResponse
    {
        $d = $request->validate([
            'device_id' => 'required|string|max:120',
            'push_token' => 'required|string|max:255',
            'plataforma' => 'nullable|string|max:12',
        ]);

        $device = AppDevice::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_id' => $d['device_id']],
            ['push_token' => $d['push_token'], 'plataforma' => $d['plataforma'] ?? null, 'ativo' => true, 'ultimo_acesso' => now()],
        );

        return response()->json(['data' => $device], 201);
    }
}
