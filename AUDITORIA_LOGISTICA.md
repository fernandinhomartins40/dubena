# AUDITORIA LOGÍSTICA — Ecossistema de Entregas

> **Fonte única da verdade: o código-fonte.** Este documento ignora deliberadamente
> READMEs, wikis, diagramas, comentários de marketing e relatórios anteriores.
> Cada afirmação abaixo foi derivada da leitura direta dos arquivos de código do
> ERP-NOVO, do App do Entregador, do App Gás em Casa, da API (rotas) e do módulo
> Monitora. Onde algo **não existe**, isso é declarado explicitamente.

**Data da auditoria:** 2026-07-01
**Escopo:** distribuição de pedidos, entrega, rastreamento, veículos (Monitora),
tempo real, autenticação, multi-tenancy, roteirização e "missões".

---

## 1. Mapa dos sistemas (o que existe fisicamente no repositório)

| Sistema | Caminho | Papel real (pelo código) |
|---|---|---|
| **ERP-NOVO** | `erp-novo/` | Laravel. Backend + API única. Toda regra de negócio. |
| **App Gás em Casa** | `app-gas-em-casa/` | Expo/RN. App do **consumidor**. Compra + acompanha pedido. |
| **App do Entregador** | `app-entregador/` | Expo/RN. App do **colaborador entregador**. Recebe pedidos atribuídos, envia GPS, conclui entrega. |
| **Monitora** | `erp-novo/app/Domain/Monitora`, `app/Models/Monitora`, `MonitoraController` | Módulo de rastreamento de **veículos** (GPS por rastreador/IMEI), cercas, histórico. |
| **API** | `erp-novo/routes/api.php` | Prefixo `app/v1/*` (mobile) e `admin` (ERP web). Auth Sanctum. |

**Conclusão estrutural:** há **uma API única** (`api.php`) e a regra vive no backend
(`app/Domain/*`). Isso confirma a premissa do projeto. Os apps são finos —
`entrega.service.ts` e `order.service.ts` só montam requisições HTTP.

---

## 2. Fluxo do pedido — como o pedido nasce e chega ao entregador

### 2.1. Origem dos pedidos (as "fontes")
Rastreado por quem cria `Pedido`:

- **App Gás em Casa (consumidor):** `POST app/v1/pedidos` → `AppClienteController::criarPedido` → `PedidoMobileService`.
- **ERP web (balcão / televendas / interno):** `Api\Admin\PedidoController` (CRUD administrativo) via `PedidoRequest`.

> **Não há "fila única de distribuição".** Cada origem cria uma linha em `pedidos`.
> O que as unifica é apenas a tabela `pedidos` + a máquina de estados
> (`PedidoSituacao.efeito` ∈ PENDENTE / CONCLUIDO / CANCELADO). Não existe entidade
> de "fila", "roteiro", "praça" ou "central".

### 2.2. Atribuição ao entregador — **o gargalo central**

O campo `pedidos.entregador_user_id` existe (`Models/Pedido/Pedido.php:28`,
`$fillable`). **Mas a única forma de preenchê-lo é passá-lo cru no corpo da
requisição** em `PedidoRequest.php:28`:

```php
'entregador_user_id' => 'nullable|integer|exists:users,id',
```

Ou seja: alguém no ERP web digita/escolhe manualmente o entregador ao editar o
pedido. **Não existe:**

- ❌ Algoritmo de distribuição (automática ou assistida).
- ❌ Fila / bandeja de pedidos não atribuídos.
- ❌ Redistribuição estruturada (só reeditar o campo).
- ❌ Priorização, balanceamento por carga, por região, por proximidade.
- ❌ Bloqueio de entregador, janela de jornada, capacidade de veículo.

O grep por `entregador_user_id` em todo o `erp-novo/app` retorna **apenas**:
`EntregaService` (recusa → seta `null`), `RastreamentoService` (lê para publicar
posição), `RelatorioService`, `PedidosMigrator` (ETL do legado), o controller do
app, o `PedidoRequest` e os models. **Nenhum serviço de atribuição.**

### 2.3. Ciclo da entrega (isto existe e é bom)
`app/v1/entregador/*` → `AppEntregadorController` → `EntregaService`:

