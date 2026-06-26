<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
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
    use AutorizaPorPermissao;

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

        // Lê de config() (não env() direto): com config:cache em produção, env()
        // retorna vazio fora dos arquivos de config.
        return response()->json(['data' => [
            'pix' => (bool) config('services.integracoes.pix'),
            'email_smtp' => (bool) config('services.integracoes.email_smtp'),
            'google_maps' => (bool) config('services.integracoes.google_maps'),
            'fcm_push' => (bool) config('services.integracoes.fcm_push'),
            'fiscal' => config('services.fiscal.driver') === 'nfephp',
        ]]);
    }
}
