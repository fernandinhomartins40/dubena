<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Crm\Checklist;
use App\Models\Crm\ChecklistExecucao;
use App\Models\Crm\MetaVenda;
use App\Models\Crm\PosVenda;
use App\Models\Crm\Promocao;
use App\Models\Crm\Sorteio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CRM / módulos satélite (C10): pós-venda, promoção, sorteio, metas, checklist.
 * CRUD por módulo + ações específicas (sortear, registrar execução de checklist).
 */
class CrmController extends Controller
{
    // ───────── Pós-venda ─────────
    public function posVendaIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'posvenda.view');

        return response()->json(['data' => PosVenda::query()->latest('data')->limit(200)->get()]);
    }

    public function posVendaSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, $id ? 'posvenda.edit' : 'posvenda.create');
        $d = $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'pedido_id' => 'nullable|integer|exists:pedidos,id',
            'data' => 'nullable|date',
            'nota' => 'nullable|integer|min:0|max:10',
            'canal' => 'nullable|string|max:30',
            'observacao' => 'nullable|string',
            'situacao' => 'nullable|in:pendente,realizado',
        ]);
        $d['data'] ??= now()->toDateString();

        $reg = $id
            ? tap(PosVenda::query()->findOrFail($id))->update($d)
            : PosVenda::create($d);

        return response()->json(['data' => $reg->refresh()], $id ? 200 : 201);
    }

    public function posVendaExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'posvenda.delete');
        PosVenda::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Pós-venda excluída.']);
    }

    // ───────── Promoção ─────────
    public function promocaoIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'promocao.view');

        return response()->json(['data' => Promocao::query()->orderByDesc('inicio')->get()]);
    }

    public function promocaoSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, $id ? 'promocao.edit' : 'promocao.create');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'ativo' => 'nullable|boolean',
        ]);

        $reg = $id ? tap(Promocao::query()->findOrFail($id))->update($d) : Promocao::create($d);

        return response()->json(['data' => $reg->refresh()], $id ? 200 : 201);
    }

    public function promocaoExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'promocao.delete');
        Promocao::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Promoção excluída.']);
    }

    // ───────── Sorteio ─────────
    public function sorteioIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'sorteio.view');

        return response()->json(['data' => Sorteio::query()->withCount('numeros')->latest()->get()]);
    }

    public function sorteioSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, $id ? 'sorteio.edit' : 'sorteio.create');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'data_sorteio' => 'nullable|date',
        ]);

        $reg = $id ? tap(Sorteio::query()->findOrFail($id))->update($d) : Sorteio::create($d);

        return response()->json(['data' => $reg->refresh()], $id ? 200 : 201);
    }

    public function sorteioNumero(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'sorteio.edit');
        $d = $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'numero' => 'required|string|max:20',
        ]);
        $sorteio = Sorteio::query()->findOrFail($id);
        $num = $sorteio->numeros()->create($d);

        return response()->json(['data' => $num], 201);
    }

    /** Sorteia um número entre os participantes. */
    public function sortear(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'sorteio.edit');
        $sorteio = Sorteio::query()->with('numeros')->findOrFail($id);
        if ($sorteio->numeros->isEmpty()) {
            throw ValidationException::withMessages(['sorteio' => 'Não há números para sortear.']);
        }
        $ganhador = $sorteio->numeros->random();
        $sorteio->update(['situacao' => 'sorteado', 'numero_sorteado' => $ganhador->numero]);

        return response()->json(['data' => ['numero' => $ganhador->numero, 'cliente_id' => $ganhador->cliente_id]]);
    }

    // ───────── Metas ─────────
    public function metaIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'meta.view');

        return response()->json(['data' => MetaVenda::query()
            ->when($request->query('competencia'), fn ($q, $c) => $q->where('competencia', $c))
            ->orderByDesc('competencia')->get()]);
    }

    public function metaSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, $id ? 'meta.edit' : 'meta.create');
        $d = $request->validate([
            'colaborador_id' => 'nullable|integer|exists:colaboradores,id',
            'competencia' => 'required|string|size:7', // YYYY-MM
            'meta_valor' => 'required|numeric|min:0',
        ]);

        $reg = $id ? tap(MetaVenda::query()->findOrFail($id))->update($d) : MetaVenda::create($d);

        return response()->json(['data' => $reg->refresh()], $id ? 200 : 201);
    }

    public function metaExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'meta.delete');
        MetaVenda::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Meta excluída.']);
    }

    // ───────── Checklist (modelo + execução) ─────────
    public function checklistIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'checklist.view');

        return response()->json(['data' => Checklist::query()->with('perguntas')->orderBy('descricao')->get()]);
    }

    public function checklistSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, $id ? 'checklist.edit' : 'checklist.create');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'ativo' => 'nullable|boolean',
            'perguntas' => 'nullable|array',
            'perguntas.*' => 'string|max:255',
        ]);

        $checklist = $id ? tap(Checklist::query()->findOrFail($id))->update(['descricao' => $d['descricao'], 'ativo' => $d['ativo'] ?? true])
            : Checklist::create(['descricao' => $d['descricao'], 'ativo' => $d['ativo'] ?? true]);

        if (isset($d['perguntas'])) {
            $checklist->perguntas()->delete();
            foreach ($d['perguntas'] as $i => $p) {
                $checklist->perguntas()->create(['pergunta' => $p, 'ordem' => $i]);
            }
        }

        return response()->json(['data' => $checklist->refresh()->load('perguntas')], $id ? 200 : 201);
    }

    public function checklistExecutar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'checklist.edit');
        $checklist = Checklist::query()->findOrFail($id);
        $d = $request->validate([
            'respostas' => 'required|array|min:1',
            'respostas.*.pergunta_id' => 'required|integer',
            'respostas.*.conforme' => 'required|boolean',
            'respostas.*.observacao' => 'nullable|string',
        ]);

        $exec = ChecklistExecucao::create([
            'checklist_id' => $checklist->id,
            'empresa_id' => $request->user()->empresa_id,
            'user_id' => $request->user()->id,
            'data' => now()->toDateString(),
        ]);
        foreach ($d['respostas'] as $r) {
            $exec->respostas()->create([
                'checklist_pergunta_id' => $r['pergunta_id'],
                'conforme' => $r['conforme'],
                'observacao' => $r['observacao'] ?? null,
            ]);
        }

        return response()->json(['data' => $exec->load('respostas')], 201);
    }

    public function checklistExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'checklist.delete');
        Checklist::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Checklist excluído.']);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
