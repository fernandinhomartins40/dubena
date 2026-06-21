<?php

use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Api\Admin\CadastroApoioController;
use App\Http\Controllers\Api\Admin\ClienteController;
use App\Http\Controllers\Api\Admin\ClienteSubrecursoController;
use App\Http\Controllers\Api\Admin\ClienteTelefoneController;
use App\Http\Controllers\Api\Admin\EmpresaConfigController;
use App\Http\Controllers\Api\Admin\EmpresaController;
use App\Http\Controllers\Api\Admin\EstoqueController;
use App\Http\Controllers\Api\Admin\GeoController;
use App\Http\Controllers\Api\Admin\PedidoController;
use App\Http\Controllers\Api\Admin\ProdutoController;
use App\Http\Controllers\Api\Admin\RegiaoController;
use App\Http\Controllers\Api\Admin\SetorController;
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

        // ── Geográfico (cidades/bairros/ruas) — N2 ──
        Route::get('geo/{entidade}', [GeoController::class, 'index']);
        Route::post('geo/{entidade}', [GeoController::class, 'store']);
        Route::put('geo/{entidade}/{id}', [GeoController::class, 'update'])->whereNumber('id');
        Route::delete('geo/{entidade}/{id}', [GeoController::class, 'destroy'])->whereNumber('id');

        // ── Clientes — N2 ──
        Route::get('clientes', [ClienteController::class, 'index']);
        Route::post('clientes', [ClienteController::class, 'store']);
        Route::get('clientes/{id}', [ClienteController::class, 'show'])->whereNumber('id');
        Route::put('clientes/{id}', [ClienteController::class, 'update'])->whereNumber('id');
        Route::delete('clientes/{id}', [ClienteController::class, 'destroy'])->whereNumber('id');

        // Sub-recursos do cliente.
        Route::get('clientes/{id}/telefones', [ClienteTelefoneController::class, 'index'])->whereNumber('id');
        Route::post('clientes/{id}/telefones', [ClienteTelefoneController::class, 'store'])->whereNumber('id');
        Route::delete('clientes/{id}/telefones/{telId}', [ClienteTelefoneController::class, 'destroy'])->whereNumber(['id', 'telId']);

        Route::get('clientes/{id}/interacoes', [ClienteSubrecursoController::class, 'interacoes'])->whereNumber('id');
        Route::post('clientes/{id}/interacoes', [ClienteSubrecursoController::class, 'addInteracao'])->whereNumber('id');
        Route::delete('clientes/{id}/interacoes/{subId}', [ClienteSubrecursoController::class, 'delInteracao'])->whereNumber(['id', 'subId']);

        Route::get('clientes/{id}/convenio', [ClienteSubrecursoController::class, 'convenio'])->whereNumber('id');
        Route::put('clientes/{id}/convenio', [ClienteSubrecursoController::class, 'salvarConvenio'])->whereNumber('id');
        Route::post('clientes/{id}/convenio/dependentes', [ClienteSubrecursoController::class, 'addDependente'])->whereNumber('id');
        Route::delete('clientes/{id}/convenio/dependentes/{depId}', [ClienteSubrecursoController::class, 'delDependente'])->whereNumber(['id', 'depId']);

        Route::get('clientes/{id}/precos', [ClienteSubrecursoController::class, 'precos'])->whereNumber('id');
        Route::get('clientes/{id}/historico', [ClienteSubrecursoController::class, 'historico'])->whereNumber('id');

        // ── Produtos — N3 ──
        Route::get('produtos', [ProdutoController::class, 'index']);
        Route::post('produtos', [ProdutoController::class, 'store']);
        Route::get('produtos/{id}', [ProdutoController::class, 'show'])->whereNumber('id');
        Route::put('produtos/{id}', [ProdutoController::class, 'update'])->whereNumber('id');
        Route::delete('produtos/{id}', [ProdutoController::class, 'destroy'])->whereNumber('id');

        // ── Estoque — N3 ──
        Route::get('setores', [SetorController::class, 'index']);
        Route::post('setores', [SetorController::class, 'store']);
        Route::put('setores/{id}', [SetorController::class, 'update'])->whereNumber('id');
        Route::delete('setores/{id}', [SetorController::class, 'destroy'])->whereNumber('id');

        Route::get('estoque/saldos', [EstoqueController::class, 'saldos']);
        Route::get('estoque/historico', [EstoqueController::class, 'historico']);
        Route::post('estoque/entrada', [EstoqueController::class, 'entrada']);
        Route::post('estoque/saida', [EstoqueController::class, 'saida']);
        Route::post('estoque/transferencias', [EstoqueController::class, 'transferir']);
        Route::post('estoque/acerto', [EstoqueController::class, 'acerto']);
        Route::post('estoque/fechamentos', [EstoqueController::class, 'fechar']);

        // ── Pedidos / Vendas — N4 ── (rotas estáticas antes de /{id})
        Route::get('pedidos/kanban', [PedidoController::class, 'kanban']);
        Route::get('pedidos/situacoes', [PedidoController::class, 'situacoes']);
        Route::get('pedidos', [PedidoController::class, 'index']);
        Route::post('pedidos', [PedidoController::class, 'store']);
        Route::get('pedidos/{id}', [PedidoController::class, 'show'])->whereNumber('id');
        Route::put('pedidos/{id}', [PedidoController::class, 'update'])->whereNumber('id');
        Route::delete('pedidos/{id}', [PedidoController::class, 'destroy'])->whereNumber('id');
        Route::put('pedidos/{id}/situacao', [PedidoController::class, 'mudarSituacao'])->whereNumber('id');
    });
});
