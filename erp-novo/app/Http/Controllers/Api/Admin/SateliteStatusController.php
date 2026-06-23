<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Monitora\Veiculo as MonitoraVeiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Satélites — status agregado (C10). Consolida os módulos satélite (convênio,
 * vale-gás, comodato, monitora) em painéis para a SPA. Sem schema novo: agrega o
 * que já existe.
 */
class SateliteStatusController extends Controller
{
    /**
     * GET /satelites/relatorios — catálogo por CATEGORIA.
     * Shape exigido pela SPA: [{categoria, relatorios: string[]}].
     */
    public function relatorios(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');

        return response()->json(['data' => [
            ['categoria' => 'Vendas', 'relatorios' => ['Vendas por período', 'Vendas por produto', 'Comissões']],
            ['categoria' => 'Financeiro', 'relatorios' => ['Contas a receber', 'Contas a pagar', 'DRE', 'Fechamentos de caixa']],
            ['categoria' => 'Estoque', 'relatorios' => ['Posição de estoque', 'Estoque baixo', 'Movimentações']],
            ['categoria' => 'Satélites', 'relatorios' => ['Fechamentos de convênio', 'Vale-gás emitidos', 'Comodatos em aberto']],
            ['categoria' => 'Fiscal', 'relatorios' => ['Notas emitidas', 'SPED Fiscal']],
        ]]);
    }

    /**
     * GET /satelites/monitoramento — status do GPS/frota.
     * Shape exigido pela SPA: {disponivel: bool, observacao: string}.
     */
    public function monitoramento(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.view');

        $veiculos = MonitoraVeiculo::query()->count();
        $comPosicao = MonitoraVeiculo::query()->whereHas('ultimaPosicao')->count();

        return response()->json(['data' => [
            'disponivel' => $veiculos > 0,
            'observacao' => $veiculos > 0
                ? "{$comPosicao} de {$veiculos} veículo(s) com posição recente."
                : 'Nenhum veículo monitorado. Configure a integração de GPS (SGCasa).',
        ]]);
    }

    /**
     * GET /satelites/integracoes — status dos gates externos.
     * Shape exigido pela SPA: mapa { chave => bool } (pix/email_smtp/google_maps/fcm_push).
     */
    public function integracoes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'empresa.view');

        return response()->json(['data' => [
            'pix' => (bool) env('PIX_CLIENT_ID'),
            'email_smtp' => (bool) env('MAIL_HOST'),
            'google_maps' => (bool) env('GOOGLE_MAPS_KEY'),
            'fcm_push' => (bool) env('FCM_SERVER_KEY'),
            'fiscal' => env('FISCAL_DRIVER') === 'nfephp',
        ]]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
