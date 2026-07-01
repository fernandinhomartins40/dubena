# PLANO DE MODERNIZAÇÃO DA LOGÍSTICA — Roadmap Oficial

> Backlog de execução para transformar o ecossistema de entregas em uma plataforma
> logística inteligente, auditável e integrada, com experiência de nível Uber/99/iFood,
> **adaptada a uma distribuidora de gás com frota própria e entregadores colaboradores**
> (não freelancers).
>
> Baseado na engenharia reversa em [`AUDITORIA_LOGISTICA.md`](AUDITORIA_LOGISTICA.md).
> **Toda a inteligência vive no ERP-NOVO.** Os apps só consomem a API.

**Princípios inegociáveis**
1. Regra crítica **sempre** no backend (`app/Domain/*`). App nunca decide distribuição, preço, estado ou permissão.
2. Aditivo sobre a fundação existente (Sanctum, tenancy/RLS, máquina de estados, ciclo de entrega P7, infra de tempo real). Não reescrever o que funciona.
3. Uma fase = um conjunto coeso, testável e commitável. Commit + push **direto na main** por fase (padrão do projeto).
4. Multi-tenant desde o primeiro campo: toda tabela nova nasce com `empresa_id` + `BelongsToTenant` + RLS.

---

## Pré-requisitos de infraestrutura (bloqueiam fases específicas)

> Confirmado na auditoria que **nada disso está ativo hoje**. Precisa ser
> provisionado/contratado. Cada item lista qual fase depende dele.

| Infra | Estado | Necessário para | Ação |
|---|---|---|---|
| **Reverb (WebSocket)** rodando na VPS | Código pronto, `BROADCAST_CONNECTION=log`, credenciais vazias | F2, F6 | Subir `php artisan reverb:start` (supervisor), preencher `REVERB_*`, `BROADCAST_CONNECTION=reverb`, expor porta/proxy TLS. |
| **Google Maps Platform** (billing + API key) | Não confirmado no `.env` | F5 (Directions/Distance Matrix), F6 (SDK mapa entregador) | Criar projeto GCP, habilitar Maps SDK Android/iOS + Directions + Distance Matrix + (opcional) Roads/Routes, restringir chave, guardar em `.env`. |
| **GPS do veículo (Monitora) em tempo real** | Sync batch com SGCasa (`MonitoraSyncService`); não streaming | F3 (fusão), F6 | Decidir a fonte de verdade da posição (ver F3). Se usar rastreador do veículo, definir cadência de ingestão. |
| **Fila/worker** (queue) | `QUEUE_CONNECTION` — confirmar redis | F3, F5, F7 (jobs de distribuição/roteirização/missões) | Garantir worker supervisionado. |
| **Storage privado** p/ evidências | Já usado (`disk local`) no P7 | F7, F8 | Avaliar volume; considerar S3-compatível para escala. |

---

## Visão geral das fases

| Fase | Nome | Entrega principal | Depende de |
|---|---|---|---|
| **F0** | Fundação de dados logísticos | Turno/jornada, vínculo entregador↔veículo, `veiculo_id` no pedido, geocodificação obrigatória | — |
| **F1** | Central de Logística (ERP) | Fila única + bandeja de distribuição + atribuição/redistribuição manual assistida | F0 |
| **F2** | Tempo real ligado | Reverb em produção; painel da central ao vivo | Reverb |
| **F3** | Distribuição inteligente | Algoritmo de sugestão + auto-atribuição (geo, carga, jornada, capacidade) | F1, F2 |
| **F4** | App Entregador — jornada operacional | Início de jornada c/ veículo + checklist; dashboard; pendentes/andamento; background GPS | F0 |
| **F5** | Roteirização | Sequência ótima + ETA + Google Directions; rota entregue pelo ERP | Google Maps, F1 |
| **F6** | Navegação e mapas ao vivo | Mapa+rota no app entregador; acompanhamento estilo Uber no app cliente | F5, F6-infra |
| **F7** | Missões e desafios | Motor de missões + execução em campo (GPS contínuo, evidências, status por casa) | F4 |
| **F8** | Vendas em campo | Entregador cria pedido/vale-gás/cadastra cliente durante missão | F7 |
| **F9** | Auditoria de missões (ERP) | Painel de revisão, aprovação/reprovação, bonificação, adiamento | F7, F8 |
| **F10** | Padronização visual do app entregador | Design system do Gás em Casa portado (tokens, Lucide, componentes) | F4 |
| **F11** | Endurecimento e observabilidade | Offline/cache, geofencing, métricas, alertas, escala | todas |

