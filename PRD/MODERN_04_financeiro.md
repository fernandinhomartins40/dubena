# MODERNIZAÇÃO (auditoria de código) — Financeiro / Tesouraria · D04

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`04_financeiro.md`](04_financeiro.md).
> Inclui Financeiro, Caixa, Cheque, Boleto, Conta, Pix, Fechamentos e os Processors (motor).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| Caixa `wwhere` / recibo CR snake | 🔴 | ✅ **corrigido** (F0) | `CaixaController.php:277,836` |
| `$dada` em vez de `$data` (cheque) | 🔴 grava sem banco | ✅ **corrigido** (F0) | Chequerecebido |
| Processors caracterizados (F1) | sem testes | ✅ **golden master** (custo/baixa/parcela) | `app/Processors/*` |
| `getLancamentosFinanceiros` SQLi | 🔴 | ❌ **ABERTO** (`$valorPesquisa` interpolado) | `FinanceiroController.php:355-365` |
| `case 4;` (ponto-e-vírgula) no switch | (achado agora) | ❌ **bug** (case malformado) | `FinanceiroController.php:361` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- **Motor financeiro é maduro** (Processors com transação, caracterizados na F1) — preservar.
- **Telas**: Financeiro/Caixa são forms densos; baixa de título com rateio/encontro de contas
  é complexa e sem preview do efeito. Filtro de lançamentos (`getLancamentosFinanceiros`) é
  poderoso mas inseguro (SQLi) e montado com whereRaw.
- HTML no backend; relatórios de caixa em telas antigas.

---

## 3. REGRAS A PRESERVAR (NÃO regredir — caracterizadas na F1)

- `financeiroProcessor`: setParcelasRequest (rateio de desconto por parcela), gravar (com/sem
  baixa), rateio 100% centro/plano. `caixaProcessor`: baixa, movimento, saldo, fechamento.
- Estorno condicionado (cheque/boleto/online). Autorização granular do Caixa.

---

## 4. BLUEPRINT DE MODERNIZAÇÃO (Filament 3 + Service)

- Manter Processors como **Domain Services** (já testáveis); UI fina por cima.
- **Tela de baixa** com preview do efeito no saldo (antes/depois) e rateio visual.
- **Extrato/lançamentos** como tabela Filament filtrável (substituir o whereRaw por query
  builder parametrizado + filtros tipados) — corrige o SQLi de quebra.
- Conta/Plano/Centro de contas como Resources Filament (árvore p/ plano/centro).

---

## 5. PENDÊNCIAS RESIDUAIS (arquivo:linha)

- `FinanceiroController.php:355-365` — `whereRaw(... = $valorPesquisa)` interpolado (SQLi de
  filtro) → **parametrizar com bindings**. Prioridade de segurança.
- `FinanceiroController.php:361` — `case 4;` (ponto-e-vírgula no lugar de `:`) → case
  malformado; revisar o switch de tipo de pesquisa.
- `FinanceiroController.php:320,347` — outros whereRaw interpolados a parametrizar.

> **Decisão herdada:** REFATORAR núcleo (Processors→Services) · REESCREVER cadastros/telas.
> O SQLi do filtro de lançamentos é quick win de segurança aplicável já.
