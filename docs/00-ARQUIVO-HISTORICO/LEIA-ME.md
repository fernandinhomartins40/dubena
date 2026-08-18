# ⚠️ ARQUIVO HISTÓRICO — NÃO É FONTE DE VERDADE

**Nada nesta pasta descreve o sistema como ele é hoje.**

São documentos de fases concluídas ou abandonadas do projeto. Foram mantidos
porque registram **por que** as decisões foram tomadas — não **o que** vale agora.

---

## Se você quer saber o estado atual

| Pergunta | Onde |
|---|---|
| **O que falta para virar o sistema?** | [`../gauntlet/GUIA_DO_DONO.md`](../gauntlet/GUIA_DO_DONO.md) |
| O que já foi entregue? | [`../gauntlet/STATUS_FINAL.md`](../gauntlet/STATUS_FINAL.md) |
| Como fazer a virada, passo a passo | [`../../deploy/CUTOVER_RUNBOOK.md`](../../deploy/CUTOVER_RUNBOOK.md) |
| Contratos de implementação por módulo | [`../01-vigente/`](../01-vigente/) |
| Como o sistema legado funciona (referência) | [`../02-auditoria-legado/`](../02-auditoria-legado/) |

---

## O que tem aqui e por que foi arquivado

### `03-modernizacao-filament/` — junho/2026
Auditoria e planejamento da interface em **Filament**. **A abordagem foi
descartada** em 2026-06-18 em favor de uma SPA em React + API Laravel. O código
nunca chegou a produção.

*Ainda útil para:* entender o mapeamento tela-a-tela do legado, que foi
reaproveitado no plano da SPA.

### `04-planos-supersedidos/` — junho/2026
Planos de modernização anteriores à virada para SPA, incluindo
`PLANO_IMPLEMENTACAO_MODERNIZACAO.md` (a estratégia Strangler in-place) e o
diagnóstico da Fase 2.

*Substituído por:* `01-vigente/PLANO_SPA_REACT.md` e, depois, pelo
`gauntlet/PLANO_PRODUCAO.md`.

### `05-planejamento-historico/` — junho/2026
Auditorias de paridade e aderência feitas durante a construção da SPA
(`AUDITORIA_FORENSE_MIGRACAO`, `COMPARATIVO_LEGADO_VS_NOVO_ATUAL`, classificação
de multi-tenancy).

*Substituído por:* `gauntlet/AUDITORIA.md`, que é mais recente e mais completa.

### `07-auditoria-plataforma-jul2026/` — julho/2026
Auditoria de 21 documentos sobre backend, banco, API, frontend e segurança.
**Os achados P1 e P2 dela foram implementados** — o que ela apontava já está
corrigido no código.

*Ainda útil para:* o raciocínio por trás de decisões de arquitetura (RLS,
tenancy, estrutura de domínio).

### `08-satelites-e-apps/` — junho a julho/2026
Auditorias e planos dos sistemas satélites: app do consumidor, app do
entregador, logística e a plataforma SaaS. **Todos foram implementados** (ver
memória do projeto: "roadmap PLANO_IMPLEMENTACAO_PLATAFORMA concluído").

Estes arquivos estavam soltos na raiz do repositório, o que os fazia parecer
documentação corrente.

---

## Por que não foram apagados

Um documento obsoleto atrapalha quando é confundido com o atual — não por
existir. O que ele guarda é a **justificativa** de escolhas que hoje parecem
arbitrárias: por que Filament foi descartado, por que o multi-tenant usa RLS em
vez de bancos separados, por que o app do entregador foi reescrito em vez de
adaptado.

Quando alguém perguntar "por que isso é assim?", a resposta costuma estar aqui.
