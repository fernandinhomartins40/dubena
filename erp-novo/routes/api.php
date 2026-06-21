<?php

use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Rotas da API (JSON). Toda a SPA e os apps consomem por aqui.
| Contrato: JSON uniforme, número cru, sem View/Redirect.
*/

// Autenticação (pública).
Route::post('/login', [AuthController::class, 'login']);

// Rotas autenticadas (Sanctum) + tenant resolvido por requisição.
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Usuário autenticado + tenant ativo (substitui o "quem sou / qual empresa" do legado).
    Route::get('/me', function (Request $request, TenantContext $tenant) {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'tenant' => [
                'empresa_id' => $tenant->empresaId(),
                'grupo_id' => $tenant->grupoId(),
            ],
        ]);
    });
});
