<?php

/*
|--------------------------------------------------------------------------
| Flags de segurança — endurecimento gradual (Strangler)
|--------------------------------------------------------------------------
|
| Lido via config() para funcionar sob `config:cache` em produção. Controlado
| por env para ligar/desligar por ambiente sem alterar código (kill-switch).
|
*/

return [

    /*
    | FASE 4 Bloco A (D11) — fechamento do BYPASS de autorização por AJAX.
    |
    | O AuthorizeCustom liberava QUALQUER request AJAX (header X-Requested-With)
    | sem checar permissão (AuthorizeCustom: `if ($request->ajax()) return true`).
    | Como o front (jQuery) envia esse header em toda chamada $.ajax/$.post, isso
    | tornava a autorização amplamente contornável nas rotas "cheias" de gravação.
    |
    | Com a flag LIGADA, requests AJAX para rotas que NÃO são `ajax.*` passam a
    | ser autorizadas pela mesma regra das telas cheias (checa permissão). As
    | rotas nomeadas `ajax.*` (dropdowns/buscas auxiliares) seguem liberadas para
    | usuário autenticado — elas não têm entrada em `permissoes` e são leitura.
    |
    | DESLIGADA (default), mantém o comportamento legado (bypass) — kill-switch
    | para deploy sem mudança de comportamento e rollback instantâneo.
    */
    'fechar_bypass_ajax' => (bool) env('SEGURANCA_FECHAR_BYPASS_AJAX', false),

];
