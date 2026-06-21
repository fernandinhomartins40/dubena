<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autenticação por token (Sanctum). Contrato JSON limpo — sem View/Redirect.
 * Substitui o login web baseado em Session do legado.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credenciais = $request->validated();

        if (! Auth::attempt(['email' => $credenciais['email'], 'password' => $credenciais['password']])) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        $user = $request->user() ?? Auth::user();

        if (! $user->ativo) {
            Auth::logout();

            return response()->json(['message' => 'Usuário inativo.'], 403);
        }

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
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
