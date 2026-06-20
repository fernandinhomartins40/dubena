<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F7 (SPA React) — API admin FISCAL: Malha Fiscal (cadastros tributários) + NF-e
 * (lista/status + ações) + SPED. Auditado: Nfgrupofiscal/NfOperacao/CSTs/Nfsituacao,
 * NfemitidaController, Sped* (docs/01-vigente/IMPL_FISCAL.md).
 *
 * MALHA: CRUD genérico (testável). NF-e e SPED: a EMISSÃO/transmissão e a geração
 * dependem de certificado + SEFAZ homologação (gate BLOQUEANTE do plano) — o motor
 * legado (SefazEvento/SpedProcessor) é preservado e apenas delegado; não testável aqui.
 */
class FiscalController extends Controller
{
    /**
     * Cadastros simples da malha (codigo+descricao por escopo grupo/empresa).
     * tabela => [escopo coluna, RBAC modulo]
     */
    private const MALHA = [
        'grupos-fiscais' => ['tabela' => 'nfgrupofiscals', 'modulo' => 'nfgrupofiscal', 'extras' => []],
        'cst-icms'       => ['tabela' => 'nficms', 'modulo' => 'nficms', 'extras' => ['codigo']],
        'cst-ipi'        => ['tabela' => 'nfipis', 'modulo' => 'nfipi', 'extras' => ['codigo']],
        'cst-pis'        => ['tabela' => 'nfpis', 'modulo' => 'nfpis', 'extras' => ['codigo']],
        'cst-cofins'     => ['tabela' => 'nfcofins', 'modulo' => 'nfcofins', 'extras' => ['codigo']],
        'cst'            => ['tabela' => 'nfcsts', 'modulo' => 'nfcst', 'extras' => ['codigo']],
    ];

