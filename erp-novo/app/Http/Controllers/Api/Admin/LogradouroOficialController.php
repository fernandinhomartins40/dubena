<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Geografico\NormalizarLogradouros;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\ImportacaoCnefe;
use App\Models\Geografico\LogradouroOficial;
use App\Models\Geografico\Rua;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Logradouros oficiais (CNEFE) — busca, sugestão e normalização do cadastro.
 *
 * Serve a dois momentos:
 *  - NA DIGITAÇÃO: `sugerir` oferece a via oficial antes de a errada entrar;
 *  - DEPOIS: `divergencias` lista o que já está torto e `normalizar` corrige.
 */
class LogradouroOficialController extends Controller
{
    use AutorizaPorPermissao;

    /**
     * GET /logradouros-oficiais?cidade_id=&q= — busca no cadastro oficial.
     *
     * É o autocompletar do endereço: o operador escolhe daqui em vez de digitar,
     * e a rua nasce com nome, bairro e CEP corretos.
     */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');

        $dados = $request->validate([
            'cidade_id' => ['required', 'integer', 'exists:cidades,id'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $cidade = Cidade::query()->findOrFail($dados['cidade_id']);
        $codIbge = $cidade->municipio_ibge ?? $cidade->cod_ibge;

        if ($codIbge === null) {
            return response()->json(['data' => [], 'message' => 'Cidade sem vínculo com o catálogo do IBGE.']);
        }

        $q = trim((string) ($dados['q'] ?? ''));
        $like = $this->operadorLike();

        $oficiais = LogradouroOficial::query()
            ->where('cod_ibge', $codIbge)
            ->when($q !== '', fn ($b) => $b->where('nome', $like, '%'.$q.'%'))
            ->orderBy('nome')
            ->limit(30)
            ->get();

        return response()->json([
            'data' => $oficiais->map(fn ($o) => [
                'id' => $o->id,
                'nome' => $o->nome_completo,
                'bairro' => $o->bairro,
                'cep' => $o->cep,
                'numero_min' => $o->numero_min,
                'numero_max' => $o->numero_max,
                'latitude' => $o->latitude,
                'longitude' => $o->longitude,
            ]),
        ]);
    }

    /**
     * GET /logradouros-oficiais/sugerir?cidade_id=&texto= — "você quis dizer…".
     *
     * Chamado enquanto o operador digita uma rua NOVA. Evita que o erro entre,
     * que é sempre mais barato que corrigi-lo depois.
     */
    public function sugerir(Request $request, NormalizarLogradouros $normalizador): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');

        $dados = $request->validate([
            'cidade_id' => ['required', 'integer', 'exists:cidades,id'],
            'texto' => ['required', 'string', 'max:120'],
        ]);

        $cidade = Cidade::query()->findOrFail($dados['cidade_id']);
        $codIbge = $cidade->municipio_ibge ?? $cidade->cod_ibge;

        if ($codIbge === null) {
            return response()->json(['data' => []]);
        }

        $sugestoes = $normalizador->sugerir((int) $codIbge, $dados['texto']);

        return response()->json([
            'data' => array_map(fn ($s) => [
                'id' => $s['oficial']->id,
                'nome' => $s['oficial']->nome_completo,
                'bairro' => $s['oficial']->bairro,
                'cep' => $s['oficial']->cep,
                'similaridade' => round($s['similaridade'], 3),
                // 1.0 é o mesmo nome escrito de outro jeito ("Dez"/"10"); abaixo
                // disso é palpite e a tela deve tratar como tal.
                'exato' => $s['similaridade'] >= 1.0,
            ], $sugestoes),
        ]);
    }

    /**
     * GET /logradouros-oficiais/divergencias?cidade_id= — o que está torto.
     *
     * É o relatório do passivo: ruas com erro de digitação e duplicatas da
     * mesma via cadastradas separadamente.
     */
    public function divergencias(Request $request, NormalizarLogradouros $normalizador): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');

        $dados = $request->validate([
            'cidade_id' => ['required', 'integer', 'exists:cidades,id'],
        ]);

        $cidade = Cidade::query()->findOrFail($dados['cidade_id']);
        $analise = $normalizador->analisar($cidade);

        if ($analise === []) {
            return response()->json([
                'data' => ['importado' => false, 'propostas' => [], 'duplicatas' => []],
                'message' => 'Cadastro oficial não importado para esta cidade.',
            ]);
        }

        $propostas = [];
        $conferem = 0;
        $ausentes = 0;

        foreach ($analise as $i) {
            if ($i['situacao'] === 'exato') {
                $conferem++;

                continue;
            }
            if ($i['situacao'] === 'ausente') {
                $ausentes++;

                continue;
            }

            $propostas[] = [
                'rua_id' => $i['rua']->id,
                'cadastrado' => $i['rua']->descricao,
                'oficial_id' => $i['oficial']->id,
                'oficial' => $i['oficial']->nome_completo,
                'bairro' => $i['oficial']->bairro,
                'cep' => $i['oficial']->cep,
                'similaridade' => round($i['similaridade'], 3),
            ];
        }

        $duplicatas = array_map(fn ($g) => [
            'oficial' => $g['oficial']->nome_completo,
            'ruas' => array_map(fn ($r) => ['id' => $r->id, 'descricao' => $r->descricao], $g['ruas']),
        ], $normalizador->duplicatas($cidade));

        return response()->json([
            'data' => [
                'importado' => true,
                'total' => count($analise),
                'conferem' => $conferem,
                'ausentes' => $ausentes,
                'propostas' => $propostas,
                'duplicatas' => $duplicatas,
            ],
        ]);
    }

    /**
     * POST /logradouros-oficiais/normalizar — aplica UMA correção.
     *
     * Uma por vez, e sempre a partir de uma proposta que alguém viu: renomear
     * em massa sem revisão é o que destruiria endereço de cliente real.
     */
    public function normalizar(Request $request, NormalizarLogradouros $normalizador): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');

        $dados = $request->validate([
            'rua_id' => ['required', 'integer', 'exists:ruas,id'],
            'oficial_id' => ['required', 'integer', 'exists:logradouros_oficiais,id'],
        ]);

        $rua = Rua::query()->findOrFail($dados['rua_id']);
        $oficial = LogradouroOficial::query()->findOrFail($dados['oficial_id']);

        $normalizador->aplicar($rua, $oficial);

        return response()->json([
            'data' => $rua->refresh(),
            'message' => 'Rua normalizada. O id não mudou — nenhum cliente trocou de endereço.',
        ]);
    }

    /** GET /logradouros-oficiais/municipios — o que já foi importado do CNEFE. */
    public function municipios(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');

        return response()->json([
            'data' => ImportacaoCnefe::query()->orderBy('municipio')->get(),
        ]);
    }

    /** sqlite (testes) não tem `ilike`; Postgres precisa dele para ignorar caixa. */
    private function operadorLike(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
