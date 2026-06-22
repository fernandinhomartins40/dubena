<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Relatorio\RelatorioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Relatórios (Query Services) — N12. Parametrizados, agregação no SQL.
 */
class RelatorioController extends Controller
{
    public function __construct(private RelatorioService $service)
    {
    }

    public function vendas(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);

        return response()->json(['data' => $this->service->vendas($request->user()->empresa_id, $d['inicio'], $d['fim'])]);
    }

    public function financeiro(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);

        return response()->json(['data' => $this->service->financeiro($request->user()->empresa_id, $d['inicio'], $d['fim'])]);
    }

    public function dre(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);

        return response()->json(['data' => $this->service->dre($request->user()->empresa_id, $d['inicio'], $d['fim'])]);
    }

    public function estoqueBaixo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');

        return response()->json(['data' => $this->service->estoqueBaixo($request->user()->empresa_id)]);
    }

    /** @return array{inicio:string,fim:string} */
    private function periodo(Request $request): array
    {
        return $request->validate([
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
        ]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
