<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Saas\AuditoriaPlataforma;
use App\Domain\Seguranca\Totp;
use App\Http\Controllers\Controller;
use App\Models\Saas\PlatformAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Auth do SuperAdmin (P4) — guard 'platform'. Toda tentativa (sucesso/falha) é
 * auditada em platform_audit_logs.
 *
 * PIE P2-E-06 — o "2FA obrigatório" existia só neste comentário: não havia rota
 * para cadastrá-lo, então todo admin entrava só com a senha. Agora:
 *  - sem 2FA cadastrado, o login devolve 423 com um token de CADASTRO (ability
 *    `superadmin:2fa-cadastro`, 15 min) que só abre `2fa/iniciar|confirmar`;
 *    o token de operação sai na confirmação;
 *  - com 2FA, o login exige o código (423 sem ele);
 *  - lockout por CONTA (5 falhas em 15 min), além do throttle por IP;
 *  - conta desativada perde o token na hora (TokenDeContaAtiva).
 */
class AuthController extends Controller
{
    /** Ability do token de operação; o middleware `plataforma.sessao` a exige. */
    public const ABILITY_OPERACAO = 'superadmin';

    /** Ability do token que só cadastra o 2FA. */
    public const ABILITY_CADASTRO_2FA = 'superadmin:2fa-cadastro';

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

        // Lockout ANTES de conferir a senha: com a conta travada, nem a senha
        // certa entra — senão o atacante usaria a resposta como oráculo.
        if ($admin !== null && $this->bloqueado($admin)) {
            $this->auditoria->registrar('login.falha', null, 'platform_admins', $admin->id, null, ['motivo' => 'lockout']);

            return response()->json(['message' => 'Muitas tentativas. Conta bloqueada por alguns minutos.'], 429);
        }

        if (! $admin || ! Hash::check($d['password'], $admin->password)) {
            $this->auditoria->registrar('login.falha', null, 'platform_admins', $admin?->id, null, ['motivo' => 'credenciais']);

            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        if ($admin->twofa_habilitado) {
            $otp = (string) ($d['otp'] ?? '');
            if ($otp === '' || ! $this->verificar2fa($admin, $otp)) {
                // Código errado conta como falha da conta: sem isso, quem já tem a
                // senha testaria os 10^6 códigos limitado só pelo throttle de IP.
                $this->auditoria->registrar('login.falha', null, 'platform_admins', $admin->id, null, ['motivo' => '2fa']);

                return response()->json(['message' => 'Código de verificação necessário.', 'two_factor_required' => true], 423);
            }
        } elseif (config('superadmin.dois_fatores_obrigatorio', true)) {
            $token = $admin->createToken(
                'superadmin-2fa-cadastro',
                [self::ABILITY_CADASTRO_2FA],
                now()->addMinutes((int) config('superadmin.token_cadastro_2fa_min', 15)),
            )->plainTextToken;
            $this->auditoria->registrar('login.cadastro_2fa', null, 'platform_admins', $admin->id);

            return response()->json([
                'message' => 'Cadastre a verificação em duas etapas para continuar.',
                'two_factor_setup_required' => true,
                'setup_token' => $token,
            ], 423);
        }

        $token = $admin->createToken('superadmin', [self::ABILITY_OPERACAO])->plainTextToken;
        $this->auditoria->registrar('login.ok', null, 'platform_admins', $admin->id);

        return response()->json([
            'token' => $token,
            'admin' => $this->payload($admin),
        ]);
    }

    /**
     * POST /superadmin/2fa/iniciar — gera o segredo (ainda NÃO habilita).
     *
     * Recusado para quem já tem 2FA: trocar o segredo é um ato que precisa do
     * código atual, e por este caminho bastaria um token roubado.
     */
    public function iniciar2fa(Request $request): JsonResponse
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->user();
        abort_if($admin->twofa_habilitado, 409, 'A verificação em duas etapas já está ativa.');

        $secret = $this->totp->gerarSecret();
        $admin->forceFill(['twofa_secret' => $secret, 'twofa_habilitado' => false, 'twofa_recovery_codes' => null])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $this->totp->uri($secret, $admin->email, config('app.name', 'ERP').' Plataforma'),
        ]);
    }

    /**
     * POST /superadmin/2fa/confirmar — confere um código do app autenticador,
     * habilita, devolve os códigos de recuperação e troca o token de cadastro
     * pelo token de operação.
     */
    public function confirmar2fa(Request $request): JsonResponse
    {
        $request->validate(['otp' => 'required|string']);
        /** @var PlatformAdmin $admin */
        $admin = $request->user();
        abort_if($admin->twofa_habilitado, 409, 'A verificação em duas etapas já está ativa.');
        abort_if(! $admin->twofa_secret, 422, 'Inicie o cadastro da verificação em duas etapas primeiro.');

        if (! $this->totp->verificar($admin->twofa_secret, (string) $request->input('otp'))) {
            $this->auditoria->registrar('2fa.falha', null, 'platform_admins', $admin->id);

            return response()->json(['message' => 'Código inválido. Confira a hora do celular e tente de novo.'], 422);
        }

        $recovery = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();

        $token = DB::transaction(function () use ($admin, $recovery, $request) {
            $admin->forceFill([
                'twofa_habilitado' => true,
                'twofa_confirmado_em' => now(),
                'twofa_recovery_codes' => $recovery,
            ])->save();

            // O token de cadastro morre aqui: ele provou só a senha.
            $atual = $request->user()->currentAccessToken();
            if ($atual !== null && method_exists($atual, 'delete')) {
                $atual->delete();
            }

            return $admin->createToken('superadmin', [self::ABILITY_OPERACAO])->plainTextToken;
        });

        $this->auditoria->registrar('2fa.habilitado', null, 'platform_admins', $admin->id);

        return response()->json([
            'message' => 'Verificação em duas etapas ativada.',
            'token' => $token,
            'recovery_codes' => $recovery,
            'admin' => $this->payload($admin),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // PIE R1-08: sem token pessoal (TransientToken) não há o que apagar.
        $token = $request->user()?->currentAccessToken();
        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    /** GET /superadmin/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['admin' => $this->payload($request->user())]);
    }

    /** @return array<string, mixed> */
    private function payload(PlatformAdmin $admin): array
    {
        return [
            'id' => $admin->id, 'nome' => $admin->nome, 'email' => $admin->email,
            'twofa_habilitado' => (bool) $admin->twofa_habilitado,
        ];
    }

    /** Falhas recentes DESTA conta (senha ou código) atingiram o teto? */
    private function bloqueado(PlatformAdmin $admin): bool
    {
        $falhas = DB::table('platform_audit_logs')
            ->where('acao', 'login.falha')
            ->where('entidade', 'platform_admins')
            ->where('entidade_id', $admin->id)
            ->where('criado_em', '>=', now()->subMinutes((int) config('superadmin.lockout_janela_min', 15)))
            ->count();

        return $falhas >= (int) config('superadmin.lockout_falhas', 5);
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