    private function grupoId(Request $request): int
    {
        $u = $request->user();
        return (int) (optional($u->empresa)->grupo_id ?? $u->grupo_id);
    }

    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless($request->user()->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    private function cfgMalha(string $tipo): array
    {
        abort_unless(isset(self::MALHA[$tipo]), 404, 'Cadastro fiscal desconhecido.');
        return self::MALHA[$tipo];
    }

    // =================== MALHA FISCAL (genérico) ===================

    /** GET /api/admin/fiscal/malha/{tipo} */
    public function malhaIndex(Request $request, string $tipo)
    {
        $cfg = $this->cfgMalha($tipo);
        $this->autorizar($request, $cfg['modulo'], 'view');
        $grupo = $this->grupoId($request);
        $q = trim((string) $request->query('q', ''));

        $cols = array_merge(['id', 'descricao'], $cfg['extras']);
        $rows = DB::table($cfg['tabela'])
            ->where(fn ($w) => $w->where('grupo_id', $grupo)->orWhereNull('grupo_id'))
            ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
            ->orderBy('descricao')->get($cols);
        return response()->json(['data' => $rows]);
    }

    /** POST /api/admin/fiscal/malha/{tipo} · PUT .../{id} */
    public function malhaSalvar(Request $request, string $tipo, $id = null)
    {
        $cfg = $this->cfgMalha($tipo);
        $this->autorizar($request, $cfg['modulo'], $id ? 'edit' : 'create');
        $grupo = $this->grupoId($request);

        $regras = ['descricao' => 'required|string|max:255'];
        foreach ($cfg['extras'] as $campo) {
            $regras[$campo] = 'nullable|string|max:20';
        }
        $data = $request->validate($regras);

        $payload = ['descricao' => $data['descricao']];
        foreach ($cfg['extras'] as $campo) {
            if (array_key_exists($campo, $data)) {
                $payload[$campo] = $data[$campo];
            }
        }
        $payload['updated_at'] = now();

        if ($id) {
            DB::table($cfg['tabela'])->where('grupo_id', $grupo)->where('id', $id)->update($payload);
            $novoId = $id;
        } else {
            $payload = array_merge($payload, ['grupo_id' => $grupo, 'empresa_id' => (int) $request->user()->empresa_id, 'created_at' => now()]);
            $novoId = DB::table($cfg['tabela'])->insertGetId($payload);
        }
        return response()->json(['data' => DB::table($cfg['tabela'])->find($novoId)], $id ? 200 : 201);
    }

    /** DELETE /api/admin/fiscal/malha/{tipo}/{id} */
    public function malhaExcluir(Request $request, string $tipo, $id)
    {
        $cfg = $this->cfgMalha($tipo);
        $this->autorizar($request, $cfg['modulo'], 'delete');
        try {
            DB::table($cfg['tabela'])->where('grupo_id', $this->grupoId($request))->where('id', $id)->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Registro fiscal em uso — não pode ser excluído.'], 409);
        }
        return response()->json(['message' => 'Registro excluído.']);
    }

    // =================== OPERAÇÕES FISCAIS (CFOP) ===================

    /** GET /api/admin/fiscal/operacoes */
    public function operacoes(Request $request)
    {
        $this->autorizar($request, 'nfoperacao', 'view');
        $rows = DB::table('nfoperacaos')->where('empresa_id', $request->user()->empresa_id)
            ->orderBy('descricao')->get(['id', 'descricao', 'descricaofiscal', 'cfop', 'movimentaestoque', 'movimentafinanceiro']);
        return response()->json(['data' => $rows]);
    }

    public function salvarOperacao(Request $request, $id = null)
    {
        $this->autorizar($request, 'nfoperacao', $id ? 'edit' : 'create');
        $data = $request->validate([
            'descricao'         => 'required|string|max:255',
            'descricaofiscal'   => 'nullable|string|max:255',
            'cfop'              => 'nullable|string|max:10',
            'movimentaestoque'  => 'nullable|boolean',
            'movimentafinanceiro' => 'nullable|boolean',
        ]);
        $payload = [
            'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
            'descricao' => $data['descricao'], 'descricaofiscal' => $data['descricaofiscal'] ?? $data['descricao'],
            'cfop' => $data['cfop'] ?? null,
            'movimentaestoque' => (int) (! empty($data['movimentaestoque'])),
            'movimentafinanceiro' => (int) (! empty($data['movimentafinanceiro'])),
            'updated_at' => now(),
        ];
        if ($id) {
            DB::table('nfoperacaos')->where('empresa_id', $request->user()->empresa_id)->where('id', $id)->update($payload);
            $novoId = $id;
        } else {
            $payload['aparecetela'] = 0; // NOT NULL no schema (default na criação)
            $payload['created_at'] = now();
            $novoId = DB::table('nfoperacaos')->insertGetId($payload);
        }
        return response()->json(['data' => DB::table('nfoperacaos')->find($novoId)], $id ? 200 : 201);
    }

    public function excluirOperacao(Request $request, $id)
    {
        $this->autorizar($request, 'nfoperacao', 'delete');
        try {
            DB::table('nfoperacaos')->where('empresa_id', $request->user()->empresa_id)->where('id', $id)->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Operação em uso — não pode ser excluída.'], 409);
        }
        return response()->json(['message' => 'Operação excluída.']);
    }

    // =================== NF-e (lista/status) ===================

    /** GET /api/admin/fiscal/nfe?q=&status= — lista de notas com status. */
    public function nfeIndex(Request $request)
    {
        $this->autorizar($request, 'nfemitida', 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $q = trim((string) $request->query('q', ''));

        $rows = DB::table('nfemitidas')
            ->where('empresa_id', $empresaId)
            ->when($q !== '', function ($w) use ($q) {
                $w->where('nfnumero', 'ilike', '%' . $q . '%')->orWhere('chaveacesso', 'ilike', '%' . $q . '%');
            })
            ->orderByDesc('datahoraemissao')->limit(300)
            ->get(['id', 'nfmodelo', 'nfserie', 'nfnumero', 'chaveacesso', 'datahoraemissao', 'nfsituacao_id']);
        return response()->json(['data' => $rows]);
    }

    /**
     * POST /api/admin/fiscal/nfe/{id}/transmitir — delega ao motor SEFAZ.
     * 🔴 GATE: depende de certificado + SEFAZ homologação validada. Não testável aqui.
     */
    public function transmitir(Request $request, $id)
    {
        $this->autorizar($request, 'nfemitida', 'edit');
        \Illuminate\Support\Facades\Session::put('empresa_padrao', \App\Empresa::find($request->user()->empresa_id));
        \Auth::login($request->user());
        try {
            $sefaz = new \App\Processors\Nfe\Tools\SefazEvento((int) $id);
            $result = $sefaz->evento('TRANSMITIR');
            return response()->json(['message' => 'Transmissão solicitada.', 'resultado' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Falha na transmissão: ' . $e->getMessage()], 422);
        }
    }

    /** POST /api/admin/fiscal/nfe/{id}/cancelar — delega ao motor (gate SEFAZ). */
    public function cancelar(Request $request, $id)
    {
        $this->autorizar($request, 'nfemitida', 'edit');
        $data = $request->validate(['justificativa' => 'required|string|min:15|max:255']);
        \Illuminate\Support\Facades\Session::put('empresa_padrao', \App\Empresa::find($request->user()->empresa_id));
        \Auth::login($request->user());
        try {
            $sefaz = new \App\Processors\Nfe\Tools\SefazEvento((int) $id);
            $result = $sefaz->evento('CANCELAR', ['justificativa' => $data['justificativa']]);
            return response()->json(['message' => 'Cancelamento solicitado.', 'resultado' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Falha no cancelamento: ' . $e->getMessage()], 422);
        }
    }

    // =================== SPED ===================

    /** GET /api/admin/fiscal/sped — preview/contagem de notas no período (sem gerar arquivo). */
    public function spedPreview(Request $request)
    {
        $this->autorizar($request, 'spedfiscal', 'view');
        $data = $request->validate(['inicio' => 'required|date', 'fim' => 'required|date']);
        $empresaId = (int) $request->user()->empresa_id;

        $notas = DB::table('nfemitidas')->where('empresa_id', $empresaId)
            ->whereBetween('datahoraemissao', [$data['inicio'] . ' 00:00:00', $data['fim'] . ' 23:59:59'])
            ->count();
        $recebidas = DB::table('nfrecebidas')->where('empresa_id', $empresaId)
            ->whereBetween('datahoraemissao', [$data['inicio'] . ' 00:00:00', $data['fim'] . ' 23:59:59'])
            ->count();

        return response()->json(['data' => [
            'notas_emitidas'  => $notas,
            'notas_recebidas' => $recebidas,
            'periodo'         => [$data['inicio'], $data['fim']],
        ]]);
    }
}
