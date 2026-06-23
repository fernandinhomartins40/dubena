<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Monitora\Veiculo as MonitoraVeiculo;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\Convenio;
use App\Models\Satelite\ValeGas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Satélites — status agregado (C10). Consolida os módulos satélite (convênio,
 * vale-gás, comodato, monitora) em painéis para a SPA. Sem schema novo: agrega o
 * que já existe.
 */
class SateliteStatusController extends Controller
{
    /** GET /satelites/relatorios — catálogo de relatórios disponíveis dos satélites. */
    public function relatorios(Request $request): JsonResponse
    {
        $this->autorizar($request, 'relatorio.view');

        return response()->json(['data' => [
            ['chave' => 'convenios-fechamentos', 'titulo' => 'Fechamentos de convênio', 'modulo' => 'convenio'],
            ['chave' => 'vale-gas-emitidos', 'titulo' => 'Vale-gás emitidos/utilizados', 'modulo' => 'valegas'],
            ['chave' => 'comodatos-ativos', 'titulo' => 'Comodatos em aberto', 'modulo' => 'comodato'],
        ]]);
    }

    /** GET /satelites/monitoramento — visão de frota/GPS. */
    public function monitoramento(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.view');

        $veiculos = MonitoraVeiculo::query()->count();
        $comUltimaPosicao = MonitoraVeiculo::query()->whereHas('ultimaPosicao')->count();

        return response()->json(['data' => [
            'veiculos' => $veiculos,
            'com_posicao' => $comUltimaPosicao,
            'sem_posicao' => max(0, $veiculos - $comUltimaPosicao),
        ]]);
    }

    /** GET /satelites/integracoes — status dos gates externos (config). */
    public function integracoes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'empresa.view');

        return response()->json(['data' => [
            'fiscal' => ['driver' => env('FISCAL_DRIVER', 'fake'), 'ativo' => env('FISCAL_DRIVER') === 'nfephp'],
            'convenios_ativos' => Convenio::query()->where('ativo', true)->count(),
            'vale_gas_ativos' => ValeGas::query()->count(),
            'comodatos_abertos' => Comodato::query()->where('situacao', 'ATIVO')->count(),
        ]]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
