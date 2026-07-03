# AUDITORIA — ARQUITETURA DA PLATAFORMA

> Engenharia reversa e auditoria técnica — julho/2026. Fonte da verdade: o código-fonte. Documentação auxiliar citada só para contexto; divergências na §8.
> Escopo: ERP-NOVO (backend Laravel + SPA React), app do consumidor (`app-gas-em-casa`), app do entregador (`app-entregador`), módulo Monitora e demais módulos. O legado (`ctrl-web`) está fora do escopo.
> Base verificada nesta auditoria: 393 arquivos PHP em `app/`, 74 migrations, 106 arquivos de teste (suíte rodada: **568 testes / 1859 assertions verdes**, 3 skips, sqlite in-memory), 167 arquivos TS/TSX na SPA, ~115 nos apps.

## 1. Visão geral

Monólito modular **Laravel 12 / PHP ≥ 8.2** servindo três superfícies por uma única API JSON:

| Superfície | Stack | Integração |
|---|---|---|
| SPA administrativa (ERP) | React 18 + Vite + TS ([erp-novo/frontend/](../../erp-novo/frontend/)) | `/api/admin/*` — Sanctum cookie **ou** Bearer |
| App do consumidor | Expo / React Native ([app-gas-em-casa/](../../app-gas-em-casa/)) | `/api/app/v1/*` — Bearer |
| App do entregador | Expo / React Native ([app-entregador/](../../app-entregador/)) | `/api/app/v1/entregador/*` — Bearer |
| Painel SuperAdmin (SaaS) | Mesma SPA (`features/superadmin`) | `/api/superadmin/*` — guard `platform` isolado |

Tempo real: Laravel Echo/Reverb (protocolo Pusher), canais privados por tenant/pedido ([routes/channels.php](../../erp-novo/routes/channels.php)). ETL próprio ([app/Etl/](../../erp-novo/app/Etl/)) faz a carga do legado com invariantes de verificação (Count/Sum/Balance/Integrity).

## 2. Organização — evidências no código

### 2.1 Backend por domínio (bounded contexts)
[app/Domain/](../../erp-novo/app/Domain/) tem ~28 domínios com serviços coesos:
- **Operação**: [PedidoService](../../erp-novo/app/Domain/Pedido/PedidoService.php) (máquina de estados explícita, `EfeitoPedido` PENDENTE↔CONCLUIDO↔CANCELADO), [EstoqueService](../../erp-novo/app/Domain/Estoque/EstoqueService.php), `Logistica/` (Central/Distribuidor/Roteirizador/Jornada), `Missao/`.
- **Dinheiro**: [FinanceiroService](../../erp-novo/app/Domain/Financeiro/FinanceiroService.php), [CaixaService](../../erp-novo/app/Domain/Caixa/CaixaService.php), `Cobranca/` (Boleto/CNAB, [PixService](../../erp-novo/app/Domain/Cobranca/PixService.php)), `Pagamento/`.
- **Fiscal**: `Fiscal/` com Contracts+Drivers ([FiscalService](../../erp-novo/app/Domain/Fiscal/FiscalService.php), [CalculoImpostoService](../../erp-novo/app/Domain/Fiscal/CalculoImpostoService.php) porta fiel da regra tributária do legado, SPED, IBPT) sobre `nfephp-org/sped-nfe`.
- **Plataforma/SaaS**: `Saas/` ([LicencaService](../../erp-novo/app/Domain/Saas/LicencaService.php), [SuperAdminService](../../erp-novo/app/Domain/Saas/SuperAdminService.php)), `Tenant/`, `Acesso/` ([PolicyEvaluator](../../erp-novo/app/Domain/Acesso/PolicyEvaluator.php) ABAC), `Seguranca/`.
- **Mobile**: `Mobile/` (auth Firebase, catálogo, cotação server-side, entrega, rastreamento, push) com Contracts/Drivers.
- **Satélites**: `Satelite/`, `Monitora/`, `Crm/`, `Rh/`, `Frota/`, `Gestao/`.

