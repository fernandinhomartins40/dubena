<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Rh\ColaboradorService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Estoque\Setor;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Models\Produto\Produto;
use App\Models\Rh\Cargo;
use App\Models\Rh\Colaborador;
use App\Rules\ExisteNoTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Colaboradores (RH) — C5. CRUD escopado por empresa + sub-recursos
 * (família, recessos, comissões). Permissão 'colaborador.*'.
 */
class ColaboradorController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private ColaboradorService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');
        $q = trim((string) $request->query('q', ''));

        $page = Colaborador::query()
            ->with(['cargo:id,descricao', 'desativadoPor:id,name'])
            ->tap(fn (Builder $b) => $this->filtrarSituacao($b, $request))
            ->when($q !== '', fn (Builder $b) => $b->where('nome', 'ilike', '%'.$q.'%'))
            ->orderBy('nome')
            ->paginate(20);

        // Envelope {data, meta} — a SPA lê data.meta.total/current_page/last_page
        // (mesmo contrato dos demais lists, ex.: ClienteResource::collection).
        return response()->json([
            'data' => $page->getCollection()->map(fn (Colaborador $c) => $this->linha($c))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');
        $c = Colaborador::query()
            ->with(['cargo:id,descricao', 'cidade:id,descricao,uf', 'bairro:id,descricao', 'rua:id,descricao'])
            ->findOrFail($id);

        return response()->json(['data' => array_merge($this->linha($c), [
            'cpf' => $c->cpf, 'rg' => $c->rg, 'telefone' => $c->telefone,
            'cargo_id' => $c->cargo_id, 'entregador' => $c->entregador,
            'data_nascimento' => $c->data_nascimento?->toDateString(),
            // O formulário lê os nomes sem underscore (herdados do legado):
            // devolvê-los é o que faz o campo aparecer preenchido na edição.
            'datanascimento' => $c->data_nascimento?->toDateString(),
            'datadesligamento' => $c->data_desligamento?->toDateString(),
            'ativo' => (bool) $c->ativo,
            // Endereço + rótulos das FKs (o AsyncSelect só busca a lista ao abrir).
            'cep' => $c->cep, 'uf' => $c->uf, 'numero' => $c->numero,
            'complemento' => $c->complemento,
            'cidade_id' => $c->cidade_id, 'bairro_id' => $c->bairro_id, 'rua_id' => $c->rua_id,
            'cidade_label' => $c->cidade?->descricao,
            'bairro_label' => $c->bairro?->descricao,
            'rua_label' => $c->rua?->descricao,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'colaborador.create');
        $colaborador = $this->service->criar($this->validar($request));

        return response()->json(['data' => $this->linha($colaborador)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);

        return response()->json(['data' => $this->linha($this->service->atualizar($colaborador, $this->validar($request)))]);
    }

    /**
     * DELETE /colaboradores/{id} — DESATIVA (não apaga).
     *
     * Apagar era especialmente destrutivo aqui: todas as sub-tabelas de RH
     * (família, recessos, comissões, exames, turnos, ponto) são
     * cascadeOnDelete, e nenhuma FK segurava a operação.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.delete');

        $motivo = $request->validate(['motivo' => 'nullable|string|max:255'])['motivo'] ?? null;

        $colaborador = $this->service->desativar(
            Colaborador::query()->findOrFail($id), $motivo, $request->user()?->id,
        );

        return response()->json([
            'message' => 'Colaborador desativado. O histórico foi preservado.',
            'data' => $this->linha($colaborador),
        ]);
    }

    /** POST /colaboradores/{id}/reativar */
    public function reativar(Request $request, int $id): JsonResponse
    {
        // Mesma permissão de desativar: quem tira da lista pode devolver.
        $this->autorizar($request, 'colaborador.delete');

        $colaborador = $this->service->reativar(Colaborador::query()->findOrFail($id));

        return response()->json([
            'message' => 'Colaborador reativado.',
            'data' => $this->linha($colaborador),
        ]);
    }

    /** Default 'ativos' — ver ClienteController::filtrarSituacao. */
    private function filtrarSituacao(Builder $b, Request $request): void
    {
        match ((string) $request->query('situacao', 'ativos')) {
            'inativos' => $b->where('ativo', false),
            'todos' => null,
            default => $b->where(fn (Builder $w) => $w->where('ativo', true)->orWhereNull('ativo')),
        };
    }

    // ── Sub-recursos ──
    public function familia(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->familias()->get()]);
    }

    public function addFamilia(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);
        $d = $request->validate([
            'nome' => 'required|string|max:255',
            'parentesco' => 'nullable|string|max:40',
            'data_nascimento' => 'nullable|date',
        ]);
        $this->service->adicionarFamiliar($colaborador, $d);

        return response()->json(['message' => 'Familiar adicionado.'], 201);
    }

    public function delFamilia(Request $request, int $id, int $famId): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $this->service->removerFamiliar(Colaborador::query()->findOrFail($id), $famId);

        return response()->json(['message' => 'Familiar removido.']);
    }

    public function recessos(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->recessos()->orderByDesc('inicio')->get()]);
    }

    public function comissoes(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->comissoes()->with('excecoes')->get()]);
    }

    // ── Escrita de recessos e comissões (T4.1/T4.7) ──
    //
    // Ambos eram SÓ LEITURA no novo, enquanto o legado tem `Route::resource`
    // completo (`RecessosController`, `ColaboradorcomissoesController`). Sem
    // POST/PUT/DELETE o operador de RH não consegue lançar férias nem alterar
    // uma comissão — precisa voltar ao legado, o que inviabiliza aposentá-lo.

    public function addRecesso(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);

        return response()->json(
            ['data' => $colaborador->recessos()->create($this->validarRecesso($request))],
            201,
        );
    }

    public function updateRecesso(Request $request, int $id, int $recessoId): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);

        // findOrFail SOBRE A RELAÇÃO: garante que o recesso é deste colaborador,
        // e não de outro cujo id o cliente tenha adivinhado.
        $recesso = $colaborador->recessos()->findOrFail($recessoId);
        $recesso->update($this->validarRecesso($request));

        return response()->json(['data' => $recesso->fresh()]);
    }

    public function deleteRecesso(Request $request, int $id, int $recessoId): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        Colaborador::query()->findOrFail($id)->recessos()->findOrFail($recessoId)->delete();

        return response()->json(['data' => ['excluido' => true]]);
    }

    public function addComissao(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);

        return response()->json(
            ['data' => $colaborador->comissoes()->create($this->validarComissao($request))],
            201,
        );
    }

    public function updateComissao(Request $request, int $id, int $comissaoId): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $comissao = Colaborador::query()->findOrFail($id)->comissoes()->findOrFail($comissaoId);
        $comissao->update($this->validarComissao($request));

        return response()->json(['data' => $comissao->fresh()]);
    }

    public function deleteComissao(Request $request, int $id, int $comissaoId): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        Colaborador::query()->findOrFail($id)->comissoes()->findOrFail($comissaoId)->delete();

        return response()->json(['data' => ['excluido' => true]]);
    }

    /** @return array<string,mixed> */
    private function validarRecesso(Request $request): array
    {
        return $request->validate([
            'tipo' => 'nullable|string|max:30',
            'inicio' => 'required|date',
            // `after_or_equal` e não `after`: recesso de um dia só é legítimo.
            'fim' => 'required|date|after_or_equal:inicio',
            'observacao' => 'nullable|string|max:255',
        ]);
    }

    /** @return array<string,mixed> */
    private function validarComissao(Request $request): array
    {
        return $request->validate([
            'produto_id' => ['nullable', 'integer', new ExisteNoTenant(Produto::class)],
            'setor_id' => ['nullable', 'integer', new ExisteNoTenant(Setor::class)],
            'condicaopagamento_id' => 'nullable|integer',
            // `integer` e não string: o model faz cast para int (o legado usa um
            // código numérico de tipo de comissão).
            'tipo_comissao' => 'nullable|integer',
            // Percentual acima de 100 é quase sempre erro de digitação (5000 em
            // vez de 50,00) e sai caro na folha.
            'percentual' => 'nullable|numeric|min:0|max:100',
            'empresa_valor' => 'nullable|numeric|min:0',
            'percentual_app' => 'nullable|numeric|min:0|max:100',
            'empresa_valor_app' => 'nullable|numeric|min:0',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'ativo' => 'nullable|boolean',
        ]);
    }

    // ── Exames ocupacionais (ASO) — C5 ──
    public function exames(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->exames()->orderByDesc('realizado_em')->get()]);
    }

    public function addExame(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);
        $d = $request->validate([
            'tipo' => 'nullable|in:admissional,periodico,demissional,retorno',
            'realizado_em' => 'required|date',
            'vencimento' => 'nullable|date|after_or_equal:realizado_em',
            'resultado' => 'nullable|in:apto,inapto,apto-com-restricao',
            'medico' => 'nullable|string|max:255',
            'observacao' => 'nullable|string|max:255',
        ]);

        return response()->json(['data' => $colaborador->exames()->create($d)], 201);
    }

    // ── Turnos/escala — C5 ──
    public function turnos(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->turnos()->orderBy('dia_semana')->get()]);
    }

    public function addTurno(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);
        $d = $request->validate([
            'dia_semana' => 'required|integer|min:0|max:6',
            'entrada' => 'required|date_format:H:i',
            'saida' => 'required|date_format:H:i|after:entrada',
        ]);
        // Upsert por dia da semana (escala é única por dia).
        $turno = $colaborador->turnos()->updateOrCreate(['dia_semana' => $d['dia_semana']], $d);

        return response()->json(['data' => $turno], 201);
    }

    // ── Ponto — C5 ──
    public function pontos(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->pontos()->orderByDesc('data')->limit(60)->get()]);
    }

    public function registrarPonto(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);
        $d = $request->validate([
            'data' => 'nullable|date',
            'entrada' => 'nullable|date_format:H:i',
            'saida' => 'nullable|date_format:H:i',
        ]);
        $d['data'] ??= now()->toDateString();
        $ponto = $colaborador->pontos()->updateOrCreate(['data' => $d['data']], $d);

        return response()->json(['data' => $ponto], 201);
    }

    /** @return array<string,mixed> */
    private function linha(Colaborador $c): array
    {
        return [
            'id' => $c->id,
            'nome' => $c->nome,
            'cpf' => $c->cpf,
            'dataadmissao' => $c->data_admissao?->toDateString(),
            'datadesligamento' => $c->data_desligamento?->toDateString(),
            'cargo' => $c->cargo?->descricao,
            // O formulario de edicao le `cargo_label` para exibir o valor ja
            // escolhido: o AsyncSelect so busca a lista quando o popover abre.
            'cargo_label' => $c->cargo?->descricao,
            // Situacao do cadastro. Sem isto a lista nao tinha como distinguir
            // ativo de desativado — e o operador marcava o estado no NOME.
            'ativo' => (bool) $c->ativo,
            'desativado_em' => $c->desativado_em?->toDateTimeString(),
            'motivo_desativacao' => $c->motivo_desativacao,
            'desativado_por_nome' => $c->relationLoaded('desativadoPor') ? $c->desativadoPor?->name : null,
        ];
    }

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'nome' => 'required|string|max:255',
            'cpf' => 'nullable|string|max:11',
            'rg' => 'nullable|string|max:20',
            'cargo_id' => ['nullable', 'integer', new ExisteNoTenant(Cargo::class)],
            'data_nascimento' => 'nullable|date',
            'data_admissao' => 'nullable|date',
            'data_desligamento' => 'nullable|date',
            // A SPA envia sem underscore (o nome do legado). Sem estas regras o
            // `validate()` as descartava e as datas nunca eram gravadas.
            'datanascimento' => 'nullable|date',
            'dataadmissao' => 'nullable|date',
            'datadesligamento' => 'nullable|date',
            'telefone' => 'nullable|string|max:20',
            'entregador' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
            // Endereço: o legado sempre teve (81 colaboradores com cidade e
            // bairro) e o formulário já enviava — faltava a coluna no destino.
            'cep' => 'nullable|string|max:8',
            'uf' => 'nullable|string|max:2',
            'cidade_id' => ['nullable', 'integer', new ExisteNoTenant(Cidade::class)],
            'bairro_id' => ['nullable', 'integer', new ExisteNoTenant(Bairro::class)],
            'rua_id' => ['nullable', 'integer', new ExisteNoTenant(Rua::class)],
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:255',
        ]);

        // Normaliza o alias legado para o nome da coluna, sem sobrescrever a
        // forma canônica quando as duas vierem.
        foreach ([
            'datanascimento' => 'data_nascimento',
            'dataadmissao' => 'data_admissao',
            'datadesligamento' => 'data_desligamento',
        ] as $alias => $coluna) {
            if (array_key_exists($alias, $dados)) {
                $dados[$coluna] ??= $dados[$alias];
                unset($dados[$alias]);
            }
        }

        return $dados;
    }
}
