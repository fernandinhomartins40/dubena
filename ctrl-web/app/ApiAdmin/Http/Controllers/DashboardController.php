<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Cliente;
use App\Produto;
use App\Pedido;
use App\Financeiro;
use Illuminate\Http\Request;

/**
 * S1 — resumo operacional do SPA (dashboard). Números reais escopados pela
 * empresa do usuário logado. Reusa os models legados.
 */
class DashboardController extends Controller
{
    public function resumo(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $escopo = fn ($q) => $empresaId ? $q->where('empresa_id', $empresaId) : $q;

        return response()->json([
            'clientes'   => $escopo(Cliente::query())->count(),
            'produtos'   => $escopo(Produto::query())->count(),
            'pedidos'    => $escopo(Pedido::query())->count(),
            'financeiro' => $escopo(Financeiro::query())->count(),
        ]);
    }
}
