<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Estoquesetor;
use App\Estoquesetorhistorico;
use App\Estoquetransferencia;
use App\Estoquetransferenciaitem;
use App\Estoquerequisicao;
use App\Estoquerequisicaoitem;
use App\Estoquefisico;
use App\Estoquefisicosetor;
use App\Estoquefechamento;
use App\Inventario;
use App\Inventarioitems;
use App\Processors\EstoqueProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * F5 (SPA React) — API admin de ESTOQUE.
 * Auditado: EstoqueProcessor (motor, baseline em EstoqueProcessorBaselineTest) +
 * controllers Transferencias/Requisicao/Setoracerto/Inventario/Estoquefisico/Fechamento.
 *
 * O MOTOR é preservado (não reescrito). Ele depende de Session('empresa_padrao');
 * como a API admin é stateless, populamos a sessão a partir do usuário antes de
 * chamar o motor (comSessao). 6 fluxos: saldos, transferência, requisição, acerto,
 * inventário, físico (efetivar), fechamento/abertura. RBAC por módulo.
 */
class EstoqueController extends Controller
{
    private function grupoId(Request $request): int
    {
        $u = $request->user();
        return (int) (optional($u->empresa)->grupo_id ?? $u->grupo_id);
    }

    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless($request->user()->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    /** Popula Session('empresa_padrao') a partir do user (o motor legado depende disso). */
    private function comSessao(Request $request, callable $fn)
    {
        Session::put('empresa_padrao', \App\Empresa::find($request->user()->empresa_id));
        return $fn();
    }

    private function hist(Request $request, int $setorId, int $produtoId, string $mov, $qtde, string $motivo, string $entidade, $entidadeId): Estoquesetorhistorico
    {
        $h = new Estoquesetorhistorico();
        $h->user_id = $request->user()->id;
        $h->setor_id = $setorId;
        $h->produto_id = $produtoId;
        $h->movimentacao = $mov;
        $h->quantidade = $qtde;
        $h->motivo = $motivo;
        $h->datahora = now();
        $h->datahoracompetencia = now();
        $h->entidade = $entidade;
        $h->entidade_id = $entidadeId;
        $h->grupo_id = $this->grupoId($request);
        $h->empresa_id = (int) $request->user()->empresa_id;
        return $h;
    }

    // =================== SALDOS ===================

    /** GET /api/admin/estoque/saldos?setor_id=&produto_id=&q= */
    public function saldos(Request $request)
    {
        $this->autorizar($request, 'estoquesetor', 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $setorId = (int) $request->query('setor_id', 0);
        $produtoId = (int) $request->query('produto_id', 0);
        $q = trim((string) $request->query('q', ''));

        $rows = Estoquesetor::query()
            ->join('produtos', 'estoquesetors.produto_id', '=', 'produtos.id')
            ->join('setors', 'estoquesetors.setor_id', '=', 'setors.id')
            ->where('estoquesetors.empresa_id', $empresaId)
            ->when($setorId, fn ($w) => $w->where('estoquesetors.setor_id', $setorId))
            ->when($produtoId, fn ($w) => $w->where('estoquesetors.produto_id', $produtoId))
            ->when($q !== '', fn ($w) => $w->where('produtos.descricao', 'ilike', '%' . $q . '%'))
            ->orderBy('setors.descricao')->orderBy('produtos.descricao')
            ->limit(500)
            ->get([
                'estoquesetors.id', 'estoquesetors.setor_id', 'estoquesetors.produto_id',
                'estoquesetors.quantidade', 'estoquesetors.quantidademinima', 'estoquesetors.quantidademaxima',
                'setors.descricao as setor', 'produtos.descricao as produto',
            ]);

        return response()->json(['data' => $rows]);
    }

    // =================== ACERTO / AJUSTE ===================

    /** POST /api/admin/estoque/acerto — ajusta saldo de um produto num setor (via motor). */
    public function acerto(Request $request)
    {
        $this->autorizar($request, 'estoquesetoracerto', 'create');
        $data = $request->validate([
            'setor_id'    => 'required|integer',
            'produto_id'  => 'required|integer',
            'movimentacao' => 'required|in:ENTRADA,SAIDA',
            'quantidade'  => 'required|numeric|min:0.0001',
            'observacao'  => 'required|string|max:255',
        ]);

        $erro = $this->comSessao($request, function () use ($request, $data) {
            $proc = new EstoqueProcessor();
            $h = $this->hist($request, $data['setor_id'], $data['produto_id'], $data['movimentacao'], $data['quantidade'], 'Acerto: ' . $data['observacao'], 'Estoquesetoracerto', 0);
            return $proc->movimentarEstoque([$h]) ? null : implode(' ', $proc->getErrors());
        });

        if ($erro) {
            return response()->json(['message' => $erro], 422);
        }
        return response()->json(['message' => 'Acerto realizado.']);
    }

    // =================== TRANSFERÊNCIA ===================

    /** GET /api/admin/estoque/transferencias */
    public function transferencias(Request $request)
    {
        $this->autorizar($request, 'estoquetransferencias', 'view');
        $rows = Estoquetransferencia::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->orderByDesc('datahora')->limit(200)
            ->get(['id', 'origemsetor_id', 'destinosetor_id', 'datahora', 'observacoes']);
        return response()->json(['data' => $rows]);
    }

    /** POST /api/admin/estoque/transferencias — cabeçalho + itens; gera SAÍDA origem + ENTRADA destino. */
    public function criarTransferencia(Request $request)
    {
        $this->autorizar($request, 'estoquetransferencias', 'create');
        $data = $request->validate([
            'origemsetor_id'  => 'required|integer|different:destinosetor_id',
            'destinosetor_id' => 'required|integer',
            'observacoes'     => 'nullable|string|max:255',
            'itens'           => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'required|numeric|min:0.0001',
        ]);

        $erro = $this->comSessao($request, function () use ($request, $data) {
            return DB::transaction(function () use ($request, $data) {
                $transf = Estoquetransferencia::create([
                    'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
                    'user_id' => $request->user()->id, 'origemsetor_id' => $data['origemsetor_id'],
                    'destinosetor_id' => $data['destinosetor_id'], 'datahora' => now(), 'datahoracompetencia' => now(),
                    'observacoes' => $data['observacoes'] ?? '',
                ]);
                $proc = new EstoqueProcessor();
                foreach ($data['itens'] as $item) {
                    Estoquetransferenciaitem::create(['estoquetransferencia_id' => $transf->id, 'produto_id' => $item['produto_id'], 'quantidade' => $item['quantidade']]);
                    $movs = [
                        $this->hist($request, $data['origemsetor_id'], $item['produto_id'], 'SAIDA', $item['quantidade'], 'Transferência: ' . ($data['observacoes'] ?? ''), 'Estoquetransferencia', $transf->id),
                        $this->hist($request, $data['destinosetor_id'], $item['produto_id'], 'ENTRADA', $item['quantidade'], 'Transferência: ' . ($data['observacoes'] ?? ''), 'Estoquetransferencia', $transf->id),
                    ];
                    if (! $proc->movimentarEstoque($movs)) {
                        throw new \RuntimeException(implode(' ', $proc->getErrors()));
                    }
                }
                return null;
            });
        });

        return $erro
            ? response()->json(['message' => $erro], 422)
            : response()->json(['message' => 'Transferência realizada.'], 201);
    }

    // =================== REQUISIÇÃO ===================

    /** GET /api/admin/estoque/requisicoes */
    public function requisicoes(Request $request)
    {
        $this->autorizar($request, 'estoquerequisicao', 'view');
        $rows = Estoquerequisicao::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->orderByDesc('datahora')->limit(200)
            ->get(['id', 'datahora', 'cancelado', 'observacoes']);
        return response()->json(['data' => $rows]);
    }

    /** POST /api/admin/estoque/requisicoes — cabeçalho + itens (entrada/saída por setor, via motor). */
    public function criarRequisicao(Request $request)
    {
        $this->autorizar($request, 'estoquerequisicao', 'create');
        $data = $request->validate([
            'observacoes' => 'nullable|string|max:255',
            'itens'       => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.setor_id'   => 'required|integer',
            'itens.*.quantidade' => 'required|numeric|min:0.0001',
            'itens.*.entradasaida' => 'required|in:ENTRADA,SAIDA',
        ]);

        $erro = $this->comSessao($request, function () use ($request, $data) {
            return DB::transaction(function () use ($request, $data) {
                $req = Estoquerequisicao::create([
                    'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
                    'user_id' => $request->user()->id, 'datahora' => now(), 'cancelado' => 0, 'observacoes' => $data['observacoes'] ?? '',
                ]);
                $proc = new EstoqueProcessor();
                foreach ($data['itens'] as $item) {
                    // A coluna entradasaida é varchar(1): grava E/S; o motor usa ENTRADA/SAIDA.
                    Estoquerequisicaoitem::create([
                        'estoquerequisicao_id' => $req->id, 'produto_id' => $item['produto_id'], 'setor_id' => $item['setor_id'],
                        'quantidade' => $item['quantidade'], 'entradasaida' => $item['entradasaida'] === 'SAIDA' ? 'S' : 'E', 'customedio' => 0,
                    ]);
                    $h = $this->hist($request, $item['setor_id'], $item['produto_id'], $item['entradasaida'], $item['quantidade'], 'Requisição: ' . ($data['observacoes'] ?? ''), 'Estoquerequisicao', $req->id);
                    if (! $proc->movimentarEstoque([$h])) {
                        throw new \RuntimeException(implode(' ', $proc->getErrors()));
                    }
                }
                return null;
            });
        });

        return $erro
            ? response()->json(['message' => $erro], 422)
            : response()->json(['message' => 'Requisição registrada.'], 201);
    }

    // =================== INVENTÁRIO (SPED) ===================

    /** GET /api/admin/estoque/inventarios */
    public function inventarios(Request $request)
    {
        $this->autorizar($request, 'inventario', 'view');
        $rows = Inventario::query()->where('empresa_id', $request->user()->empresa_id)
            ->orderByDesc('datainventario')->limit(200)
            ->get(['id', 'datainventario', 'mesentrega', 'valorinventario']);
        return response()->json(['data' => $rows]);
    }

    /** POST /api/admin/estoque/inventarios — documento de inventário (valoração) + itens. */
    public function criarInventario(Request $request)
    {
        $this->autorizar($request, 'inventario', 'create');
        $data = $request->validate([
            'datainventario' => 'required|date',
            'mesentrega'     => 'required|date',
            'itens'          => 'required|array|min:1',
            'itens.*.produto_id'   => 'required|integer',
            'itens.*.quantidade'   => 'required|numeric',
            'itens.*.valorunitario' => 'required|numeric',
        ]);

        $total = collect($data['itens'])->sum(fn ($i) => $i['quantidade'] * $i['valorunitario']);
        $inv = DB::transaction(function () use ($request, $data, $total) {
            $inv = Inventario::create([
                'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
                'datainventario' => $data['datainventario'], 'mesentrega' => $data['mesentrega'], 'valorinventario' => $total,
            ]);
            Inventarioitems::insert(array_map(fn ($i) => [
                'inventario_id' => $inv->id, 'produto_id' => $i['produto_id'], 'quantidade' => $i['quantidade'],
                'valorunitario' => $i['valorunitario'], 'created_at' => now(), 'updated_at' => now(),
            ], $data['itens']));
            return $inv;
        });
        return response()->json(['data' => ['id' => $inv->id, 'valorinventario' => $total]], 201);
    }

    // =================== ESTOQUE FÍSICO ===================

    /** GET /api/admin/estoque/fisico */
    public function fisicos(Request $request)
    {
        $this->autorizar($request, 'estoquefisico', 'view');
        $rows = Estoquefisico::query()->where('empresa_id', $request->user()->empresa_id)
            ->orderByDesc('datacompetencia')->limit(200)
            ->get(['id', 'datacompetencia', 'efetivado']);
        return response()->json(['data' => $rows]);
    }

    /** POST /api/admin/estoque/fisico — registra contagem (sem efetivar). */
    public function criarFisico(Request $request)
    {
        $this->autorizar($request, 'estoquefisico', 'create');
        $data = $request->validate([
            'datacompetencia' => 'required|date',
            'itens'           => 'required|array|min:1',
            'itens.*.setor_id'   => 'required|integer',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidadesistema' => 'required|numeric',
            'itens.*.quantidadefisica'  => 'required|numeric',
        ]);

        $fis = DB::transaction(function () use ($request, $data) {
            $fis = Estoquefisico::create([
                'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
                'user_id' => $request->user()->id, 'datacompetencia' => $data['datacompetencia'], 'efetivado' => 0,
            ]);
            foreach ($data['itens'] as $i) {
                Estoquefisicosetor::create([
                    'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
                    'setor_id' => $i['setor_id'], 'produto_id' => $i['produto_id'], 'estoquefisico_id' => $fis->id,
                    'quantidadesistema' => $i['quantidadesistema'], 'quantidadefisica' => $i['quantidadefisica'],
                    'quantidadediferenca' => abs($i['quantidadefisica'] - $i['quantidadesistema']),
                    'estoquezerar' => 0, 'estoqueremover' => 0,
                ]);
            }
            return $fis;
        });
        return response()->json(['data' => ['id' => $fis->id]], 201);
    }

    /** POST /api/admin/estoque/fisico/{id}/efetivar — aplica a diferença ao saldo (via motor). */
    public function efetivarFisico(Request $request, $id)
    {
        $this->autorizar($request, 'estoquefisico', 'edit');
        $fisico = Estoquefisico::where('empresa_id', $request->user()->empresa_id)->findOrFail($id);

        $erro = $this->comSessao($request, function () use ($fisico) {
            $proc = new EstoqueProcessor();
            return $proc->efetivarEstoquefisico($fisico) ? null : implode(' ', $proc->getErrors());
        });
        return $erro
            ? response()->json(['message' => $erro], 422)
            : response()->json(['message' => 'Estoque físico efetivado.']);
    }

    // =================== FECHAMENTO / ABERTURA ===================

    /** GET /api/admin/estoque/fechamentos */
    public function fechamentos(Request $request)
    {
        $this->autorizar($request, 'consultaestoquesetor', 'view');
        $rows = Estoquefechamento::query()->where('empresa_id', $request->user()->empresa_id)
            ->orderByDesc('datahorafechamento')->limit(200)
            ->get(['id', 'datahorafechamento', 'reaberto', 'reabertomotivo']);
        return response()->json(['data' => $rows]);
    }

    /** POST /api/admin/estoque/fechamentos — fecha o estoque até a data (via motor). */
    public function fechar(Request $request)
    {
        $this->autorizar($request, 'consultaestoquesetor', 'create');
        $data = $request->validate(['datahorafechamento' => 'required|date']);

        $erro = $this->comSessao($request, function () use ($request, $data) {
            $fech = new Estoquefechamento();
            $fech->grupo_id = $this->grupoId($request);
            $fech->empresa_id = (int) $request->user()->empresa_id;
            $fech->datahorafechamento = $data['datahorafechamento'];
            $fech->reaberto = 0;
            $fech->fechamentomensal = 0;
            $proc = new EstoqueProcessor();
            return $proc->fecharEstoque($fech) ? null : implode(' ', $proc->getErrors());
        });
        return $erro
            ? response()->json(['message' => $erro], 422)
            : response()->json(['message' => 'Estoque fechado.'], 201);
    }

    /** POST /api/admin/estoque/fechamentos/abrir — reabre o estoque a partir da data (via motor). */
    public function abrir(Request $request)
    {
        $this->autorizar($request, 'consultaestoquesetor', 'edit');
        $data = $request->validate([
            'datahorafechamento' => 'required|date',
            'motivo'             => 'required|string|max:255',
        ]);

        $erro = $this->comSessao($request, function () use ($request, $data) {
            $fech = new Estoquefechamento();
            $fech->empresa_id = (int) $request->user()->empresa_id;
            $fech->datahorafechamento = $data['datahorafechamento'];
            $fech->reabertouser_id = $request->user()->id;
            $fech->reabertomotivo = $data['motivo'];
            $proc = new EstoqueProcessor();
            return $proc->abrirEstoque($fech) ? null : implode(' ', $proc->getErrors());
        });
        return $erro
            ? response()->json(['message' => $erro], 422)
            : response()->json(['message' => 'Estoque reaberto.']);
    }
}
