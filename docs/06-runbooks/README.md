# 06 — Runbooks (procedimentos operacionais)

> ✅ **Vigente — para operação.** Procedimentos executáveis passo-a-passo para a
> virada do CTRL-WEB (legado) para o ERP-NOVO + SPA. Diferente das pastas de
> plano/PRD: aqui o foco é **"como operar"**, com comando, critério de sucesso e
> ponto de rollback por passo.

## Conteúdo

| Arquivo | O que é | Quando usar |
|---|---|---|
| [F14_RUNBOOK_HOMOLOGACAO.md](F14_RUNBOOK_HOMOLOGACAO.md) | UAT lado-a-lado com o CTRL-WEB, módulo a módulo (passo, onde fazer na SPA, comparativo esperado). | Antes do go-live, com usuários reais. Pré-requisito: F00–F13. |
| [F16_RUNBOOK_GOLIVE.md](F16_RUNBOOK_GOLIVE.md) | Cutover & go-live executável (pré-condições, `.env`, comandos `golive:check`/`cutover:check`, rollback). | Na virada para produção. Pré-requisito: F00, F02, F08, F09, F15. |

> Ordem natural: **F14 (homologar)** → **F16 (virar)**.