> **Ordem recomendada de valor entregue:** F0 → F1 → F2 → F4 → F3 → F5 → F6 → F10 →
> F7 → F8 → F9 → F11. (F4 antes de F3 porque a jornada com veículo alimenta os dados
> que a distribuição inteligente consome; F10 pode entrar em paralelo a partir de F4.)

---

## F0 — Fundação de dados logísticos

**Objetivo:** criar o esqueleto de dados sem o qual nada inteligente é possível.

**Backend (ERP-NOVO):**
- [ ] Migration `jornadas` (turno do entregador): `empresa_id`, `entregador_user_id`, `veiculo_id` (FK `monitora_veiculos`), `iniciada_em`, `encerrada_em`, `km_inicial`, `checklist_json`, `status`. `BelongsToTenant` + RLS.
- [ ] FK real entregador↔veículo: coluna `veiculo_id` em `jornadas` (a jornada é o vínculo, não o veículo). Manter `monitora_veiculos.motorista` como legado, mas parar de usá-lo como identidade.
- [ ] Migration: `pedidos.veiculo_id` (nullable, FK) — para saber em qual veículo a entrega saiu.
- [ ] `Domain/Logistica/JornadaService`: `iniciar(entregador, veiculo, checklist)`, `encerrar(jornada, km_final)`, `jornadaAtiva(entregador)`.
- [ ] Tornar geocodificação do cliente **garantida** no fluxo de pedido: enfileirar `GeocodificarClienteJob` na criação do pedido quando `lat/lng` nulos; bloquear distribuição por proximidade até resolver (fallback: distribuição sem geo).
- [ ] Enum/consts de `EfeitoPedido` — reusar; adicionar situações intermediárias se faltarem (ex.: EM_ROTA, A_CAMINHO) por grupo, sem hard-code.

**Testes:** unit `JornadaService` (abrir/fechar/uma ativa por vez); feature de geocodificação enfileirada.

**Migração de dados:** nenhuma destrutiva. Colunas novas nullable.

---

## F1 — Central de Logística (ERP-NOVO)

**Objetivo:** transformar `pedidos` numa **fila única operável**, com bandeja de
distribuição e atribuição/redistribuição rastreada (ainda manual, mas estruturada).

**Backend:**
- [ ] `Domain/Logistica/CentralService`:
  - `filaDistribuicao(empresa, filtros)` — pedidos PENDENTE **sem** entregador (ou todos, com flag), ordenáveis por urgência/idade/região/proximidade.
  - `atribuir(pedido, entregador, veiculo?)` — grava `entregador_user_id` (+ `veiculo_id`), registra evento/auditoria, dispara push ao entregador.
  - `redistribuir(pedido, novoEntregador, motivo)` — troca com histórico.
  - `bloquearEntregador(entregador, motivo, ate)` / `priorizar(pedido)` / `reagendar(pedido, quando)`.
- [ ] Tabela `pedido_atribuicoes` (auditoria): quem, quando, de→para, motivo, automático×manual.
- [ ] `Api\Admin\CentralController` (RBAC `logistica.*`): `GET central/fila`, `POST central/atribuir`, `POST central/redistribuir`, `POST central/priorizar`, `POST central/reagendar`, `POST central/entregadores/{id}/bloquear`, `GET central/entregadores` (com jornada ativa/veículo/última posição/carga atual).
- [ ] Push ao entregador na atribuição (reusar `PushService`).

**Frontend (SPA React do ERP):**
- [ ] Página **Central de Logística**: coluna "Fila" (pendentes) + coluna "Em rota" + lista de entregadores (status/carga). Drag ou botão "Atribuir". Ações de redistribuir/priorizar/bloquear.

