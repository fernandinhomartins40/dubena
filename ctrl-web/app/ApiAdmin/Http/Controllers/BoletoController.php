<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F6 (SPA React) — API admin de BOLETOS (consulta) e PIX (visão).
 * Auditado: Boleto + BoletoProcessor (docs/01-vigente/IMPL_FINANCEIRO.md).
 * A GERAÇÃO de remessa/retorno CNAB e a baixa PIX online dependem de conta
 * bancária/provedor reais (BoletoProcessor/PixService) e permanecem no fluxo
 * legado; aqui expomos a CONSULTA consolidada (lista + status). RBAC: boleto / pix.
 */
class BoletoController extends Controller
{
    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless($request->user()->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    /** GET /api/admin/boletos?q=&status= — boletos emitidos com parcela/cliente. */
    public function index(Request $request)
    {
        $this->autorizar($request, 'boleto', 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status'); // pendente|cancelado|todos

        $rows = DB::table('boletos as b')
            ->leftJoin('financeiroparcelas as fp', 'fp.id', '=', 'b.financeiroparcela_id')
            ->leftJoin('financeiros as f', 'f.id', '=', 'b.financeiro_id')
            ->leftJoin('clientes as c', 'c.id', '=', 'f.cliente_id')
            ->where('b.empresa_id', $empresaId)
            ->when($status === 'pendente', fn ($w) => $w->where('b.cancelado', 0))
            ->when($status === 'cancelado', fn ($w) => $w->where('b.cancelado', 1))
            ->when($q !== '', function ($w) use ($q) {
                $w->where('b.nossonumero', 'ilike', '%' . $q . '%')->orWhere('c.nome', 'ilike', '%' . $q . '%');
            })
            ->orderByDesc('b.datahora')->limit(300)
            ->get([
                'b.id', 'b.nossonumero', 'b.datahora', 'b.cancelado', 'b.gerouremessa',
                'fp.valor', 'fp.datavencimento', 'fp.baixado', 'c.nome as cliente',
            ]);

        return response()->json(['data' => $rows]);
    }

    /** GET /api/admin/boletos/resumo — contadores p/ cards. */
    public function resumo(Request $request)
    {
        $this->autorizar($request, 'boleto', 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $base = fn () => DB::table('boletos')->where('empresa_id', $empresaId);
        return response()->json(['data' => [
            'total'      => (clone $base())->count(),
            'pendentes'  => (clone $base())->where('cancelado', 0)->count(),
            'cancelados' => (clone $base())->where('cancelado', 1)->count(),
            'com_remessa' => (clone $base())->where('gerouremessa', 1)->count(),
        ]]);
    }

    /** GET /api/admin/pix/config — status da configuração PIX da empresa (sem expor segredos). */
    public function pixStatus(Request $request)
    {
        $this->autorizar($request, 'pix', 'view');
        $cfg = \App\Empresaconfig::where('empresa_id', $request->user()->empresa_id)->first();
        return response()->json(['data' => [
            'configurado'    => $cfg && ! empty($cfg->chavepix) && ! empty($cfg->client_id),
            'valida_entrega' => $cfg ? (int) $cfg->validapixentrega : 0,
        ]]);
    }
}
