# MODERNIZAÇÃO (auditoria de código) — Vale-Gás / Convênio / Cond. Pagamento · D07

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`07_valegas_convenio.md`](07_valegas_convenio.md).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `gerarCodigo` recursão sem retorno (código duplicado) | 🔴 | ✅ **corrigido** (retorna recursão; checa coluna `codigo`) | `ValegasvendaController.php:470-479` |
| Conveniogb hardcode "empresa 2" + Oracle CONNECT BY | 🔴 | ✅ **traduzido p/ PG** (generate_series) | `ConveniogbgestaoController.php:94+` |
| Convênio SQLi `$produto` | 🔴 | ⚠️ **verificar bindings** nas queries de chart | `ConveniogbgestaoController.php` |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- Vale-gás: venda/consulta/baixar/cancelar em controllers separados; fluxo fragmentado.
- Convênio: fechamento + dashboards (charts) montados com SQL grande no controller.
- Condição de pagamento: cadastro de apoio (usado em cliente/pedido).

---

## 3. REGRAS A PRESERVAR

- Geração de código único do vale-gás; ciclo venda→baixa/cancelamento; fechamento de
  convênio (limite, período); condição de pagamento (tipo à vista/cartão/boleto).

---

## 4. BLUEPRINT DE MODERNIZAÇÃO

- Vale-gás como recurso com ciclo de status visível; charts de convênio via query service
  parametrizado (sem SQL no controller).
- CondicaopagamentoResource Filament (cadastro de apoio simples).

---

## 5. PENDÊNCIAS RESIDUAIS

- `ConveniogbgestaoController.php` — confirmar que as queries de chart usam bindings (não
  interpolam `$produto`/empresa). SQL grande no controller → mover p/ query service.

> **Decisão herdada:** REFATORAR · REESCREVER. Bugs 🔴 (gerarCodigo, Oracle/hardcode) já
> fechados na F0.
