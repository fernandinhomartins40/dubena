# PLANO MESTRE — CONCLUSÃO DOS MÓDULOS SATÉLITES DO ERP-NOVO

> **Fonte da verdade: apenas código-fonte.** Cada lacuna aponta `arquivo:linha`. Nenhuma
> documentação foi usada como base. Gerado a partir de engenharia reversa do `ctrl-web`
> (legado) e do `erp-novo` + SPA. Data-base da análise: 2026-06-27.

---

## SUMÁRIO DO VEREDITO

- O ERP-NOVO **não** implementa arquitetura de "satélites" — é um **monólito modular**
  (1 processo, 1 banco, Sanctum único). Aceitável, desde que pare de chamar de satélite.
- **Satélites REAIS do legado (3):** Monitora (GPS), API Mobile (B2C/SGCM), API Admin.
- **Mal-rotulados como satélite no erp-novo:** Vale-Gás, Convênio, Comodato — eram **core ERP**
  no legado e estão **completos**; só precisam sair da pasta `features/satelites/`.
- **Conclusão por satélite real:** Monitora ~25% · API Mobile ~8% · API Admin 100% (absorvido).

---

## ETAPA 1 — INVENTÁRIO DOS SATÉLITES (do código)

Critério de "satélite" no legado: sub-app com **conexão de banco própria + migrations próprias + auth própria**.

### S1 — Monitora (GPS)
- **Onde:** `app/Monitora/` · conn `monitora` (`ctrl-web/config/database.php:75/125`) · `database/migrations_monitora/` · guard `auth.monitora` · `routes/monitora.php`.
- **Tamanho:** 44 PHP / 4.461 LOC + 47 Blade / 6.686 LOC.
- **Funcionalidades:** rastreamento ao vivo (`RastreamentoController`), rota (`RotaController`), evento (`EventoController`), **relatório paradas/excesso de velocidade PDF** (`ReportController.php:35`), cercas poligonais c/ setor (`CercaController`), veículos + **tipos** (ícone/velocidade-máx), multi-empresa, ingestão `savePosition` + **WebSocket push** (`ApiController.php:180`), **Traccar** (`ReportController.php:51`), sync `SyncPosicoesSGCasa` (conn `sgcasa`).

### S2 — API Mobile / B2C (SGCM / Gás-em-Casa)
- **Onde:** `app/Api/` · **Passport/OAuth** (`PassportController`, `OauthClientController`) · conn `sgcm_api`/`sgcm_logs` · `database/migrations_api/` · `routes/api_mobile.php` (**443 linhas, ~70 endpoints**).
- **Tamanho:** 82 PHP / **7.912 LOC**. Núcleo: `PedidoController` 1.142 LOC, `ClienteController` 486, `ApiResources` 562, `SecretController` 255, `EnderecoController` 262, `CondicaoPagamentoController` 271, `UserController` 349.
- **Funcionalidades (de `api_mobile.php`):** cadastro/login cliente, endereços (favorito/padrão/CRUD/geocode), revenda (`isGpAllowed`), **pedido completo** (criar, atualizar, get, histórico, **track**, **tracking**, **evaluate**, **cancel**, root, **PIX** `pixpaid`/`ispaid`/`expired`, `getLastestStatus`, `getItems`), produtos `getToOrder`, condições de pagamento + **preços**, **cupons** (verify/get), feriados, **camada de integração `getToLink`/`link`** (sincroniza catálogo API-standalone ↔ ERP), **polígonos de entrega** (`UserController@polylines`), notificações push (recompra/cupom/entregador), vídeos, `app/init` (`SecretController@onOpenApp`).

### S3 — API Admin (backend do ecossistema)
- **Onde:** `app/ApiAdmin/` (26 PHP / **4.497 LOC**), inclui `SatelitesController`, `ValeGasController`.
- **Status no ERP-NOVO:** **absorvido** — virou os controllers `Api/Admin/*` que a SPA consome. Não é mais satélite. **Fora do escopo deste plano.**

### NÃO são satélites (core ERP mal-rotulado)
- **Vale-Gás** (`app/ApiAdmin/.../ValeGasController.php` + core), **Convênio** (`app/Clienteconvenio.php`, `app/Conveniofechamento.php`), **Comodato** (`app/Comodato.php`, `ComodatoController.php`).

---

## ETAPA 2 — COMPARAÇÃO COM O ERP-NOVO

