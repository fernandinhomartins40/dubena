# MODERNIZAÇÃO (auditoria de código) — Integrações / Notificações / Misc · D15

> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`15_integracoes_misc.md`](15_integracoes_misc.md).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `Appnotification` fcmtitle/fcmbody mismatch | 🟠 grava errado | ✅ **corrigido** (consistente) | `AppnotificationController.php:20,123,177,203` |
| Integrações (Pix, eRede, boleto, FCM) | EOL/diversas | atualizadas na F2 (libs) | composer (F2) |

---

## 2. ESCOPO

- Notificações push (FCM), Pix, dashboards gerenciais, busca global, sorteios.
- Misc: pontos de integração externa (gateways de pagamento, mensageria).

---

## 3. PÁGINA-ALVO (ver MODERN_00)

- Notificações como recurso (envio/histórico); dashboards como widgets Filament; integrações
  encapsuladas em Services (não no controller). Descartar o que é obsoleto (PRD fiel marca).

---

## 4. PENDÊNCIAS RESIDUAIS

- Revisar integrações por Service + config (chaves via `config()`, nunca env() direto sob
  config:cache — lição recorrente do projeto).
- Descartar código/integrações obsoletas marcadas no PRD fiel.

> **Decisão herdada:** REESCREVER · DESCARTAR obsoleto. Bug 🟠 (fcm) já fechado na F0.
