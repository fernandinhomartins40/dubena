<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F8d/e/f (SPA React) — Satélites: Central de Relatórios (catálogo) +
 * Monitoramento (status) + Integrações (status). Auditado: Report*Controllers,
 * App\Monitora, Empresaconfig (docs/01-vigente/IMPL_SATELITES.md).
 *
 * Relatórios: catálogo por categoria (a geração de cada PDF/XLSX permanece no
 * motor legado de relatórios). Monitoramento GPS (App\Monitora, schema/guard
 * próprios) e Integrações (FCM/Pix/eRede) são expostos como STATUS — a operação
 * plena fica no legado/serviços externos.
 */
class SatelitesController extends Controller
{
    /** GET /api/admin/satelites/relatorios — catálogo agrupado por categoria. */
    public function relatorios(Request $request)
    {
        // Catálogo derivado dos Report*Controllers (Central de Relatórios).
        $catalogo = [
            'Administrativo' => ['Colaboradores', 'Comissões'],
            'Operacionais'   => ['Estoque', 'Veículos', 'Checklists'],
            'Financeiros'    => ['Caixa', 'Financeiro', 'Contas a receber/pagar'],
            'Vendas'         => ['Vendas mensais', 'Vale-Gás', 'Convênio'],
            'Gestão'         => ['Dashboard gerencial'],
        ];
        $data = [];
        foreach ($catalogo as $categoria => $itens) {
            $data[] = ['categoria' => $categoria, 'relatorios' => $itens];
        }
        return response()->json(['data' => $data]);
    }

    /** GET /api/admin/satelites/monitoramento — status do módulo de rastreamento. */
    public function monitoramento(Request $request)
    {
        // App\Monitora tem schema/guard próprios; aqui expomos disponibilidade.
        $disponivel = DB::getSchemaBuilder()->hasTable('monitoraveiculos')
            || DB::getSchemaBuilder()->hasTable('rastreamentos');
        return response()->json(['data' => [
            'disponivel' => $disponivel,
            'observacao' => 'O módulo de Monitoramento GPS roda em schema próprio (App\\Monitora). '
                . 'A operação (mapa, cercas, rotas) permanece no módulo dedicado.',
        ]]);
    }

    /** GET /api/admin/satelites/integracoes — status das integrações (config da empresa). */
    public function integracoes(Request $request)
    {
        $cfg = \App\Empresaconfig::where('empresa_id', $request->user()->empresa_id)->first();
        return response()->json(['data' => [
            'pix'          => $cfg ? (! empty($cfg->chavepix) && ! empty($cfg->client_id)) : false,
            'email_smtp'   => $cfg ? ! empty($cfg->emailservidorsmtp) : false,
            'google_maps'  => $cfg ? ! empty($cfg->keygooglemaps) : false,
            'fcm_push'     => $cfg ? ! empty($cfg->emailkeygoogle) : false,
        ]]);
    }
}