**Testes:** feature de atribuição/redistribuição + auditoria; policy RBAC.

---

## F2 — Tempo real ligado (produção)

**Objetivo:** ligar o que já existe.

**Infra/Backend:**
- [ ] Subir Reverb na VPS (supervisor/systemd), TLS via proxy, `REVERB_*` preenchidos, `BROADCAST_CONNECTION=reverb`.
- [ ] Novo canal `empresa.{id}.central` (broadcasting auth por `podeAcessarEmpresa`) para o painel da central receber: novo pedido na fila, atribuição, mudança de status, posição agregada de entregadores.
- [ ] Eventos: `PedidoEntrouNaFila`, `PedidoAtribuido`, reusar `EntregadorPosicaoAtualizada` + status.

**Frontend:**
- [ ] Central de Logística **ao vivo** (Echo no SPA): fila e mapa atualizam sem refresh.
- [ ] App cliente: confirmar que `useAcompanharPedido` liga (só ativa quando `realtimeDisponivel()`), reduzindo o polling.

**Testes:** teste de autorização de canal (não vazar entre tenants).

---

## F3 — Distribuição inteligente

**Objetivo:** o ERP **sugere** e (quando ligado) **auto-atribui** o melhor entregador.

**Backend:**
- [ ] `Domain/Logistica/DistribuidorService::melhorEntregador(pedido): array<ranked>` considerando (peso configurável por empresa):
  - proximidade (posição atual do entregador × `cliente.lat/lng`);
  - carga atual (nº de pedidos ativos do entregador);
  - jornada ativa + veículo compatível (capacidade em botijões vs. itens do pedido);
  - região/setor/cerca (Monitora `Cerca` — reusar polígonos!);
  - prioridade/urgência do pedido (`entrega_urgente`);
  - tipo de produto vs. tipo de veículo.
- [ ] `distancia()` — inicialmente Haversine (grátis, sem Google); F5 troca por Distance Matrix (tempo real de trânsito) onde valer o custo.
- [ ] Configuração por empresa: `logistica_config` (pesos, modo `sugerir` | `auto`, raio máximo, teto de carga).
- [ ] Job `AtribuirPedidoJob` (fila): ao entrar pedido PENDENTE sem entregador e modo=auto, roda o distribuidor e atribui; senão só popula a "sugestão" na fila.
- [ ] Endpoint `GET central/pedidos/{id}/sugestoes` para o operador ver o ranking.

**Frontend:**
- [ ] Na fila, cada pedido mostra "sugerido: Fulano (1,2 km · 3 na carga)" com 1-clique para aceitar a sugestão.

**Testes:** unit do ranking com cenários (proximidade empata → desempata por carga; sem geo → cai para região; veículo sem capacidade → exclui).

---

## F4 — App Entregador: jornada operacional

**Objetivo:** o app vira ferramenta de trabalho de verdade.

**App entregador:**
- [ ] **Início de jornada:** tela que lista veículos da empresa (`GET monitora/veiculos` ou endpoint dedicado do entregador), o entregador seleciona o veículo, preenche **checklist** (pneus, gás no veículo, documentos, avarias) e confirma → `POST app/v1/entregador/jornada/iniciar`. Substitui o toggle volátil `emServico`.
- [ ] **Encerrar jornada:** km final + resumo do dia.
- [ ] **Dashboard:** entregas do dia, concluídas, pendentes, km, tempo em serviço.
- [ ] Abas **Pendentes** × **Em andamento** × **Histórico** (hoje é lista única cronológica).
- [ ] **Background location:** migrar de `requestForegroundPermissionsAsync` para background (`expo-location` + `expo-task-manager`), respeitando bateria e permissões; só ativo durante jornada.
- [ ] Comunicação com a central (canal de mensagens simples / push bidirecional — mínimo: receber avisos da central).

**Backend:**
- [ ] `POST app/v1/entregador/jornada/iniciar` / `.../encerrar` / `GET .../jornada/atual` → `JornadaService`.
- [ ] `GET app/v1/entregador/veiculos` (veículos ativos da empresa para seleção).
- [ ] `atualizarStatus`/`posicao` passam a exigir jornada ativa (regra no backend).

