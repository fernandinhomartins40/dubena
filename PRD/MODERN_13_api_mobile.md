# MODERNIZAÇÃO (auditoria de código) — API Mobile (App\Api) · D13

> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`13_api_mobile.md`](13_api_mobile.md).
> O subsistema MAIS moderno do projeto (decisão: MANTER/REFATORAR).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| Estrutura em camadas | já boa | ✅ Http/Models/Repository/Resources/Services | `app/Api/` |
| `SecretController` segredo via env/sha1 | (S2/S7) | ✅ **corrigido** (`config('integracoes.*')`) | `SecretController.php:37-61` |
| Passport (auth do app) | v3 → v13 | ✅ atualizado na F2 (L12) | `app/Api/Models/User.php` (HasApiTokens) |

---

## 2. AVALIAÇÃO

- É o subsistema que **já segue boas práticas** (repos/resources/services, Passport).
  Serve de **referência de arquitetura** para a reescrita dos demais módulos.
- Contrato com o app mobile deve ser **preservado** durante toda a modernização do ERP
  (pedido/cliente/situação são consumidos pelo app — D01/D05).

---

## 3. PÁGINA-ALVO

- Não tem UI (é API). Modernização = manter padrão, versionar endpoints, documentar (OpenAPI),
  e garantir que a reescrita do ERP (Services de domínio) **reuse** as mesmas regras que a API
  expõe (evitar duplicar lógica entre ERP e API).

---

## 4. PENDÊNCIAS RESIDUAIS

- Confirmar que não restou SQLi em endpoints de escrita do app (ex.: cadastro de cliente via
  app — `newClientWithPhone` citado no PRD fiel; validar binding no controller atual da API).
- Documentação/versionamento de API.

> **Decisão herdada:** MANTER/REFATORAR. É o modelo a seguir, não a refazer.
