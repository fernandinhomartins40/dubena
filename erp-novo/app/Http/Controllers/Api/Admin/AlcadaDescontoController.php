<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Venda\AlcadaDesconto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cadastro das alçadas de desconto (F2).
 *
 * **Por que isto é indispensável e não um extra.** A verificação de alçada é
 * fail-closed: sem regra cadastrada, o teto é ZERO e ninguém concede desconto
 * nenhum. Ou seja, enquanto não houver por onde cadastrar, o sistema fica *mais*
 * travado que antes — o oposto do que a fase pretende. A tabela sem CRUD é uma
 * funcionalidade que não existe.
 *
 * Permissão própria (`venda.alcada`): definir quanto cada perfil pode descontar é
 * decisão de política comercial, diferente de aprovar um pedido específico
 * (`venda.aprovar`). Quem opera a fila não deveria poder alargar o próprio teto.
 */
class AlcadaDescontoController extends Controller
{
    use AutorizaPorPermissao;

    /** GET /alcadas — regras da empresa, da mais específica para a mais geral. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'venda.alcada');

        $regras = AlcadaDesconto::query()
            ->with(['produto:id,descricao'])
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $regras->map(fn (AlcadaDesconto $a) => [
                'id' => $a->id,
                'role_id' => $a->role_id,
                'colaborador_id' => $a->colaborador_id,
                'produto_id' => $a->produto_id,
                'produto' => $a->produto?->descricao,
                'setor_id' => $a->setor_id,
                'condicaopagamento_id' => $a->condicaopagamento_id,
                'percentual_max' => (float) $a->percentual_max,
                'valor_max' => $a->valor_max !== null ? (float) $a->valor_max : null,
                'base_calculo' => $a->base_calculo,
                'permite_solicitar' => (bool) $a->permite_solicitar,
                'data_inicio' => optional($a->data_inicio)->toDateString(),
                'data_fim' => optional($a->data_fim)->toDateString(),
                'ativo' => (bool) $a->ativo,
                // Ajuda o gestor a entender qual regra vence quando várias batem.
                'especificidade' => $a->especificidade(),
            ])->all(),
        ]);
    }

    /** POST /alcadas · PUT /alcadas/{id} */
    public function salvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, 'venda.alcada');

        $d = $request->validate([
            'role_id' => 'nullable|integer|exists:roles,id',
            'colaborador_id' => 'nullable|integer|exists:colaboradores,id',
            'produto_id' => 'nullable|integer|exists:produtos,id',
            'setor_id' => 'nullable|integer|exists:setores,id',
            'condicaopagamento_id' => 'nullable|integer|exists:condicaopagamentos,id',
            'percentual_max' => 'required|numeric|min:0|max:100',
            'valor_max' => 'nullable|numeric|min:0',
            // 'tabela' = sobre o preço de lista; 'praticado' = sobre o preço já
            // negociado do item (preço especial do cliente, convênio). Ver a
            // migration: sem isso, cliente com preço especial ganharia desconto
            // em cascata.
            'base_calculo' => 'nullable|in:tabela,praticado',
            'permite_solicitar' => 'nullable|boolean',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'ativo' => 'nullable|boolean',
        ]);

        $d['base_calculo'] ??= 'tabela';
        $d['permite_solicitar'] ??= true;
        $d['ativo'] ??= true;
        $d['empresa_id'] = (int) $request->user()->empresa_id;

        $regra = $id !== null
            ? tap($this->localizar($request, $id))->update($d)
            : AlcadaDesconto::create($d);

        return response()->json(['data' => $regra->fresh()], $id !== null ? 200 : 201);
    }

    /** DELETE /alcadas/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'venda.alcada');

        $this->localizar($request, $id)->delete();

        return response()->json(['data' => ['removido' => true]]);
    }

    /** Anti-IDOR: a regra tem de ser da empresa do token. */
    private function localizar(Request $request, int $id): AlcadaDesconto
    {
        return AlcadaDesconto::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);
    }
}
