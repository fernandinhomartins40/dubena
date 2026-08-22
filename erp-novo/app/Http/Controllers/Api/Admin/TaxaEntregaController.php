<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Logistica\CalculadoraTaxaEntrega;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Logistica\TaxaEntrega;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Regras de cobrança de entrega.
 *
 * Gated por `logistica.config` — quem configura a operação de entrega define
 * quanto ela custa. Ver CalculadoraTaxaEntrega para a ordem de precedência.
 */
class TaxaEntregaController extends Controller
{
    use AutorizaPorPermissao;

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'logistica.config');

        $regras = TaxaEntrega::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->with(['bairro:id,descricao', 'cidade:id,descricao'])
            ->orderByDesc('prioridade')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $regras->map(fn (TaxaEntrega $r) => $this->linha($r))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'logistica.config');

        $regra = TaxaEntrega::query()->create(array_merge($this->validar($request), [
            'empresa_id' => (int) $request->user()->empresa_id,
            'grupo_id' => (int) $request->user()->grupo_id,
        ]));

        return response()->json(['data' => $this->linha($regra)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.config');

        $regra = TaxaEntrega::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);

        $regra->update($this->validar($request));

        return response()->json(['data' => $this->linha($regra->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.config');

        TaxaEntrega::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id)
            ->delete();

        return response()->json(['message' => 'Regra removida.']);
    }

    /**
     * GET /taxas-entrega/simular — quanto sairia a entrega para este cliente.
     *
     * Existe para a revenda CONFERIR a configuração antes de vender: sem isto,
     * o primeiro teste de uma tabela de preços seria um pedido real.
     */
    public function simular(Request $request, CalculadoraTaxaEntrega $calculadora): JsonResponse
    {
        $this->autorizar($request, 'logistica.config');

        $d = $request->validate([
            'cliente_id' => 'required|integer',
            'valor_pedido' => 'nullable|numeric|gte:0',
        ]);

        $cliente = Cliente::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($d['cliente_id']);

        $resultado = $calculadora->calcular(
            (int) $request->user()->empresa_id,
            $cliente,
            (float) ($d['valor_pedido'] ?? 0),
        );

        return response()->json(['data' => $resultado->paraArray()]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'descricao' => 'required|string|max:120',
            'criterio' => 'required|in:bairro,cidade,distancia,valor_pedido,padrao',
            'bairro_id' => 'nullable|integer|exists:bairros,id|required_if:criterio,bairro',
            'cidade_id' => 'nullable|integer|exists:cidades,id|required_if:criterio,cidade',
            // Faixa dos critérios numéricos (km ou R$).
            'faixa_de' => 'nullable|numeric|gte:0',
            'faixa_ate' => 'nullable|numeric|gte:0|gte:faixa_de',
            'valor' => 'required|numeric|gte:0',
            'isenta' => 'boolean',
            'custo_estimado' => 'nullable|numeric|gte:0',
            'prioridade' => 'nullable|integer',
            'ativo' => 'boolean',
        ]);
    }

    /** @return array<string, mixed> */
    private function linha(TaxaEntrega $r): array
    {
        return [
            'id' => $r->id,
            'descricao' => $r->descricao,
            'criterio' => $r->criterio,
            'bairro_id' => $r->bairro_id,
            'bairro' => $r->bairro?->descricao,
            'cidade_id' => $r->cidade_id,
            'cidade' => $r->cidade?->descricao,
            'faixa_de' => $r->faixa_de !== null ? (float) $r->faixa_de : null,
            'faixa_ate' => $r->faixa_ate !== null ? (float) $r->faixa_ate : null,
            'valor' => (float) $r->valor,
            'isenta' => (bool) $r->isenta,
            'custo_estimado' => $r->custo_estimado !== null ? (float) $r->custo_estimado : null,
            // Margem pronta: é a pergunta que a revenda faz olhando a tabela.
            'margem' => $r->margem,
            'prioridade' => (int) $r->prioridade,
            'ativo' => (bool) $r->ativo,
        ];
    }
}
