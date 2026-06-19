# MODERNIZAÇÃO (auditoria de código) — Relatórios / Dashboards · D12

> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`12_relatorios.md`](12_relatorios.md).
> Paradigma-alvo: ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md). 26 Report*Controllers.

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `dd($nao)` ATIVO no Reportvendapdv | 🔴 | ✅ **removido** (F0) | ausente em `ReportvendapdvController.php` |
| `ReportCaixa` CONNECT BY/START WITH (Oracle) | 🔴 quebra no PG | ✅ **traduzido** (WITH RECURSIVE) | `ReportCaixaController.php:600,881,964` |
| rownum órfão em reports | 🔴 | ✅ **traduzido** (comentários `rownum→limit`) | `ReportmovimentacaoController.php:155`, `ReportResumoVendasController.php:78` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- 26 controllers de relatório, cada um uma tela de filtro→PDF/XLS; lógica SQL pesada no
  controller. Sem central de relatórios; exportação em telas isoladas.

---

## 3. REGRAS A PRESERVAR

- Os relatórios fiscais/financeiros/vendas (DRE, caixa, comissões, etc.) e suas fórmulas
  (já traduzidas p/ PG na F0). Exportação XLSX/PDF (migrada p/ PhpSpreadsheet na F2).

---

## 4. PÁGINA-ALVO (ver MODERN_00)

- **Área de Relatórios** dedicada no sidebar (grupo *Relatórios*), com cada relatório como
  página de filtros + preview + export (PDF/XLSX). Dashboards com widgets do Filament
  (gráficos) em vez de telas estáticas. Query em Service parametrizado (sem SQL/whereRaw
  interpolado no controller).

---

## 5. PENDÊNCIAS RESIDUAIS

- Parametrizar whereRaw/`$_GET` interpolados remanescentes nos reports (segurança).
- SQL pesado no controller → mover p/ query services testáveis.

> **Decisão herdada:** REESCREVER a camada de relatórios. Bugs 🔴 (dd/Oracle) já fechados na F0.
