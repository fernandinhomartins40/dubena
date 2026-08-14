<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paginação padrão das listagens de volume.
 *
 * Existe porque vários endpoints nasceram com `->limit(200)->get()` cravado —
 * suficiente quando o banco tinha dados de demonstração, enganoso com dados
 * reais: a tela de NF-e mostrava 200 de 241 mil notas e **não avisava** que
 * havia mais. O usuário conclui que o módulo não carregou.
 *
 * O formato da resposta é o mesmo que `ResourceCollection::response()` produz
 * (`data` + `meta` + `links`), que o frontend já sabe consumir.
 */
trait PaginaListagem
{
    /** Teto por página: protege o servidor de um `per_page=100000`. */
    private const POR_PAGINA_MAX = 200;

    private const POR_PAGINA_PADRAO = 50;

    /**
     * Pagina a query e devolve no formato que as telas esperam.
     *
     * @param  Builder<*>  $query
     * @param  (callable(mixed):array<string,mixed>)|null  $mapear  transforma cada linha
     */
    protected function paginar(Request $request, Builder $query, ?callable $mapear = null): JsonResponse
    {
        $pagina = $query->paginate($this->porPagina($request))->withQueryString();

        $itens = $mapear === null
            ? $pagina->items()
            : array_map($mapear, $pagina->items());

        return response()->json([
            'data' => $itens,
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
            'links' => [
                'prev' => $pagina->previousPageUrl(),
                'next' => $pagina->nextPageUrl(),
            ],
        ]);
    }

    /** `?per_page=`, limitado — pedir 100 mil linhas derrubaria o servidor. */
    protected function porPagina(Request $request): int
    {
        $pedido = (int) $request->query('per_page', (string) self::POR_PAGINA_PADRAO);

        return max(1, min($pedido ?: self::POR_PAGINA_PADRAO, self::POR_PAGINA_MAX));
    }

    /**
     * Filtro de período comum às listagens de volume.
     *
     * Sem ele, a tela abre sempre nas linhas mais recentes e não há como
     * chegar a um mês específico — que é o uso real de fiscal e financeiro.
     *
     * @param  Builder<*>  $query
     */
    protected function filtrarPeriodo(Request $request, Builder $query, string $coluna): void
    {
        $inicio = $request->query('inicio');
        $fim = $request->query('fim');

        if (is_string($inicio) && $inicio !== '') {
            $query->whereDate($coluna, '>=', $inicio);
        }
        if (is_string($fim) && $fim !== '') {
            $query->whereDate($coluna, '<=', $fim);
        }
    }
}
