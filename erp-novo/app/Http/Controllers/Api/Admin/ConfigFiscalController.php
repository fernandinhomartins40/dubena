<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fiscal\ConfigFiscal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuração fiscal da empresa — N9. CSC token nunca volta no JSON.
 */
class ConfigFiscalController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');
        $config = ConfigFiscal::firstOrCreate(['empresa_id' => $request->user()->empresa_id]);

        return response()->json(['data' => $config]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.edit');
        $d = $request->validate([
            'ambiente' => 'nullable|in:1,2',
            'serie_nfe' => 'nullable|integer|min:1',
            'serie_nfce' => 'nullable|integer|min:1',
            'regime_tributario' => 'nullable|in:1,2,3',
            'csc_id' => 'nullable|string|max:10',
            'csc_token' => 'nullable|string|max:255',
        ]);

        $config = ConfigFiscal::firstOrCreate(['empresa_id' => $request->user()->empresa_id]);
        $config->update($d);

        return response()->json(['data' => $config->refresh()]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