**Testes:** feature de jornada (não iniciar duas; posição só conta com jornada ativa).

---

## F5 — Roteirização

**Objetivo:** o ERP calcula a **sequência ótima** e o app **nunca** deixa escolher aleatoriamente.

**Backend (depende de Google Maps):**
- [ ] `Domain/Logistica/RoteirizadorService`:
  - dado o conjunto de entregas ativas de um entregador, resolve a ordem (TSP aproximado; ponto de partida = posição atual/base).
  - usa **Distance Matrix** para tempos reais (com trânsito); cache por par origem-destino (TTL curto) para conter custo.
  - devolve: sequência ordenada, distância total, ETA por parada, polyline por trecho (Directions).
- [ ] Endpoint `GET app/v1/entregador/rota` → rota atual do entregador (sequência + próximo destino + ETA).
- [ ] Recalcular a rota quando: nova atribuição, conclusão de parada, ocorrência.
- [ ] Guardar rota calculada (para auditoria e para não recomputar a cada request).

**Testes:** unit do ordenador (mock Distance Matrix); teto de chamadas Google.

---

## F6 — Navegação e mapas ao vivo

**Objetivo:** experiência Uber Driver (entregador) + Uber rider (cliente).

**App entregador:**
- [ ] Tela de **rota**: `MapView` (Google) com a sequência de paradas, polyline, próximo destino em destaque, botão "Navegar" (deep link para Google Maps / navegação turn-by-turn), ETA, distância. Marcar parada como concluída avança a sequência.

**App cliente (evoluir `track.tsx`):**
- [ ] Rota do entregador até o cliente (polyline), **ETA dinâmico**, nome/foto do entregador, veículo/placa, "faltam N paradas" quando aplicável.
- [ ] Depende de Reverb (F2) para o movimento ser fluido.

**Backend:** reusar `EntregadorPosicaoAtualizada`; enriquecer o payload do pedido com dados do entregador/veículo (respeitando privacidade).

---

## F7 — Missões e desafios (motor + execução)

**Objetivo:** quando o entregador fica ocioso, o ERP gera missões de campo inteligentes.

**Backend:**
- [ ] Modelos: `Missao` (tipo, área/cerca, meta, janela, config), `MissaoAtribuicao` (entregador, status, início/fim), `MissaoVisita` (residência: lat/lng, status ∈ visitada/ausente/interessado/venda/frustrada, tempo, evidências), `MissaoEvidencia` (fotos: fachada/panfleto/visita — storage privado, como o P7).
- [ ] `Domain/Missao/GeradorMissaoService`: ao detectar ociosidade (sem entregas há X min configurável, dentro da jornada), gera missão considerando a **posição atual** e cercas/regiões (reusar `Cerca`).
- [ ] Tipos: panfletagem, visita comercial, divulgação Vale Gás, prospecção, ação promocional, campanha de bairro.
- [ ] "Próxima casa mais próxima": endpoint que, dada a posição, sugere a próxima residência da missão.
- [ ] GPS contínuo durante missão (trilha): tabela `missao_trilha` (pontos) para calcular percurso/distância/tempo por casa.

**App entregador:**
- [ ] Aba **Missões**: missão ativa, mapa das residências, registrar por casa (visitada/ausente/interessado/venda/frustrada) + foto obrigatória conforme tipo, sugestão da próxima casa.

**Testes:** geração respeita janela/ociosidade/jornada; evidência obrigatória por tipo.

---

## F8 — Vendas em campo

**Objetivo:** converter missão em receita sem sair do app.

**Backend:**
- [ ] `Domain/Missao/VendaCampoService` reusando o **fluxo de pedido existente** (nada de reescrever venda): cria `Pedido` com origem "campo", vincula à `MissaoVisita`.
- [ ] Cadastro rápido de cliente em campo (reusar `ClienteService` + geocodificação).
- [ ] Vale-gás em campo (reusar `ValeGasService`).
- [ ] Recebimento conforme políticas (reusar condições de pagamento; PIX via `PagamentoOnlineService`/`PixService`).

