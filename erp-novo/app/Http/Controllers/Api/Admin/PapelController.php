<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Shared\PermissaoCatalogo;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionCondition;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Central de Acessos — Perfis/Papéis (A2). CRUD de papéis do GRUPO ativo, com
 * marcação das permissões de cada papel a partir do catálogo (fonte da verdade).
 *
 * Papel pertence ao grupo (rede); um papel é atribuído ao usuário POR empresa
 * (pivot role_user.empresa_id) — isso é feito no UsuarioController. Aqui só se
 * define o papel e SUAS permissões.
 *
 * Anti-escalonamento (princípio de menor privilégio): um administrador não-suporte
 * só pode conceder a um papel permissões que ELE PRÓPRIO possui. Assim ninguém
 * cria um papel mais poderoso que si mesmo. O `support` (plataforma) não tem esse
 * limite.
 */
class PapelController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'papel.view');

        $papeis = Role::query()
            ->where('grupo_id', $this->tenant->requireGrupoId())
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q->where('nome', 'ilike', '%'.$b.'%'))
            ->withCount('users')
            ->with('permissions:id,chave')
            ->orderBy('nome')
            ->get();

        return response()->json(['data' => $papeis->map(fn (Role $r) => $this->serializar($r))]);
    }

    /** Catálogo de permissões (para montar a UI de marcação), agrupado por módulo. */
    public function catalogo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'papel.view');

        $itens = [];
        foreach (PermissaoCatalogo::comDescricoes() as $chave => $descricao) {
            [$modulo] = explode('.', $chave, 2);
            $itens[$modulo][] = ['chave' => $chave, 'descricao' => $descricao];
        }

        return response()->json(['data' => $itens]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'papel.create');
        $dados = $this->validar($request);

        $papel = Role::create([
            'grupo_id' => $this->tenant->requireGrupoId(),
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
        ]);
        $this->sincronizarPermissoes($request, $papel, $dados['permissoes'] ?? []);

        return response()->json(['data' => $this->serializar($papel->fresh(['permissions']))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'papel.edit');
        $papel = $this->doGrupo($id);
        $dados = $this->validar($request, $papel->id);

        $papel->update(['nome' => $dados['nome'], 'descricao' => $dados['descricao'] ?? null]);
        if (array_key_exists('permissoes', $dados)) {
            $this->sincronizarPermissoes($request, $papel, $dados['permissoes']);
        }

        return response()->json(['data' => $this->serializar($papel->fresh(['permissions']))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'papel.delete');
        $papel = $this->doGrupo($id);

        abort_if($papel->users()->exists(), 422, 'Papel em uso por usuários — remova as atribuições antes de excluir.');

        $papel->permissions()->detach();
        $papel->delete();

        return response()->json(['message' => 'Papel excluído.']);
    }

    // ─────────────── Condições ABAC (A4) ───────────────

    /** Lista as condições ABAC de um papel (na empresa ativa). */
    public function condicoesIndex(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'papel.view');
        $papel = $this->doGrupo($id);

        $rows = PermissionCondition::query()
            ->where('role_id', $papel->id)
            ->with('permission:id,chave')
            ->orderBy('id')
            ->get()
            ->map(fn (PermissionCondition $c) => [
                'id' => $c->id,
                'permissao' => $c->permission->chave,
                'tipo' => $c->tipo,
                'parametros' => $c->parametros,
                'ativo' => $c->ativo,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function condicaoStore(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'papel.edit');
        $papel = $this->doGrupo($id);
        $dados = $this->validarCondicao($request, $papel);

        $cond = PermissionCondition::create([
            'role_id' => $papel->id,
            'permission_id' => $dados['permission_id'],
            'tipo' => $dados['tipo'],
            'parametros' => $dados['parametros'] ?? [],
            'ativo' => true,
        ]);

        return response()->json(['data' => ['id' => $cond->id]], 201);
    }

    public function condicaoDestroy(Request $request, int $id, int $condId): JsonResponse
    {
        $this->autorizar($request, 'papel.edit');
        $papel = $this->doGrupo($id);

        // Garante que a condição é deste papel (e da empresa ativa, via global scope).
        PermissionCondition::query()->where('role_id', $papel->id)->findOrFail($condId)->delete();

        return response()->json(['message' => 'Condição removida.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validarCondicao(Request $request, Role $papel): array
    {
        $dados = $request->validate([
            // Aceita o id direto OU a chave 'modulo.acao' (a SPA usa a chave).
            'permission_id' => ['required_without:permissao', 'integer', 'exists:permissions,id'],
            'permissao' => ['required_without:permission_id', 'string'],
            'tipo' => ['required', Rule::in(PermissionCondition::TIPOS)],
            'parametros' => 'nullable|array',
        ]);

        $permissionId = $dados['permission_id']
            ?? Permission::query()->where('chave', $dados['permissao'])->value('id');

        if ($permissionId === null) {
            throw ValidationException::withMessages(['permissao' => 'Permissão inexistente.']);
        }

        // A permissão precisa pertencer ao papel (não faz sentido condicionar uma
        // permissão que o papel nem concede).
        if (! $papel->permissions()->whereKey($permissionId)->exists()) {
            throw ValidationException::withMessages(['permission_id' => 'Esta permissão não pertence ao papel.']);
        }

        $dados['permission_id'] = $permissionId;

        return $dados;
    }

    /** Garante que o papel é do grupo ativo (isolamento de tenant). */
    private function doGrupo(int $id): Role
    {
        return Role::query()
            ->where('grupo_id', $this->tenant->requireGrupoId())
            ->findOrFail($id);
    }

    /**
     * Sincroniza permissões do papel a partir de chaves do catálogo, com guarda
     * anti-escalonamento: não-suporte só concede o que ele próprio possui.
     *
     * @param  list<string>  $chaves
     */
    private function sincronizarPermissoes(Request $request, Role $papel, array $chaves): void
    {
        $chaves = array_values(array_unique($chaves));

        // Só chaves do catálogo (rejeita inventadas).
        $validas = array_intersect($chaves, PermissaoCatalogo::chaves());
        if (count($validas) !== count($chaves)) {
            throw ValidationException::withMessages([
                'permissoes' => 'Há permissões fora do catálogo do sistema.',
            ]);
        }

        // Anti-escalonamento: o ator não-suporte não pode conceder além do que tem.
        $ator = $request->user();
        if (! $ator->support) {
            $minhas = $ator->permissoesEfetivas($this->tenant->empresaId());
            $excedente = array_values(array_diff($validas, $minhas));
            if ($excedente !== []) {
                throw ValidationException::withMessages([
                    'permissoes' => 'Você não pode conceder permissões que não possui: '.implode(', ', $excedente),
                ]);
            }
        }

        $ids = Permission::query()->whereIn('chave', $validas)->pluck('id')->all();
        $papel->permissions()->sync($ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'nome' => [
                'required', 'string', 'max:120',
                // Único por grupo (nome do papel não colide na mesma rede).
                'unique:roles,nome,'.($ignorarId ?? 'NULL').',id,grupo_id,'.$this->tenant->requireGrupoId(),
            ],
            'descricao' => 'nullable|string|max:255',
            'permissoes' => 'sometimes|array',
            'permissoes.*' => 'string',
        ]);
    }

    /** @return array<string, mixed> */
    private function serializar(Role $papel): array
    {
        return [
            'id' => $papel->id,
            'nome' => $papel->nome,
            'descricao' => $papel->descricao,
            'usuarios_count' => $papel->users_count ?? $papel->users()->count(),
            'permissoes' => $papel->permissions->pluck('chave')->values()->all(),
        ];
    }
}
