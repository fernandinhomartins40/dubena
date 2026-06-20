<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Coexistência Strangler. Se a UI moderna (SPA React em /app) estiver ligada
 * para o módulo (config ui_moderna.modulos.$modulo), uma requisição GET à tela
 * LEGADA daquele módulo é redirecionada para a página nova correspondente.
 *
 * Uso na rota legada:
 *   ->middleware('ui.moderna:cidade,/app/geografico')
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
