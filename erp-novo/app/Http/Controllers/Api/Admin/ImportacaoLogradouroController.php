<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Geografico\ImportarLogradourosJob;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\ImportacaoLogradouro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Importação de logradouros de uma cidade a partir da base de CEP.
 *
 * Serve ao onboarding: a revenda nova entra com a base geográfica vazia e o
 * entregador acaba digitando o mesmo logradouro de várias formas. Com a cidade
 * importada, rua e bairro viram seleção.
 */
class ImportacaoLogradouroController extends Controller
{
    use AutorizaPorPermissao;

    /** GET /logradouros/importacoes — histórico por cidade. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');

        $importacoes = ImportacaoLogradouro::query()
            ->with('cidade:id,descricao,uf')
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $importacoes->map(fn ($i) => [
                'id' => $i->id,
                'cidade_id' => $i->cidade_id,
                'cidade' => $i->cidade?->descricao,
                'uf' => $i->cidade?->uf,
                'situacao' => $i->situacao,
                'ruas_criadas' => $i->ruas_criadas,
                'bairros_criados' => $i->bairros_criados,
                'ruas_atualizadas' => $i->ruas_atualizadas,
                'consultas' => $i->consultas,
                'termos_truncados' => $i->termos_truncados,
                'erro' => $i->erro,
                'criado_em' => $i->created_at,
            ]),
        ]);
    }

    /**
     * POST /logradouros/importacoes — dispara a importação de uma cidade.
     *
     * Responde 202: a varredura leva minutos e roda em fila. A tela acompanha
     * pelo registro devolvido.
     */
    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.create');

        $dados = $request->validate([
            'cidade_id' => ['required', 'integer', 'exists:cidades,id'],
        ]);

        $cidade = Cidade::query()->findOrFail($dados['cidade_id']);

        // Uma importação em curso por cidade: disparar a segunda só duplicaria
        // centenas de requisições para chegar ao mesmo resultado.
        $emCurso = ImportacaoLogradouro::query()
            ->where('cidade_id', $cidade->id)
            ->where('situacao', 'processando')
            ->exists();

        if ($emCurso) {
            return response()->json(['message' => 'Já existe uma importação em andamento para esta cidade.'], 409);
        }

        $registro = ImportacaoLogradouro::create([
            'grupo_id' => $cidade->grupo_id,
            'cidade_id' => $cidade->id,
            'fonte' => 'viacep',
            'situacao' => 'processando',
            'executado_por' => $request->user()->id,
        ]);

        ImportarLogradourosJob::dispatch($registro->id);

        return response()->json([
            'data' => ['id' => $registro->id, 'situacao' => 'processando'],
            'message' => 'Importação iniciada. Pode levar alguns minutos.',
        ], 202);
    }

    /** GET /logradouros/importacoes/{id} — andamento de uma importação. */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');

        $i = ImportacaoLogradouro::query()->with('cidade:id,descricao,uf')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $i->id,
                'cidade' => $i->cidade?->descricao,
                'situacao' => $i->situacao,
                'ruas_criadas' => $i->ruas_criadas,
                'bairros_criados' => $i->bairros_criados,
                'ruas_atualizadas' => $i->ruas_atualizadas,
                'consultas' => $i->consultas,
                'termos_truncados' => $i->termos_truncados,
                'erro' => $i->erro,
            ],
        ]);
    }
}
