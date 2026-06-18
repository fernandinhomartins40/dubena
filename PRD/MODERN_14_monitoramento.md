# MODERNIZAÇÃO (auditoria de código) — Monitoramento GPS (App\Monitora) · D14

> Auditoria do CÓDIGO REAL (pós F0–F4A) vs. PRD fiel [`14_monitoramento.md`](14_monitoramento.md).

---

## 1. ANTES × AGORA (verificado)

| Item (PRD fiel) | Original | HOJE (auditado) | Ref. |
|---|---|---|---|
| `getEmpresas` sem `use Session` | 🔴 fatal | ✅ **corrigido** (`use Session` presente) | `app/Monitora/Http/Controllers/ApiController.php:5,32` |
| Hardcode "empresa 2" em getPedidosPendentes | 🔴 quebra multi-empresa | ⚠️ verificar | `Monitora/Http/Controllers/SearchController.php:65` |
| Guard próprio `monitora` (schema separado) | — | ✅ presente (F2 unificação) | `config/auth.php` |

---

## 2. AVALIAÇÃO

- Subsistema com schema/guard próprios (`monitora`), estrutura organizada (Console/Events/
  Jobs/Models/Repository). Funções: rastreamento GPS, cercas, eventos, localização de clientes.
- Tem seu PRÓPRIO menu/permissões (`App\Monitora\Models\Menu`) — separado do ERP.

---

## 3. PÁGINA-ALVO (ver MODERN_00)

- Painel de monitoramento (mapa + status de veículos/entregas) como página dedicada;
  navegação declarativa (grupo próprio). Permissões via roles (alinhar ao D11).
- Manter jobs/eventos de rastreamento; modernizar só a camada de apresentação.

---

## 4. PENDÊNCIAS RESIDUAIS

- `SearchController.php:65` (getPedidosPendentes) — confirmar se ainda filtra empresa 2 fixa.
- Alinhar o menu/permissões próprios do Monitora à decisão de RBAC do D11 (sem menu-no-banco).

> **Decisão herdada:** REESCREVER (camada de UI). Bug 🔴 (fatal getEmpresas) já fechado na F0.
