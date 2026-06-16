<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * FASE 3 — coexistência Strangler. Se a UI moderna (Filament) estiver ligada
 * para o módulo (config ui_moderna.modulos.$modulo), uma requisição GET à tela
 * LEGADA daquele módulo é redirecionada para o recurso Filament correspondente.
 *
 * Uso na rota legada:
 *   ->middleware('ui.moderna:cidade,/admin/cidades')
 *
 * Só atua em GET (não rouba POST/AJAX de gravação) e só quando a flag está ON —
 * desligar a flag reverte instantaneamente para a tela antiga (rollback).
 */
class RedirecionaUiModerna
{
    public function handle(Request $request, Closure $next, $modulo = null, $destino = null)
    {
        if ($modulo && $destino && $request->isMethod('get') && ! $request->ajax() && uiModernaAtiva($modulo)) {
            return redirect()->to($destino);
        }

        return $next($request);
    }
}
