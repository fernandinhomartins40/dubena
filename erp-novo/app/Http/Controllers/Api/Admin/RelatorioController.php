<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Auditoria\ConsultaTrilha;
use App\Domain\Relatorio\RelatorioService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relatórios (Query Services) — N12. Parametrizados, agregação no SQL.
 */
class RelatorioController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private RelatorioService $service,
        private CamposPermitidos $campos,
        private TenantContext $tenant,
    ) {}

    /** GET /dashboard/resumo — contadores da home da SPA. */
    public function dashboardResumo(Request $request): JsonResponse
    {
        // Sem permissão específica: o dashboard é a home de qualquer usuário logado.
        return response()->json(
            $this->service->dashboardResumo($this->empresaId()),
        );
    }

    public function vendas(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);

        return response()->json(['data' => $this->service->vendas($this->empresaId(), $d['inicio'], $d['fim'])]);
    }

    public function financeiro(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);

        return response()->json(['data' => $this->service->financeiro($this->empresaId(), $d['inicio'], $d['fim'])]);
    }

    public function dre(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);

        return response()->json(['data' => $this->service->dre($this->empresaId(), $d['inicio'], $d['fim'])]);
    }

    public function estoqueBaixo(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');

        $linhas = $this->service->estoqueBaixo($this->empresaId());

        return $this->exportar($request, $linhas, 'estoque-baixo', 'Estoque abaixo do mínimo');
    }

    public function fechamentosCaixa(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->fechamentosCaixa($this->empresaId(), $d['inicio'], $d['fim']);

        return $this->exportar($request, $linhas, 'fechamentos-caixa', 'Fechamentos de caixa');
    }

    // ── Relatórios adicionais (C8) ──
    public function clientesAniversariantes(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $mes = (int) ($request->query('mes') ?: now()->month);
        $linhas = $this->service->clientesAniversariantes($this->empresaId(), $mes);

        return $this->exportar($request, $linhas, 'aniversariantes', "Aniversariantes (mês {$mes})");
    }

    public function valeGas(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->valeGas($this->empresaId(), $d['inicio'], $d['fim']);

        return $this->exportar($request, $linhas, 'vale-gas', 'Vale-gás por situação');
    }

    public function comodatos(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $linhas = $this->service->comodatos($this->empresaId());

        return $this->exportar($request, $linhas, 'comodatos', 'Comodatos em aberto');
    }

    public function comissoes(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->comissoes($this->empresaId(), $d['inicio'], $d['fim']);

        return $this->exportar($request, $linhas, 'comissoes', 'Comissões por colaborador');
    }

    public function movimentacaoCaixa(Request $request): Response
    {
        $this->autorizar($request, 'relatorio.view');
        $d = $this->periodo($request);
        $linhas = $this->service->movimentacaoCaixa($this->empresaId(), $d['inicio'], $d['fim']);

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
    /**
     * Registry da CENTRAL de relatórios (F10): slug => [método do service, precisa
     * período?, precisa mês?, título, extras?]. Adicionar relatório = 1 linha + 1
     * método no service — sem novo controller/rota. Substitui a abordagem
     * 1-tela-por-relatório.
     *
     * `extras` (opcional) declara parâmetros de query além de período/mês, como
     * `dias => int`. Sem ele, um relatório com corte configurável exigiria rota
     * própria — que é justamente o que este registry existe para evitar.
     *
     * @var array<string, array{0:string,1:bool,2:bool,3:string,4?:array<string,string>}>
     */
    private const RELATORIOS = [
        'vendas' => ['vendas', true, false, 'Vendas por período'],
        'financeiro' => ['financeiro', true, false, 'Posição financeira'],
        'dre' => ['dre', true, false, 'DRE (resultado)'],
        'movimentacao-caixa' => ['movimentacaoCaixa', true, false, 'Movimentação de caixa'],
        'fechamentos-caixa' => ['fechamentosCaixa', true, false, 'Fechamentos de caixa'],
        'comissoes' => ['comissoes', true, false, 'Comissões por colaborador'],
        'vale-gas' => ['valeGas', true, false, 'Vale-gás por situação'],
        'estoque-baixo' => ['estoqueBaixo', false, false, 'Estoque abaixo do mínimo'],
        'comodatos' => ['comodatos', false, false, 'Comodatos em aberto'],
        'aniversariantes' => ['clientesAniversariantes', false, true, 'Aniversariantes do mês'],
        // F10 — novos relatórios (cobre os faltantes da auditoria).
        'vendas-entregador' => ['vendasPorEntregador', true, false, 'Vendas por entregador'],
        'vendas-operacao' => ['vendasPorOperacao', true, false, 'Vendas por operação (PDV/Disk)'],
        'vendas-produto' => ['vendasPorProduto', true, false, 'Vendas por produto'],
        'nf-emitidas' => ['nfEmitidas', true, false, 'NF-e emitidas'],
        'nf-recebidas' => ['nfRecebidas', true, false, 'NF de entrada (recebidas)'],
        'promocoes' => ['promocoes', false, false, 'Promoções e adesão'],
        'veiculos' => ['veiculos', false, false, 'Frota e abastecimentos'],
        // Triagem F4 §5 — os dois relatórios classificados como PRÉ-GO-LIVE.
        'fluxo-caixa' => ['fluxoCaixa', true, false, 'Fluxo de caixa projetado'],
        'clientes-sem-compra' => ['clientesSemCompra', false, false, 'Clientes sem compra (inativos)', ['dias' => 'int']],
    ];

    /**
     * GET /relatorios/{slug} — central única. ?inicio&fim (período), ?mes, ?formato=csv|pdf.
     */
    public function mostrar(Request $request, string $slug): Response
    {
        $this->autorizar($request, 'relatorio.view');

        $cfg = self::RELATORIOS[$slug] ?? null;
        abort_if($cfg === null, 404, 'Relatório desconhecido.');
        [$metodo, $precisaPeriodo, $precisaMes, $titulo] = $cfg;
        $extras = $cfg[4] ?? [];

        $empresaId = $this->empresaId();
        $args = [$empresaId];
        if ($precisaPeriodo) {
            $d = $this->periodo($request);
            $args[] = $d['inicio'];
            $args[] = $d['fim'];
        }
        if ($precisaMes) {
            $args[] = (int) ($request->query('mes') ?: now()->month);
        }
        foreach ($extras as $nome => $tipo) {
            // Só repassa o que o cliente mandou: ausente = o default do método
            // no service, que é onde a regra de negócio do corte mora.
            $valor = $request->query($nome);
            if ($valor !== null && $valor !== '') {
                $args[] = $tipo === 'int' ? (int) $valor : $valor;
            }
        }

        $linhas = $this->service->{$metodo}(...$args);

        return $this->exportar($request, $linhas, $slug, $titulo);
    }

    /** Catálogo dos relatórios disponíveis (alimenta o seletor da SPA). */
    public function catalogo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');

        $itens = collect(self::RELATORIOS)->map(fn ($c, $slug) => [
            'slug' => $slug, 'titulo' => $c[3], 'periodo' => $c[1], 'mes' => $c[2],
            // A SPA precisa saber quais filtros extras desenhar.
            'extras' => array_keys($c[4] ?? []),
        ])->values();

        return response()->json(['data' => $itens]);
    }

    /**
     * GET /relatorios/auditoria — trilha de auditoria (F11) da empresa ativa, com
     * filtros opcionais por entidade/ação/período. Paginada.
     */
    public function auditoria(Request $request, ConsultaTrilha $trilha): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');

        $logs = AuditLog::query()
            ->where('empresa_id', $this->empresaId())
            ->when($request->query('entidade'), fn ($q, $e) => $q->where('entidade', $e))
            ->when($request->query('acao'), fn ($q, $a) => $q->where('acao', $a))
            ->when($request->query('inicio'), fn ($q, $i) => $q->where('criado_em', '>=', $i.' 00:00:00'))
            ->when($request->query('fim'), fn ($q, $f) => $q->where('criado_em', '<=', $f.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate(50);

        $mostrarCusto = $this->campos->pode($request->user(), 'produto', 'custo', 'view');
        $data = collect($logs->items())->map(function (AuditLog $l) use ($trilha, $mostrarCusto) {
            $valores = $trilha->valoresBrutos($l, $mostrarCusto);

            return [
                'id' => $l->id,
                'entidade' => $l->entidade,
                'entidade_id' => $l->entidade_id,
                'acao' => $l->acao,
                'user_id' => $l->user_id,
                'antes' => $valores['antes'],
                'depois' => $valores['depois'],
                'ip' => $l->ip,
                'criado_em' => $l->criado_em?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    private function periodo(Request $request): array
    {
        return $request->validate([
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
        ]);
    }

    private function empresaId(): int
    {
        return $this->tenant->requireEmpresaId();
    }
}
