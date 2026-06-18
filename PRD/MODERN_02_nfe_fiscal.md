# MODERNIZAÇÃO (auditoria de código) — NF-e / NFC-e / SAT / Tributação · D02

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`02_nfe_fiscal.md`](02_nfe_fiscal.md).
> Módulo mais maduro do sistema (decisão: REFATORAR).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `dd()/dump()` no Nfemitida getXmlByTxt | 🔴 debug em prod | ✅ **removido** (F0) | ausente em `NfemitidaController.php` |
| `sha1(APP_KEY)` no `getToken` (S2) | 🔴 previsível | ✅ **corrigido** → `config('integracoes.app_token_key')` + `hash_equals` | `NfwebController.php:85-94` |
| `NfRequest` unique `chaveacesso` PG | (achado agora) | ❌ **risco** (`,NULL,id` / id vazio → `<> ''`) | `NfRequest.php:148,150` |
| Emissão fiscal validada em homologação SEFAZ | pendente | ⏳ **PENDENTE (user)** — Carbon 3/PHP 8.3 podem afetar datas/decimais | — |

---

## 2. DÍVIDA DE UX/UI E FLUXO

- **Núcleo NF-e maduro** (NfemitidaController + NfeImpostoProcessor) — preservar/refatorar.
- Cadastros fiscais (ICMS/IPI/PIS/COFINS/CST/grupo fiscal/operação) são MUITOS CRUDs
  AdminLTE separados; UX fragmentada — montar a malha fiscal exige passar por várias telas.
- Emissão/transmissão via AJAX; status da NF pouco visível como fluxo.

---

## 3. REGRAS A PRESERVAR

- Motor de impostos (init encadeia ICMS/ST/IPI/PIS/COFINS), emissão/transmissão, carta de
  correção, importação XML, SAT/NFC-e. Contratos com SEFAZ (homologação pendente de validar).

---

## 4. BLUEPRINT DE MODERNIZAÇÃO (Filament 3)

- Manter motor fiscal; refatorar em Service testável (oráculo = SEFAZ homologação).
- **Painel fiscal unificado**: agrupar os cadastros da malha fiscal (grupo fiscal + impostos)
  num fluxo coeso (abas/wizard) em vez de ~10 telas isoladas.
- NF como recurso com status visível (rascunho/transmitida/autorizada/cancelada) e ações.

---

## 5. PENDÊNCIAS RESIDUAIS (arquivo:linha)

- `NfRequest.php:148,150` — mesmo padrão `unique` que quebra no PG quando id vazio
  (`unique:nfrecebidas,chaveacesso,NULL,id` / `... . $this->request->get('id') . ,id`).
  **Aplicar o fix do ClienteRequest** (normalizar id→NULL) para evitar 500 ao salvar NF.
- **VALIDAR EMISSÃO EM HOMOLOGAÇÃO SEFAZ** (pendência do usuário) — Carbon 3 + PHP 8.3.

> **Decisão herdada:** REFATORAR (código mais maduro). Bugs 🔴 (debug/segurança) já fechados
> na F0; resta o `unique` PG e a validação fiscal SEFAZ.
