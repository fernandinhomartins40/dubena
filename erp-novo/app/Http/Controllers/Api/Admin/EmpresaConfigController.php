<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Fiscal\CertificadoService;
use App\Domain\Fiscal\ProntidaoFiscalService;
use App\Domain\Integracao\IntegracaoTenant;
use App\Domain\Tenant\TenantContext;
use App\Domain\Tenant\TenantEnvelopeRuntime;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Fiscal\ConfigFiscal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

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

    /**
     * Contenção F0-04/A-12.4: somente configuração operacional plana. Segredos
     * e blocos estruturados possuem endpoints próprios, write-only e cifrados.
     * F2/F6 substituirão esta lista por catálogo/capabilities versionados.
     *
     * @var list<string>
     */
    private const DADOS_EDITAVEIS = [
        'planoconta_id', 'centrocusto_id', 'pc_receita_desconto_id', 'pc_receita_juro_id',
        'pc_despesa_desconto_id', 'pc_despesa_juro_id', 'cc_receita_juro_id',
        'cc_receita_desconto_id', 'cc_despesa_juro_id', 'cc_despesa_desconto_id',
        'ccfrete_id', 'pcfrete_id', 'frete_modalidade', 'setor_principal_id',
        'pedido_status_padrao', 'pedido_emite_nfce', 'pedido_valida_cartao',
        'pedido_valida_cartao_dias', 'pedido_controla_tempo_ligacoes', 'operacao_disk',
        'pedido_operacao_id', 'nf_operacao_id', 'nfce_cliente_id', 'presenca_comprador',
        'transportador_padrao_id', 'quantidade_padrao', 'permite_estoque_negativo',
        'valida_coordenadas_entrega', 'valida_atraso', 'valida_pix_entrega',
        'dias_trabalhados_semana', 'dias_inativo_compra', 'tela_controla_km',
        'impressao_automatica', 'impressao_vias_pedido', 'percentual_encargos',
        'percentual_provisao_devedores', 'percentual_remuneracao_capital',
        'percentual_distribuicao_resultado', 'fator_potencial_venda', 'maloteconta_id',
        'conta_check_troco', 'ccvalegas_id', 'pcvalegas_id', 'android_utiliza',
        // As abas Contábil e Impressão sempre ofereceram estes campos, mas eles
        // não estavam na lista: o salvar inteiro era recusado com 422.
        'pccartao_id', 'impressao_tipo', 'impressao_modelo', 'impressao_porta',
        'android_envia_todos', 'valida_gas_bolso', 'mensagem_gas_bolso',
        'mensagem_duplicata', 'appnf_pedido_operacao_id', 'appnf_presenca_comprador',
        'appnf_frete_modalidade', 'appnf_transportador_id', 'appnf_conta_id',
        'convenio_conta_id', 'convenio_nf_operacao_id', 'convenio_cc_id',
        'convenio_pc_id', 'convenio_ccfrete_id', 'convenio_pcfrete_id',
        'convenio_setor_id', 'convenio_veiculo_id', 'convenio_condicaopagamento_id',
        'convenio_presenca_comprador', 'convenio_frete_modalidade',
        'convenio_transportador_id', 'gp_produto_id', 'valorfretegp', 'ccfretegp_id',
        'pcfretegp_id', 'gp_condicaopagamento_frete_id', 'gp_condicaopagamento_id',
        'setor_ressarcimento_id', 'operacao_ressarcimento_id', 'email_assunto',
        'email_corpo', 'email_requer_autenticacao', 'email_requer_tls',
    ];

    /** @var array<string,string> chave JSON => tabela de configuracao financeira */
    private const CONFIGURACOES_FINANCEIRAS = [
        'planoconta_id' => 'planos_conta', 'pc_receita_desconto_id' => 'planos_conta',
        'pc_receita_juro_id' => 'planos_conta', 'pc_despesa_desconto_id' => 'planos_conta',
        'pc_despesa_juro_id' => 'planos_conta', 'pcfrete_id' => 'planos_conta',
        'pcvalegas_id' => 'planos_conta', 'pccartao_id' => 'planos_conta', 'convenio_pc_id' => 'planos_conta',
        'convenio_pcfrete_id' => 'planos_conta', 'pcfretegp_id' => 'planos_conta',
        'centrocusto_id' => 'centros_custo', 'cc_receita_juro_id' => 'centros_custo',
        'cc_receita_desconto_id' => 'centros_custo', 'cc_despesa_desconto_id' => 'centros_custo',
        'ccfrete_id' => 'centros_custo', 'ccvalegas_id' => 'centros_custo',
        'convenio_cc_id' => 'centros_custo', 'convenio_ccfrete_id' => 'centros_custo',
        'ccfretegp_id' => 'centros_custo',
    ];

    public function show(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $this->empresaDoGrupo($request, $empresaId);
        $this->autorizarNaEmpresa($request, 'empresa.view', $empresaId);

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);

        return response()->json(['data' => $this->serializar($config)]);
    }

    public function update(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $this->empresaDoGrupo($request, $empresaId, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $empresaId);

        $permitidas = array_merge(self::COLUNAS, self::DADOS_EDITAVEIS);
        $inesperadas = array_diff(array_keys($request->all()), $permitidas);
        if ($inesperadas !== []) {
            throw ValidationException::withMessages([
                'config' => 'Chaves não permitidas no endpoint genérico: '.implode(', ', $inesperadas).'.',
            ]);
        }

        $entrada = $request->only($permitidas);
        foreach ($entrada as $chave => $valor) {
            if (is_array($valor) || is_object($valor)) {
                throw ValidationException::withMessages([
                    $chave => 'Configuração estruturada deve usar seu endpoint específico.',
                ]);
            }
        }

        $colunas = array_intersect_key($entrada, array_flip(self::COLUNAS));
        $dados = array_diff_key($entrada, array_flip(self::COLUNAS));
        $this->validarConfiguracoesFinanceirasDoTenant($dados);

        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
        $config->fill($colunas);
        $config->dados = array_merge($config->dados ?? [], $dados);
        $config->save();

        return response()->json(['data' => $this->serializar($config->refresh())]);
    }

    /** PUT /empresas/{id}/config/senha-mestra */
    public function senhaMestra(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $this->empresaDoGrupo($request, $empresaId, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $empresaId);

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
        $empresa = $this->empresaDoGrupo($request, $empresaId);
        $this->autorizarNaEmpresa($request, 'empresa.view', $empresaId);
        $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);

        return response()->json(['data' => $service->status($config)]);
    }

    /** POST /empresas/{id}/certificado — upload do A1 (multipart: certificado + senha). */
    public function uploadCertificado(Request $request, int $empresaId, CertificadoService $service): JsonResponse
    {
        $empresa = $this->empresaDoGrupo($request, $empresaId, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $empresaId);

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

    /**
     * GET /empresas/{id}/fiscal — o que a EMISSÃO usa, para a tela da empresa.
     *
     * O formulário da empresa sempre teve ambiente, regime, séries, emissão,
     * contador e cadastro fiscal — e mandava tudo para `PUT /empresas/{id}`,
     * que descartava. A emissão lê `config_fiscais`, que nenhuma tela gravava.
     * Aqui as duas pontas se encontram. CSC nunca volta: só se está definido.
     */
    public function fiscalShow(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $this->empresaDoGrupo($request, $empresaId);
        $this->autorizarNaEmpresa($request, 'empresa.view', $empresaId);

        $fiscal = ConfigFiscal::withoutTenant()->where('empresa_id', $empresa->id)->first();
        $dados = EmpresaConfig::query()->where('empresa_id', $empresa->id)->value('dados');
        $dados = is_array($dados) ? $dados : (json_decode((string) $dados, true) ?: []);
        $ultimo = fn (int $modelo, ?int $serie) => $serie === null ? null
            : DB::table('sequencias')->where('chave', "nf:{$empresa->id}:{$modelo}:{$serie}")->value('valor');

        return response()->json(['data' => [
            'ambiente' => $fiscal?->ambiente,
            'regime_tributario' => $fiscal?->regime_tributario,
            'serie_nfe' => $fiscal?->serie_nfe,
            'serie_nfce' => $fiscal?->serie_nfce,
            'csc_id' => $fiscal?->csc_id,
            'tem_csc' => filled($fiscal?->getRawOriginal('csc_token')),
            // Só leitura: o último número emitido. Editar à mão é o caminho
            // para nota duplicada; quem controla é o sistema.
            'ultimo_numero_nfe' => $ultimo(55, $fiscal?->serie_nfe),
            'ultimo_numero_nfce' => $ultimo(65, $fiscal?->serie_nfce),
            'nfe_emite' => (bool) ($dados['fiscal']['nfe_emite'] ?? false),
            'nfce_emite' => (bool) ($dados['fiscal']['nfce_emite'] ?? false),
            'sped_emite' => (bool) ($dados['sped']['emite'] ?? false),
            'contador' => $dados['contador'] ?? (object) [],
            'cadastro' => $dados['cadastro'] ?? (object) [],
        ]]);
    }

    /** GET /empresas/{id}/fiscal/prontidao — o que ainda falta para emitir nota. */
    public function fiscalProntidao(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $this->empresaDoGrupo($request, $empresaId);
        $this->autorizarNaEmpresa($request, 'empresa.view', $empresaId);

        return response()->json(['data' => app(ProntidaoFiscalService::class)->verificar($empresa)]);
    }

    /** PUT /empresas/{id}/fiscal */
    public function fiscalUpdate(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $this->empresaDoGrupo($request, $empresaId);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $empresaId);

        $d = $request->validate([
            'ambiente' => 'sometimes|in:1,2',
            'regime_tributario' => 'sometimes|in:1,2,3',
            'serie_nfe' => 'sometimes|integer|min:1|max:999',
            'serie_nfce' => 'sometimes|integer|min:1|max:999',
            'csc_id' => 'sometimes|nullable|string|max:10',
            'csc_token' => 'sometimes|nullable|string|max:255',
            'nfe_emite' => 'sometimes|boolean',
            'nfce_emite' => 'sometimes|boolean',
            'sped_emite' => 'sometimes|boolean',
            'contador' => 'sometimes|array',
            'contador.nome' => 'nullable|string|max:120',
            'contador.cpf' => 'nullable|string|max:14',
            'contador.cnpj' => 'nullable|string|max:18',
            'contador.crc' => 'nullable|string|max:30',
            'contador.telefone' => 'nullable|string|max:30',
            'contador.email' => 'nullable|email|max:120',
            'cadastro' => 'sometimes|array',
            'cadastro.cnae' => 'nullable|string|max:10',
            'cadastro.registro_anp' => 'nullable|string|max:20',
            'cadastro.suframa' => 'nullable|string|max:20',
            'cadastro.inscricao_estadual_st' => 'nullable|string|max:20',
            'cadastro.email' => 'nullable|email|max:120',
            // A tela é uma caixa de seleção; o legado guardava texto ("1"/"0").
            'cadastro.distribuidora' => 'nullable',
        ]);

        DB::transaction(function () use ($empresa, $d) {
            $fiscal = ConfigFiscal::withoutTenant()->firstOrNew(['empresa_id' => $empresa->id]);
            // A dona da linha é a empresa editada — não necessariamente a ativa.
            $fiscal->tenant_account_id ??= $empresa->tenant_account_id;
            $fiscal->fill(array_intersect_key($d, array_flip(['ambiente', 'regime_tributario', 'serie_nfe', 'serie_nfce', 'csc_id'])));
            // Token vazio = "não mexer": a tela não recebe o segredo de volta,
            // então o campo chega vazio sempre que a pessoa não o redigita.
            if (filled($d['csc_token'] ?? null)) {
                $fiscal->csc_token = $d['csc_token'];
            }
            $fiscal->save();

            $config = EmpresaConfig::firstOrCreate(['empresa_id' => $empresa->id]);
            $dados = $config->dados ?? [];
            foreach (['nfe_emite', 'nfce_emite'] as $flag) {
                if (array_key_exists($flag, $d)) {
                    $dados['fiscal'][$flag] = (bool) $d[$flag];
                }
            }
            if (array_key_exists('sped_emite', $d)) {
                $dados['sped']['emite'] = (bool) $d['sped_emite'];
            }
            foreach (['contador', 'cadastro'] as $bloco) {
                if (array_key_exists($bloco, $d)) {
                    $dados[$bloco] = array_filter(array_merge($dados[$bloco] ?? [], $d[$bloco]), fn ($v) => $v !== null && $v !== '');
                }
            }
            $config->dados = $dados;
            $config->save();
        });

        return $this->fiscalShow($request, $empresaId);
    }

    /** PUT /empresas/{id}/nfce-token — CSC da NFC-e (id + token, token encriptado). */
    public function nfceToken(Request $request, int $empresaId): JsonResponse
    {
        $empresa = $this->empresaDoGrupo($request, $empresaId, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $empresaId);

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
        $empresa = $this->empresaDoGrupo($request, $empresaId, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $empresaId);

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
        $empresa = $this->empresaDoGrupo($request, $empresaId, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $empresaId);
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
        $empresa = $this->empresaDoGrupo($request, $empresaId, exigirAtiva: true);
        $this->autorizarNaEmpresa($request, 'empresa.edit', $empresaId);

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
            $int['pix'] = IntegracaoTenant::cifrarBloco(
                $d['pix'], ['client_secret', 'webhook_hmac_secret'], is_array($int['pix'] ?? null) ? $int['pix'] : [],
            );
        }
        if (array_key_exists('cartao', $d)) {
            $int['cartao'] = IntegracaoTenant::cifrarBloco(
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

    /** @param array<string,mixed> $dados */
    private function validarConfiguracoesFinanceirasDoTenant(array $dados): void
    {
        $tenantAccountId = app(TenantEnvelopeRuntime::class)->current()?->tenantAccountId;
        if ($tenantAccountId === null) {
            return;
        }

        foreach (self::CONFIGURACOES_FINANCEIRAS as $chave => $tabela) {
            if (! array_key_exists($chave, $dados) || $dados[$chave] === null || $dados[$chave] === '') {
                continue;
            }
            $id = filter_var($dados[$chave], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $tenantDaConfiguracao = $id === false ? null : DB::table($tabela)->whereKey($id)->value('tenant_account_id');
            if ($tenantDaConfiguracao === null || (int) $tenantDaConfiguracao !== $tenantAccountId) {
                throw ValidationException::withMessages([$chave => 'A configuracao financeira deve pertencer ao tenant ativo.']);
            }
        }
    }

    private function empresaDoGrupo(Request $request, int $empresaId, bool $exigirAtiva = false): Empresa
    {
        $user = $request->user();
        $grupo = (int) ($user->empresa?->grupo_id ?? $user->grupo_id);

        $empresa = Empresa::query()->where('grupo_id', $grupo)->findOrFail($empresaId);
        abort_unless($user->podeAcessarEmpresa($empresaId), 404);

        if ($exigirAtiva && ! $user->acessoElevado($empresaId)) {
            abort_unless(app(TenantContext::class)->empresaId() === $empresaId, 404);
        }

        return $empresa;
    }
}