| Módulo | Existe? | Estado | Evidência |
|---|---|---|---|
| **S1 Monitora** | Sim | **Parcial ~25%** | 766 PHP + 457 SPA vs 11.147 |
| **S2 API Mobile** | Sim | **Apenas iniciado ~8%** | 583 LOC, **6 endpoints** (`erp-novo/routes/api.php:547-559`) vs ~70 |
| **S3 API Admin** | Sim | **Completo (absorvido)** | `Api/Admin/*` |
| Vale-Gás (core) | Sim | **Completo** | `erp-novo/app/Domain/Satelite/ValeGasService.php` |
| Convênio (core) | Sim | **Completo** | `erp-novo/app/Domain/Satelite/ConvenioFechamentoService.php` |
| Comodato (core) | Sim | **Completo** | `erp-novo/app/Domain/Satelite/ComodatoService.php` |

---

## ETAPA 3 — PROBLEMAS ARQUITETURAIS

1. **Sem fronteira de satélite** — tudo no monólito. Legado tinha banco/auth separados; erp-novo regrediu em isolamento, ganhou em coesão/UX. Aceitável se reclassificar.
2. **Classificação enganosa** — Vale-Gás/Convênio/Comodato em `features/satelites/` poluem auditorias.
3. 🔴 **Tenancy furado:** `monitora_cerca_pontos` sem `empresa_id`/`grupo_id` → fora da RLS auto-descoberta (`erp-novo/database/migrations/2026_06_26_000300_rls_tenant_completa.php:91`) e sem `BelongsToTenant` (`erp-novo/app/Models/Monitora/CercaPonto.php:12`).
4. 🟠 **Geofencing morto/incoerente:** `dentroDaCerca` usa círculo (centro+raio), dados são polígono; `cercasNoPonto()` não é chamado por ninguém (`erp-novo/app/Domain/Monitora/MonitoraService.php:53`).
5. 🟠 **Placeholders no Mobile:** `setorDeEntrega` = "primeiro setor ativo" (`erp-novo/app/Domain/Mobile/PedidoMobileService.php:39`) — sem regra de região/polígono.
6. **Positivos:** Vale-Gás/Convênio usam o **gerador único de financeiro** (não duplicam N5); models usam `BelongsToTenant`; RLS por auto-descoberta é boa arquitetura.

---

## ETAPA 4 — LACUNAS COMPLETAS

### S1 — MONITORA
| # | Tipo | Lacuna | Evidência legado |
|---|---|---|---|
| M1 | Tela+API | **Rastreamento ao vivo** (mapa frota) | `RastreamentoController` |
| M2 | Tela+API | **Rota** (replay histórico no mapa) | `RotaController` |
| M3 | Tela+API | **Evento** | `EventoController` |
| M4 | Relatório | **Paradas/excesso de velocidade (PDF)** | `ReportController.php:35` |
| M5 | Integração | **Traccar** (histórico/endereços) | `ReportController.php:51` |
| M6 | Evento | **WebSocket push de posição** | `ApiController.php:180` |
| M7 | Model/DB | **Tipos de veículo** (ícone, velocidade-máx) | `Veiculotipo` |
| M8 | Campos | veículo: `motorista`, `km_atual`, `deviceid` | `Veiculo` legado vs `erp-novo/app/Models/Monitora/Veiculo.php:21` |
| M9 | Regra | cerca↔setor na UI (campo existe, SPA não envia) | `CercasTab.tsx` |
| M10 | 🔴 Tenancy | `monitora_cerca_pontos` sem isolamento | Etapa 3.3 |
| M11 | 🟠 Bug | geofencing círculo×polígono morto | Etapa 3.4 |
| M12 | API | consulta de histórico de posições (persiste, não lê) | sem rota |

### S2 — API MOBILE
| # | Tipo | Lacuna | Evidência legado |
|---|---|---|---|
| A1 | Auth | OAuth/Passport + gestão de clients + `app/init` | `OauthClientController`, `SecretController` |
| A2 | Cliente | cadastro/update/delete/getToLink/migrate/push-token | `ClienteController` (486) |
| A3 | Endereço | CRUD + favorito + padrão + geocode | `EnderecoController` (262) |
| A4 | Pedido | histórico, **track/tracking**, **evaluate**, **cancel**, getLastestStatus, getItems, root/newRoot | `PedidoController` (1.142) |
| A5 | PIX | `pixpaid`/`ispaid`/`expired` (fluxo mobile) | `api_mobile.php:234-255` |
| A6 | Pagamento | condições + **preços** + link | `CondicaoPagamentoController` (271) |
| A7 | Cupons | verify/get | `CouponsController` (116) |
| A8 | Integração | camada `getToLink`/`link` (catálogo API↔ERP) | todos os controllers |
| A9 | Entrega | **polígonos de entrega** | `UserController@polylines` |
| A10 | Notificações | push recompra/cupom/entregador | `NotificacaoController` (164) |
| A11 | Feriados/Vídeo/Config | feriados, vídeos, config GP | Feriado/Video/User |
| A12 | Avaliação | `PedidoAvaliacao` (rating) | model + endpoint evaluate |

