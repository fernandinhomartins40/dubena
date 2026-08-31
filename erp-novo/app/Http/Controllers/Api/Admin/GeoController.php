<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Apoio\InconsistenciaService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;
use App\Models\Geografico\Rua;
use App\Rules\ExisteNoTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Geográfico (cidades/bairros/ruas) — N2. Base do endereço do cliente.
 * Rotas /geo/{entidade} paginadas { data, meta }. Escopo por grupo automático.
 */
class GeoController extends Controller
{
    use AutorizaPorPermissao;

    /** @var array<string, array{model: class-string, regras: array<string,string>, filtros: list<string>}> */
    private const ENTIDADES = [
        'cidades' => [
            'model' => Cidade::class,
            // F3-08: `municipio_ibge` liga a cidade ao catalogo autoritativo.
            // `cod_ibge` continua aceito para nao quebrar quem ja envia, mas e
            // conferido contra o catalogo em `normalizarCidade`.
            'regras' => [
                'descricao' => 'required|string|max:255',
                'uf' => 'required|string|size:2',
                'cod_ibge' => 'nullable|integer',
                'municipio_ibge' => 'nullable|integer|exists:municipios_ibge,cod_ibge',
                'ativo' => 'nullable|boolean',
            ],
            'filtros' => ['uf'],
        ],
        'bairros' => [
            'model' => Bairro::class,
            'regras' => ['descricao' => 'required|string|max:255', 'cidade_id' => 'required|integer|exists:cidades,id', 'ativo' => 'nullable|boolean'],
            'filtros' => ['cidade_id'],
        ],
        'ruas' => [
            'model' => Rua::class,
            'regras' => ['descricao' => 'required|string|max:255', 'cidade_id' => 'required|integer|exists:cidades,id', 'bairro_id' => 'nullable|integer|exists:bairros,id', 'cep' => 'nullable|string|max:10', 'ativo' => 'nullable|boolean'],
            'filtros' => ['cidade_id', 'bairro_id'],
        ],
    ];

    public function index(Request $request, string $entidade): JsonResponse
    {
        $cfg = $this->cfg($request, $entidade, 'cliente.view');

        // sqlite (suíte de testes) não tem `ilike`; Postgres precisa dele para
        // a busca ser insensível a caixa.
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = $cfg['model']::query()
            ->when(trim((string) $request->query('q', '')), fn (Builder $b, $q) => $b->where('descricao', $like, '%'.$q.'%'))
            ->orderBy('descricao');

        // Rua carrega o bairro: é o vínculo que permite preencher o bairro a
        // partir da rua escolhida no endereço.
        if ($entidade === 'ruas') {
            $query->with('bairro:id,descricao');
        }

        foreach ($cfg['filtros'] as $filtro) {
            $valor = $request->query($filtro);
            if ($valor !== null && $valor !== '') {
                $query->where($filtro, $valor);
            }
        }

        $p = $query->paginate(20);

        // Contrato { data, meta } esperado pela SPA.
        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    public function store(Request $request, string $entidade): JsonResponse
    {
        $cfg = $this->cfg($request, $entidade, 'cliente.create');
        $dados = $this->normalizarCidade($entidade, $request->validate($cfg['regras']));

        return response()->json(['data' => $cfg['model']::create($dados)], 201);
    }

    public function update(Request $request, string $entidade, int $id): JsonResponse
    {
        $cfg = $this->cfg($request, $entidade, 'cliente.edit');
        $registro = $cfg['model']::query()->findOrFail($id);
        $registro->update($this->normalizarCidade($entidade, $request->validate($cfg['regras'])));

        return response()->json(['data' => $registro->refresh()]);
    }

    /**
     * F3-08 — o municipio IBGE e o catalogo autoritativo.
     *
     * `cidades` e por GRUPO: cada tenant tem a sua copia de "Guarapuava/PR",
     * que e um fato nacional. Isso por si so nao e o problema — o problema e o
     * `cod_ibge` ser um inteiro livre, digitado a mao.
     *
     * Um codigo errado nao da erro no cadastro: da REJEICAO da SEFAZ na
     * primeira nota, quando ninguem lembra de onde veio o numero.
     *
     * Aqui, portanto:
     *  - se veio `municipio_ibge`, o `cod_ibge` e DERIVADO dele (nao se confia
     *    em dois campos que podem discordar), e a UF tambem;
     *  - se veio so `cod_ibge`, ele e conferido contra o catalogo e vira o
     *    vinculo — um codigo que nao existe e recusado na hora, que e onde
     *    custa um minuto em vez de uma nota rejeitada.
     *
     * @param  array<string,mixed>  $dados
     * @return array<string,mixed>
     */
    private function normalizarCidade(string $entidade, array $dados): array
    {
        if ($entidade !== 'cidades') {
            return $dados;
        }

        $codigo = $dados['municipio_ibge'] ?? $dados['cod_ibge'] ?? null;

        if ($codigo === null) {
            return $dados;
        }

        $municipio = MunicipioIbge::query()->find((int) $codigo);

        if ($municipio === null) {
            throw ValidationException::withMessages([
                'cod_ibge' => 'Codigo IBGE inexistente. Escolha o municipio no catalogo oficial.',
            ]);
        }

        // A UF vem do catalogo: uma cidade com UF divergente do proprio codigo
        // IBGE e rejeitada pela SEFAZ, e o cadastro nao tem como saber qual das
        // duas o operador quis.
        if (isset($dados['uf']) && strtoupper((string) $dados['uf']) !== strtoupper($municipio->uf)) {
            throw ValidationException::withMessages([
                'uf' => 'A UF nao confere com o municipio IBGE informado ('.$municipio->nome.'/'.$municipio->uf.').',
            ]);
        }

        $dados['municipio_ibge'] = $municipio->cod_ibge;
        $dados['cod_ibge'] = $municipio->cod_ibge;
        $dados['uf'] = $municipio->uf;

        return $dados;
    }

    public function destroy(Request $request, string $entidade, int $id): JsonResponse
    {
        $cfg = $this->cfg($request, $entidade, 'cliente.delete');
        $cfg['model']::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Registro excluído.']);
    }

    /**
     * GET /cadastros/inconsistencias?tipo=ruas|bairros|todas — prováveis duplicatas
     * de rua/bairro por similaridade de nome na mesma cidade (F11). Substitui o
     * UTL_MATCH (Oracle) do legado por similaridade agnóstica de banco.
     */
    public function inconsistencias(Request $request, InconsistenciaService $service): JsonResponse
    {
        // Geográfico é base do endereço do cliente — gerido sob a permissão de cliente
        // (mesma usada no CRUD deste controller). Mantém o catálogo sem chave órfã.
        $this->autorizar($request, 'cliente.view');
        $grupoId = (int) $request->user()->grupo_id;
        $tipo = (string) $request->query('tipo', 'todas');

        $pares = match ($tipo) {
            'ruas' => $service->ruas($grupoId),
            'bairros' => $service->bairros($grupoId),
            default => $service->todas($grupoId),
        };

        return response()->json(['data' => $pares]);
    }

    /**
     * POST /cadastros/inconsistencias/ignorar — marca um par como NÃO-duplicado.
     *
     * É a ação que fecha o ciclo da tela (T4.1): sem ela o detector repete os
     * mesmos falsos positivos indefinidamente e a fila nunca esvazia. Espelha o
     * `ignorarRua`/`ignorarBairro` do legado.
     */
    public function ignorarInconsistencia(Request $request, InconsistenciaService $service): JsonResponse
    {
        // Escrita exige permissão de EDIÇÃO, não a de leitura usada no GET acima.
        $this->autorizar($request, 'cliente.edit');

        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:rua,bairro'],
            'item_id' => ['required', 'integer', 'min:1'],
            'item_ignorado_id' => ['required', 'integer', 'min:1', 'different:item_id'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            $novo = $service->ignorarPar(
                tipo: $dados['tipo'],
                itemId: (int) $dados['item_id'],
                itemIgnoradoId: (int) $dados['item_ignorado_id'],
                grupoId: (int) $user->grupo_id,
                empresaId: $user->empresa_id !== null ? (int) $user->empresa_id : null,
                userId: (int) $user->id,
                motivo: $dados['motivo'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            // Id de outro tenant ou inexistente: 422, não 500.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => ['ignorado' => true, 'novo' => $novo],
            'message' => $novo ? 'Par marcado como distinto.' : 'Este par já estava ignorado.',
        ]);
    }

    /** DELETE /cadastros/inconsistencias/ignorar — devolve o par à fila. */
    public function reconsiderarInconsistencia(Request $request, InconsistenciaService $service): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');

        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:rua,bairro'],
            'item_id' => ['required', 'integer', 'min:1'],
            'item_ignorado_id' => ['required', 'integer', 'min:1', 'different:item_id'],
        ]);

