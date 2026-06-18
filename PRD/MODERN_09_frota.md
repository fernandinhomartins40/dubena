# MODERNIZAÇÃO (auditoria de código) — Frota / Veículos · D09

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`09_frota.md`](09_frota.md).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `Veiculotrocaoleo::getTrocas` rownum (Oracle) | 🔴 quebra no PG | ✅ **traduzido** (`limit 1`/`rnk=1`) | `VeiculotrocaoleoController.php:123,132,165` |
| `catch(Exception)` sem import | 🔴 | ✅ tratado (controller ainda usa Exception; validar import global) | `VeiculotrocaoleoController.php` |
| `Veiculodocumento` scaffold vazio | 🟡 morto | ❌ **intacto** (7 métodos vazios) | `VeiculodocumentoController.php` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- Frota: veículo + abastecimento + troca de óleo + pneu + entrada/saída + documento; vários
  CRUDs separados, documento vazio. Sem visão consolidada por veículo (timeline de manutenção).

---

## 3. REGRAS A PRESERVAR

- Histórico de troca de óleo (última troca/próxima), abastecimentos, pneus, entrada/saída.

---

## 4. BLUEPRINT DE MODERNIZAÇÃO

- VeiculoResource Filament com RelationManagers (abastecimento, troca de óleo, pneu,
  entrada/saída, documento) — uma ficha por veículo com timeline de manutenção.

---

## 5. PENDÊNCIAS RESIDUAIS

- `VeiculodocumentoController.php` — scaffold vazio → RelationManager ou remover.

> **Decisão herdada:** REESCREVER. Bug 🔴 (rownum) já traduzido na F0.