**App entregador:**
- [ ] Dentro da visita: "Vender gás" / "Vender Vale Gás" / "Cadastrar cliente" / "Registrar interesse".

**Testes:** venda em campo gera pedido válido, tenant-scoped, com estoque/financeiro corretos (via máquina de estados existente).

---

## F9 — Auditoria de missões (ERP)

**Objetivo:** operador revisa e governa o que foi feito em campo.

**Backend/SPA:**
- [ ] `Api\Admin\MissaoAuditoriaController` + página SPA: missões executadas/pendentes, mapa da trilha (GPS), fotos, tempo, distância, casas visitadas, conversões, vendas, evidências.
- [ ] Ações: aprovar, reprovar, solicitar revisão, advertência, bonificação — tudo auditado (reusar padrão de `AuditoriaController`).
- [ ] **Adiamento de missão** (ETAPA 11): entregador solicita adiamento (motivo: nova entrega, emergência, veículo, clima, outro) → registra motivo/horário/aprovação/reagendamento/penalidade. Endpoint no app + fluxo de aprovação na central.

**Testes:** transições de estado da missão; penalidade só com aprovação.

---

## F10 — Padronização visual do app entregador

**Objetivo:** os dois apps parecerem a mesma plataforma (ETAPA 12).

- [ ] Portar o **design system** do Gás em Casa: tokens (`theme.ts` laranja #FF6200 / lime #DBFB3B / grafite), tipografia, `radius/spacing/shadow`, **Lucide** icons, componentes (StatCard, cards, headers, bottom-nav declarativa).
- [ ] Refazer telas do entregador sobre esses componentes (dashboard, jornada, pedidos, rota, missões).
- [ ] Manter UX consistente: telas dedicadas para formulários (padrão já adotado no app cliente), sem bottom-sheets para formulário.

> Pode rodar **em paralelo** a partir de F4 (cada tela nova já nasce no design system).

---

## F11 — Endurecimento e observabilidade

**Objetivo:** nível profissional/produção.

- [ ] **Offline/cache** no app entregador (fila de ações quando sem rede; sync ao reconectar) — crítico para campo.
- [ ] **Geofencing** operacional (reusar `Cerca`): alertar entrada/saída de área, cerca de entrega, cerca de missão.
- [ ] **Observabilidade:** métricas de distribuição (tempo fila→atribuição→entrega), health de Reverb, custo Google Maps, taxa de recusa, SLA de entrega.
- [ ] **Escala:** revisar índices (posições, trilha), retenção de GPS, storage de evidências (S3-compatível), throttles.
- [ ] **Segurança:** revisar permissões `logistica.*`/`missao.*` (RBAC/ABAC existente), rate limits, tamanho de upload.
- [ ] **Push confiável:** confirmar Firebase Admin no backend (dependência já anotada no projeto do app mobile).

---

## Dependências entre fases (resumo)

```
F0 ─┬─> F1 ─> F2 ─> F3
    └─> F4 ─┬─> F5 ─> F6
            ├─> F7 ─> F8 ─> F9
            └─> F10
todas ─> F11
```

## Decisões que precisam do dono do produto (antes das fases marcadas)

1. **Fonte de verdade do GPS (F3/F6):** celular do entregador (já existe) ou rastreador do veículo (Monitora)? Recomendação: **celular do entregador** como primário (já pronto, granular por pessoa), Monitora como cruzamento/auditoria de frota.
2. **Modo de distribuição (F3):** começar em `sugerir` (operador confirma) e migrar para `auto` por empresa quando houver confiança nos dados.
3. **Orçamento Google Maps (F5/F6):** Distance Matrix/Directions são pagos por chamada; definir cache e teto. Haversine cobre o MVP de distribuição sem custo.
4. **Capacidade do veículo (F0/F3):** modelar capacidade em botijões por tipo de veículo (`VeiculoTipo`) para a regra de "não estourar o veículo".

---

*Este plano é o backlog oficial da plataforma logística. Cada fase é entregue,
testada e commitada na main isoladamente, conforme o fluxo do projeto.*
