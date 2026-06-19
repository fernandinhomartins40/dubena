# MODERNIZAÇÃO (auditoria de código) — Colaboradores / RH · D08

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`08_colaboradores.md`](08_colaboradores.md).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `dd($data)` ATIVO no Recessotipo::update | 🔴 editar recesso quebrado | ✅ **removido** (sobrou só `//dd` comentado) | `RecessotipoController.php:27` |
| `Tiporecessos` unique coluna errada (grupo×empresa) | 🟠 | ⚠️ verificar (Request pode não existir; checar controller) | — |
| `Colaboradorfamilia` scaffold vazio | 🟡 morto | ❌ **intacto** (7 métodos só com doc/`//`) | `ColaboradorfamiliaController.php:22-88` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- Colaborador: cadastro longo (dados + cargo + comissão + família + exames); família é
  scaffold vazio. Comissões têm cálculo (refatorar).
- Vários cadastros de apoio (cargo, estado civil, parentesco, tipo exame, tipo recesso).

---

## 3. REGRAS A PRESERVAR

- Cálculo de comissões do colaborador; vínculo colaborador↔setor; recessos por período.

---

## 4. BLUEPRINT DE MODERNIZAÇÃO

- ColaboradorResource Filament com abas (Dados, Cargo, Comissão, Família, Exames);
  RelationManagers para família/exames (substituem scaffolds vazios).
- Comissões como Service testável; cadastros de apoio como Resources simples.

---

## 5. PENDÊNCIAS RESIDUAIS (arquivo:linha)

- `ColaboradorfamiliaController.php:22-88` — scaffold 100% vazio → reescrever (RelationManager)
  ou remover.
- Confirmar `unique` de Tiporecessos (coluna grupo_id × empresa_id) e sobreposição de período
  de recesso (padrão furado recorrente).

> **Decisão herdada:** REESCREVER · REFATORAR comissões. Bug 🔴 (dd) já fechado na F0.
