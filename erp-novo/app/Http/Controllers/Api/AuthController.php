<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autenticação (Sanctum) — suporta os DOIS modos no mesmo endpoint:
 *  - SPA (cookie): se a requisição é stateful (mesmo domínio + csrf-cookie), o
 *    login fixa a sessão (Auth::attempt + regenerate) — o cookie autentica as
 *    próximas requisições.
 *  - Apps/integrações (token): sempre devolve um token Bearer no corpo.
 * Contrato JSON limpo — sem View/Redirect.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credenciais = $request->validated();

        if (! Auth::attempt(['email' => $credenciais['email'], 'password' => $credenciais['password']], remember: true)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        $user = Auth::user();

        if (! $user->ativo) {
            Auth::logout();

            return response()->json(['message' => 'Usuário inativo.'], 403);
        }

        // SPA cookie-based: fixa a sessão (o cookie autentica as próximas requisições).
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Token Bearer para apps/integrações (a SPA pode ignorar e usar o cookie).
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'empresa_id' => $user->empresa_id,
                'grupo_id' => $user->grupo_id,
                'support' => $user->support,
            ],
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
