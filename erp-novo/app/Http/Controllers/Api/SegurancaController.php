<?php

namespace App\Http\Controllers\Api;

use App\Domain\Seguranca\AuditoriaSeguranca;
use App\Domain\Seguranca\PasswordPolicyService;
use App\Domain\Seguranca\Totp;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\PasswordPolicy;
use App\Models\User2fa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Conta do próprio usuário — segurança (A5): 2FA (TOTP) e sessões ativas.
 *
 * Tudo aqui opera sobre o USUÁRIO AUTENTICADO (não exige permissão de admin): é
 * o próprio dono gerindo seu segundo fator e seus dispositivos/sessões.
 */
class SegurancaController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private Totp $totp,
        private TenantContext $tenant,
        private PasswordPolicyService $politica,
        private AuditoriaSeguranca $auditoria,
    ) {}

    /** Estado do 2FA do usuário (para a tela de segurança). */
    public function twoFactorStatus(Request $request): JsonResponse
    {
        $twofa = $request->user()->twoFactor;

        return response()->json([
            'habilitado' => (bool) ($twofa?->habilitado),
            'pendente' => $twofa !== null && ! $twofa->habilitado,
        ]);
    }

    /** Inicia a configuração: gera segredo + URI do QR Code (ainda NÃO habilita). */
    public function twoFactorSetup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->totp->gerarSecret();

        User2fa::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['secret' => $secret, 'habilitado' => false, 'confirmado_em' => null, 'recovery_codes' => null],
        );

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $this->totp->uri($secret, $user->email, config('app.name', 'ERP')),
        ]);
    }

    /** Confirma o 2FA com um código do app; habilita e devolve recovery codes. */
    public function twoFactorConfirm(Request $request): JsonResponse
    {
        $request->validate(['otp' => 'required|string']);
        $user = $request->user();
        $twofa = $user->twoFactor;

        abort_if($twofa === null, 422, 'Inicie a configuração do 2FA primeiro.');
        abort_unless($this->totp->verificar($twofa->secret, $request->string('otp')), 422, 'Código inválido.');

        // Gera 8 recovery codes de uso único.
        $recovery = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();
        $twofa->update(['habilitado' => true, 'confirmado_em' => now(), 'recovery_codes' => $recovery]);
        $this->auditoria->registrar('2fa.habilitado', "user:{$user->id}");

        return response()->json([
            'message' => '2FA habilitado.',
            'recovery_codes' => $recovery,
        ]);
    }

    /** Desabilita o 2FA (exige a senha atual como confirmação). */
    public function twoFactorDisable(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();

        abort_unless(Hash::check($request->string('password'), $user->password), 422, 'Senha incorreta.');

        User2fa::query()->where('user_id', $user->id)->delete();
        $this->auditoria->registrar('2fa.desabilitado', "user:{$user->id}");

        return response()->json(['message' => '2FA desabilitado.']);
    }

    /** Lista as sessões/tokens ativos do usuário (Sanctum). */
    public function sessoes(Request $request): JsonResponse
    {
        $atual = $request->user()->currentAccessToken();
        $atualId = $atual && isset($atual->id) ? $atual->id : null;

        $rows = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'last_used_at', 'created_at'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'last_used_at' => $t->last_used_at,
                'created_at' => $t->created_at,
                'atual' => $t->id === $atualId,
            ]);

        return response()->json(['data' => $rows]);
    }

    /** Revoga um token específico (encerra aquela sessão/dispositivo). */
    public function revogarSessao(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return response()->json(['message' => 'Sessão revogada.']);
    }

    /** Revoga todas as OUTRAS sessões (mantém a atual). */
    public function revogarOutras(Request $request): JsonResponse
    {
        $atual = $request->user()->currentAccessToken();
        $atualId = $atual && isset($atual->id) ? $atual->id : 0;

        $request->user()->tokens()->where('id', '!=', $atualId)->delete();

        return response()->json(['message' => 'Outras sessões revogadas.']);
    }

    // ─────────────── Política de senha da empresa (admin) ───────────────

    /** Política de senha vigente na empresa ativa. */
    public function politicaSenhaShow(Request $request): JsonResponse
    {
        $this->autorizar($request, 'usuario.edit');

        return response()->json(['data' => $this->politica->politicaAtiva()]);
    }

    /** Define a política de senha da empresa ativa. */
    public function politicaSenhaUpdate(Request $request): JsonResponse
    {
        $this->autorizar($request, 'usuario.edit');

        $dados = $request->validate([
            'min_len' => 'required|integer|min:6|max:64',
            'exige_complexidade' => 'required|boolean',
            'expira_dias' => 'required|integer|min:0|max:3650',
        ]);

        PasswordPolicy::query()->updateOrCreate(
            ['empresa_id' => $this->tenant->requireEmpresaId()],
            $dados,
        );

        return response()->json(['data' => $this->politica->politicaAtiva()]);
    }
}
