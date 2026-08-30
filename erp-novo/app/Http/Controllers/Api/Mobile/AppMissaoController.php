<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Missao\MissaoService;
use App\Domain\Missao\VendaCampoService;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Geografico\Cidade;
use App\Models\Missao\MissaoAtribuicao;
use App\Models\Produto\Produto;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de MISSÕES do app do entregador (L7/L8) — `app/v1/entregador/missao/*`.
 * Execução em campo: iniciar, visitas (com evidência), trilha GPS, próxima casa,
 * adiamento (ETAPA 11), conclusão e VENDA em campo (gás/vale-gás/cadastro).
 * Anti-IDOR: a atribuição é sempre resolvida pelo entregador do token.
 */
class AppMissaoController extends Controller
{
    public function __construct(
        private MissaoService $missoes,
        private VendaCampoService $vendas,
    ) {}

    /** Resolve a atribuição em execução do entregador (404 se não houver). */
    private function atribuicao(Request $request): MissaoAtribuicao
    {
        $atr = $this->missoes->atribuicaoAtiva($request->user()->id);
        abort_if($atr === null || (int) $atr->empresa_id !== (int) $request->user()->empresa_id, 404, 'Sem missão ativa.');

        return $atr;
    }

    /** GET /entregador/missao — missão ativa (ou data:null). */
    public function atual(Request $request): JsonResponse
    {
        $atr = $this->missoes->atribuicaoAtiva($request->user()->id);
        if ($atr === null) {
            return response()->json(['data' => null]);
        }

        $metricas = $this->missoes->metricas($atr);

        return response()->json(['data' => [
            'id' => $atr->id,
            'status' => $atr->status,
            'iniciada_em' => $atr->iniciada_em?->toIso8601String(),
            'missao' => [
                'id' => $atr->missao?->id,
                'tipo' => $atr->missao?->tipo,
                'titulo' => $atr->missao?->titulo,
                'descricao' => $atr->missao?->descricao,
                'meta_visitas' => $atr->missao?->meta_visitas,
                'exige_foto' => (bool) $atr->missao?->exige_foto,
            ],
            'metricas' => $metricas,
        ]]);
    }

    /** POST /entregador/missao/iniciar */
    public function iniciar(Request $request): JsonResponse
    {
        $atr = $this->missoes->iniciar($this->atribuicao($request));

        return response()->json(['data' => ['id' => $atr->id, 'status' => $atr->status]]);
    }

    /** POST /entregador/missao/visitas — registra residência (multipart, foto conforme missão). */
    public function registrarVisita(Request $request): JsonResponse
    {
        $d = $request->validate([
            'status' => 'required|string|in:visitada,ausente,interessado,venda,frustrada',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'cliente_id' => ['nullable', 'integer', new ExisteNoTenant(Cliente::class)],
            'observacao' => 'nullable|string|max:255',
            'duracao_seg' => 'nullable|integer|min:0',
            'foto' => 'nullable|image|max:8192',
            'tipo_foto' => 'nullable|string|in:fachada,panfleto,visita',
        ]);

        $visita = $this->missoes->registrarVisita(
            $this->atribuicao($request), $d, $request->file('foto'), $d['tipo_foto'] ?? 'visita',
        );

        return response()->json(['data' => ['id' => $visita->id, 'status' => $visita->status]], 201);
    }

    /** POST /entregador/missao/trilha — lote de pontos GPS. */
    public function trilha(Request $request): JsonResponse
    {
        $d = $request->validate([
            'pontos' => 'required|array|min:1|max:500',
            'pontos.*.latitude' => 'required|numeric|between:-90,90',
            'pontos.*.longitude' => 'required|numeric|between:-180,180',
            'pontos.*.registrado_em' => 'nullable|date',
        ]);

        $gravados = $this->missoes->registrarTrilha($this->atribuicao($request), $d['pontos']);

        return response()->json(['data' => ['gravados' => $gravados]]);
    }

    /** GET /entregador/missao/proxima-casa?lat&lng — sugestão da próxima residência. */
    public function proximaCasa(Request $request): JsonResponse
    {
        $d = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        return response()->json(['data' => $this->missoes->proximaCasa($this->atribuicao($request), (float) $d['lat'], (float) $d['lng'])]);
    }

