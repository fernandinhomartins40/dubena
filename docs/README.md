# Documentação — Dubena / ctrl-web

Organizada por finalidade. **Comece pelo que está VIGENTE.**

| Pasta | O que é | Usar? |
|---|---|---|
| [01-vigente/](01-vigente/) | **Plano e PRDs ATUAIS** — o que guia a implementação agora (SPA React + Laravel API). | ✅ **SIM** |
| [02-auditoria-legado/](02-auditoria-legado/) | PRDs FIÉIS (linha-a-linha) do sistema legado — referência do comportamento original. | 📖 referência |
| [03-modernizacao-filament/](03-modernizacao-filament/) | Auditoria da fase Filament (DESCARTADA — viramos SPA React). Histórico. | 🗄️ histórico |
| [04-historico/](04-historico/) | Planos antigos supersedidos (Filament/fases F0–F4). Histórico. | 🗄️ histórico |

## Por onde começar (implementação)
1. [01-vigente/PLANO_IMPLEMENTACAO_SPA.md](01-vigente/PLANO_IMPLEMENTACAO_SPA.md) — **plano operacional
   por fases** (F1 Produto … F10): o passo-a-passo de execução, consome os IMPL como contrato.
2. [01-vigente/PLANO_SPA_REACT.md](01-vigente/PLANO_SPA_REACT.md) — plano macro/ADR (fases S1–S8).
3. [01-vigente/MAPA_NAVEGACAO_ALVO.md](01-vigente/MAPA_NAVEGACAO_ALVO.md) — como as telas dispersas
   do legado se reorganizam em páginas completas (de-para; reorganizar ≠ eliminar).
4. [01-vigente/IMPL_00_INDICE.md](01-vigente/IMPL_00_INDICE.md) — PRDs de implementação por módulo
   (auditados do código), na ordem de implementação. Cada um é o CONTRATO (paridade + DoD).

> Raiz do repo mantém só `README.md` e `SEGREDOS_LOCAIS.md`. Toda documentação de
> planejamento/PRD vive aqui em `docs/`.
