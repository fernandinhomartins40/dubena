<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * S1 (SPA React) — autenticação do SPA via Sanctum (cookie de sessão).
 *
 * Fluxo do frontend:
 *   1. GET /sanctum/csrf-cookie  (Sanctum, fora daqui) → seta o XSRF-TOKEN
 *   2. POST /api/admin/login     → valida e cria a sessão (cookie httpOnly)
 *   3. requests autenticadas seguem com o cookie; CSRF via header X-XSRF-TOKEN
 *   4. POST /api/admin/logout    → invalida a sessão
 *
 * Reusa o guard 'web' e a regra de "usuário ativo" do ERP. Não emite token
 * Bearer (cookie-based é mais seguro contra XSS).
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        // Inclui 'ativo' no attempt (mesma regra do ERP legado: inativo não loga).
        $cred = ['email' => $data['email'], 'password' => $data['password'], 'ativo' => 1];

        if (! Auth::attempt($cred, true)) {
            throw ValidationException::withMessages([
                'email' => ['E-mail e/ou senha inválidos.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json($this->mePayload($request->user()));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout efetuado.']);
    }

    /**
     * Dados do usuário autenticado + suas permissões RBAC — consumido pelo SPA
     * para montar a navegação e aplicar guardas de rota por permissão.
     */
    public function me(Request $request)
    {
        return response()->json($this->mePayload($request->user()));
    }

    private function mePayload($user): array
    {
        $isSupport = (string) ($user->support ?? '') === '1';

        // Permissões RBAC (spatie). Support vê tudo.
        $permissoes = $isSupport
            ? \Spatie\Permission\Models\Permission::query()->pluck('name')->all()
            : $user->getAllPermissions()->pluck('name')->all();

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'empresa_id'  => $user->empresa_id,
            'is_support'  => $isSupport,
            'roles'       => $user->getRoleNames()->all(),
            'permissions' => array_values($permissoes),
        ];
    }
}
