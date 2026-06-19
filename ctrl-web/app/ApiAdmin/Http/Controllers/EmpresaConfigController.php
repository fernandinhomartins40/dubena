<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Empresa;
use App\Empresaconfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * F3 (SPA React) — API admin de CONFIGURAÇÃO da empresa (106 colunas).
 * Auditado: EmpresaconfigController(716) (docs/01-vigente/IMPL_EMPRESA.md).
 * REORGANIZAÇÃO: as flags viram SUB-ABAS temáticas no React (Pedido/Estoque/
 * Impressão/E-mail/Contábil/Frete/Percentuais). Preservado:
 *   - senha mestra (Hash), teste SMTP, segredos PIX (encrypt), e-mail (customCrypt);
 *   - default null para empresa sem config (armadilha do compact legado).
 */
class EmpresaConfigController extends Controller
{
    private function autorizar(Request $request, string $acao): void
    {
        abort_unless($request->user()->podeRecurso('empresa', $acao), 403, 'Sem permissão.');
    }

    private function escopo(Request $request, int $empresaId): array
    {
        $user = $request->user();
        $grupo = (int) (optional($user->empresa)->grupo_id ?? $user->grupo_id);
        // support pode configurar qualquer empresa; demais, só as suas.
        if ((string) $user->support !== '1') {
            $tem = DB::table('empresa_user')->where('user_id', $user->id)->where('empresa_id', $empresaId)->exists();
            abort_unless($tem, 403, 'Sem acesso a esta empresa.');
        }
        return [$grupo, $empresaId];
    }

    /** GET /api/admin/empresas/{empresaId}/config — 106 col (sem segredos). */
    public function show(Request $request, $empresaId)
    {
        $this->autorizar($request, 'view');
        [$grupo] = $this->escopo($request, (int) $empresaId);

        $config = Empresaconfig::where(['empresa_id' => $empresaId, 'grupo_id' => $grupo])->first();
        if (! $config) {
            return response()->json(['data' => null]); // empresa ainda sem config
        }

        $data = $config->toArray();
        // Nunca expõe segredos.
        foreach (['senhamestre', 'emailsenha', 'client_id', 'client_secret', 'chavepix'] as $s) {
            unset($data[$s]);
        }
        // Sinaliza presença sem revelar.
        $data['tem_senhamestre'] = ! empty($config->senhamestre);
        $data['tem_chavepix'] = ! empty($config->chavepix);
        return response()->json(['data' => $data]);
    }

    /** PUT /api/admin/empresas/{empresaId}/config */
    public function update(Request $request, $empresaId)
    {
        $this->autorizar($request, 'edit');
        [$grupo, $empresaId] = $this->escopo($request, (int) $empresaId);

        $config = Empresaconfig::firstOrNew(['empresa_id' => $empresaId, 'grupo_id' => $grupo]);
        $data = $request->only($config->getFillable());

        // Flags booleanas (paridade dadosExtras).
        foreach ([
            'validaatraso', 'pedidovalidacartao', 'validagasbolso', 'validapixentrega', 'androidutiliza',
            'androidenviatodos', 'validacordenadasentrega', 'impressaoautomatica', 'emailrequerautenticacao',
            'emailrequerconexaotls', 'pedidocontrolatempoligacoes', 'permiteestoquenegativo', 'pedidoemitenfce',
        ] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = ! empty($data[$f]) ? 1 : 0;
            }
        }

        // Segredos: só atualiza se enviados; nunca grava em claro nem apaga por engano.
        unset($data['senhamestre']); // alterada só por endpoint próprio
        if (empty($data['emailsenha'])) {
            unset($data['emailsenha']);
        } else {
            $data['emailsenha'] = customCrypt($data['emailsenha'], 8);
        }
        foreach (['client_id', 'client_secret', 'chavepix'] as $s) {
            if (empty($data[$s])) {
                unset($data[$s]);
            } else {
                $data[$s] = encrypt($data[$s]);
            }
        }

        $data['grupo_id'] = $grupo;
        $data['empresa_id'] = $empresaId;

        // Na PRIMEIRA gravação, preenche colunas NOT NULL sem default no schema.
        if (! $config->exists) {
            if (empty($data['diastrabalhadosemana'])) {
                $data['diastrabalhadosemana'] = 6;
            }
            if (empty($data['setorprincipal_id'])) {
                $setorId = $data['setorprincipal_id']
                    ?? \App\Setor::where('empresa_id', $empresaId)->orderBy('id')->value('id');
                if (! $setorId) {
                    return response()->json([
                        'message' => 'Cadastre ao menos um Setor para esta empresa antes de salvar as configurações (setor principal é obrigatório).',
                    ], 422);
                }
                $data['setorprincipal_id'] = $setorId;
            }
        }

        DB::transaction(fn () => tap($config)->fill($data)->save());
        return response()->json(['message' => 'Configurações salvas.']);
    }

    /** PUT /api/admin/empresas/{empresaId}/config/senha-mestra — define/altera (Hash). */
    public function senhaMestra(Request $request, $empresaId)
    {
        $this->autorizar($request, 'edit');
        [$grupo, $empresaId] = $this->escopo($request, (int) $empresaId);
        $config = Empresaconfig::where(['empresa_id' => $empresaId, 'grupo_id' => $grupo])->first();
        abort_unless($config, 422, 'Cadastre a configuração da empresa antes de definir a senha mestra.');

        $data = $request->validate([
            'senha_atual' => 'nullable|string',
            'senha_nova'  => 'required|string|min:4',
        ]);

        // Se já existe senha, exige a atual correta (paridade changePassword).
        if (! empty($config->senhamestre)) {
            if (! Hash::check($data['senha_atual'] ?? '', $config->senhamestre)) {
                return response()->json(['message' => 'A senha atual está incorreta.'], 422);
            }
        }

        $config->update(['senhamestre' => Hash::make($data['senha_nova'])]);
        return response()->json(['message' => 'Senha mestra atualizada.']);
    }

    /** POST /api/admin/empresas/{empresaId}/config/testar-email — testa SMTP (sendEmail legado). */
    public function testarEmail(Request $request, $empresaId)
    {
        $this->autorizar($request, 'edit');
        $this->escopo($request, (int) $empresaId);
        $data = $request->validate([
            'to'      => 'required|email',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string',
        ]);
        try {
            sendMail([
                'to'      => $data['to'],
                'subject' => $data['subject'] ?? 'Teste de e-mail (Dubena)',
                'content' => $data['content'] ?? 'Este é um e-mail de teste das configurações SMTP.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Falha ao enviar: ' . $e->getMessage()], 422);
        }
        return response()->json(['message' => 'E-mail de teste enviado.']);
    }
}
