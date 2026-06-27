<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Seguranca\PasswordPolicyService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Organizacao\Departamento;
use App\Models\Organizacao\SetorOrg;
use App\Models\Organizacao\Unidade;
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

    public function __construct(
        private TenantContext $tenant,
        private PasswordPolicyService $politicaSenha,
    ) {}

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

        $dados = $request->validate(['password' => ['required', 'string', 'confirmed', $this->politicaSenha->regra()]]);
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
     * Atribui papéis ao usuário NA empresa ativa, com ESCOPO hierárquico opcional
     * (A3). Aceita duas formas em `papeis[]`:
     *   - inteiro: só o id do papel (escopo = empresa inteira);
     *   - objeto: { id, unidade_id?, departamento_id?, setor_id?, herda_filhos? }.
     * Só papéis do grupo ativo são aceitos; nós de escopo são validados como da
     * empresa ativa (global scope garante o tenant).
     *
     * @param  list<int|array<string,mixed>>  $papeis
     */
    private function sincronizarPapeis(User $usuario, array $papeis): void
    {
        $empresaId = $this->tenant->requireEmpresaId();
        $grupoId = $this->tenant->requireGrupoId();

        // Papéis válidos do grupo (mapa id => true para filtro rápido).
        $papeisDoGrupo = Role::query()->where('grupo_id', $grupoId)->pluck('id')->flip();

        // Nós de escopo que existem na empresa ativa (validação de tenant).
        $unidadesOk = Unidade::query()->pluck('id')->flip();
        $deptosOk = Departamento::query()->pluck('id')->flip();
        $setoresOk = SetorOrg::query()->pluck('id')->flip();

        // Recria apenas as atribuições DESTA empresa (não toca outras empresas).
        $usuario->roles()->wherePivot('empresa_id', $empresaId)->detach();

        foreach ($papeis as $item) {
            $roleId = is_array($item) ? (int) ($item['id'] ?? 0) : (int) $item;
            if (! $papeisDoGrupo->has($roleId)) {
                continue;
            }

            $esc = is_array($item) ? $item : [];
            $unidadeId = isset($esc['unidade_id']) && $unidadesOk->has((int) $esc['unidade_id']) ? (int) $esc['unidade_id'] : null;
            $deptoId = isset($esc['departamento_id']) && $deptosOk->has((int) $esc['departamento_id']) ? (int) $esc['departamento_id'] : null;
            $setorId = isset($esc['setor_id']) && $setoresOk->has((int) $esc['setor_id']) ? (int) $esc['setor_id'] : null;

            $usuario->roles()->attach($roleId, [
                'empresa_id' => $empresaId,
                'unidade_id' => $unidadeId,
                'departamento_id' => $deptoId,
                'setor_id' => $setorId,
                'herda_filhos' => $esc['herda_filhos'] ?? true,
            ]);
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
            'password' => $usuario ? 'prohibited' : ['required', 'string', 'confirmed', $this->politicaSenha->regra()],
            'ativo' => 'sometimes|boolean',
            // Aceita inteiro (id) OU objeto com escopo (A3). Normalizado em sincronizarPapeis.
            'papeis' => 'sometimes|array',
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
            'papeis' => $usuario->roles->map(fn (Role $r) => [
                'id' => $r->id,
                'nome' => $r->nome,
                // Escopo hierárquico da atribuição (A3) — null = empresa inteira.
                'unidade_id' => $r->pivot->unidade_id,
                'departamento_id' => $r->pivot->departamento_id,
                'setor_id' => $r->pivot->setor_id,
                'herda_filhos' => (bool) $r->pivot->herda_filhos,
            ])->values()->all(),
        ];
    }
}
