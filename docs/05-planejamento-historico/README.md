# 05 — Planejamento histórico (auditorias, inventários e planos)

> 🗄️ **Material de planejamento arquivado.** Estes documentos registram o estado e as
> decisões em momentos específicos da migração `ctrl-web` (legado) → `erp-novo` + SPA.

## ⚠️ NÃO usar como fonte de verdade para auditorias futuras

Estes arquivos são **fotos no tempo** — refletem o código e as métricas do dia em que
foram escritos. Eles ficam desatualizados conforme o código evolui. As próprias auditorias
aqui dentro declaram que ignoram documentação como fonte e medem **só o código executável**.

Portanto, uma auditoria futura deve:

- **Medir o código-fonte real** (controllers, models, migrations, services, rotas, jobs, SPA),
  não os percentuais/checklists guardados aqui.
- Tratar este conteúdo apenas como **contexto histórico** (o que se pensava/planejava então),
  nunca como o estado atual.

## Conteúdo

| Arquivo | O que é |
|---|---|
| [AUDITORIA_ADERENCIA_LEGADO_VS_NOVO.md](AUDITORIA_ADERENCIA_LEGADO_VS_NOVO.md) | Auditoria de aderência legado × novo (baseada só em código). |
| [AUDITORIA_FORENSE_MIGRACAO.md](AUDITORIA_FORENSE_MIGRACAO.md) | Auditoria forense da migração (2026-06-22), rastreável a arquivo/linha. |
| [COMPARATIVO_LEGADO_VS_NOVO_ATUAL.md](COMPARATIVO_LEGADO_VS_NOVO_ATUAL.md) | Comparativo do estado pós-fases C1–C12. |
| [INVENTARIO_BACKEND_COMPLETO.md](INVENTARIO_BACKEND_COMPLETO.md) | Inventário linha-a-linha do backend legado. |
| [PLANO_CONCLUSAO_MIGRACAO.md](PLANO_CONCLUSAO_MIGRACAO.md) | Plano de conclusão da migração (derivado da auditoria de aderência). |
| [PLANO_CONCLUSAO_REESCRITA.md](PLANO_CONCLUSAO_REESCRITA.md) | Plano de conclusão da reescrita (status de execução das fases C). |
| [PLANO_REESCRITA_BACKEND.md](PLANO_REESCRITA_BACKEND.md) | Plano de reescrita greenfield do backend. |
| [PLANO_SEEDS.md](PLANO_SEEDS.md) | Plano de seeds para popular a homologação. |
