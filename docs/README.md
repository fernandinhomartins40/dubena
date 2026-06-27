# Documentação — Dubena / ERP-NOVO

Organizada por finalidade. **Comece pelo que está VIGENTE.** A raiz desta pasta
contém **apenas este índice** — todo documento vive numa subpasta classificada.

| Pasta | O que é | Usar? |
|---|---|---|
| [01-vigente/](01-vigente/) | **Planos, PRDs e specs ATUAIS** — guiam a implementação agora (SPA React + Laravel API). | ✅ **SIM** |
| [06-runbooks/](06-runbooks/) | **Procedimentos operacionais executáveis** — homologação e go-live. Usar na virada. | ✅ **SIM (operação)** |
| [02-auditoria-legado/](02-auditoria-legado/) | PRDs FIÉIS (linha-a-linha) do sistema legado — referência do comportamento original. | 📖 referência |
| [03-modernizacao-filament/](03-modernizacao-filament/) | Auditoria da fase Filament (DESCARTADA — viramos SPA React). | 🗄️ histórico |
| [04-historico/](04-historico/) | Planos antigos supersedidos (Filament / fases F0–F4). | 🗄️ histórico |
| [05-planejamento-historico/](05-planejamento-historico/) | Auditorias, inventários e planos concluídos (**fotos no tempo**). **Não** medir auditoria por aqui — medir o código. | 🗄️ histórico |

> ⚠️ **Para auditorias:** a fonte da verdade é **o código**, não estes documentos.
> As pastas 03/04/05 são registros do passado e podem divergir do estado atual de
> propósito — não os trate como especificação vigente.

## Por onde começar (implementação)
1. [01-vigente/PLANO_IMPLEMENTACAO_SPA.md](01-vigente/PLANO_IMPLEMENTACAO_SPA.md) — **plano operacional
   por fases** (F1 Produto … F10): o passo-a-passo de execução, consome os IMPL como contrato.
2. [01-vigente/PLANO_SPA_REACT.md](01-vigente/PLANO_SPA_REACT.md) — plano macro/ADR (fases S1–S8).
3. [01-vigente/MAPA_NAVEGACAO_ALVO.md](01-vigente/MAPA_NAVEGACAO_ALVO.md) — como as telas dispersas
   do legado se reorganizam em páginas completas (de-para; reorganizar ≠ eliminar).
4. [01-vigente/IMPL_00_INDICE.md](01-vigente/IMPL_00_INDICE.md) — PRDs de implementação por módulo
   (auditados do código), na ordem de implementação. Cada um é o CONTRATO (paridade + DoD).

## Controle de acesso (RBAC/ABAC/segurança)
- [01-vigente/PLANO_CONTROLE_ACESSO_HIERARQUIA.md](01-vigente/PLANO_CONTROLE_ACESSO_HIERARQUIA.md) —
  arquitetura oficial de autenticação, autorização (RBAC+ABAC), hierarquia, segurança e auditoria.
  **Fases A0–A7 concluídas** (enforcement central, Central de Acessos, hierarquia, ABAC, 2FA/lockout/
  sessões, auditoria de segurança, granularidade fina). O documento traz a tabela de fases com o
  status de cada uma.

## Operação (virada para produção)
1. [06-runbooks/F14_RUNBOOK_HOMOLOGACAO.md](06-runbooks/F14_RUNBOOK_HOMOLOGACAO.md) — UAT lado-a-lado
   com o CTRL-WEB, módulo a módulo (pré-requisito: F00–F13).
2. [06-runbooks/F16_RUNBOOK_GOLIVE.md](06-runbooks/F16_RUNBOOK_GOLIVE.md) — cutover & go-live
   executável (comando, critério de sucesso e ponto de rollback por passo).

## Histórico relevante (consulta, não especificação)
- [05-planejamento-historico/AUDITORIA_PARIDADE_MODERNIZACAO.md](05-planejamento-historico/AUDITORIA_PARIDADE_MODERNIZACAO.md)
  — auditoria forense linha-a-linha legado × backend × SPA (foto no tempo).
- [05-planejamento-historico/PLANO_MESTRE_CONCLUSAO_MODERNIZACAO.md](05-planejamento-historico/PLANO_MESTRE_CONCLUSAO_MODERNIZACAO.md)
  — backlog derivado da auditoria (fases ✅ concluídas).
- [05-planejamento-historico/AUDITORIA_FRONTEND.md](05-planejamento-historico/AUDITORIA_FRONTEND.md)
  — diagnóstico que originou a F17 (reorganização de frontend, concluída).
- [05-planejamento-historico/F02_CLASSIFICACAO_MULTITENANCY.md](05-planejamento-historico/F02_CLASSIFICACAO_MULTITENANCY.md)
  — classificação tenancy dos models (referência da aplicação de `BelongsToTenant`/RLS).

---

> **Convenção:** raiz do repositório mantém só `README.md` e `SEGREDOS_LOCAIS.md`;
> raiz de `docs/` mantém só este `README.md`. Todo novo documento entra numa subpasta
> classificada (vigente / runbook / referência / histórico) — nada solto.
