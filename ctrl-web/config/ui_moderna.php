<?php

/*
|--------------------------------------------------------------------------
| Feature flags da UI moderna (Filament 3) — FASE 3
|--------------------------------------------------------------------------
|
| Durante o Strangler (Fase 3/4), cada módulo pode ter a UI nova (Filament,
| sob /admin) ligada ou desligada independentemente. Com a flag DESLIGADA, o
| usuário segue na tela legada (AdminLTE); LIGADA, é direcionado ao recurso
| Filament. Isso dá coexistência + rollback instantâneo (basta desligar a flag).
|
| Lido via config() para funcionar sob `config:cache` em produção. Controlado
| por env (UI_MODERNA_*) para alternar por ambiente sem alterar código.
|
| Chave = "módulo" (nome curto, casado com o helper uiModernaAtiva()).
|
*/

return [

    // Liga o painel Filament como um todo (a rota /admin). Se false, mesmo com
    // módulos marcados true abaixo, nada é direcionado para lá.
    'habilitado' => (bool) env('UI_MODERNA_HABILITADO', true),

    // Flags por módulo. O piloto da Fase 3 é o cadastro geográfico.
    'modulos' => [
        'cidade' => (bool) env('UI_MODERNA_CIDADE', false),
        'bairro' => (bool) env('UI_MODERNA_BAIRRO', false),
    ],

];
