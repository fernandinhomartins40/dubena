<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Geocoding (N2 — gate externo). Usa a MESMA env que o painel de status
    // (SateliteStatusController → "Google Maps"), para o indicador refletir de
    // verdade se o geocoding funciona. GEOCODING_API_KEY mantido como fallback.
    'geocoding' => [
        'key' => env('GOOGLE_MAPS_KEY', env('GEOCODING_API_KEY')),
    ],

    // PIX (N7 — gate bancário, Itaú). Segredos só no .env.
    // webhook_secret       = segredo compartilhado no header X-Webhook-Token (autentica o chamador);
    // webhook_hmac_secret  = segredo do HMAC-SHA256 sobre o corpo cru (integridade/origem — S-1);
    // webhook_signature_header = nome do header onde o PSP envia a assinatura hex.
    'pix' => [
        'enabled' => env('PIX_ENABLED', false),
        // Gate do driver (F6): 'fake' = BR Code sintético (dev/CI); um PSP real
        // ('itau', …) ativa o registro de verdade, com credencial POR EMPRESA.
        'driver' => env('PIX_DRIVER', 'fake'),
        'psp' => env('PIX_PSP', 'itau'),
        'ambiente' => env('PIX_AMBIENTE', 'homologacao'),
        'webhook_secret' => env('PIX_WEBHOOK_SECRET'),
        'webhook_hmac_secret' => env('PIX_WEBHOOK_HMAC_SECRET'),
        'webhook_signature_header' => env('PIX_WEBHOOK_SIGNATURE_HEADER', 'X-Webhook-Signature'),
    ],

    // Push FCM (N10 — gate). 'v1' ativa o transporte real (FCM HTTP v1 via service
    // account do Firebase); qualquer outro valor mantém o Fake (CI/homolog). O v1
    // usa as credenciais do bloco 'firebase' (FIREBASE_CREDENTIALS/PROJECT_ID).
    // server_key é o legacy (depreciado pelo Google), mantido só para migração.
    'fcm' => [
        'driver' => env('FCM_DRIVER', 'fake'),
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    // Firebase Auth (F1 — GATE). Verifica o ID token do telefone (phone-auth do app).
    // 'kreait' ativa o verificador real (precisa do JSON de service account); qualquer
    // outro valor mantém o Fake (CI/homolog). project_id valida a audience do token.
    'firebase' => [
        'driver' => env('FIREBASE_DRIVER', 'fake'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        // Caminho do arquivo de credenciais (service account JSON) — só no servidor.
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    // Driver fiscal (N9/C7b — GATE). 'nfephp' ativa o driver SEFAZ real.
    // Lido via config() (não env() direto) p/ funcionar com config:cache em prod.
    'fiscal' => [
        'driver' => env('FISCAL_DRIVER', 'fake'),
    ],

    // Driver de cobrança/boleto (N7/F08 — GATE bancário). 'caixa' (104) ou 'itau'
    // (341) ativam o CNAB real; qualquer outro valor mantém o Fake (CI/homolog).
    'cobranca' => [
        'driver' => env('COBRANCA_DRIVER', 'fake'),
    ],

    // Conciliação contábil (CONSISA) — F08. API externa; gate por URL configurada.
    // As credenciais por empresa ficam em empresa_configs.dados['consisa'].
    'consisa' => [
        'url' => env('CONSISA_API_URL'),
        'enabled' => (bool) env('CONSISA_API_URL'),
    ],

    // Driver de pagamento online (N10/F12 — GATE Rede). 'erede' ativa o real; senão Fake.
    'pagamento' => [
        'driver' => env('PAGAMENTO_DRIVER', 'fake'),
    ],
    'erede' => [
        'url' => env('EREDE_API_URL', 'https://api.userede.com.br/erede/v1'),
        'pv' => env('EREDE_PV'),
        'token' => env('EREDE_TOKEN'),
    ],

    // Driver de rastreamento GPS (N11/F12 — GATE). 'sgcasa' ativa o real; senão Fake.
    'monitora' => [
        'driver' => env('MONITORA_DRIVER', 'fake'),
        // Encaixar o trajeto nas ruas via Roads API (paga, com cache no banco).
        // Desligado por padrão: quem liga aceita o custo da primeira apuração
        // de cada trecho novo.
        'snap_vias' => env('MONITORA_SNAP_VIAS', false),
        // Malha viária do OpenStreetMap para as ferramentas assistidas de
        // cerca. Gratuita e sem cota, mas é serviço comunitário: fica desligada
        // por padrão para a suíte nunca sair para a rede, e o cache no banco
        // evita repetir consulta.
        'malha_viaria' => env('MONITORA_MALHA_VIARIA', false),
    ],
    'sgcasa' => [
        'url' => env('SGCASA_API_URL'),
        'token' => env('SGCASA_TOKEN'),
    ],

    // Traccar — o rastreador que a frota da Dubena usa de fato. Credenciais
    // herdadas da tabela `configs` do sistema legado de monitoramento.
    'traccar' => [
        'url' => env('TRACCAR_URL'),
        'usuario' => env('TRACCAR_USUARIO'),
        'senha' => env('TRACCAR_SENHA'),
        // Cadastrar veículo automaticamente para aparelho ainda sem cadastro no
        // ERP. Sem isto, aparelho novo instalado num caminhão fica invisível até
        // alguém cadastrá-lo à mão — e ninguém percebe que está faltando.
        'autocadastrar' => env('TRACCAR_AUTOCADASTRAR', false),
        // Empresa dona da conta no Traccar. OBRIGATÓRIA para o auto-cadastro:
        // a conta no provedor é uma só e a lista de aparelhos é global, então
        // sem dizer de quem são os rastreadores o sistema replicaria os mesmos
        // aparelhos em toda empresa ativa.
        'empresa_id' => env('TRACCAR_EMPRESA_ID'),
    ],

    // Flags dos gates externos para o painel de status (SateliteStatusController).
    // Resolvidas aqui (build-time do config:cache) para não dependerem de env()
    // em runtime — env() retorna vazio quando a config está cacheada (prod).
    'integracoes' => [
        'pix' => (bool) env('PIX_ENABLED', false) || (bool) env('PIX_CLIENT_ID'),
        'email_smtp' => env('MAIL_MAILER') === 'smtp' && (bool) env('MAIL_HOST'),
        'google_maps' => (bool) env('GOOGLE_MAPS_KEY', env('GEOCODING_API_KEY')),
        'fcm_push' => (bool) env('FCM_SERVER_KEY'),
        'cobranca' => env('COBRANCA_DRIVER', 'fake') !== 'fake',
        'consisa' => (bool) env('CONSISA_API_URL'),
    ],

    /**
     * PABX / bina (T4.4). Segredo DEDICADO — o legado usava sha1(APP_KEY) e a
     * APP_KEY dele vazou no repositorio (auditoria §4 §2.6-7). Sem este valor
     * o webhook recusa tudo (fail-closed).
     */
    'pabx' => [
        'webhook_secret' => env('PABX_WEBHOOK_SECRET', ''),
        'empresa_id' => env('PABX_EMPRESA_ID'),
    ],

];
