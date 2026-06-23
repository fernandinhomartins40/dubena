<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Relatorio\RelatorioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relatórios (Query Services) — N12. Parametrizados, agregação no SQL.
 */
class RelatorioController extends Controller
{
    public function __construct(private RelatorioService $service) {}

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

    public function estoqueBaixo(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');

        $linhas = $this->service->estoqueBaixo($request->user()->empresa_id);
        if ($this->querCsv($request)) {
            return $this->csv($linhas, 'estoque-baixo');
        }

        return response()->json(['data' => $linhas]);
    }

    public function fechamentosCaixa(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->fechamentosCaixa($request->user()->empresa_id, $d['inicio'], $d['fim']);

        if ($this->querCsv($request)) {
            return $this->csv($linhas, 'fechamentos-caixa');
        }

        return response()->json(['data' => $linhas]);
    }

    /** Resposta CSV (download) a partir de linhas tabulares. */
    private function csv(array $linhas, string $nome): Response
    {
        $conteudo = $this->service->csv($linhas);

        return response($conteudo, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nome}.csv\"",
        ]);
    }

    private function querCsv(Request $request): bool
    {
        return $request->query('formato') === 'csv';
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
