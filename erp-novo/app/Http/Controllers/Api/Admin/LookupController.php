<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LookupController (C-fix) — alimenta os AsyncSelect da SPA (/lookups/{tipo}).
 *
 * Cada lookup devolve uma lista [{id, label}] (shape do AsyncSelect), filtrável
 * por ?q=. Registry mapeia o slug → (tabela, coluna de label, escopo). Slugs sem
 * tabela própria caem em listas estáticas (enums fixos) ou vazio.
 */
class LookupController extends Controller
{
    /**
     * Registry: slug => [tabela, coluna_label, escopo('grupo'|'empresa'|null), where_extra].
     *
     * @var array<string, array{0:string,1:string,2:?string,3?:array<string,mixed>}>
     */
    private const MAPA = [
        'cidades' => ['cidades', 'descricao', 'grupo'],
        'bairros' => ['bairros', 'descricao', 'grupo'],
        'ruas' => ['ruas', 'descricao', 'grupo'],
        'estados' => ['estados', 'descricao', null],
        'regioes' => ['regioes', 'descricao', 'grupo'],
        'segmentos' => ['segmentos', 'descricao', 'grupo'],
        'cargos' => ['cargos', 'descricao', 'grupo'],
        'colaboradores' => ['colaboradores', 'nome', 'empresa'],
        'clientes-fornecedores' => ['clientes', 'nome', 'empresa'],
        'clientes' => ['clientes', 'nome', 'empresa'],
        'produtos' => ['produtos', 'descricao', 'empresa'],
        'produtos-vasilhame' => ['produtos', 'descricao', 'empresa'],
        'setores' => ['setores', 'descricao', 'empresa'],
        'contas' => ['contas', 'descricao', 'empresa'],
        'planos-conta' => ['planos_conta', 'descricao', 'grupo'],
        'centros-custo' => ['centros_custo', 'descricao', 'grupo'],
        'condicoes-pagamento' => ['condicaopagamentos', 'descricao', 'grupo'],
        'pedido-operacoes' => ['pedidooperacoes', 'descricao', 'grupo'],
        'pedido-situacoes' => ['pedidosituacoes', 'descricao', 'grupo'],
        'produto-classes' => ['produtoclasses', 'descricao', 'grupo'],
        'unidades' => ['unidadesmedida', 'descricao', 'grupo'],
        'combustiveis' => ['tipo_combustiveis', 'descricao', 'grupo'],
        'veiculo-tipos' => ['veiculo_tipos', 'descricao', 'grupo'],
        'bancos' => ['bancos', 'descricao', 'grupo'],
        'telefone-tipos' => ['telefonetipos', 'descricao', 'grupo'],
        'tipo-pessoa' => ['tipopessoas', 'descricao', 'grupo'],
        'contato-tipos' => ['clientecontatotipos', 'descricao', 'grupo'],
        'contato-situacoes' => ['clientecontatosituacoes', 'descricao', 'grupo'],
    ];

    /** Lookups sem tabela própria — listas fixas (id sintético + label). */
    private const ESTATICOS = [
        'tipo-glp' => ['P2', 'P5', 'P13', 'P20', 'P45', 'P90', 'Granel'],
        'nf-grupos-fiscais' => ['Tributado', 'Substituição tributária', 'Isento', 'Não tributado'],
        'parentescos' => ['Cônjuge', 'Filho(a)', 'Pai', 'Mãe', 'Irmão(ã)', 'Outro'],
    ];

    public function index(Request $request, string $tipo): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (isset(self::ESTATICOS[$tipo])) {
            $itens = collect(self::ESTATICOS[$tipo])
                ->when($q !== '', fn ($c) => $c->filter(fn ($v) => stripos($v, $q) !== false)->values())
                ->map(fn ($v, $i) => ['id' => $i + 1, 'label' => $v])
                ->values();

            return response()->json(['data' => $itens]);
        }

        if (! isset(self::MAPA[$tipo])) {
            return response()->json(['data' => []]);
        }

        [$tabela, $colLabel, $escopo] = self::MAPA[$tipo];
        $user = $request->user();

        $rows = DB::table($tabela)
            ->when($escopo === 'grupo' && $user?->grupo_id, fn ($b) => $b->where('grupo_id', $user->grupo_id))
            ->when($escopo === 'empresa' && $user?->empresa_id, fn ($b) => $b->where('empresa_id', $user->empresa_id))
            ->when(Schema::hasColumn($tabela, 'ativo'), fn ($b) => $b->where('ativo', true))
            ->when($q !== '', fn ($b) => $b->whereRaw("LOWER({$colLabel}) LIKE ?", ['%'.mb_strtolower($q).'%']))
            ->orderBy($colLabel)
            ->limit(50)
            ->get(['id', DB::raw("{$colLabel} as label")]);

        return response()->json(['data' => $rows]);
    }
}