    /** POST /entregador/missao/adiar — solicita adiamento (aprovação pendente). */
    public function adiar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'motivo' => 'required|string|in:nova_entrega,emergencia,veiculo,clima,outro',
            'detalhe' => 'nullable|string|max:255',
        ]);

        $atr = $this->missoes->adiar($this->atribuicao($request), $d['motivo'], $d['detalhe'] ?? null);

        return response()->json(['data' => ['id' => $atr->id, 'status' => $atr->status, 'aprovacao' => $atr->adiamento_aprovacao]]);
    }

    /** POST /entregador/missao/concluir */
    public function concluir(Request $request): JsonResponse
    {
        $atr = $this->missoes->concluir($this->atribuicao($request));

        return response()->json(['data' => ['id' => $atr->id, 'status' => $atr->status]]);
    }

    // ── Vendas em campo (L8) ──

    /** GET /entregador/missao/produtos — catálogo para venda em campo (preço server-side). */
    public function produtos(Request $request): JsonResponse
    {
        $produtos = Produto::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('ativo', true)
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'preco_venda'])
            ->map(fn (Produto $p) => ['id' => $p->id, 'descricao' => $p->descricao, 'preco' => (float) $p->preco_venda]);

        return response()->json(['data' => $produtos]);
    }

    /** POST /entregador/missao/venda — venda de gás entregue na hora (multipart c/ foto). */
    public function venderGas(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cliente_id' => ['required', 'integer', new ExisteNoTenant(Cliente::class)],
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => ['required', 'integer', new ExisteNoTenant(Produto::class)],
            'itens.*.quantidade' => 'required|numeric|gt:0',
            'condicaopagamento_id' => ['nullable', 'integer', new ExisteNoTenant(CondicaoPagamento::class)],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'observacao' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:8192',
        ]);

        $atr = $this->atribuicao($request);

        // A venda também é uma VISITA (status=venda) com evidência conforme a missão.
        $visita = $this->missoes->registrarVisita($atr, [
            'status' => 'venda',
            'latitude' => $d['latitude'] ?? null,
            'longitude' => $d['longitude'] ?? null,
            'cliente_id' => $d['cliente_id'],
            'observacao' => $d['observacao'] ?? null,
        ], $request->file('foto'), 'visita');

        $visita = $this->vendas->venderGas($atr, $visita, [
            'cliente_id' => (int) $d['cliente_id'],
            'itens' => $d['itens'],
            'condicaopagamento_id' => $d['condicaopagamento_id'] ?? null,
            'lat' => $d['latitude'] ?? null,
            'lng' => $d['longitude'] ?? null,
            'observacao' => $d['observacao'] ?? null,
        ], $request->user()->id);

        return response()->json(['data' => ['visita_id' => $visita->id, 'pedido_id' => $visita->pedido_id]], 201);
    }

    /** POST /entregador/missao/vale-gas — emissão de Vale Gás em campo. */
    public function venderValeGas(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cliente_id' => ['required', 'integer', new ExisteNoTenant(Cliente::class)],
            'valor' => 'required|numeric|gt:0',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'foto' => 'nullable|image|max:8192',
        ]);

        $atr = $this->atribuicao($request);

        $visita = $this->missoes->registrarVisita($atr, [
            'status' => 'venda',
            'latitude' => $d['latitude'] ?? null,
            'longitude' => $d['longitude'] ?? null,
            'cliente_id' => $d['cliente_id'],
        ], $request->file('foto'), 'visita');

        $vale = $this->vendas->venderValeGas($atr, $visita, [
            'cliente_id' => (int) $d['cliente_id'],
            'valor' => (float) $d['valor'],
        ]);

        return response()->json(['data' => ['visita_id' => $visita->id, 'vale_id' => $vale->id, 'codigo' => $vale->codigo]], 201);
    }

    /** POST /entregador/missao/clientes — cadastro rápido em campo. */
    public function cadastrarCliente(Request $request): JsonResponse
    {
        $d = $request->validate([
            'nome' => 'required|string|max:160',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'cidade_id' => ['nullable', 'integer', new ExisteNoTenant(Cidade::class)],
            'telefone' => 'nullable|string|max:30',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $cliente = $this->vendas->cadastrarCliente($this->atribuicao($request), [
            'nome' => $d['nome'],
            'endereco' => $d['endereco'] ?? null,
            'numero' => $d['numero'] ?? null,
            'cidade_id' => $d['cidade_id'] ?? null,
            'telefone' => $d['telefone'] ?? null,
            'lat' => $d['latitude'] ?? null,
            'lng' => $d['longitude'] ?? null,
        ]);

        return response()->json(['data' => ['id' => $cliente->id, 'nome' => $cliente->nome]], 201);
    }
}
