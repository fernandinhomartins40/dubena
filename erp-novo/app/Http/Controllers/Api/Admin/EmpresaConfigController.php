<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Configuração da empresa — N1.
 * GET /empresas/{id}/config  ·  PUT /empresas/{id}/config  ·  senha-mestra.
 * Campos estruturais conhecidos viram colunas; o resto fica em `dados` (JSON).
 */
class EmpresaConfigController extends Controller
{
    /** Colunas estruturais (fora do JSON `dados`). */
    private const COLUNAS = [
        'tempoentrega', 'tempourgente', 'maximoparcelas',
        'email_host', 'email_port', 'email_username', 'email_password',
        'email_from_address', 'email_from_name', 'email_encryption',
    ];

    public function show(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.view');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);

        return response()->json(['data' => $this->serializar($config)]);
    }

    public function update(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        $entrada = $request->all();
        unset($entrada['senha_mestra'], $entrada['tem_senhamestre'], $entrada['empresa_id'], $entrada['id']);

        $colunas = array_intersect_key($entrada, array_flip(self::COLUNAS));
        $dados = array_diff_key($entrada, array_flip(self::COLUNAS));

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
        $config->fill($colunas);
        $config->dados = array_merge($config->dados ?? [], $dados);
        $config->save();

        return response()->json(['data' => $this->serializar($config->refresh())]);
    }

    /** PUT /empresas/{id}/config/senha-mestra */
    public function senhaMestra(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        $data = $request->validate([
            'senha_atual' => 'nullable|string',
            'senha_nova' => 'required|string|min:4',
        ]);

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);

        if ($config->senha_mestra && ! Hash::check($data['senha_atual'] ?? '', $config->senha_mestra)) {
            return response()->json(['message' => 'Senha atual incorreta.'], 422);
        }

        $config->senha_mestra = Hash::make($data['senha_nova']);
        $config->save();

        return response()->json(['message' => 'Senha-mestra atualizada.']);
    }

    /** @return array<string, mixed> */
    private function serializar(EmpresaConfig $config): array
    {
        // Achata: colunas estruturais + chaves do JSON `dados` no mesmo nível
        // (a SPA trata a config como um mapa plano de chave→valor).
        $base = $config->only(self::COLUNAS);
        $base['empresa_id'] = $config->empresa_id;
        $base['tem_senhamestre'] = (bool) $config->senha_mestra;
        unset($base['email_password']); // segredo nunca volta

        return array_merge($config->dados ?? [], $base);
    }

    private function empresaDoGrupo(Request $request, int $empresaId): Empresa
    {
        $user = $request->user();
        $grupo = (int) ($user->empresa?->grupo_id ?? $user->grupo_id);

        return Empresa::query()->where('grupo_id', $grupo)->findOrFail($empresaId);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