- `GET  entregador/pedidos` — pedidos com `entregador_user_id = auth`.
- `POST entregador/pedidos/{id}/aceitar` — hoje é **no-op** (só grava `datahora_acao`); a atribuição já veio pronta.
- `POST entregador/pedidos/{id}/recusar` — gera `PedidoOcorrencia` e zera `entregador_user_id` (volta "para a fila" que **não tem consumidor automático**).
- `POST entregador/pedidos/{id}/ocorrencia` — imprevisto + foto.
- `POST entregador/pedidos/{id}/concluir` — comprovação (foto **ou** assinatura obrigatória) + move para 1ª situação `CONCLUIDO` do grupo, via `PedidoService::mudarSituacao` (reusa estoque/financeiro/evento).
- `POST entregador/pedidos/{id}/status` — muda situação; valida que a situação-destino é do **mesmo grupo** (anti-cross-tenant).
- `POST entregador/posicao` — ping de GPS (throttle 120/min).

**Anti-IDOR correto:** todo pedido é resolvido por `empresa_id + entregador_user_id`
do token (`AppEntregadorController::pedidoDoEntregador`).

---

## 3. Rastreamento em tempo real — existe, sólido, mas **desligado em produção**

### 3.1. O caminho da posição
1. App entregador (`useRastreamento.ts`) lê o GPS do **celular** (`expo-location`) a cada `POSICAO_INTERVALO_MS`, **somente** enquanto `emServico = true`.
2. `POST app/v1/entregador/posicao` → `RastreamentoService::registrarPing`:
   - Upsert de `EntregadorPosicao` (1 linha por entregador — snapshot).
   - Para cada pedido **ATIVO** (efeito PENDENTE) do entregador, dispara `EntregadorPosicaoAtualizada`.
3. App cliente (`useAcompanharPedido.ts`) assina os canais privados:
   - `pedido.{id}.entregador` → evento `.entregador.posicao` → move o `Marker` no `MapView`.
   - `pedido.{id}` → evento `.pedido.status` → invalida a query do pedido.
4. `track.tsx` mostra o mapa (`PROVIDER_GOOGLE`) com o marcador **só quando** `emAtendimento && posicao` existe.

**Privacidade correta:** a posição só é publicada para pedidos PENDENTE; ao
concluir/cancelar, o rastreamento cessa naturalmente.

**Autorização de canal correta** (`channels.php`): `pedido.{id}` e
`pedido.{id}.entregador` exigem mesmo `empresa_id` **e** ser o cliente dono, o
entregador ou o atendente. Defense-in-depth com `withoutTenant()` explícito.

### 3.2. O problema
`.env.example`:
```
BROADCAST_CONNECTION=log        # ← eventos vão para o log, não para um socket
REVERB_APP_ID=                  # ← vazio
REVERB_APP_KEY=                 # ← vazio
```
E no cliente `useAcompanharPedido` só liga se `realtimeDisponivel()`; senão cai no
**polling de 30s** (`track.tsx:26`). **Logo, hoje o "tempo real" está inativo** — o
código está pronto, falta o servidor Reverb rodando + credenciais + `BROADCAST_CONNECTION=reverb`.

---

## 4. Monitora (GPS de veículos) — **isolado do app do entregador**

`monitora_veiculos` (`Models/Monitora/Veiculo.php`) tem: `placa`, `descricao`,
`tipo_id`, `motorista` (texto livre!), `km_atual`, `imei`, `deviceid`, `ativo`.
Relações: `tipo`, `posicoes`, `ultimaPosicao`. Há cercas poligonais (`Cerca` +
`CercaPonto`), histórico (`historico`), última posição (mapa), relatório de
eventos (paradas/excessos), e um **sync com o SGCasa** (`MonitoraSyncService`).

### O achado mais importante da auditoria
**Não há vínculo entre o entregador (usuário) e o veículo (Monitora).**

- `monitora_veiculos.motorista` é uma **string**, não um FK para `users`.
- `pedidos` referencia `entregador_user_id`, nunca `veiculo_id`.
- A posição usada para o cliente vem do **celular do entregador**
  (`EntregadorPosicao`), **não** do rastreador do veículo (`UltimaPosicao` do
  Monitora).

Ou seja, existem **duas fontes de GPS que nunca se encontram**:

| Fonte | Origem | Consumidor | Tabela |
|---|---|---|---|
| Celular do entregador | `useRastreamento` | App cliente (acompanhar pedido) | `entregador_posicoes` |
| Rastreador do veículo | rastreador/IMEI + `MonitoraSyncService` | Mapa do ERP (Monitora) | `monitora_ultimas_posicoes` |