O padrão **ports & adapters** é consistente: `Fiscal/Contracts+Drivers`, `Logistica/Contracts` (`MatrizDistancia`, `TracadorRota` — Haversine grátis vs Google Routes), `Mobile/Contracts` (`FirebaseVerifier`), `Cobranca/Contracts` (`BoletoDriver`: Itaú/Caixa/Fake), `Monitora/Contracts` (`SgcasaDriver`). Cada externo tem gate de configuração — features se desligam sem env, sem quebrar.

### 2.2 Camada HTTP fina
Controllers em três namespaces espelhando as superfícies (`Api/Admin` 47, `Api/Mobile` 5, `Api/SuperAdmin` 4). Autorização centralizada: trait [AutorizaPorPermissao](../../erp-novo/app/Http/Controllers/Concerns/AutorizaPorPermissao.php) → Gate único ([AuthServiceProvider](../../erp-novo/app/Providers/AuthServiceProvider.php)) → PolicyEvaluator. Bootstrap Laravel 12 moderno ([bootstrap/app.php](../../erp-novo/erp-novo/bootstrap/app.php)): API sempre JSON, `statefulApi()`, exceção de tenant → 409.

### 2.3 Frontend por feature
[frontend/src/features/](../../erp-novo/frontend/src/features/) com 30 domínios espelhando o backend; kit de UI próprio (27 componentes shadcn/Radix); 47 chunks lazy ([routes.tsx](../../erp-novo/frontend/src/routes.tsx)); estado de servidor via TanStack Query; RBAC no cliente (`can`/`canField` em [auth.tsx](../../erp-novo/frontend/src/lib/auth.tsx)); navegação declarativa em [AppShell.tsx](../../erp-novo/frontend/src/layouts/AppShell.tsx) (sem menu-no-banco).

### 2.4 Apps mobile
Expo Router (rotas por arquivo), Zustand em MMKV **cifrado** (chave no keychain/keystore via expo-secure-store — [storage.ts](../../app-gas-em-casa/src/store/storage.ts)), camada HTTP única com retry/normalização ([http.ts](../../app-gas-em-casa/src/helpers/http.ts)) e Echo/Reverb com fallback para polling. O app do entregador replica a estrutura do consumidor (paridade deliberada).

## 3. Pontos fortes

1. **Consistência**: mesmo padrão Controller→Service→Model em todos os domínios; novo cadastro de apoio = 1 linha no [CadastroApoioRegistry](../../erp-novo/app/Domain/Apoio/CadastroApoioRegistry.php).
2. **Separação real**: controllers validam/autorizam e delegam; efeitos patrimoniais (estoque/financeiro/caixa) vivem só nos services, com transação, lock pessimista e idempotência (`estoque_movimentado`, `saldo_resultante`, invariante `Σ movimentos = saldo`).
3. **Baixo acoplamento externo**: Google, PSP, SEFAZ, Firebase, SGCasa entram por contract+driver com gate; circuit breaker + cache no Google Routes ([GoogleRoutesDriver](../../erp-novo/app/Domain/Logistica/Drivers/GoogleRoutesDriver.php)).
4. **Defense-in-depth de tenant** em 3 camadas — ver AUDITORIA_MULTI_TENANT.md.
5. **Testabilidade**: 106 arquivos de teste, suíte verde; contratos de RBAC verificados por teste (`RbacContratoTest`).

## 4. Fragilidades e oportunidades

