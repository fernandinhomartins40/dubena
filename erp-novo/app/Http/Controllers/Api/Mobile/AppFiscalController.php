<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Fiscal\CupomTextoService;
use App\Domain\Fiscal\DanfePdfService;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\ModeloDocumento;
use App\Http\Controllers\Controller;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Emissão fiscal em campo (F6) — o vendedor industrial.
 *
 * Só o industrial emite nota da rua: é o perfil que vende para empresa e
 * indústria, onde a NF-e sai na hora. O middleware `approle:industrial` guarda
 * estas rotas.
 *
 * **Nada de fiscal é reimplementado aqui.** O `Domain/Fiscal` já monta XML,
 * transmite à SEFAZ, numera e gera DANFE; este controller só expõe ao app o que
 * já existia em rota admin — o item mais barato do plano, e por isso o primeiro
 * a fechar.
 *
 * **Fail-closed permanece.** Sem certificado da empresa o `FiscalService` lança
 * `CredencialNaoConfiguradaException` → 503 (bootstrap/app.php), e o vendedor vê
 * que não pode emitir em vez de gerar nota inválida.
 */
class AppFiscalController extends Controller
{
    public function __construct(private FiscalService $fiscal) {}

    /**
     * POST /app/v1/entregador/fiscal/emitir — emite a nota de um pedido do campo.
     *
     * O pedido precisa ser da empresa do token: sem esse filtro, um id de outra
     * revenda emitiria nota no CNPJ errado. A RLS já barra no banco, mas a
     * checagem aqui devolve 404 em vez de erro de baixo nível.
     */
    public function emitir(Request $request): JsonResponse
    {
        $d = $request->validate([
            'pedido_id' => 'required|integer|exists:pedidos,id',
            // 55=NF-e, 65=NFC-e. O legado decide por `appnfceauto` da condição de
            // pagamento (NfwebController::savePedido:375); aqui o app escolhe,
            // porque o industrial sabe se o cliente é PJ com IE.
            'modelo' => 'required|in:55,65',
        ]);

        $pedido = Pedido::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($d['pedido_id']);

        $nota = $this->fiscal->emitirDoPedido($pedido, ModeloDocumento::from($d['modelo']));

        return response()->json(['data' => $nota->load('itens')], 201);
    }

    /**
     * GET /app/v1/entregador/fiscal/notas/{id}/danfe — PDF para imprimir/enviar.
     *
     * O DanfePdfService recusa nota não autorizada com DomainException (→ 422),
     * que é a mesma regra do MovelApp: `NotaFiscalImpressaoActivity:120` só
     * imprime com `nfsituacao_id == 100`.
     */
    public function danfe(Request $request, int $id, DanfePdfService $danfe): \Illuminate\Http\Response
    {
        $nota = NotaFiscal::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);

        return response($danfe->gerar($nota), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="danfe-'.$nota->numero.'.pdf"',
        ]);
    }

    /**
     * GET /app/v1/entregador/fiscal/notas/{id}/cupom — DANFE em TEXTO (F8).
     *
     * Para impressora térmica: linhas de largura fixa, prontas para a camada
     * Bluetooth do app. O servidor decide o conteúdo; o app só transmite os
     * bytes — assim uma correção de layout não exige republicar APK.
     *
     * `largura` é parâmetro porque o parque tem impressoras de 32, 48 e 55
     * colunas; o padrão é o 55 do MovelApp.
     */
    public function cupomNota(Request $request, int $id, CupomTextoService $cupom): JsonResponse
    {
        $d = $request->validate(['largura' => 'nullable|integer|min:24|max:96']);

        $nota = NotaFiscal::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'largura' => (int) ($d['largura'] ?? CupomTextoService::LARGURA_PADRAO),
                'linhas' => $cupom->daNota($nota, (int) ($d['largura'] ?? CupomTextoService::LARGURA_PADRAO)),
            ],
        ]);
    }
}
