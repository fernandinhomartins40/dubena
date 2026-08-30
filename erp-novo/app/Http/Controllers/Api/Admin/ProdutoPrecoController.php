<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Produto\ProdutoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Produto\ProdutoClasse;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reajuste de preços em massa — C1. Preview (não persiste) + aplicar.
 * Permissão 'produto.preco'.
 */
class ProdutoPrecoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private ProdutoService $service) {}

    /** GET /produtos-precos/preview?tipo=&valor=&classe_id= */
    public function preview(Request $request): JsonResponse
    {
        $this->autorizar($request, 'produto.preco');
        $d = $this->validar($request);

        $linhas = $this->service->previewReajuste($d['tipo'], (float) $d['valor'], $d['classe_id'] ?? null);

        return response()->json(['data' => $linhas]);
    }

    /** PUT /produtos-precos/aplicar */
    public function aplicar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'produto.preco');
        $d = $this->validar($request);

        $afetados = $this->service->aplicarReajuste($d['tipo'], (float) $d['valor'], $d['classe_id'] ?? null);

        return response()->json(['message' => "{$afetados} produto(s) reajustado(s).", 'afetados' => $afetados]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo' => 'required|in:percentual,fixo',
            'valor' => 'required|numeric',
            'classe_id' => ['nullable', 'integer', new ExisteNoTenant(ProdutoClasse::class)],
        ]);
    }
}
