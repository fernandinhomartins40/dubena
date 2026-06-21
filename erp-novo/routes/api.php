<?php

use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Api\Admin\CadastroApoioController;
use App\Http\Controllers\Api\Admin\EmpresaConfigController;
use App\Http\Controllers\Api\Admin\EmpresaController;
use App\Http\Controllers\Api\Admin\RegiaoController;
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

    // ── Admin (consumido pela SPA em /api/admin) — N1 ──
    Route::prefix('admin')->group(function () {
        // Empresas (entidade-tenant) + config + troca de tenant ativo.
        Route::get('empresas', [EmpresaController::class, 'index']);
        Route::post('empresas', [EmpresaController::class, 'store']);
        Route::get('empresas/{id}', [EmpresaController::class, 'show'])->whereNumber('id');
        Route::put('empresas/{id}', [EmpresaController::class, 'update'])->whereNumber('id');
        Route::delete('empresas/{id}', [EmpresaController::class, 'destroy'])->whereNumber('id');
        Route::post('empresas/{id}/ativar', [EmpresaController::class, 'ativar'])->whereNumber('id');

        Route::get('empresas/{id}/config', [EmpresaConfigController::class, 'show'])->whereNumber('id');
        Route::put('empresas/{id}/config', [EmpresaConfigController::class, 'update'])->whereNumber('id');
        Route::put('empresas/{id}/config/senha-mestra', [EmpresaConfigController::class, 'senhaMestra'])->whereNumber('id');

        // Regiões de atendimento.
        Route::get('regioes', [RegiaoController::class, 'index']);
        Route::post('regioes', [RegiaoController::class, 'store']);
        Route::put('regioes/{id}', [RegiaoController::class, 'update'])->whereNumber('id');
        Route::delete('regioes/{id}', [RegiaoController::class, 'destroy'])->whereNumber('id');

        // Cadastros de apoio genéricos (parametrizados por tipo).
        Route::get('cadastros/{tipo}', [CadastroApoioController::class, 'index']);
        Route::post('cadastros/{tipo}', [CadastroApoioController::class, 'store']);
        Route::put('cadastros/{tipo}/{id}', [CadastroApoioController::class, 'update'])->whereNumber('id');
        Route::delete('cadastros/{tipo}/{id}', [CadastroApoioController::class, 'destroy'])->whereNumber('id');
    });
});
