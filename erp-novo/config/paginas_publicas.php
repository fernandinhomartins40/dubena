<?php

/**
 * Páginas públicas exigidas pelas lojas de aplicativo (Play e App Store):
 * política de privacidade e instruções de exclusão de conta.
 *
 * Os dados do CONTROLADOR (LGPD) vêm do ambiente, não do banco: a página é
 * anônima e roda como `erp_app` sem tenant — sob RLS a tabela `empresas`
 * devolveria vazio. E não são constante no código porque cada revenda que
 * publicar o seu app tem o seu controlador.
 *
 * Sem razão social configurada as rotas respondem 404: política de privacidade
 * sem controlador identificado não serve à loja e induziria o titular a erro.
 */
return [
    'app_nome' => env('PUBLICO_APP_NOME', 'Gás em Casa'),
    'razao_social' => env('PUBLICO_RAZAO_SOCIAL'),
    'cnpj' => env('PUBLICO_CNPJ'),
    'endereco' => env('PUBLICO_ENDERECO'),
    'telefone' => env('PUBLICO_TELEFONE'),
    'whatsapp' => env('PUBLICO_WHATSAPP'),
    'email' => env('PUBLICO_EMAIL'),
    // Data da versão vigente do texto — muda quando o texto muda, não a cada deploy.
    'vigencia' => env('PUBLICO_POLITICA_VIGENCIA', '2026-10-29'),
];