### Core mal-rotulado
| C1 | Classificação | mover Vale-Gás/Convênio/Comodato de `satelites/` p/ domínio próprio |

---

## ETAPA 5 — PLANO MESTRE (FASES)

> **Modernização:** OAuth/Passport → **Sanctum** (já adotado). `getToLink`/`link` (A8) que
> sincronizava o app-standalone com o ERP → **ELIMINAR** (no monólito o app lê catálogo direto,
> não há 2 bancos a reconciliar). WebSocket/Traccar → avaliar driver SGCasa atual + polling antes
> de reintroduzir broadcast.

### FASE 0 — Reclassificação + correções de risco — **Complexidade: Baixa**
- **Objetivo:** parar de chamar core de "satélite" e fechar o furo de tenancy.
- **Backend/DB:** migration add `empresa_id`+`grupo_id` em `monitora_cerca_pontos`; `BelongsToTenant` em `CercaPonto`; rodar RLS. Resolver geofencing: decidir polígono, reescrever `dentroDaCerca` com ray-casting, remover código de círculo morto, ligar `cercasNoPonto` a evento entrada/saída.
- **Frontend:** mover `Vale-Gás/Convênio/Comodato` de `features/satelites/` → `features/{valegas,convenios,comodatos}/` (rotas/menu já separados — só pasta/nomenclatura).
- **Testes:** guardião RLS `monitora_cerca_pontos` (empresa A não lê pontos de B via `DB::table`); `dentroDaCerca` com polígono.
- **DoD:** RLS cobre 100% das tabelas monitora; geofencing correto; `satelites/` só com Monitora + painel.

### FASE 1 — Monitora: paridade operacional (mapa ao vivo + dados do veículo) — **Média**
- **Funcionalidades:** M1, M7, M8, M9, M12.
- **Backend:** model `VeiculoTipo` + migration (icone, velocidade_maxima); colunas em `monitora_veiculos`; `GET monitora/veiculos/{id}/posicoes?de&ate`.
- **Frontend:** `MonitoraPage` → aba **Mapa ao vivo** (Google Maps, marcadores de `ultimas-posicoes`, refresh 30s, ícone por tipo); form de veículo com novos campos; seletor de setor na `CercasTab`.
- **Testes:** unit posições por período; API contrato; E2E marcadores no mapa.
- **DoD:** operador vê frota no mapa quase-real e gerencia veículos/tipos. **Depende: F0.**

### FASE 2 — Monitora: relatórios e replay — **Média-Alta**
- **Funcionalidades:** M2 (rota/replay), M3 (evento), M4 (relatório paradas/excesso PDF), M5 (**decisão**: usar driver SGCasa atual em vez de Traccar, salvo exigência de homologação).
- **Backend:** `RelatorioMonitoraService` (paradas >300s, excesso por `velocidade_maxima`), PDF (stack do ERP); endpoint replay.
- **Frontend:** aba **Rota** (polyline animada); tela de relatório c/ filtros + download.
- **Testes:** unit cálculo paradas/excesso (dataset fixo); snapshot do PDF.
- **DoD:** relatório bate com `ReportController` legado. **Depende: F1.**

### FASE 3 — API Mobile: pedido B2C completo (núcleo) — **Alta**
- **Achados:** hoje só `criarPedido/pagar` (`AppClienteController`).
- **Backend:** estender `PedidoMobileService` + `AppClienteController`: histórico, **track/tracking**, **getLastestStatus**, **getItems**, **cancel**, **evaluate** (model `PedidoAvaliacao`); fluxo **PIX** mobile integrado ao PIX do ERP; CRUD endereços + favorito/padrão; cadastro/atualizar cliente.
- **DB:** `pedido_avaliacoes`; reusar `clientes`/`enderecos` (com `empresa_id`).
- **Modernização:** **eliminar** `getToLink`/`link` (A8); substituir `setorDeEntrega` placeholder por regra real (Fase 5).
- **Segurança:** token Sanctum por device (`erp-novo/app/Http/Controllers/Api/Mobile/AppAuthController.php:50`); escopo `empresa_id`.
- **Testes:** API por endpoint; E2E "abrir app → pedir → pagar PIX → acompanhar".
- **DoD:** app do cliente faz pedido, paga (cartão+PIX), acompanha e avalia.

### FASE 4 — API Mobile: catálogo, cupons e config do app — **Média**
- **Funcionalidades:** A6 (condições + **preços**), A7 (**cupons** integrados aos do ERP), A11 (feriados/vídeos/config GP), `app/init`/`onOpenApp` (payload de abertura).
- **Backend:** endpoints `app/v1` lendo catálogo/preços/cupons do core (sem camada de "link").
- **Testes:** API cupom válido/expirado; preço por condição.
- **DoD:** app abre com catálogo, preços e cupons corretos por empresa. **Depende: F3.**

