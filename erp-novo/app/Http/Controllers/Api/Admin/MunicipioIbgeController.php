<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Geografico\CatalogoIbge;
use App\Domain\Identidade\NormalizadorTexto;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo oficial de municípios do IBGE — busca e adoção pela operação.
 *
 * A tela de cidades passa a SELECIONAR daqui em vez de digitar nome + código.
 * Digitar o código à mão produziu, na base real, código inventado, zerado e o
 * código de outra cidade — e ele vai para o XML da NF-e, onde erro é rejeição
 * da SEFAZ.
 */
class MunicipioIbgeController extends Controller
{
    use AutorizaPorPermissao;

    /** GET /municipios-ibge?q=&uf= — busca no catálogo nacional. */
    public function index(Request $request): JsonResponse
    {
        // Geográfico é base do endereço do cliente — mesma permissão do GeoController.
        $this->autorizar($request, 'cliente.view');

        $q = trim((string) $request->query('q', ''));
        $uf = strtoupper(trim((string) $request->query('uf', '')));

        $query = MunicipioIbge::query()->orderBy('nome');

        if ($uf !== '') {
            $query->where('uf', $uf);
        }

        if ($q !== '') {
            // Busca pelo nome normalizado: o operador não precisa acertar o
            // acento de "Sao Jose dos Pinhais" para achar a cidade.
            $query->where('nome_busca', 'like', '%'.NormalizadorTexto::basico($q).'%');
        }

        $municipios = $query->limit(50)->get(['cod_ibge', 'nome', 'uf']);

        return response()->json(['data' => $municipios]);
    }

    /**
     * POST /municipios-ibge/adotar — cria (ou vincula) a cidade do grupo a partir
     * do município oficial.
     *
     * É a porta única para cidade nova: nome e código vêm do catálogo, então a
     * classe de erro que originou este trabalho deixa de ser possível.
     */
    public function adotar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.create');

        $dados = $request->validate([
            'cod_ibge' => ['required', 'integer', 'exists:municipios_ibge,cod_ibge'],
        ]);

        $municipio = MunicipioIbge::query()->findOrFail($dados['cod_ibge']);
        $grupoId = (int) $request->user()->grupo_id;

        // Já existe uma cidade deste município no grupo? Devolve-a em vez de
        // criar a segunda — duplicar cidade é justamente o que corrompe a base.
        $existente = Cidade::query()
            ->where('grupo_id', $grupoId)
            ->where(fn ($q) => $q
                ->where('municipio_ibge', $municipio->cod_ibge)
                ->orWhere(fn ($q2) => $q2->where('uf', $municipio->uf)->where('descricao', $municipio->nome)))
            ->first();

        if ($existente !== null) {
            if ($existente->municipio_ibge === null) {
                $existente->forceFill([
                    'municipio_ibge' => $municipio->cod_ibge,
                    'cod_ibge' => $municipio->cod_ibge,
                ])->save();
            }

            return response()->json(['data' => $existente->refresh(), 'message' => 'Cidade já cadastrada.']);
        }

        $cidade = Cidade::create([
            'grupo_id' => $grupoId,
            'descricao' => $municipio->nome,
            'uf' => $municipio->uf,
            'cod_ibge' => $municipio->cod_ibge,
            'ativo' => true,
        ]);

        $cidade->forceFill(['municipio_ibge' => $municipio->cod_ibge])->save();

        return response()->json(['data' => $cidade->refresh()], 201);
    }

    /**
     * GET /municipios-ibge/conciliacao — quais cidades do grupo têm código
     * divergente do catálogo oficial.
     *
     * É o relatório do risco fiscal: cada linha aqui é uma NF-e que a SEFAZ
     * pode rejeitar.
     */
    public function conciliacao(Request $request, CatalogoIbge $catalogo): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');

        $problemas = [];

        foreach ($catalogo->conciliar() as $item) {
            if ($item['criterio'] === 'codigo') {
                continue;
            }

            $problemas[] = [
                'cidade_id' => $item['cidade']->id,
                'cidade' => $item['cidade']->descricao,
                'uf' => $item['cidade']->uf,
                'cod_ibge_atual' => $item['cidade']->cod_ibge,
                'cod_ibge_correto' => $item['municipio']?->cod_ibge,
                'nome_oficial' => $item['municipio']?->nome,
                'criterio' => $item['criterio'],
            ];
        }

        return response()->json(['data' => $problemas]);
    }

    /** POST /municipios-ibge/conciliacao/aplicar — corrige os códigos divergentes. */
    public function aplicarConciliacao(Request $request, CatalogoIbge $catalogo): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');

        $r = $catalogo->aplicar($catalogo->conciliar());

        return response()->json([
            'data' => $r,
            'message' => "{$r['corrigidas']} código(s) corrigido(s); {$r['orfas']} sem correspondência.",
        ]);
    }
}
