<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Identidade\ConsolidarClientes;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteRevisao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fila de revisão de cadastros possivelmente duplicados.
 *
 * A dúvida do motor de identidade vira trabalho de retaguarda — nunca trava de
 * balcão. Aqui uma pessoa vê os dois cadastros lado a lado, com o que casou, e
 * decide: consolidar ou marcar como pessoas diferentes.
 *
 * Gated por `cliente.edit`: quem pode editar cadastro pode resolvê-los.
 */
class ClienteRevisaoController extends Controller
{
    use AutorizaPorPermissao;

    /** GET /clientes/revisoes — a fila, do escore mais alto para o mais baixo. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');

        $situacao = (string) $request->query('situacao', 'pendente');

        $pagina = ClienteRevisao::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->when($situacao !== 'todas', fn ($q) => $q->where('situacao', $situacao))
            ->with([
                'cliente:id,nome,cpf,cnpj,email,endereco,numero,cidade_id,ativo,created_at',
                'candidato:id,nome,cpf,cnpj,email,endereco,numero,cidade_id,ativo,created_at',
                'cliente.telefones:id,cliente_id,telefone',
                'candidato.telefones:id,cliente_id,telefone',
                'decidiuUser:id,name',
            ])
            // Escore alto primeiro: são os mais prováveis e os que mais
            // rendem por minuto de atenção humana.
            ->orderByDesc('escore')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (ClienteRevisao $r) => $this->linha($r))->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'total' => $pagina->total(),
                'pendentes' => ClienteRevisao::query()
                    ->where('empresa_id', (int) $request->user()->empresa_id)
                    ->where('situacao', 'pendente')->count(),
            ],
        ]);
    }

    /**
     * POST /clientes/revisoes/{id}/consolidar — são a mesma pessoa.
     *
     * O corpo pode indicar qual dos dois SOBREVIVE (`principal_id`); sem isso,
     * vence o cadastro mais antigo, que costuma carregar o histórico.
     */
    public function consolidar(Request $request, int $id, ConsolidarClientes $consolidador): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');

        $revisao = ClienteRevisao::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);

        $d = $request->validate(['principal_id' => 'nullable|integer']);

        [$principalId, $absorvidoId] = $this->definirLados($revisao, $d['principal_id'] ?? null);

        $principal = Cliente::query()->findOrFail($principalId);
        $absorvido = Cliente::query()->findOrFail($absorvidoId);

        $consolidador->executar(
            $principal, $absorvido, (int) $revisao->escore, 'humano', (array) $revisao->tracos,
        );

        // O ConsolidarClientes já fecha as revisões do par; garante a desta.
        $revisao->forceFill([
            'situacao' => 'consolidado',
            'decidido_por_user_id' => $request->user()->id,
            'decidido_em' => now(),
        ])->save();

        return response()->json([
            'message' => 'Cadastros consolidados. O histórico foi preservado.',
            'data' => ['principal_id' => $principal->id, 'absorvido_id' => $absorvido->id],
        ]);
    }

    /**
     * POST /clientes/revisoes/{id}/descartar — são pessoas diferentes.
     *
     * Fecha o par sem tocar nos cadastros. É o caso real da família que divide
     * o telefone: nada a fazer além de registrar que já foi analisado.
     */
    public function descartar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');

        $revisao = ClienteRevisao::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);

        $d = $request->validate(['observacao' => 'nullable|string|max:255']);

        $revisao->forceFill([
            'situacao' => 'descartado',
            'decidido_por_user_id' => $request->user()->id,
            'decidido_em' => now(),
            'observacao' => $d['observacao'] ?? null,
        ])->save();

        return response()->json(['message' => 'Marcado como pessoas diferentes.']);
    }

    /**
     * Quem sobrevive e quem é absorvido.
     *
     * @return array{0: int, 1: int}
     */
    private function definirLados(ClienteRevisao $revisao, ?int $escolhido): array
    {
        $ids = [(int) $revisao->cliente_id, (int) $revisao->candidato_id];

        if ($escolhido !== null && in_array($escolhido, $ids, true)) {
            return [$escolhido, $escolhido === $ids[0] ? $ids[1] : $ids[0]];
        }

        // Default: o mais antigo vence — é quem tende a ter o histórico.
        sort($ids);

        return [$ids[0], $ids[1]];
    }

    /** @return array<string, mixed> */
    private function linha(ClienteRevisao $r): array
    {
        return [
            'id' => $r->id,
            'escore' => $r->escore,
            'confianca' => $r->escore >= 100 ? 'alta' : ($r->escore >= 50 ? 'media' : 'baixa'),
            'motivos' => $r->tracos ?? [],
            'origem' => $r->origem,
            'situacao' => $r->situacao,
            'criado_em' => $r->created_at?->toIso8601String(),
            'decidido_por' => $r->decidiuUser?->name,
            'decidido_em' => $r->decidido_em?->toIso8601String(),
            'observacao' => $r->observacao,
            'cliente' => $this->cadastro($r->cliente),
            'candidato' => $this->cadastro($r->candidato),
        ];
    }

    /** @return array<string, mixed>|null */
    private function cadastro(?Cliente $c): ?array
    {
        if ($c === null) {
            return null;
        }

        return [
            'id' => $c->id,
            'nome' => $c->nome,
            'documento' => $c->cpf ?: $c->cnpj,
            'email' => $c->email,
            'endereco' => trim(($c->endereco ?? '').' '.($c->numero ?? '')) ?: null,
            'telefones' => $c->telefones->pluck('telefone')->values(),
            'ativo' => (bool) $c->ativo,
            'criado_em' => $c->created_at?->toIso8601String(),
        ];
    }
}