### FASE 5 — API Mobile: entrega, notificações e geofencing de venda — **Média-Alta**
- **Funcionalidades:** A9 (**polígonos de entrega** — reusar `monitora_cerca` polígono), A10 (push recompra/cupom/entregador via `PushService`), regra real de `setorDeEntrega` por polígono, melhorias do app entregador.
- **Backend:** `setorPorPonto()` ray-casting (compartilha código com Monitora F0); jobs de notificação.
- **Integração:** **unificar geofencing Monitora↔Mobile** (1 algoritmo, 2 consumidores).
- **Testes:** unit ponto-em-polígono; job de recompra dispara push.
- **DoD:** pedido do app cai no setor certo por geoloc; campanhas de push funcionam. **Depende: F0, F3, F4.**

---

## ORDEM DE EXECUÇÃO E DEPENDÊNCIAS

- **Trilha Monitora:** F0 → F1 → F2.
- **Trilha Mobile:** F0 → F3 → F4 → F5.
- F1–F2 e F3–F5 podem correr **em paralelo** após a F0.
- **Tudo depende de F0.** F2 depende de F1. F4 e F5 dependem de F3. F5 reusa o geofencing da F0.
- **Complexidade:** F0 Baixa · F1 Média · F2 Média-Alta · F3 Alta · F4 Média · F5 Média-Alta.

---

## CONCLUSÃO POR MÓDULO

| Módulo | % | Completas | Parciais | Ausentes |
|---|---|---|---|---|
| **Monitora** | ~25% | cadastro veículo, última posição, cercas poligonais | geofencing (bugado), cerca↔setor | rota, evento, relatório PDF, Traccar, WebSocket, tipos veículo, multi-empresa |
| **API Mobile** | ~8% | criar pedido (matching geoloc), pagar cartão, listar produtos, app entregador básico | — | histórico/track/cancel/evaluate, PIX mobile, endereços, cupons, preços, polígonos, push, app/init, OAuth |
| **API Admin** | 100% (absorvido) | tudo virou `Api/Admin/*` | — | — |

Core mal-rotulado (não satélites): Vale-Gás, Convênio, Comodato — **completos**.

---

## VALIDAÇÃO DA ANÁLISE (métricas)

- **Arquivos analisados:** Monitora erp-novo (13 PHP + 3 SPA) e legado (controllers de negócio, command, models, rotas); `app/Api` legado — inventário LOC dos **82 arquivos** + leitura integral de `api_mobile.php` (443 L) e controllers/serviços-núcleo; `app/ApiAdmin` — inventário dos **26 arquivos**; erp-novo `Domain/Mobile` + `Api/Mobile` (**10 arquivos, 583 LOC — 100% lidos**); rotas, providers, `config/database.php` legado, migrations de tenancy/RLS.
- **LOC analisadas (satélites):** legado **Monitora 11.147 + Api 7.912 + ApiAdmin 4.497 = 23.556**; erp-novo **Monitora 1.223 + Mobile 583 = 1.806**.
- **Satélites encontrados:** **3** (Monitora, API Mobile, API Admin).
- **Analisados integralmente:** Monitora (ambos), erp-novo Mobile (10 arquivos), `api_mobile.php`, services Vale-Gás/Convênio.

### ⚠️ PENDÊNCIA DE LEITURA (antes de implementar Fases 3–5)
As Fases 3–5 (API Mobile) foram fundamentadas no **contrato de rotas + assinaturas dos controllers**,
**não na leitura linha-a-linha** do corpo de:
- `ctrl-web/app/Api/Http/Controllers/PedidoController.php` (**1.142 L**)
- `ctrl-web/app/Api/Resources/ApiResources.php` (**562 L**)
- (parcial) `ClienteController` (486), `EnderecoController` (262), `CondicaoPagamentoController` (271), `UserController` (349), `SecretController` (255).

**Antes de codar A4/A5**, fazer passe de leitura integral desses 2 primeiros para capturar regras de
borda (frete, cancelamento/avaliação, formato exato do payload PIX). O inventário de lacunas e a ordem
de fases **não mudam**; podem mudar detalhes de regra de negócio dentro de A4/A5.

---

## PRÓXIMO PASSO RECOMENDADO

1. **Executar FASE 0** (baixa complexidade, alto valor, destrava o resto): reclassificação +
   correção do furo de tenancy de `monitora_cerca_pontos` + geofencing.
2. **OU** passe de leitura integral de `PedidoController` (1.142 L) + `ApiResources` (562 L) para
   fechar 100% das Fases 3–5 antes de implementar.

> Fluxo de commit do projeto: ao concluir cada fase, commit + push direto na `main` (sem branch).
> Build do frontend (`erp-novo/public/app`) é commitado — rodar `npm run build` a cada mudança de UI.
