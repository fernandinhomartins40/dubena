<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F4 (SPA React) — Cadastros de apoio (consolidado).
 * Auditado: controllers simples + tabelas (docs/01-vigente/IMPL_CADASTROS_APOIO.md).
 * REORGANIZAÇÃO: cada CRUD simples deixa de ser tela isolada e vira ABA de
 * "Configurações" do módulo dono (Clientes/Financeiro). Genérico, parametrizado
 * por TIPO: escopo (grupo/empresa) + unicidade descricao + FK amigável.
 *
 * Não inclui Pedidosituacao/Pedidooperacao (config crítica do Pedido → F9 Vendas)
 * nem cadastros de RH/Frota (→ F8 Satélites).
 */
class CadastroApoioController extends Controller
{
    /**
     * Registro dos cadastros suportados.
     * cada um: tabela, escopo (grupo|empresa), modulo RBAC, campos extras [campo => regra].
     */
    private const TIPOS = [
        // Clientes → Configurações
        'segmentos'            => ['tabela' => 'segmentos', 'escopo' => 'grupo', 'modulo' => 'cliente', 'extras' => []],
        'tipos-pessoa'         => ['tabela' => 'tipopessoas', 'escopo' => 'grupo', 'modulo' => 'cliente', 'extras' => ['tipopessoacadastro' => 'nullable|string|max:1']],
        'telefone-tipos'       => ['tabela' => 'telefonetipos', 'escopo' => 'grupo', 'modulo' => 'cliente', 'extras' => ['celular' => 'bool']],
        'contato-tipos'        => ['tabela' => 'clientecontatotipos', 'escopo' => 'grupo', 'modulo' => 'cliente', 'extras' => []],
        'contato-situacoes'    => ['tabela' => 'clientecontatosituacaos', 'escopo' => 'grupo', 'modulo' => 'cliente', 'extras' => []],
        // Financeiro → Configurações
        'bancos'               => ['tabela' => 'bancos', 'escopo' => 'grupo', 'modulo' => 'financeiro', 'extras' => ['codigo' => 'nullable|string|max:10', 'site' => 'nullable|string|max:255']],
        'tipos-movimento'      => ['tabela' => 'contamovimentotipos', 'escopo' => 'grupo', 'modulo' => 'financeiro', 'extras' => ['pagarreceber' => 'nullable|string|max:1', 'cheque' => 'bool', 'cartao' => 'bool', 'valegas' => 'bool', 'convenio' => 'bool']],
    ];

    private function cfg(string $tipo): array
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404, 'Cadastro desconhecido.');
        return self::TIPOS[$tipo];
    }

    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless($request->user()->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    private function escopoWhere(Request $request, array $cfg): array
    {
        $user = $request->user();
        $grupo = (int) (optional($user->empresa)->grupo_id ?? $user->grupo_id);
        return $cfg['escopo'] === 'empresa'
            ? ['empresa_id' => (int) $user->empresa_id]
            : ['grupo_id' => $grupo];
    }

    /** GET /api/admin/cadastros/{tipo} */
    public function index(Request $request, string $tipo)
    {
        $cfg = $this->cfg($tipo);
        $this->autorizar($request, $cfg['modulo'], 'view');
        $where = $this->escopoWhere($request, $cfg);
        $q = trim((string) $request->query('q', ''));

        $colunas = array_merge(['id', 'descricao', 'ativo'], array_keys($cfg['extras']));
        $rows = DB::table($cfg['tabela'])->where($where)
            ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
            ->orderBy('descricao')->get($colunas);

        return response()->json(['data' => $rows]);
    }

    /** POST /api/admin/cadastros/{tipo}  ·  PUT /api/admin/cadastros/{tipo}/{id} */
    public function salvar(Request $request, string $tipo, $id = null)
    {
        $cfg = $this->cfg($tipo);
        $this->autorizar($request, $cfg['modulo'], $id ? 'edit' : 'create');
        $where = $this->escopoWhere($request, $cfg);

        $regras = array_merge(['descricao' => 'required|string|max:255', 'ativo' => 'nullable|boolean'], $cfg['extras']);
        $data = $request->validate(array_map(fn ($r) => $r === 'bool' ? 'nullable|boolean' : $r, $regras));

        // Unicidade descricao no escopo.
        $existe = DB::table($cfg['tabela'])->where($where)
            ->whereRaw('lower(descricao) = ?', [mb_strtolower($data['descricao'])])
            ->when($id, fn ($w) => $w->where('id', '<>', $id))->exists();
        if ($existe) {
            return response()->json(['message' => 'Já existe um registro com essa descrição.'], 422);
        }

        // Monta payload: descricao + ativo + extras (flags bool → 0/1).
        $payload = ['descricao' => $data['descricao'], 'ativo' => isset($data['ativo']) ? (int) (! empty($data['ativo'])) : 1];
        foreach ($cfg['extras'] as $campo => $regra) {
            if (array_key_exists($campo, $data)) {
                $payload[$campo] = $regra === 'bool' ? (int) (! empty($data[$campo])) : $data[$campo];
            }
        }
        $payload['updated_at'] = now();

        if ($id) {
            DB::table($cfg['tabela'])->where($where)->where('id', $id)->update($payload);
            $novoId = $id;
        } else {
            $payload = array_merge($payload, $where, ['created_at' => now()]);
            $novoId = DB::table($cfg['tabela'])->insertGetId($payload);
        }

        $row = DB::table($cfg['tabela'])->where('id', $novoId)->first();
        return response()->json(['data' => $row], $id ? 200 : 201);
    }

    /** DELETE /api/admin/cadastros/{tipo}/{id} */
    public function excluir(Request $request, string $tipo, $id)
    {
        $cfg = $this->cfg($tipo);
        $this->autorizar($request, $cfg['modulo'], 'delete');
        $where = $this->escopoWhere($request, $cfg);

        try {
            DB::table($cfg['tabela'])->where($where)->where('id', $id)->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Registro em uso — não pode ser excluído.'], 409);
        }
        return response()->json(['message' => 'Registro excluído.']);
    }
}
