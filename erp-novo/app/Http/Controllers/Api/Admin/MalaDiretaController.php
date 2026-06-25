<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Relatorio\RelatorioService;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Mala direta (F12 — CRM) — segmentação de clientes para campanha + export CSV.
 * Moderniza o MaladiretaController do legado (aniversariantes/endereço/recompra)
 * num filtro único parametrizado. Escopo por empresa (Cliente é tenant-scoped).
 *
 * Filtros: segmento_id, cidade_id, bairro_id, mes_aniversario (1-12), gasdopovo,
 * com_email, sem_compra_dias (não compra há N dias). O envio em massa é gate SMTP
 * (config global F01); aqui exportamos a lista (CSV) p/ a campanha.
 */
class MalaDiretaController extends Controller
{
    /** GET /crm/mala-direta — lista segmentada (JSON) ou CSV (?formato=csv). */
    public function index(Request $request, RelatorioService $relatorio): Response|JsonResponse
    {
        abort_unless($request->user()->temPermissao('cliente.view'), 403, 'Sem permissão.');

        $d = $request->validate([
            'segmento_id' => 'nullable|integer',
            'cidade_id' => 'nullable|integer',
            'bairro_id' => 'nullable|integer',
            'mes_aniversario' => 'nullable|integer|min:1|max:12',
            'gasdopovo' => 'nullable|boolean',
            'com_email' => 'nullable|boolean',
            'sem_compra_dias' => 'nullable|integer|min:1',
            'formato' => 'nullable|in:csv',
        ]);

        $clientes = Cliente::query()
            ->where('ativo', true)
            ->when(isset($d['segmento_id']), fn (Builder $q) => $q->where('segmento_id', $d['segmento_id']))
            ->when(isset($d['cidade_id']), fn (Builder $q) => $q->where('cidade_id', $d['cidade_id']))
            ->when(isset($d['bairro_id']), fn (Builder $q) => $q->where('bairro_id', $d['bairro_id']))
            ->when(isset($d['mes_aniversario']), fn (Builder $q) => $q->whereNotNull('datanascimento')->whereMonth('datanascimento', $d['mes_aniversario']))
            ->when(! empty($d['gasdopovo']), fn (Builder $q) => $q->where('gasdopovo', true))
            ->when(! empty($d['com_email']), fn (Builder $q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->when(isset($d['sem_compra_dias']), fn (Builder $q) => $q->where(function (Builder $w) use ($d) {
                $w->whereNull('data_ultima_compra')
                    ->orWhere('data_ultima_compra', '<', now()->subDays((int) $d['sem_compra_dias'])->toDateString());
            }))
            ->orderBy('nome')
            ->get(['id', 'nome', 'email', 'cep', 'endereco', 'numero', 'bairro_id', 'cidade_id', 'datanascimento', 'data_ultima_compra']);

        $linhas = $clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'nome' => $c->nome,
            'email' => $c->email,
            'cep' => $c->cep,
            'endereco' => trim(($c->endereco ?? '').' '.($c->numero ?? '')),
            'nascimento' => optional($c->datanascimento)->toDateString() ?? (string) $c->datanascimento,
            'ultima_compra' => optional($c->data_ultima_compra)->toDateString() ?? (string) $c->data_ultima_compra,
        ])->all();

        if (($d['formato'] ?? null) === 'csv') {
            return response($relatorio->csv($linhas), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="mala-direta.csv"',
            ]);
        }

        return response()->json(['data' => $linhas, 'total' => count($linhas)]);
    }
}
