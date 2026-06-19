<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * S2 — sub-recursos do Cliente (abas da ficha, SPEC_CLIENTE):
 * Interações (CRM), Histórico de pedidos, Convênio (empresa) + dependentes, Preços.
 * Escopo por empresa + RBAC cliente.*.
 */
class ClienteSubController extends Controller
{
    private function cliente(Request $request, $id): Cliente
    {
        $this->autorizar($request, 'cliente.view');
        $user = $request->user();
        $q = Cliente::query();
        if ((string) $user->support !== '1') {
            $q->where('empresa_id', $user->empresa_id);
        }
        return $q->findOrFail($id);
    }

    private function autorizar(Request $request, string $permissao): void
    {
        $user = $request->user();
        [$modulo, $acao] = explode('.', $permissao);
        abort_unless($user->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    // ---------- INTERAÇÕES (CRM) — clientecontatos ----------
    public function interacoes(Request $request, $id)
    {
        $c = $this->cliente($request, $id);
        $rows = DB::table('clientecontatos as cc')
            ->leftJoin('clientecontatotipos as t', 't.id', '=', 'cc.tipo_id')
            ->leftJoin('clientecontatosituacaos as s', 's.id', '=', 'cc.situacao_id')
            ->where('cc.cliente_id', $c->id)
            ->orderByDesc('cc.datahora')
            ->get(['cc.id', 'cc.datahora', 'cc.descricao', 'cc.acao', 'cc.tipo_id', 't.descricao as tipo', 'cc.situacao_id', 's.descricao as situacao']);
        return response()->json(['data' => $rows]);
    }

    public function addInteracao(Request $request, $id)
    {
        $this->autorizar($request, 'cliente.edit');
        $c = $this->cliente($request, $id);
        $data = $request->validate([
            'tipo_id'     => 'required|integer',
            'situacao_id' => 'required|integer',
            'descricao'   => 'required|string',
            'acao'        => 'nullable|string',
        ]);
        $novo = DB::table('clientecontatos')->insertGetId([
            'cliente_id' => $c->id, 'grupo_id' => $c->grupo_id, 'empresa_id' => $c->empresa_id,
            'responsavel_id' => $request->user()->id,
            'tipo_id' => $data['tipo_id'], 'situacao_id' => $data['situacao_id'],
            'descricao' => $data['descricao'], 'acao' => $data['acao'] ?? null,
            'datahora' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['id' => $novo], 201);
    }

    public function delInteracao(Request $request, $id, $subId)
    {
        $this->autorizar($request, 'cliente.edit');
        $c = $this->cliente($request, $id);
        DB::table('clientecontatos')->where('cliente_id', $c->id)->where('id', $subId)->delete();
        return response()->json(['message' => 'Interação removida.']);
    }

    // ---------- HISTÓRICO de pedidos ----------
    public function historico(Request $request, $id)
    {
        $c = $this->cliente($request, $id);
        $rows = DB::table('pedidos as p')
            ->where('p.cliente_id', $c->id)
            ->orderByDesc('p.datahora')
            ->limit(100)
            ->get(['p.id', 'p.datahora', 'p.valorvenda']);
        return response()->json(['data' => $rows]);
    }

    // ---------- CONVÊNIO (empresa conveniada) — clienteconvenios ----------
    public function convenio(Request $request, $id)
    {
        $c = $this->cliente($request, $id);
        $conv = DB::table('clienteconvenios')->where('cliente_id', $c->id)->first();
        $deps = DB::table('clienteconveniodependentes as d')
            ->leftJoin('parentescos as p', 'p.id', '=', 'd.parentesco_id')
            ->where('d.cliente_id', $c->id)
            ->get(['d.id', 'd.nome', 'd.parentesco_id', 'p.descricao as parentesco', 'd.ativo']);
        return response()->json([
            'convenioativo' => (int) $c->convenioativo,
            'convenio'      => $conv,
            'dependentes'   => $deps,
            // colaborador de convênio (este cliente é conveniado de outra empresa)
            'conveniado'    => (int) $c->convenio,
            'convenio_id'   => $c->convenio_id,
            'conveniolimite'=> $c->conveniolimite,
            'codigo_convenio'=> $c->codigo_convenio,
        ]);
    }

    public function salvarConvenio(Request $request, $id)
    {
        $this->autorizar($request, 'cliente.edit');
        $c = $this->cliente($request, $id);
        $data = $request->validate([
            'convenioativo'  => 'nullable|boolean',
            'datacontrato'   => 'nullable|date',
            'limitecompra'   => 'nullable|numeric',
            'comissao'       => 'nullable|numeric',
            'comissaodestino'=> 'nullable|integer',
            'diafechamento'  => 'nullable|integer|min:0|max:31',
            'diavencimento'  => 'nullable|integer|min:0|max:31',
            'nomerepresentante' => 'nullable|string|max:255',
            'cpfrepresentante'  => 'nullable|string|max:14',
            'rgrepresentante'   => 'nullable|string|max:20',
        ]);

        // Colunas NOT NULL de clienteconvenios — default 0 se não enviadas.
        $valores = array_merge(
            ['diafechamento' => 0, 'diavencimento' => 0, 'comissao' => 0, 'limitecompra' => 0],
            collect($data)->except('convenioativo')->filter(fn ($v) => $v !== null && $v !== '')->toArray(),
            ['updated_at' => now()]
        );

        DB::transaction(function () use ($c, $data, $valores) {
            $c->update(['convenioativo' => ! empty($data['convenioativo']) ? 1 : 0]);
            DB::table('clienteconvenios')->updateOrInsert(['cliente_id' => $c->id], $valores);
        });
        return response()->json(['message' => 'Convênio salvo.']);
    }

    public function addDependente(Request $request, $id)
    {
        $this->autorizar($request, 'cliente.edit');
        $c = $this->cliente($request, $id);
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'parentesco_id' => 'required|integer',
            'ativo' => 'nullable|boolean',
        ]);
        $novo = DB::table('clienteconveniodependentes')->insertGetId([
            'cliente_id' => $c->id, 'nome' => $data['nome'], 'parentesco_id' => $data['parentesco_id'],
            'ativo' => ! empty($data['ativo']) ? 1 : 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['id' => $novo], 201);
    }

    public function delDependente(Request $request, $id, $depId)
    {
        $this->autorizar($request, 'cliente.edit');
        $c = $this->cliente($request, $id);
        DB::table('clienteconveniodependentes')->where('cliente_id', $c->id)->where('id', $depId)->delete();
        return response()->json(['message' => 'Dependente removido.']);
    }

    // ---------- PREÇOS especiais — clienteprodutos ----------
    public function precos(Request $request, $id)
    {
        $c = $this->cliente($request, $id);
        $rows = DB::table('clienteprodutos as cp')
            ->leftJoin('produtos as pr', 'pr.id', '=', 'cp.produto_id')
            ->where('cp.cliente_id', $c->id)
            ->get(['cp.id', 'cp.produto_id', 'pr.descricao as produto', 'cp.preco']);
        return response()->json(['data' => $rows]);
    }
}
