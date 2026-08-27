<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Auditoria\CatalogoAuditoria;
use App\Domain\Auditoria\ConsultaTrilha;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cliente\Cliente;
use App\Models\LoginLog;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Auditoria de segurança (A6) — leitura da trilha de eventos sensíveis e do
 * histórico de login. Gated por `auditoria.view`. Escopo por empresa (RLS +
 * filtro explícito por empresa ativa, defense-in-depth).
 */
class AuditoriaController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private CamposPermitidos $campos,
        private TenantContext $tenant,
    ) {}

    /** Eventos de segurança (papel/usuário/2fa/403…), paginados e filtráveis. */
    public function eventos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'auditoria.view');
        $empresaId = $this->tenant->requireEmpresaId();

        $eventos = SecurityEvent::query()
            ->where('empresa_id', $empresaId)
            ->with('user:id,name')
            ->when($request->query('tipo'), fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->query('inicio'), fn ($q, $i) => $q->where('criado_em', '>=', $i.' 00:00:00'))
            ->when($request->query('fim'), fn ($q, $f) => $q->where('criado_em', '<=', $f.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($eventos->items())->map(fn (SecurityEvent $e) => [
                'id' => $e->id,
                'tipo' => $e->tipo,
                'alvo' => $e->alvo,
                'detalhes' => $e->detalhes,
                'autor' => $e->user?->name,
                'ip' => $e->ip,
                'criado_em' => $e->criado_em,
            ]),
            'meta' => [
                'current_page' => $eventos->currentPage(),
                'last_page' => $eventos->lastPage(),
                'total' => $eventos->total(),
            ],
        ]);
    }

    /** Histórico de login (tentativas), da empresa ativa. */
    public function logins(Request $request): JsonResponse
    {
        $this->autorizar($request, 'auditoria.view');

        $empresaId = $this->tenant->requireEmpresaId();

        $logs = LoginLog::query()
            // Contenção F0-04/A-12.17: evento pré-login sem owner não pertence a
            // tenant algum. Ele fica restrito à futura auditoria de plataforma.
            ->where('empresa_id', $empresaId)
            ->when($request->boolean('apenas_falhas'), fn ($q) => $q->where('sucesso', false))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($logs->items())->map(fn (LoginLog $l) => [
                'id' => $l->id,
                'email' => $l->email,
                'sucesso' => $l->sucesso,
                'motivo' => $l->motivo,
                'ip' => $l->ip,
                'criado_em' => $l->criado_em,
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * GET /auditoria/trilha — LINHA DO TEMPO GERAL das ações do sistema.
     *
     * É a resposta a "quem tomou esta decisão". Filtros: entidade, ação, autor,
     * período; `cliente_id` recorta a trilha de um cliente específico.
     */
    public function trilha(Request $request, ConsultaTrilha $trilha): JsonResponse
    {
        $this->autorizar($request, 'auditoria.view');

        $filtros = $request->validate([
            'entidade' => 'nullable|string|max:80',
            'entidade_id' => 'nullable|integer',
            'acao' => 'nullable|string|max:30',
            'user_id' => 'nullable|integer',
            'inicio' => 'nullable|date',
            'fim' => 'nullable|date',
            'apenas_sensiveis' => 'nullable|boolean',
            'cliente_id' => 'nullable|integer',
        ]);

        // Busca por cliente: atalho para (entidade=clientes + id), que e o
        // recorte mais pedido — "o que aconteceu com este cliente".
        if ($clienteId = $filtros['cliente_id'] ?? null) {
            $filtros['entidade'] = 'clientes';
            $filtros['entidade_id'] = $clienteId;
        }
        unset($filtros['cliente_id']);

        $pagina = $trilha->geral($this->tenant->requireEmpresaId(), $filtros);
        $mostrarCusto = $this->campos->pode($request->user(), 'produto', 'custo', 'view');

        return response()->json([
            'data' => collect($pagina->items())
                ->map(fn (AuditLog $l) => $trilha->apresentar($l, $mostrarCusto))->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    /**
     * GET /auditoria/registro/{entidade}/{id} — trilha de UM registro, com o
     * resumo por tipo de ação que a tela usa para agrupar a linha do tempo.
     */
    public function registro(Request $request, string $entidade, int $id, ConsultaTrilha $trilha): JsonResponse
    {
        $this->autorizar($request, 'auditoria.view');

        // Só entidades do catálogo: sem isto, o parâmetro de rota viraria um
        // seletor livre de tabela vindo do cliente.
        abort_unless(array_key_exists($entidade, CatalogoAuditoria::ENTIDADES), 404);

        $empresaId = $this->tenant->requireEmpresaId();
        $pagina = $trilha->doRegistro($empresaId, $entidade, $id);
        $mostrarCusto = $this->campos->pode($request->user(), 'produto', 'custo', 'view');

        return response()->json([
            'data' => collect($pagina->items())
                ->map(fn (AuditLog $l) => $trilha->apresentar($l, $mostrarCusto))->values(),
            'resumo' => $trilha->resumoPorAcao($empresaId, $entidade, $id),
            'entidade_rotulo' => CatalogoAuditoria::rotuloEntidade($entidade),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    /**
     * GET /auditoria/opcoes — alimenta os selects da tela com entidades, ações
     * e autores que REALMENTE aparecem na trilha desta empresa.
     *
     * Listar o catálogo inteiro ofereceria filtros que não devolvem nada.
     */
    public function opcoes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'auditoria.view');
        $empresaId = $this->tenant->requireEmpresaId();

        $entidades = AuditLog::query()->where('empresa_id', $empresaId)
            ->distinct()->orderBy('entidade')->pluck('entidade')
            ->map(fn (string $e) => ['valor' => $e, 'rotulo' => CatalogoAuditoria::rotuloEntidade($e)]);

        $acoes = AuditLog::query()->where('empresa_id', $empresaId)
            ->distinct()->orderBy('acao')->pluck('acao')
            ->map(fn (string $a) => [
                'valor' => $a,
                'rotulo' => CatalogoAuditoria::rotuloAcao($a),
                'sensivel' => CatalogoAuditoria::acaoSensivel($a),
            ]);

        $autores = User::query()
            ->whereIn('id', AuditLog::query()->where('empresa_id', $empresaId)
                ->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['valor' => $u->id, 'rotulo' => $u->name]);

        return response()->json(['data' => [
            'entidades' => $entidades,
            'acoes' => $acoes,
            'autores' => $autores,
        ]]);
    }

    /**
     * GET /auditoria/clientes?q= — busca de cliente para o filtro da tela.
     *
     * Endpoint próprio (em vez de reusar /clientes) porque aqui a busca precisa
     * alcançar TAMBÉM o cliente desativado — que é justamente sobre quem mais
     * se pergunta "quem desativou, quando e por quê".
     */
    public function buscarClientes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'auditoria.view');
        $q = trim((string) $request->query('q', ''));
        $empresaId = $this->tenant->requireEmpresaId();

        // `ilike` é do Postgres (produção) e o sqlite da suíte não o conhece;
        // no sqlite, LIKE já é case-insensitive para ASCII.
        $like = Cliente::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $clientes = Cliente::query()
            ->where('empresa_id', $empresaId)
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('nome', $like, '%'.$q.'%')
                ->orWhere('fantasia', $like, '%'.$q.'%')
                ->orWhere('cpf', $like, '%'.$q.'%')
                ->orWhere('cnpj', $like, '%'.$q.'%')))
            ->orderBy('nome')
            ->limit(20)
            ->get(['id', 'nome', 'fantasia', 'cpf', 'cnpj', 'ativo']);

        return response()->json(['data' => $clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'nome' => $c->nome,
            'documento' => $c->cpf ?: $c->cnpj,
            'ativo' => (bool) $c->ativo,
        ])]);
    }
}
