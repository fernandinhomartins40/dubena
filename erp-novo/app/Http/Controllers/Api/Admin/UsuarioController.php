<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Central de Acessos — Usuários (A2). CRUD de usuários da EMPRESA ativa, com
 * atribuição de papéis (escopados na empresa ativa via role_user.empresa_id),
 * ativação/inativação e reset de senha — tudo por interface, sem deploy.
 *
 * Escopo: lista usuários cuja empresa padrão é a ativa OU que têm acesso a ela
 * (empresa_user). O isolamento por grupo é garantido porque só se opera dentro
 * do tenant resolvido.
 *
 * Garantias de segurança (plano A2):
 *  - NÃO se concede/edita a flag `support` por aqui (privilégio de plataforma).
 *  - Papéis atribuíveis são apenas os do GRUPO ativo.
 *  - O ator não pode inativar/excluir a si mesmo (evita auto-lockout).
 */
class UsuarioController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'usuario.view');
        $empresaId = $this->tenant->requireEmpresaId();

        $usuarios = User::query()
            ->where('grupo_id', $this->tenant->requireGrupoId())
            ->where(fn ($q) => $q
                ->where('empresa_id', $empresaId)
                ->orWhereHas('empresas', fn ($e) => $e->whereKey($empresaId)))
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q
                ->where(fn ($w) => $w->where('name', 'ilike', '%'.$b.'%')->orWhere('email', 'ilike', '%'.$b.'%')))
            ->with(['roles' => fn ($r) => $r->wherePivot('empresa_id', $empresaId)])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $usuarios->map(fn (User $u) => $this->serializar($u))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'usuario.create');
        $dados = $this->validar($request);

        $usuario = User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'password' => Hash::make($dados['password']),
            'empresa_id' => $this->tenant->requireEmpresaId(),
            'grupo_id' => $this->tenant->requireGrupoId(),
            'ativo' => $dados['ativo'] ?? true,
            'support' => false, // nunca por aqui
        ]);

        if (array_key_exists('papeis', $dados)) {
            $this->sincronizarPapeis($usuario, $dados['papeis']);
        }

        return response()->json(['data' => $this->serializar($usuario->fresh(['roles']))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'usuario.edit');
        $usuario = $this->doTenant($id);
        $dados = $this->validar($request, $usuario);

        // Auto-lockout: o ator não pode inativar a si mesmo.
        if ((int) $usuario->id === (int) $request->user()->id && array_key_exists('ativo', $dados) && ! $dados['ativo']) {
            abort(422, 'Você não pode inativar o próprio usuário.');
        }

        $usuario->update([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'ativo' => $dados['ativo'] ?? $usuario->ativo,
        ]);

        if (array_key_exists('papeis', $dados)) {
            $this->sincronizarPapeis($usuario, $dados['papeis']);
        }

        return response()->json(['data' => $this->serializar($usuario->fresh(['roles']))]);
    }

    /** Reset de senha (gera ou define). Permissão dedicada usuario.reset. */
    public function resetarSenha(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'usuario.reset');
        $usuario = $this->doTenant($id);

        $dados = $request->validate(['password' => 'required|string|min:8|confirmed']);
        $usuario->update(['password' => Hash::make($dados['password'])]);

        // Revoga tokens de API existentes (força novo login nos apps).
        $usuario->tokens()->delete();

        return response()->json(['message' => 'Senha redefinida.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'usuario.delete');
        $usuario = $this->doTenant($id);

        abort_if((int) $usuario->id === (int) $request->user()->id, 422, 'Você não pode excluir o próprio usuário.');
        abort_if((bool) $usuario->support, 422, 'Usuário de suporte não pode ser excluído por aqui.');

        $usuario->roles()->detach();
        $usuario->empresas()->detach();
        $usuario->delete();

        return response()->json(['message' => 'Usuário excluído.']);
    }

    /** Garante que o usuário pertence ao grupo ativo (isolamento de tenant). */
    private function doTenant(int $id): User
    {
        return User::query()
            ->where('grupo_id', $this->tenant->requireGrupoId())
            ->findOrFail($id);
    }

    /**
     * Atribui papéis ao usuário NA empresa ativa. Aceita só papéis do grupo ativo.
     *
     * @param  list<int>  $papelIds
     */
    private function sincronizarPapeis(User $usuario, array $papelIds): void
    {
        $empresaId = $this->tenant->requireEmpresaId();
        $grupoId = $this->tenant->requireGrupoId();

        // Só papéis do grupo ativo (rejeita ids de outra rede).
        $validos = Role::query()
            ->where('grupo_id', $grupoId)
            ->whereIn('id', array_values(array_unique($papelIds)))
            ->pluck('id')
            ->all();

        // Remove apenas as atribuições DESTA empresa e recria (não toca papéis de
        // outras empresas do mesmo usuário).
        $usuario->roles()->wherePivot('empresa_id', $empresaId)->detach();
        foreach ($validos as $roleId) {
            $usuario->roles()->attach($roleId, ['empresa_id' => $empresaId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?User $usuario = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'password' => $usuario ? 'prohibited' : 'required|string|min:8|confirmed',
            'ativo' => 'sometimes|boolean',
            'papeis' => 'sometimes|array',
            'papeis.*' => 'integer',
        ]);
    }

    /** @return array<string, mixed> */
    private function serializar(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'ativo' => (bool) $usuario->ativo,
            'support' => (bool) $usuario->support,
            'papeis' => $usuario->roles->map(fn (Role $r) => ['id' => $r->id, 'nome' => $r->nome])->values()->all(),
        ];
    }
}
