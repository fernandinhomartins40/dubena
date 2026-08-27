# Instruções duráveis — transformação SaaS

Este repositório possui um objetivo ativo de longa duração: implementar integralmente `docs/01-vigente/PLANO_TRANSFORMACAO_SAAS.md`.

## Fonte de verdade

1. Ler primeiro `docs/01-vigente/PROCEDIMENTO_EXECUCAO_CONTINUA_SAAS.md`.
2. Consultar `docs/01-vigente/implementacao-saas/ESTADO_ATUAL.md` para retomar exatamente do último checkpoint.
3. Cumprir as releituras e gates de `docs/01-vigente/PLANO_TRANSFORMACAO_SAAS.md`.
4. Não tratar documentação como substituta do código, schema efetivo, dados e testes.

## Regras de execução

- Trabalhar em microlotes de uma a três tarefas, na ordem de dependência F0→F10.
- Não iniciar o próximo microlote antes de validar e registrar o atual.
- Usar subagentes apenas para recortes independentes e delimitados; evitar contexto duplicado e edição concorrente dos mesmos arquivos.
- Preservar alterações preexistentes do usuário e nunca modificar `ctrl-web/`.
- Não declarar conclusão por presença de tabela, rota, tela ou teste superficial: o gate executável precisa passar.
- Nunca converter ausência de contexto, fonte, linhas ou checks em sucesso/vazio normal.
- Não inventar limites, horários de renovação ou temporizadores do Codex. Registrar somente valores expostos pelo ambiente ou `Retry-After` real.
- Quando houver interrupção de serviço/limite, deixar o checkpoint autocontido antes de parar; a retomada começa por `ESTADO_ATUAL.md`.
- Não realizar push/deploy destrutivo ou irreversível sem destino, impacto e rollback inequivocamente verificados, mesmo havendo autonomia para o trabalho local.

## Condição de parada

Continuar enquanto houver trabalho seguro e em escopo. Parar somente quando todos os gates do plano estiverem aprovados, ou quando uma dependência externa indispensável impedir progresso em todas as frentes seguras restantes. Nesse caso, registrar evidência, tentativas e ação exata necessária.
