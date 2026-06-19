<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Empresa;
use App\EmpresasGrupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F3 (SPA React) — API admin de EMPRESA + GRUPOS.
 * Auditado: EmpresaController(800), EmpresasGrupoController (docs/01-vigente/IMPL_EMPRESA.md).
 * REORGANIZAÇÃO: ficha em abas (Identificação/Endereço/Contato/Fiscal/Contador) +
 * "ativar empresa" (troca a empresa ativa — equivalente ao change legado) + Grupos.
 *
 * Escopo: support vê todas; demais veem só as empresas vinculadas (empresa_user).
 * Campos fiscais densos (certificado/contingência/numeração) preservados como
 * editáveis; o MOTOR de emissão é território da F7 (Fiscal), não tocado aqui.
 */
class EmpresaController extends Controller
{
    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless($request->user()->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    /** IDs de empresa que o usuário pode ver (todas, se support). */
    private function empresasPermitidas(Request $request): ?array
    {
        $user = $request->user();
        if ((string) $user->support === '1') {
            return null; // null = todas
        }
        return DB::table('empresa_user')->where('user_id', $user->id)->pluck('empresa_id')->all();
    }

    /** GET /api/admin/empresas */
    public function index(Request $request)
    {
        $this->autorizar($request, 'empresa', 'view');
        $ids = $this->empresasPermitidas($request);

        $empresas = Empresa::query()
            ->when($ids !== null, fn ($w) => $w->whereIn('id', $ids))
            ->orderBy('nome_informal')
            ->get(['id', 'nome_informal', 'razao_social', 'cnpj', 'uf', 'ativo', 'matriz', 'grupo_id']);

        $ativaId = (int) $request->user()->empresa_id;
        return response()->json([
            'data' => $empresas->map(fn ($e) => array_merge($e->toArray(), ['ativa' => $e->id === $ativaId])),
        ]);
    }

    /** GET /api/admin/empresas/{id} — ficha completa (sem segredos). */
    public function show(Request $request, $id)
    {
        $this->autorizar($request, 'empresa', 'view');
        $empresa = $this->buscar($request, $id);
        $data = $empresa->toArray();
        // Não expõe blobs/segredos.
        unset($data['logoimg'], $data['nfesenhapfx']);

        $data['cidade_label'] = optional(\App\Cidade::find($empresa->cidade_id))->descricao;
        $data['bairro_label'] = $empresa->bairro_id ? optional(\App\Bairro::find($empresa->bairro_id))->descricao : null;
        $data['regiao_label'] = $empresa->regiao_id ? optional(\App\Regiao::find($empresa->regiao_id))->descricao : null;
        return response()->json(['data' => $data]);
    }

    /** POST /api/admin/empresas */
    public function store(Request $request)
    {
        $this->autorizar($request, 'empresa', 'create');
        $request->validate(['razao_social' => 'required|string|max:255', 'cidade_id' => 'required|integer', 'uf' => 'required|string|max:2']);
        $data = $this->dados($request, new Empresa());
        $data['grupo_id'] = (int) (optional($request->user()->empresa)->grupo_id ?? $request->user()->grupo_id);

        $empresa = DB::transaction(fn () => Empresa::create($data));
        return response()->json(['data' => ['id' => $empresa->id]], 201);
    }

    /** PUT /api/admin/empresas/{id} */
    public function update(Request $request, $id)
    {
        $this->autorizar($request, 'empresa', 'edit');
        $empresa = $this->buscar($request, $id);
        $request->validate(['razao_social' => 'required|string|max:255', 'cidade_id' => 'required|integer', 'uf' => 'required|string|max:2']);
        $data = $this->dados($request, $empresa);

        DB::transaction(function () use ($empresa, $data) {
            // matriz única no grupo (paridade updateMatriz).
            if (! empty($data['matriz'])) {
                Empresa::where('grupo_id', $empresa->grupo_id)->update(['matriz' => false]);
            }
            $empresa->update($data);
        });
        return response()->json(['data' => ['id' => $empresa->id]]);
    }

    /** POST /api/admin/empresas/{id}/ativar — troca a empresa ativa do usuário (= change). */
    public function ativar(Request $request, $id)
    {
        $this->autorizar($request, 'empresa', 'view');
        $empresa = $this->buscar($request, $id);
        $user = $request->user();

        // Garante que o usuário tem acesso à empresa (ou é support).
        $ids = $this->empresasPermitidas($request);
        abort_unless($ids === null || in_array($empresa->id, $ids), 403, 'Sem acesso a esta empresa.');

        $user->empresa_id = $empresa->id;
        $user->save();
        return response()->json(['message' => 'Empresa ativa alterada.', 'empresa_id' => $empresa->id]);
    }

    /** DELETE /api/admin/empresas/{id} */
    public function destroy(Request $request, $id)
    {
        $this->autorizar($request, 'empresa', 'delete');
        $empresa = $this->buscar($request, $id);
        try {
            DB::transaction(fn () => $empresa->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Empresa em uso — não pode ser excluída.'], 409);
        }
        return response()->json(['message' => 'Empresa excluída.']);
    }

    // ==================== GRUPOS ====================

    public function grupos(Request $request)
    {
        $this->autorizar($request, 'empresa', 'view');
        return response()->json(['data' => EmpresasGrupo::orderBy('descricao')->get(['id', 'descricao', 'ativo'])]);
    }

    public function salvarGrupo(Request $request, $id = null)
    {
        $this->autorizar($request, 'empresa', $id ? 'edit' : 'create');
        $data = $request->validate(['descricao' => 'required|string|max:255', 'ativo' => 'nullable|boolean']);
        $data['ativo'] = isset($data['ativo']) ? (int) (! empty($data['ativo'])) : 1;
        $grupo = $id
            ? tap(EmpresasGrupo::findOrFail($id))->update($data)
            : EmpresasGrupo::create($data);
        return response()->json(['data' => $grupo], $id ? 200 : 201);
    }

    public function excluirGrupo(Request $request, $id)
    {
        $this->autorizar($request, 'empresa', 'delete');
        try {
            DB::transaction(fn () => EmpresasGrupo::findOrFail($id)->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Grupo em uso — não pode ser excluído.'], 409);
        }
        return response()->json(['message' => 'Grupo excluído.']);
    }

    // ==================== util ====================

    private function buscar(Request $request, $id): Empresa
    {
        $ids = $this->empresasPermitidas($request);
        return Empresa::query()->when($ids !== null, fn ($w) => $w->whereIn('id', $ids))->findOrFail($id);
    }

    /** Pega só os campos fillable + normaliza flags booleanas (paridade dadosExtras). */
    private function dados(Request $request, Empresa $empresa): array
    {
        $data = $request->only($empresa->getFillable());

        // Flags booleanas (checkboxes do React → smallint/bool).
        $flags = ['ativo', 'matriz', 'nfeemite', 'nfceemite', 'spedemite', 'usasat',
            'depd', 'depr', 'prt', 'prr', 'prd', 'geraibscbs', 'contingenciaemissao', 'distribuidora'];
        foreach ($flags as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = ! empty($data[$f]);
            }
        }

        // País fixo p/ NF-e (paridade).
        $data['codigoibgepais'] = 1058;

        // Senha do certificado: só grava se enviada (criptografada). Nunca apaga por engano.
        if (array_key_exists('nfesenhapfx', $data)) {
            if (mb_strlen((string) $data['nfesenhapfx']) > 0) {
                $data['nfesenhapfx'] = customCrypt($data['nfesenhapfx'], 6, 'hash');
            } else {
                unset($data['nfesenhapfx']);
            }
        }
        return $data;
    }
}