| # | Achado | Evidência | Impacto | Prior. |
|---|---|---|---|---|
| A1 | Controllers mobile grandes concentrando orquestração | `AppClienteController` 572 linhas | Manutenção na superfície mais quente | Média |
| A2 | Validação inline domina — só 5 FormRequests | 116 `validate()` nos controllers Admin | Regras duplicadas create/update | Média |
| A3 | Serialização manual (arrays) — só 4 API Resources | [app/Http/Resources/](../../erp-novo/app/Http/Resources/) | Shape ad-hoc por controller; drift com SPA/apps | Média |
| A4 | Aliases de rota duplicados | [routes/api.php](../../erp-novo/routes/api.php) L374-393, 424-432, 563-570 (`cheques/recebidos`, `cobranca/*`, `fiscal/nfe*`, `financeiro/dre`) | Duas grafias por contrato; versionar/depreciar difícil | Média |
| A5 | `routes/api.php` monolítico (724 linhas) | idem | Navegabilidade; conflitos de merge | Baixa |
| A6 | Contrato formal não vivo (OpenAPI só do admin, manual) | `docs/01-vigente/openapi-api-admin.yaml` | Pode divergir do código (§8) | Média |
| A7 | Infra dos dois apps duplicada (http/realtime/storage ~idênticos) | `app-gas-em-casa/src/helpers` vs `app-entregador/src/helpers` | Bug precisa correção 2× | Média |

## 5. Fluxo principal (venda no app → entrega)

```
Origem: app consumidor POST /app/v1/pedidos (AppClienteController → PedidoMobileService)
  ↓ cotação server-side (CotacaoMobileService — preço nunca vem do cliente, anti-fraude F3c)
Processamento: matching cliente/setor por geofence → PedidoService::criar (transação; máquina de estados; totais)
  ↓
Persistência: pedidos + pedidoitens + pedidohistoricos (empresa_id/grupo_id via BelongsToTenant + RLS)
  ↓
Integrações: PedidoEntrouNaFila (broadcast Central) + AtribuirPedidoJob (fila, TenantAwareJob)
             → CentralService::atribuir → push FCM + PedidoAtribuido (Reverb)
  ↓
Resposta: JSON {data:…}; app acompanha via canal privado pedido.{id}(.entregador)
          entregador: /entregador/rota (RoteirizadorService nearest-neighbor + cache persistente de trajeto)
          conclusão: /entregador/pedidos/{id}/concluir → efeito CONCLUIDO (baixa estoque + gera financeiro)
```

## 6. Preparação para produção/evolução

- **Multi-tenant**: operacional por linha (empresa/grupo) com RLS; pendências são de ops (Reverb, worker de fila, PostGIS) — ver AUDITORIA_MULTI_TENANT / AUDITORIA_PERFORMANCE.
- **Novos módulos**: registry + contracts tornam satélites baratos.
- **Risco arquitetural nº1**: a fronteira de contrato backend↔superfícies é informal (A3/A6). Antes do dump real, congelar contrato com FormRequests + Resources + OpenAPI gerado.

## 7. Divergências documentação × implementação

1. `openapi-api-admin.yaml` não cobre logística (`central/*`, `missoes/*`), SaaS (`superadmin/*`) nem os aliases — implementação é a referência.
2. `PLANO_IMPLEMENTACAO_PLATAFORMA.md` trata Reverb/PostGIS como pendências de infra; o **código** já suporta ambos (eventos de broadcast; cercas com polígono + ray-casting) — a pendência é operacional, confirmado pela ausência de serviço Reverb/worker no [docker-compose.homolog.yml](../../erp-novo/docker-compose.homolog.yml).
3. `docs/03-modernizacao-filament/` descreve direção (Filament) abandonada — manter só como histórico.

## 8. Conclusão

Arquitetura **sólida e consistente**: monólito modular com fronteiras de domínio claras, contratos para externos e defense-in-depth de tenant. Maior retorno de melhoria: **formalizar contrato** (FormRequests, Resources, OpenAPI vivo) e **reduzir duplicação** (aliases; infra dos apps) — nada exige mudança estrutural.

→ Plano: [PLANO_ARQUITETURA.md](PLANO_ARQUITETURA.md)
