<?php

/*
| Regras de acesso do operador da PLATAFORMA (SuperAdmin). É a única identidade
| que cruza todas as revendas: uma senha vazada aqui vaza todas elas.
*/
return [
    // PIE P2-E-06 — 2FA obrigatório ANTES de emitir token de operação. Sem ele
    // configurado, o login devolve só um token de cadastro do 2FA (15 min), que
    // não abre nenhuma outra rota. Desligar só faz sentido em ambiente de teste
    // descartável; fail-closed por padrão.
    'dois_fatores_obrigatorio' => (bool) env('SUPERADMIN_2FA_OBRIGATORIO', true),

    // Lockout POR CONTA: o throttle da rota é por IP e não segura quem troca de
    // IP. Mesmos números do login de revenda (LoginSeguranca).
    'lockout_falhas' => 5,
    'lockout_janela_min' => 15,

    // Validade do token que só serve para cadastrar o 2FA.
    'token_cadastro_2fa_min' => 15,
];
