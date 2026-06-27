<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Organizacao\Departamento;
use App\Models\Organizacao\SetorOrg;
use App\Models\Organizacao\Unidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Estrutura organizacional (A3) — árvore Unidade → Departamento → Setor.
 *
 * Tudo escopado por empresa (models BelongsToTenant → global scope + RLS), então
 * as consultas já filtram pela empresa ativa e cross-tenant retorna 404. Cada
 * recurso tem sua permissão própria (unidade.* / departamento.* / setor.*).
 */
class EstruturaController extends Controller
{
    use AutorizaPorPermissao;

    // ─────────────── Unidades (filiais) ───────────────

    public function unidadesIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'unidade.view');

        $rows = Unidade::query()
            ->withCount('departamentos')
            ->orderBy('nome')
            ->get(['id', 'parent_id', 'tipo', 'nome', 'cnpj', 'ativo']);

        return response()->json(['data' => $rows]);
    }

    public function unidadeStore(Request $request): JsonResponse
    {
        $this->autorizar($request, 'unidade.create');
        $dados = $this->validarUnidade($request);

        return response()->json(['data' => Unidade::create($dados)], 201);
    }

    public function unidadeUpdate(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'unidade.edit');
        $unidade = Unidade::query()->findOrFail($id);
        $dados = $this->validarUnidade($request);

        // Anti-ciclo: parent não pode ser a própria unidade nem uma descendente.
        if (! empty($dados['parent_id']) && $this->criaCiclo($unidade->id, (int) $dados['parent_id'])) {
            throw ValidationException::withMessages(['parent_id' => 'A unidade-pai não pode ser ela mesma ou uma filial subordinada.']);
        }

        $unidade->update($dados);

        return response()->json(['data' => $unidade->refresh()]);
    }

    public function unidadeDestroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'unidade.delete');
        $unidade = Unidade::query()->findOrFail($id);

        abort_if($unidade->filhas()->exists(), 422, 'Remova as filiais subordinadas antes de excluir.');
        abort_if($unidade->departamentos()->exists(), 422, 'Remova os departamentos desta unidade antes de excluir.');

        $unidade->delete();

        return response()->json(['message' => 'Unidade excluída.']);
    }

    // ─────────────── Departamentos ───────────────

    public function departamentosIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'departamento.view');

        $rows = Departamento::query()
            ->when($request->integer('unidade_id'), fn ($q, $u) => $q->where('unidade_id', $u))
            ->withCount('setores')
            ->orderBy('nome')
            ->get(['id', 'unidade_id', 'nome', 'ativo']);

        return response()->json(['data' => $rows]);
    }

    public function departamentoStore(Request $request): JsonResponse
    {
        $this->autorizar($request, 'departamento.create');

        return response()->json(['data' => Departamento::create($this->validarDepartamento($request))], 201);
    }

    public function departamentoUpdate(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'departamento.edit');
        $dep = Departamento::query()->findOrFail($id);
        $dep->update($this->validarDepartamento($request));

        return response()->json(['data' => $dep->refresh()]);
    }

    public function departamentoDestroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'departamento.delete');
        $dep = Departamento::query()->findOrFail($id);

        abort_if($dep->setores()->exists(), 422, 'Remova os setores deste departamento antes de excluir.');
        $dep->delete();

        return response()->json(['message' => 'Departamento excluído.']);
    }

    // ─────────────── Setores/Equipes ───────────────

    public function setoresIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'setor.view');

        $rows = SetorOrg::query()
            ->when($request->integer('departamento_id'), fn ($q, $d) => $q->where('departamento_id', $d))
            ->orderBy('nome')
            ->get(['id', 'departamento_id', 'nome', 'ativo']);

        return response()->json(['data' => $rows]);
    }

    public function setorStore(Request $request): JsonResponse
    {
        $this->autorizar($request, 'setor.create');

        return response()->json(['data' => SetorOrg::create($this->validarSetor($request))], 201);
    }

    public function setorUpdate(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'setor.edit');
        $setor = SetorOrg::query()->findOrFail($id);
        $setor->update($this->validarSetor($request));

        return response()->json(['data' => $setor->refresh()]);
    }

    public function setorDestroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'setor.delete');
        SetorOrg::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Setor excluído.']);
    }

    // ─────────────── Validação ───────────────

    /** @return array<string, mixed> */
    private function validarUnidade(Request $request): array
    {
        return $request->validate([
            'nome' => 'required|string|max:255',
            'tipo' => ['nullable', Rule::in(['matriz', 'filial'])],
            'cnpj' => 'nullable|string|max:18',
            // parent deve existir DENTRO da empresa (scope global garante o tenant).
            'parent_id' => ['nullable', 'integer', Rule::exists('unidades', 'id')],
            'ativo' => 'nullable|boolean',
        ]);
    }

    /** @return array<string, mixed> */
    private function validarDepartamento(Request $request): array
    {
        return $request->validate([
            'unidade_id' => ['required', 'integer', Rule::exists('unidades', 'id')],
            'nome' => 'required|string|max:255',
            'ativo' => 'nullable|boolean',
        ]);
    }

    /** @return array<string, mixed> */
    private function validarSetor(Request $request): array
    {
        return $request->validate([
            'departamento_id' => ['required', 'integer', Rule::exists('departamentos', 'id')],
            'nome' => 'required|string|max:255',
            'ativo' => 'nullable|boolean',
        ]);
    }

    /**
     * Detecta se tornar $novoParent o pai de $unidadeId criaria um ciclo —
     * isto é, se $novoParent é a própria unidade ou uma descendente dela.
     * Sobe a cadeia de parents a partir de $novoParent; se topar com $unidadeId, é ciclo.
     */
    private function criaCiclo(int $unidadeId, int $novoParent): bool
    {
        $atual = $novoParent;
        $visitados = [];
        while ($atual !== 0) {
            if ($atual === $unidadeId) {
                return true;
            }
            if (in_array($atual, $visitados, true)) {
                break; // proteção contra dado já inconsistente
            }
            $visitados[] = $atual;
            $atual = (int) (Unidade::query()->whereKey($atual)->value('parent_id') ?? 0);
        }

        return false;
    }
}
