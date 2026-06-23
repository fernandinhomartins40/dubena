<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Gestao\BemService;
use App\Http\Controllers\Controller;
use App\Models\Gestao\CupomFiscal;
use App\Models\Gestao\Documento;
use App\Models\Gestao\EmpresaBem;
use App\Models\Gestao\Mcmm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Gestão (C11): cupom fiscal (SAT/CFe), MCMM, documentos e bens (depreciação).
 * Emissão SAT/CFe real é gate regional; aqui o registro/estado é gerenciado.
 */
class GestaoController extends Controller
{
    // ───────── Cupom fiscal (SAT/CFe) ─────────
    public function cupomIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cupomfiscal.view');

        return response()->json(['data' => CupomFiscal::query()->with('itens')->latest()->limit(200)->get()]);
    }

    public function cupomCriar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cupomfiscal.create');
        $d = $request->validate([
            'pedido_id' => 'nullable|integer|exists:pedidos,id',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'nullable|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|gt:0',
            'itens.*.valor_unitario' => 'required|numeric|gte:0',
        ]);

        $cupom = CupomFiscal::create([
            'pedido_id' => $d['pedido_id'] ?? null,
            'situacao' => 'rascunho',
        ]);
        $total = 0.0;
        foreach ($d['itens'] as $i) {
            $vt = round($i['quantidade'] * $i['valor_unitario'], 2);
            $cupom->itens()->create([
                'produto_id' => $i['produto_id'] ?? null,
                'quantidade' => $i['quantidade'],
                'valor_unitario' => $i['valor_unitario'],
                'valor_total' => $vt,
            ]);
            $total += $vt;
        }
        $cupom->update(['valor_total' => round($total, 2)]);

        return response()->json(['data' => $cupom->refresh()->load('itens')], 201);
    }

    /**
     * Emite o cupom (estado → emitido). A transmissão real ao SAT/SEFAZ-SP é gate
     * regional; aqui marca como emitido e numera (anti-duplicidade simples).
     */
    public function cupomEmitir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'cupomfiscal.edit');
        $cupom = CupomFiscal::query()->findOrFail($id);
        if ($cupom->situacao === 'emitido') {
            throw ValidationException::withMessages(['cupom' => 'Cupom já emitido.']);
        }
        $proximo = (int) CupomFiscal::withoutTenant()->where('empresa_id', $cupom->empresa_id)->max('numero') + 1;
        $cupom->update(['situacao' => 'emitido', 'numero' => (string) $proximo, 'emitido_em' => now()]);

        return response()->json(['data' => $cupom->refresh(), 'message' => 'Cupom emitido (transmissão SAT/CFe é gate regional).']);
    }

    // ───────── MCMM ─────────
    public function mcmmIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'mcmm.view');

        return response()->json(['data' => Mcmm::query()->latest('data')->limit(200)->get()]);
    }

    public function mcmmSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, $id ? 'mcmm.edit' : 'mcmm.create');
        $d = $request->validate([
            'data' => 'nullable|date',
            'descricao' => 'required|string|max:255',
            'tipo' => 'required|in:entrada,saida',
            'quantidade' => 'required|numeric',
            'produto_id' => 'nullable|integer|exists:produtos,id',
        ]);
        $d['data'] ??= now()->toDateString();

        $reg = $id ? tap(Mcmm::query()->findOrFail($id))->update($d) : Mcmm::create($d);

        return response()->json(['data' => $reg->refresh()], $id ? 200 : 201);
    }

    public function mcmmExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'mcmm.delete');
        Mcmm::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Registro excluído.']);
    }

    // ───────── Documentos ─────────
    public function documentoIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'documento.view');

        return response()->json(['data' => Documento::query()->orderBy('validade')->get()]);
    }

    public function documentoSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, $id ? 'documento.edit' : 'documento.create');
        $d = $request->validate([
            'tipo' => 'nullable|string|max:40',
            'descricao' => 'required|string|max:255',
            'numero' => 'nullable|string|max:60',
            'emissao' => 'nullable|date',
            'validade' => 'nullable|date',
        ]);

        $reg = $id ? tap(Documento::query()->findOrFail($id))->update($d) : Documento::create($d);

        return response()->json(['data' => $reg->refresh()], $id ? 200 : 201);
    }

    public function documentoExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'documento.delete');
        Documento::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Documento excluído.']);
    }

    // ───────── Bens + depreciação ─────────
    public function bemIndex(Request $request, BemService $service): JsonResponse
    {
        $this->autorizar($request, 'bem.view');

        $bens = EmpresaBem::query()->orderBy('descricao')->get()
            ->map(fn (EmpresaBem $b) => array_merge($b->toArray(), ['depreciacao' => $service->depreciacao($b)]));

        return response()->json(['data' => $bens]);
    }

    public function bemSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, $id ? 'bem.edit' : 'bem.create');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'data_aquisicao' => 'nullable|date',
            'valor_aquisicao' => 'required|numeric|gte:0',
            'taxa_depreciacao_anual' => 'nullable|numeric|min:0|max:100',
            'valor_residual' => 'nullable|numeric|gte:0',
            'ativo' => 'nullable|boolean',
        ]);

        $reg = $id ? tap(EmpresaBem::query()->findOrFail($id))->update($d) : EmpresaBem::create($d);

        return response()->json(['data' => $reg->refresh()], $id ? 200 : 201);
    }

    public function bemExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'bem.delete');
        EmpresaBem::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Bem excluído.']);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
