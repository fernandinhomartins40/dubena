# MODERNIZAÇÃO (auditoria de código) — SPED (EFD ICMS/IPI + Contribuições) · D03

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`03_sped.md`](03_sped.md).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `catch(Excpetion)` typo (Spedcreditos destroy) | 🔴 destroy quebra | ✅ **corrigido** (F0) | ausente em `Sped*Controller.php` |
| Motor de registros SPED | — | presente, intacto | `app/Processors/Sped/SpedProcessor.php` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- Geração de SPED (ICMS/IPI e Contribuições) é um motor de montagem de registros (blocos).
- UI: telas de geração com filtros de período; download do arquivo TXT. Fluxo técnico,
  pouco feedback (o que entrou em cada bloco, validações).

---

## 3. REGRAS A PRESERVAR

- `SpedProcessor`: montagem de blocos/registros conforme legislação; depende da NF (D02) e
  do financeiro/estoque. Validação real = aceitação pelo validador da Receita (PVA).

---

## 4. BLUEPRINT DE MODERNIZAÇÃO

- Manter o motor; refatorar em Service testável (golden por bloco quando houver dados reais).
- UI: tela de geração com preview de blocos/contagem de registros e validações antes do
  download; histórico de gerações.

---

## 5. PENDÊNCIAS RESIDUAIS

- Geração depende de NF/financeiro/estoque corretos (baseline). Sem dados fiscais reais, o
  golden do SPED fica para validação com PVA (como a emissão SEFAZ do D02).

> **Decisão herdada:** REFATORAR (motor de registros). Bug 🔴 (typo) já fechado na F0.
