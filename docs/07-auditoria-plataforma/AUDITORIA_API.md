# AUDITORIA — API

> Base: [routes/api.php](../../erp-novo/routes/api.php) (724 linhas), [channels.php](../../erp-novo/routes/channels.php), controllers `Api/*`.

## 1. Organização e contrato

Uma API JSON única, três superfícies segregadas por prefixo:
- `POST /api/login`, `/api/health`, webhooks e login de app **públicos** (com throttle/segredo).
- `/api/admin/*` — SPA, sob `auth:sanctum` + `tenant` + `throttle:api`.
- `/api/app/v1/*` — apps, mesmo grupo autenticado.
- `/api/superadmin/*` — guard `platform` isolado, **fora** do middleware de tenant.

**Contrato de resposta** (documentado no topo de [api.php](../../erp-novo/routes/api.php)): JSON uniforme, número cru, sem View/Redirect. Erros no padrão Laravel `{message, errors}`; tenant não resolvido → 409; guest em rota autenticada → 401 (não 500) graças ao `redirectGuestsTo(null)` + `shouldRenderJsonWhen` no [bootstrap/app.php](../../erp-novo/erp-novo/bootstrap/app.php). Semântica HTTP correta: 402 (licença), 403 (RBAC), 409 (tenant), 422 (validação), 423 (2FA), 429 (lockout).

## 2. Versionamento

- Apps: prefixo **`app/v1`** — versionado explicitamente. Bom.
- Admin/SuperAdmin: **sem versão** no path. Aceitável para consumidor interno (SPA), mas limita evolução com quebra.

## 3. Consistência e padronização

- Recursos REST-like (`GET/POST/PUT/DELETE` por entidade) com `whereNumber('id')` — bom para roteamento seguro.
- Rotas estáticas colocadas **antes** das dinâmicas (`clientes/exportar` antes de `clientes/{id}`, `relatorios/catalogo` antes de `{slug}`) — cuidado correto.
- **Inconsistência de shape**: parte das respostas usa **API Resources** (`ClienteResource`, `PedidoResource`, `ProdutoResource`, `EmpresaResource`) e a maioria monta **arrays manuais** no controller/service. O envelope também varia: `response()->json(['data' => …])` vs `Resource::collection(...)->response()` (que aninha `data` + `meta` de paginação). Isso obriga a SPA/apps a conhecer dois formatos.

## 4. Achados classificados

| ID | Prio | Achado | Evidência | Recomendação |
|---|---|---|---|---|
| API-1 | **P2** | Shape de resposta inconsistente (Resource vs array; envelope `data` variável) | [PedidoController](../../erp-novo/app/Http/Controllers/Api/Admin/PedidoController.php) usa Resource; [EstoqueController](../../erp-novo/app/Http/Controllers/Api/Admin/EstoqueController.php)/mobile montam arrays | Padronizar: sempre `{data, meta?}`; migrar respostas de lista para Resources |
| API-2 | **P2** | Aliases duplicados criam dois contratos por função | [api.php](../../erp-novo/routes/api.php) `cheques/recebidos`, `cobranca/*`, `fiscal/nfe*`, `financeiro/dre`, `conciliacao-contabil` | Escolher a grafia canônica, marcar o resto `@deprecated`, remover após a SPA migrar |
| API-3 | **P2** | Contrato OpenAPI (`openapi-api-admin.yaml`) parcial e manual → drift | não cobre logística/SaaS/aliases | Gerar OpenAPI do código (ou testes de contrato) e versionar no CI |
| API-4 | **P3** | Admin sem versão no path | `/api/admin/*` | Introduzir `/api/admin/v1` ao primeiro breaking change |
| API-5 | **P3** | Paginação fixa em 20, não parametrizável | `->paginate(20)` em Cliente/Pedido | Aceitar `?per_page` com teto |
| API-6 | **P3** | `routes/api.php` monolítico (724 linhas) | idem | Quebrar por domínio (`routes/admin/*.php` via `Route::group`) |
| API-7 | **P4** | Rate-limits ad-hoc por rota (60,1 / 120,1 / 30,1) sem nomes | marketplace, posição, missão | Nomear limiters (`throttle:marketplace`) centralizados |

## 5. Integração com o ERP e apps

- Os apps consomem **exatamente** os contratos server-side (preço/cotação server-side, cliente derivado do token) — a fronteira app↔ERP está limpa e sem bridge HTTP (o legado tinha reconciliação `getToLink`; foi eliminada).
- Broadcast (Reverb) integra apps e SPA no tempo real com autorização por canal — contrato de eventos claro (`pedido.status`, `entregador.posicao`, `PixConfirmado`).
- **Achado API-8 (P3)**: não há mecanismo de negociação de versão de app (o servidor não rejeita/avisa versões antigas de app apesar de receber `app_versao` no login). Útil para forçar update em breaking change.

## 6. Tratamento de erros

Uniforme e testado (`AuditoriaSegurancaTest`, `AppAuthHardeningTest`). Webhook PIX responde 422 genérico sem vazar interno; validação de payload antes do processamento. Bom.

## 7. Conclusão

API **bem desenhada na semântica e na segregação de superfícies**; o débito é de **consistência de contrato** (shapes/aliases/OpenAPI) — puramente de formalização, sem risco funcional. Padronizar o envelope e congelar o OpenAPI antes da homologação reduz o risco de regressão silenciosa entre backend e as 3 superfícies.

→ Plano: [PLANO_API.md](PLANO_API.md)
