<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Fiscal\CertificadoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Configuração da empresa — N1.
 * GET /empresas/{id}/config  ·  PUT /empresas/{id}/config  ·  senha-mestra.
 * Campos estruturais conhecidos viram colunas; o resto fica em `dados` (JSON).
 */
class EmpresaConfigController extends Controller
{
    use AutorizaPorPermissao;

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

    /** GET /empresas/{id}/certificado — status do A1 (sem segredos). */
    public function certificadoStatus(Request $request, int $empresaId, CertificadoService $service): JsonResponse
    {
        $this->autorizar($request, 'empresa.view');
        $empresa = $this->empresaDoGrupo($request, $empresaId);
        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);

        return response()->json(['data' => $service->status($config)]);
    }

    /** POST /empresas/{id}/certificado — upload do A1 (multipart: certificado + senha). */
    public function uploadCertificado(Request $request, int $empresaId, CertificadoService $service): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        $request->validate([
            // Só PKCS#12 (.pfx/.p12): filtro barato de extensão (S-3). A prova real
            // do conteúdo é o openssl_pkcs12_read do CertificadoService, que rejeita
            // qualquer coisa que não abra como certificado com a senha dada.
            'certificado' => 'required|file|max:10240|mimes:pfx,p12,x-pkcs12', // até 10 MB
            'senha' => 'required|string',
        ]);

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
        $conteudo = (string) file_get_contents($request->file('certificado')->getRealPath());

        $status = $service->armazenar($config, $conteudo, $request->string('senha')->value());

        return response()->json(['data' => $status, 'message' => 'Certificado armazenado.']);
    }

    /** PUT /empresas/{id}/nfce-token — CSC da NFC-e (id + token, token encriptado). */
    public function nfceToken(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        $d = $request->validate([
            'nfce_csc_id' => 'required|string|max:10',
            'nfce_csc_token' => 'required|string|max:255',
        ]);

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
        $config->forceFill([
            'nfce_csc_id' => $d['nfce_csc_id'],
            'nfce_csc_token' => $d['nfce_csc_token'],
        ])->save();

        return response()->json(['message' => 'Token NFC-e atualizado.', 'data' => ['nfce_csc_id' => $config->nfce_csc_id]]);
    }

    /** POST /empresas/{id}/config/testar-email — envia um e-mail de teste via SMTP da empresa. */
    public function testarEmail(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        $d = $request->validate([
            'to' => 'required|email',
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string|max:2000',
        ]);

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
        if (! $config->email_host || ! $config->email_username) {
            return response()->json(['message' => 'Configure o SMTP (host/usuário) antes de testar.'], 422);
        }

        // Mailer dinâmico com as credenciais da empresa (não toca a config global).
        // Em teste, 'mail.empresa_test_transport' força o coletor 'array' (sem rede).
        Config::set('mail.mailers.empresa_smtp', Config::get('mail.empresa_test_transport')
            ? ['transport' => 'array']
            : [
                'transport' => 'smtp',
                'host' => $config->email_host,
                'port' => $config->email_port ?? 587,
                'encryption' => $config->email_encryption ?: 'tls',
                'username' => $config->email_username,
                'password' => $config->email_password, // descriptografado pelo cast
                'timeout' => 15,
            ]);

        $assunto = $d['subject'] ?? 'Teste de e-mail — '.($empresa->nome_fantasia ?? 'ERP');
        $corpo = $d['content'] ?? 'Este é um e-mail de teste do ERP. Se você recebeu, o SMTP está configurado corretamente.';
        $de = $config->email_from_address ?: $config->email_username;
        $nome = $config->email_from_name ?: ($empresa->nome_fantasia ?? null);

        try {
            Mail::mailer('empresa_smtp')->raw($corpo, function ($m) use ($d, $assunto, $de, $nome) {
                $m->to($d['to'])->subject($assunto)->from($de, $nome);
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Falha ao enviar: '.$e->getMessage()], 422);
        }

        return response()->json(['message' => "E-mail de teste enviado para {$d['to']}."]);
    }

    /**
     * GET /empresas/{id}/integracoes — estado das integrações da empresa (PIX/cartão).
     * WRITE-ONLY: nunca devolve segredo; só `configurado` + campos públicos (psp,
     * client_id, chave, ambiente, gateway, pv) para a SPA exibir "•••• configurado".
     */
    public function integracoes(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $empresa = $this->empresaDoGrupo($request, $empresaId);
        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
        $int = ($config->dados['integracoes'] ?? []);

        $pix = is_array($int['pix'] ?? null) ? $int['pix'] : [];
        $cartao = is_array($int['cartao'] ?? null) ? $int['cartao'] : [];

        return response()->json(['data' => [
            'pix' => [
                'psp' => $pix['psp'] ?? null,
                'client_id' => $pix['client_id'] ?? null,
                'chave' => $pix['chave'] ?? null,
                'ambiente' => $pix['ambiente'] ?? 'homologacao',
                'client_secret_configurado' => ! empty($pix['client_secret']),
                'webhook_hmac_configurado' => ! empty($pix['webhook_hmac_secret']),
            ],
            'cartao' => [
                'gateway' => $cartao['gateway'] ?? null,
                'pv' => $cartao['pv'] ?? null,
                'url' => $cartao['url'] ?? null,
                'token_configurado' => ! empty($cartao['token']),
            ],
        ]]);
    }

    /**
     * PUT /empresas/{id}/integracoes — salva credenciais de PIX/cartão da empresa.
     * Segredos (client_secret, webhook_hmac_secret, token) são cifrados por-valor;
     * campo vazio/ausente PRESERVA o segredo já salvo (não apaga ao reeditar sem
     * reenviar). Escopo empresa (multi-tenant): cada revenda com o seu credenciamento.
     */
    public function salvarIntegracoes(Request $request, int $empresaId): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $empresa = $this->empresaDoGrupo($request, $empresaId);

        $d = $request->validate([
            'pix' => 'sometimes|array',
            'pix.psp' => 'nullable|string|max:40',
            'pix.client_id' => 'nullable|string|max:255',
            'pix.client_secret' => 'nullable|string|max:512',
            'pix.chave' => 'nullable|string|max:140',
            'pix.ambiente' => 'nullable|in:producao,homologacao',
            'pix.webhook_hmac_secret' => 'nullable|string|max:255',
            'cartao' => 'sometimes|array',
            'cartao.gateway' => 'nullable|string|max:40',
            'cartao.pv' => 'nullable|string|max:80',
            'cartao.token' => 'nullable|string|max:512',
            'cartao.url' => 'nullable|url|max:255',
        ]);

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
        $dados = $config->dados ?? [];
        $int = $dados['integracoes'] ?? [];

        if (array_key_exists('pix', $d)) {
            $int['pix'] = \App\Domain\Integracao\IntegracaoTenant::cifrarBloco(
                $d['pix'], ['client_secret', 'webhook_hmac_secret'], is_array($int['pix'] ?? null) ? $int['pix'] : [],
            );
        }
        if (array_key_exists('cartao', $d)) {
            $int['cartao'] = \App\Domain\Integracao\IntegracaoTenant::cifrarBloco(
                $d['cartao'], ['token'], is_array($int['cartao'] ?? null) ? $int['cartao'] : [],
            );
        }

        $dados['integracoes'] = $int;
        $config->dados = $dados;
        $config->save();

        return $this->integracoes($request, $empresaId);
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

        // `integracoes` (PIX/cartão) guardam segredos cifrados — NUNCA voltam no
        // achatado da config. A tela de integrações usa endpoints próprios que só
        // devolvem "configurado: bool" + campos públicos.
        $dados = $config->dados ?? [];
        unset($dados['integracoes']);

        return array_merge($dados, $base);
    }

    private function empresaDoGrupo(Request $request, int $empresaId): Empresa
    {
        $user = $request->user();
        $grupo = (int) ($user->empresa?->grupo_id ?? $user->grupo_id);

        return Empresa::query()->where('grupo_id', $grupo)->findOrFail($empresaId);
    }
}
