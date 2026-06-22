<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Mobile\AppDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Auth do app (cliente/entregador) — N10. Token REAL por usuário (Sanctum),
 * eliminando o usuário-mestre via env do legado. Registra o device (push).
 */
class AppAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $d = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:120',
            'push_token' => 'nullable|string|max:255',
            'plataforma' => 'nullable|string|max:12',
            'app_versao' => 'nullable|string|max:20',
        ]);

        $user = User::query()->where('email', $d['email'])->where('ativo', true)->first();
        if (! $user || ! Hash::check($d['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        // Registra/atualiza o device para push.
        if (! empty($d['device_id'])) {
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

        $token = $user->createToken('app-'.($d['device_id'] ?? 'mobile'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'empresa_id' => $user->empresa_id],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout efetuado.']);
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
