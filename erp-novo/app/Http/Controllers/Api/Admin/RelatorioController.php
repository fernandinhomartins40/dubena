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

    /** GET /dashboard/resumo — contadores da home da SPA. */
    public function dashboardResumo(Request $request): JsonResponse
    {
        // Sem permissão específica: o dashboard é a home de qualquer usuário logado.
        return response()->json(
            $this->service->dashboardResumo((int) $request->user()->empresa_id),
        );
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

    public function estoqueBaixo(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');

        $linhas = $this->service->estoqueBaixo($request->user()->empresa_id);

        return $this->exportar($request, $linhas, 'estoque-baixo', 'Estoque abaixo do mínimo');
    }

    public function fechamentosCaixa(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->fechamentosCaixa($request->user()->empresa_id, $d['inicio'], $d['fim']);

        return $this->exportar($request, $linhas, 'fechamentos-caixa', 'Fechamentos de caixa');
    }

    // ── Relatórios adicionais (C8) ──
    public function clientesAniversariantes(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $mes = (int) ($request->query('mes') ?: now()->month);
        $linhas = $this->service->clientesAniversariantes($request->user()->empresa_id, $mes);

        return $this->exportar($request, $linhas, 'aniversariantes', "Aniversariantes (mês {$mes})");
    }

    public function valeGas(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->valeGas($request->user()->empresa_id, $d['inicio'], $d['fim']);

        return $this->exportar($request, $linhas, 'vale-gas', 'Vale-gás por situação');
    }

    public function comodatos(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $linhas = $this->service->comodatos($request->user()->empresa_id);

        return $this->exportar($request, $linhas, 'comodatos', 'Comodatos em aberto');
    }

    public function comissoes(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->comissoes($request->user()->empresa_id, $d['inicio'], $d['fim']);

        return $this->exportar($request, $linhas, 'comissoes', 'Comissões por colaborador');
    }

    public function movimentacaoCaixa(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->movimentacaoCaixa($request->user()->empresa_id, $d['inicio'], $d['fim']);

        return $this->exportar($request, $linhas, 'movimentacao-caixa', 'Movimentação de caixa');
    }

    /**
     * Resposta conforme ?formato: csv | pdf (download) ou JSON (default).
     *
     * @param  list<array<string,mixed>>  $linhas
     */
    private function exportar(Request $request, array $linhas, string $nome, string $titulo): Response
    {
        $formato = $request->query('formato');

        if ($formato === 'csv') {
            return response($this->service->csv($linhas), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$nome}.csv\"",
            ]);
        }
        if ($formato === 'pdf') {
            return response($this->service->pdf($linhas, $titulo), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$nome}.pdf\"",
            ]);
        }

        return response()->json(['data' => $linhas]);
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
