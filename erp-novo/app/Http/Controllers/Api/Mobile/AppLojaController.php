<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Integracao\IntegracaoTenant;
use App\Domain\Mobile\CatalogoMobileService;
use App\Domain\Mobile\CotacaoMobileService;
use App\Http\Controllers\Controller;
use App\Models\Apoio\Feriado;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Monitora\Cerca;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App do cliente — LOJA (B-1): catálogo, cotação (preço server-side), config da
 * revenda, feriados e polígonos de entrega. Extraído do AppClienteController
 * (que tinha 572 linhas) — mesmas rotas/contrato, só melhor organizado.
 */
class AppLojaController extends Controller
{
    public function __construct(
        private CatalogoMobileService $catalogo,
        private CotacaoMobileService $cotacao,
    ) {}

    /**
     * POST /app/v1/carrinho/cotacao — preço do carrinho calculado no SERVIDOR (F3).
     * O app envia só itens (produto_id+quantidade) + condição/cupom/gp; recebe
     * subtotal, desconto e total. Nenhum preço é aceito do cliente.
     */
    public function cotar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'required|numeric|gt:0',
            'condicao_id' => ['nullable', 'integer', new ExisteNoTenant(CondicaoPagamento::class)],
            'codigo_cupom' => 'nullable|string|max:40',
            'gasdopovo' => 'boolean',
        ]);

        $user = $request->user();

        return response()->json(['data' => $this->cotacao->cotar($user->empresa_id, $user->grupo_id, $d)]);
    }

    /** GET /app/v1/config — config do app por empresa (vídeo de abertura, Gás do Povo). */
    public function config(Request $request): JsonResponse
    {
        $user = $request->user();
        $cfg = EmpresaConfig::query()->where('empresa_id', $user->empresa_id)->first();
        $dados = (array) ($cfg?->dados ?? []);
        $app = (array) ($dados['app'] ?? []);

        // F6: quais meios ONLINE a empresa suporta — SÓ booleanos (nunca credencial).
        // Com driver fake (dev/CI) tudo fica disponível; com gate real, disponível =
        // a empresa tem o próprio credenciamento (fail-closed coerente com F2).
        $integracao = app(IntegracaoTenant::class);
        $pixDisponivel = config('services.pix.driver', 'fake') === 'fake'
            || $integracao->pixConfigurado($user->empresa_id);
        $cartaoDisponivel = config('services.pagamento.driver', 'fake') !== 'erede'
            || $integracao->cartaoConfigurado($user->empresa_id);

        return response()->json(['data' => [
            'gaspovo_ativo' => (bool) ($app['gaspovo_ativo'] ?? false),
            'frete_gaspovo' => isset($app['frete_gaspovo']) ? (float) $app['frete_gaspovo'] : null,
            'video' => $app['video'] ?? null, // { url, titulo } ou null
            'tempo_entrega_min' => $cfg?->tempoentrega,
            'pagamentos_online' => ['pix' => $pixDisponivel, 'cartao' => $cartaoDisponivel],
        ]]);
    }

    /** GET /app/v1/reseller — dados da revenda (empresa do token) exibidos no app (F3b). */
    public function reseller(Request $request): JsonResponse
    {
        $empresa = Empresa::query()->findOrFail($request->user()->empresa_id);
        $cfg = EmpresaConfig::query()->where('empresa_id', $empresa->id)->first();

        return response()->json(['data' => [
            'id' => $empresa->id,
            'nome' => $empresa->nome_fantasia ?: $empresa->razao_social,
            'telefone' => $empresa->telefone1,
            'whatsapp' => $empresa->telefone2,
            'latitude' => $empresa->latitude !== null ? (float) $empresa->latitude : null,
            'longitude' => $empresa->longitude !== null ? (float) $empresa->longitude : null,
            'tempo_entrega_min' => $cfg?->tempoentrega,
        ]]);
    }

    /** GET /app/v1/feriados — feriados do grupo (afetam agendamento) — F3b. */
    public function feriados(Request $request): JsonResponse
    {
        $feriados = Feriado::query()
            ->where('grupo_id', $request->user()->grupo_id)
            ->where('ativo', true)
            ->orderBy('data')
            ->get(['descricao', 'data', 'recorrente'])
            ->map(fn ($f) => [
                'descricao' => $f->descricao,
                'data' => $f->data?->toDateString(),
                'recorrente' => (bool) $f->recorrente,
            ]);

        return response()->json(['data' => $feriados]);
    }

    /** GET /app/v1/poligonos — cercas/polígonos de entrega da empresa (F3b). */
    public function poligonos(Request $request): JsonResponse
    {
        $cercas = Cerca::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('ativo', true)
            ->with('pontos:id,cerca_id,latitude,longitude,ordem')
            ->get(['id', 'descricao', 'setor_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'descricao' => $c->descricao,
                'setor_id' => $c->setor_id,
                'pontos' => $c->pontos->sortBy('ordem')->values()->map(fn ($p) => [
                    'lat' => (float) $p->latitude,
                    'lng' => (float) $p->longitude,
                ]),
            ]);

        return response()->json(['data' => $cercas]);
    }

    /** GET /app/v1/produtos — catálogo da empresa (só ativos com preço). */
    public function produtos(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->catalogo->produtos($request->user()->empresa_id)]);
    }

    /** GET /app/v1/init — pacote de abertura do app: produtos + condições de pagamento. */
    public function init(Request $request): JsonResponse
    {
        $user = $request->user();
        $apenasGp = $request->boolean('gasdopovo');

        return response()->json(['data' => $this->catalogo->init($user->empresa_id, $user->grupo_id, $apenasGp)]);
    }

    /** GET /app/v1/cupom?codigo= — valida um cupom (promoção com código) vigente. */
    public function cupom(Request $request): JsonResponse
    {
        $codigo = (string) $request->query('codigo', '');
        $promo = $this->catalogo->validarCupom($request->user()->grupo_id, $codigo);

        return response()->json(['data' => [
            'codigo' => $promo->codigo,
            'descricao' => $promo->descricao,
            'desconto_percentual' => (float) $promo->desconto_percentual,
        ]]);
    }
}