        $removido = $service->reconsiderarPar(
            $dados['tipo'],
            (int) $dados['item_id'],
            (int) $dados['item_ignorado_id'],
            (int) $request->user()->grupo_id,
        );

        return response()->json([
            'data' => ['reconsiderado' => $removido],
            'message' => $removido ? 'Par devolvido à fila.' : 'Este par não estava ignorado.',
        ]);
    }

    /** @return array{model: class-string, regras: array<string,string>, filtros: list<string>} */
    private function cfg(Request $request, string $entidade, string $permissao): array
    {
        abort_unless(isset(self::ENTIDADES[$entidade]), 404, 'Entidade geográfica desconhecida.');
        $this->autorizar($request, $permissao);

        $cfg = self::ENTIDADES[$entidade];
        $cfg['regras'] = $this->regrasComEscopo($cfg['regras']);

        return $cfg;
    }

    /**
     * Troca `exists:tabela,id` por `ExisteNoTenant` (F2-02).
     *
     * A conversão é feita aqui, e não na const: PHP não aceita `new` em
     * constante. `exists:cidades,id` validaria contra a tabela inteira, deixando
     * um bairro nascer apontando para cidade de outro grupo.
     *
     * @param  array<string, string>  $regras
     * @return array<string, mixed>
     */
    private function regrasComEscopo(array $regras): array
    {
        $porTabela = [
            'cidades' => Cidade::class,
            'bairros' => Bairro::class,
            'ruas' => Rua::class,
        ];

        foreach ($regras as $campo => $regra) {
            if (! is_string($regra) || ! str_contains($regra, 'exists:')) {
                continue;
            }
            $partes = [];
            foreach (explode('|', $regra) as $p) {
                if (preg_match('/^exists:([a-z_]+),id$/', $p, $m) && isset($porTabela[$m[1]])) {
                    $partes[] = new ExisteNoTenant($porTabela[$m[1]]);
                } else {
                    $partes[] = $p;
                }
            }
            $regras[$campo] = $partes;
        }

        return $regras;
    }
}
