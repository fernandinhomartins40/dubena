<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API ADMIN (SPA React) — prefixo /api/admin, grupo 'api_admin' (Sanctum)
|--------------------------------------------------------------------------
| Namespace: App\ApiAdmin\Http\Controllers (definido no RouteServiceProvider).
| Auth por sessão/cookie (Sanctum SPA). Separada da API do app mobile.
*/

// Público (sessão stateful, mas sem exigir login) — login + health.
Route::post('login', 'AuthController@login')->name('login');
Route::get('health', fn () => response()->json(['ok' => true, 'app' => 'ctrl-web-admin-api']))->name('health');

// Autenticado (Sanctum / guard web).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', 'AuthController@logout')->name('logout');
    Route::get('me', 'AuthController@me')->name('me');
    Route::get('dashboard/resumo', 'DashboardController@resumo')->name('dashboard.resumo');

    // S2 — lookups p/ selects do SPA
    Route::get('lookups/cidades', 'LookupController@cidades')->name('lookups.cidades');
    Route::get('lookups/bairros', 'LookupController@bairros')->name('lookups.bairros');

    // S2 — Cliente (página completa)
    Route::get('clientes', 'ClienteController@index')->name('clientes.index');
    Route::post('clientes', 'ClienteController@store')->name('clientes.store');
    Route::get('clientes/{id}', 'ClienteController@show')->name('clientes.show');
    Route::put('clientes/{id}', 'ClienteController@update')->name('clientes.update');
    Route::delete('clientes/{id}', 'ClienteController@destroy')->name('clientes.destroy');

    // S2 — sub-recurso telefones do cliente (aba na ficha)
    Route::get('clientes/{id}/telefones', 'ClienteController@telefones')->name('clientes.telefones');
    Route::post('clientes/{id}/telefones', 'ClienteController@addTelefone')->name('clientes.telefones.add');
    Route::delete('clientes/{id}/telefones/{telId}', 'ClienteController@delTelefone')->name('clientes.telefones.del');

    // lookup de tipos de telefone
    Route::get('lookups/telefone-tipos', 'LookupController@telefoneTipos')->name('lookups.telefonetipos');
});
