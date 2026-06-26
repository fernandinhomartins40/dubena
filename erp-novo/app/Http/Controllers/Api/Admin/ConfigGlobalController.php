<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\ConfigGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Config global por GRUPO (F01) — RT/CSRT, SMTP, SAT, Google Maps. Modernização do
 * `configuracoesgerais` do legado. Escopada por grupo (BelongsToGrupo); segredos
 * nunca voltam no GET (campos $hidden + flags "*_definido"). RBAC: grupo.view/edit.
 */
class ConfigGlobalController extends Controller
{
    use AutorizaPorPermissao;

    /** GET /config-global — config do grupo ativo (cria vazia se não existir). */
    public function show(Request $request): JsonResponse
    {
        $this->autorizar($request, 'grupo.view');
        $grupoId = (int) $request->user()->grupo_id;

        $config = ConfigGlobal::query()->firstOrCreate(['grupo_id' => $grupoId]);

        return response()->json(['data' => $this->payload($config)]);
    }

    /** PUT /config-global — atualiza a config do grupo ativo. */
    public function update(Request $request): JsonResponse
    {
        $this->autorizar($request, 'grupo.edit');
        $grupoId = (int) $request->user()->grupo_id;

        $d = $request->validate([
            'rt_cnpj' => 'nullable|string|max:14',
            'rt_contato' => 'nullable|string|max:120',
            'rt_email' => 'nullable|email|max:120',
            'rt_telefone' => 'nullable|string|max:20',
            'rt_id_csrt' => 'nullable|string|max:10',
            'rt_csrt' => 'nullable|string|max:255',
            'email_remetente' => 'nullable|email|max:120',
            'email_nome_remetente' => 'nullable|string|max:120',
            'email_host' => 'nullable|string|max:120',
            'email_porta' => 'nullable|integer|min:1|max:65535',
            'email_usuario' => 'nullable|string|max:120',
            'email_senha' => 'nullable|string|max:255',
            'email_tls' => 'nullable|boolean',
            'sat_cnpj_prod' => 'nullable|string|max:14',
            'sat_cnpj_homolog' => 'nullable|string|max:14',
            'sat_signac_prod' => 'nullable|string|max:255',
            'sat_signac_homolog' => 'nullable|string|max:255',
            'google_maps_key' => 'nullable|string|max:120',
            'link_monitoramento' => 'nullable|string|max:255',
        ]);

        $config = ConfigGlobal::query()->firstOrCreate(['grupo_id' => $grupoId]);

        // Segredos só são atualizados quando vierem preenchidos (não apaga ao enviar vazio).
        foreach (['rt_csrt', 'email_senha', 'sat_signac_prod', 'sat_signac_homolog'] as $segredo) {
            if (! array_key_exists($segredo, $d) || $d[$segredo] === null || $d[$segredo] === '') {
                unset($d[$segredo]);
            }
        }

        $config->update($d);

        return response()->json(['data' => $this->payload($config->refresh()), 'message' => 'Configuração salva.']);
    }

    /** Payload sem segredos: devolve flags "*_definido" no lugar dos valores sensíveis. */
    private function payload(ConfigGlobal $c): array
    {
        return [
            'rt_cnpj' => $c->rt_cnpj,
            'rt_contato' => $c->rt_contato,
            'rt_email' => $c->rt_email,
            'rt_telefone' => $c->rt_telefone,
            'rt_id_csrt' => $c->rt_id_csrt,
            'rt_csrt_definido' => filled($c->rt_csrt),
            'email_remetente' => $c->email_remetente,
            'email_nome_remetente' => $c->email_nome_remetente,
            'email_host' => $c->email_host,
            'email_porta' => $c->email_porta,
            'email_usuario' => $c->email_usuario,
            'email_senha_definida' => filled($c->email_senha),
            'email_tls' => (bool) $c->email_tls,
            'sat_cnpj_prod' => $c->sat_cnpj_prod,
            'sat_cnpj_homolog' => $c->sat_cnpj_homolog,
            'sat_signac_prod_definido' => filled($c->sat_signac_prod),
            'sat_signac_homolog_definido' => filled($c->sat_signac_homolog),
            'google_maps_key' => $c->google_maps_key,
            'link_monitoramento' => $c->link_monitoramento,
        ];
    }
}