A ETAPA 4 do pedido do cliente ("no início do expediente o entregador seleciona o
veículo cadastrado no Monitora e o ERP passa a usar a localização daquele veículo")
**não tem nenhum suporte no código atual.** Falta: `emServico` virar
"iniciar jornada com veículo", tabela de jornada/turno, FK entregador↔veículo, e a
decisão de qual GPS é a verdade.

---

## 5. Roteirização e navegação — **inexistentes**

Busca por Directions / Distance Matrix / roteiro / sequência otimizada / TSP /
`optimize` no `erp-novo` e nos apps: **nada**.

- O app entregador (`pedidos.tsx`) lista pedidos em `orderByDesc('datahora')` —
  ordem cronológica, **não** otimizada por rota.
- Não há "próxima entrega sugerida", nem sequência, nem ETA calculado, nem mapa de
  rota no app do entregador (o `MapView` só existe no **app cliente**).
- Google Maps aparece só como `PROVIDER_GOOGLE` no `MapView` do cliente. **Não há
  API key confirmada** (não encontrada no `.env.example`) e **nenhuma chamada a
  Directions/Distance Matrix** em lugar algum.

A ETAPA 5 ("o ERP envia rota inteligente, o app nunca deixa escolher aleatoriamente")
está a **0%**.

---

## 6. Missões, desafios, prospecção e venda em campo — **inexistentes**

Busca por missão / desafio / panfleto / prospecção / vale-gás-em-campo / visita /
`Missao` em todo o repositório: **nada** relacionado a operação de campo do
entregador.

- Não há entidade `Missao`, `Desafio`, `Visita`, `Prospeccao`.
- `ValeGasService` existe (`Domain/Satelite`), mas é o **produto vale-gás**
  (comodato/convênio), não uma campanha de campo executada pelo entregador.
- Não há registro contínuo de GPS por missão, casas visitadas, tempo por
  residência, evidências fotográficas de fachada/panfleto, "próxima casa mais
  próxima".
- Não há venda em campo pelo app do entregador (o entregador **não cria pedido**;
  só executa entrega). O único fluxo de venda mobile é o do **cliente**.
- Não há módulo de auditoria de missões no ERP, nem aprovação/reprovação/
  advertência/bonificação, nem adiamento de missão.

As ETAPAS 7 a 11 estão a **0%** — são features totalmente novas.

---

## 7. Autenticação e Multi-Tenancy (base sólida — reaproveitável)

- **Auth:** Sanctum. Três guards distintos: `sanctum` (usuários ERP + apps),
  `platform` (SuperAdmin, cross-tenant isolado), e o app usa token com rotação
  (`app/v1/token/refresh`) e registro de device (`app/v1/devices`).
- **Login entregador:** `POST app/v1/login` → `AppAuthController::login`.
- **Multi-tenancy:** `BelongsToTenant` (trait) em `Pedido`, `Veiculo`, etc. Tenant
  derivado do token no servidor; o app **não** decide empresa. RLS + broadcasting
  auth por tenant. **Robusto.** (Confirmado por `withoutTenant()` explícito no
  `channels.php`, que é a forma correta de contornar RLS só onde há checagem manual
  de `empresa_id`.)

Nada aqui precisa ser reescrito para a logística; é fundação aproveitável.

---

## 8. App do Entregador — estado atual (funcional, porém cru)

Estrutura (`app-entregador/src`):
- **Telas:** `login.tsx`, `(app)/pedidos.tsx` (lista + toggle "em serviço"),
  `(app)/pedido/[id]/index.tsx`, `.../concluir.tsx`, `.../ocorrencia.tsx`.
- **Serviços:** `auth.service.ts`, `entrega.service.ts` (cobre todo o ciclo P7).
- **Hook:** `useRastreamento.ts` (GPS foreground).
- **Realtime:** `helpers/realtime.ts` presente.
- **UI:** `components/ui.tsx` — um único arquivo (Cartao/Etiqueta). Design
  **diverge** do App Gás em Casa (que já foi modernizado com tokens laranja/lime,
  Lucide, componentes ricos).

Lacunas do app entregador vs. o pedido:
- ❌ Dashboard / resumo do dia.
- ❌ Seleção de veículo + checklist no início da jornada.
- ❌ Mapa e rota (o app não tem `MapView`).
- ❌ Estados "em andamento" vs "pendentes" separados.
- ❌ Comunicação com a central.
- ⚠️ Rastreamento é **foreground-only** (`requestForegroundPermissionsAsync`) — para
  operar de verdade precisa de **background location** + task manager.
- ⚠️ `emServico` é um booleano volátil no store; não há conceito de **turno**
  persistido no servidor.

---

## 9. App Gás em Casa (consumidor) — lado logístico

Já modernizado (identidade nova). Para logística, o relevante:
- `track.tsx` acompanha o pedido: timeline + mapa com marcador do entregador
  (quando há posição em tempo real) + polling 30s de fallback.
- **Só mostra o marcador do entregador** — não mostra rota, ETA, nome/foto do
  entregador, nem "faltam X paradas". A experiência "Uber/99" (ETA dinâmico, rota
  do entregador até você) está **parcial**: tem a posição, falta o resto.
- `useAcompanharPedido` já está pronto para tempo real; depende do Reverb ligado.

---

## 10. Matriz de conformidade com o pedido (ETAPAS 1–13)

| Etapa | Tema | Estado no código | % |
|---|---|---|---|
| 1 | Auditoria | — (este documento) | ✅ |
| 2 | Central de Logística (fila única, distribuição, redistribuição, prioridade) | Só `pedidos` + campo manual. Sem central. | **~5%** |
| 3 | Distribuição inteligente (geo, distância, trânsito, capacidade, jornada) | Inexistente. | **0%** |
| 4 | App entregador operacional (login ✅, veículo ❌, jornada ❌, dashboard ❌, checklist ❌) | Ciclo de entrega ✅; resto ❌. | **~30%** |
| 5 | Roteirização (sequência ótima, melhor caminho, ETA, Google) | Inexistente. | **0%** |
| 6 | Mapas em tempo real (entregador vê rota; cliente vê entregador) | Cliente vê marcador (Reverb desligado); entregador não tem mapa. | **~25%** |
| 7 | Missões e desafios | Inexistente. | **0%** |
| 8 | Execução de missões (GPS contínuo, evidências, status por casa) | Inexistente. | **0%** |
| 9 | Vendas pelo app (entregador cria pedido/vale-gás/cliente em campo) | Inexistente (só o cliente compra). | **0%** |
| 10 | Auditoria de missões no ERP | Inexistente. | **0%** |
| 11 | Adiamento de missões | Inexistente. | **0%** |
| 12 | Padronização visual (entregador = Gás em Casa) | Divergente. | **~20%** |
| 13 | Modernização (offline, cache, background GPS, WebSocket, geofencing, observabilidade) | Base parcial (tempo real pronto porém off; cercas existem no Monitora). | **~20%** |

---

## 11. Fundações reaproveitáveis (o que **não** precisa ser refeito)

1. **API única + Sanctum + multi-tenant/RLS** — sólida.
2. **Máquina de estados do pedido** (`PedidoService::mudarSituacao` + efeitos) — reusar para toda transição logística.
3. **Ciclo de entrega P7** (aceite/recusa/ocorrência/conclusão com comprovação) — completo e seguro.
4. **Infra de tempo real** (canais privados por pedido, eventos, autorização por posse) — pronta; só ligar Reverb.
5. **Monitora** (veículos, cercas poligonais, histórico, última posição, relatório de eventos) — base para geofencing e para a fusão veículo↔entregador.
6. **App cliente** já modernizado — é o **template visual** para o app entregador (Etapa 12).

---

## 12. Riscos e dívidas técnicas identificados

| # | Risco | Evidência | Impacto |
|---|---|---|---|
| R1 | Atribuição 100% manual | `PedidoRequest:28` é o único caminho | Não escala; operador vira gargalo. |
| R2 | Duas fontes de GPS desconexas | `EntregadorPosicao` × `UltimaPosicao` | Impossível "usar o GPS do veículo" sem ligar as duas. |
| R3 | Tempo real desligado | `BROADCAST_CONNECTION=log`, Reverb vazio | Cliente não vê ao vivo; cai no polling. |
| R4 | GPS foreground-only | `requestForegroundPermissionsAsync` | App em background para de enviar posição. |
| R5 | `emServico` volátil | booleano no store, sem turno no servidor | Sem auditoria de jornada; base para distribuição não confiável. |
| R6 | `motorista` é texto livre | `Veiculo.php:24` | Sem integridade entregador↔veículo. |
| R7 | Sem Google Directions/Distance Matrix | grep vazio | Zero roteirização/ETA. |
| R8 | Cliente `lat/lng` pode ser nulo | `AppEntregadorController::pedidos` retorna `null` | Distribuição por proximidade exige geocodificação garantida (há `GeocodificarClienteJob`, mas não obrigatório). |

---

## 13. Conclusão

O ecossistema tem uma **fundação de plataforma excelente** (API única, tenancy,
máquina de estados, ciclo de entrega seguro, infra de tempo real pronta) mas a
**camada logística inteligente praticamente não existe**: a distribuição é um campo
digitado à mão, o veículo do Monitora e o entregador do app vivem em silos, não há
roteirização, e o inteiro universo de "missões/prospecção/venda em campo" é
greenfield.

A boa notícia: **quase nada precisa ser reescrito** — o trabalho é
majoritariamente **aditivo** sobre fundações sólidas. O roadmap está em
[`PLANO_MODERNIZACAO_LOGISTICA.md`](PLANO_MODERNIZACAO_LOGISTICA.md).
