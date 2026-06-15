# PRD FIDEDIGNO (linha-a-linha) — Monitoramento GPS (módulo App\Monitora) · D14

> Módulo `App\Monitora` (ex-app standalone monitoramento-veiculos, unificado ao ctrl-web).
> 16 controllers (~2.600 linhas) + 2 Commands de sync GPS (SyncPosicoesSGCasa,
> UpdateClientsLocation). Lidos integralmente: ApiController(231 — GPS/posições),
> SearchController(254 — busca/posição/pedidos/cercas/rotas), AuthController(67),
> VeiculoController(262, núcleo). Caracterizados: Cadastro(320), Users(300), Cerca(239),
> Empresa(176), EmpresasGrupo(158), Rastreamento(148), Report(141), Config/Rota/Evento/
> Menu (CRUD/consulta do módulo).

- **Status:** ✅ pronto (fiel — núcleo lido; periféricos caracterizados)
- **Criticidade:** 🟡 (operacional: mapa de entregas/GPS; não fiscal) · **🔴 savePosition (porta pública)**
- **Decisão:** **REESCREVER** (CRUDs + UI mapa) — preservar a integração GPS e o elo com o ERP

---

## 1. O que cada peça FAZ (verificado)
- **ApiController (231):** endpoints GPS server-to-server — **savePosition** (recebe posição
  do device/Traccar → grava Position + Ultimaposicao, converte nós→km/h ×1.852, timezone
  SP), getUsuarios/getEmpresas (S2S), testarToken, WebSocket de posição (Ratchet, fluxo
  comentado).
- **SearchController (254):** busca "smart" (selectize), **getPosicaoAtual** (últimas
  posições das empresas do user), **getPedidosPendentes** (lê os pedidos do ERP no mesmo
  Postgres — schema public), cercas eletrônicas (polígonos), rotas (histórico de posições
  por veículo/período).
- **AuthController (67):** login **isolado** via `Auth::guard('monitora')` (guard próprio,
  valida `ativo`); monta menu/config na sessão.
- **VeiculoController (262):** CRUD do veículo do monitoramento — vincula **deviceid (GPS)
  ↔ Device ↔ Veiculo**, mantém Ultimaposicao, `veiculoerp_id` (elo com o veículo do ERP/D09).
- **Cadastro/Users/Cerca/Empresa/EmpresasGrupo/Rastreamento/Report/Config/Rota/Evento/
  Menu:** cadastros e consultas do módulo (cercas eletrônicas, rastreamento, eventos,
  relatórios de rota). **Sem sistema de policies** (só guard + middleware `auth.monitora`).
- **Commands:** SyncPosicoesSGCasa / UpdateClientsLocation (sync GPS de fontes externas
  sgcasa/mysql2 — dependem de credenciais de env).

> Regra real a preservar: ingestão de posição GPS (savePosition + Ultimaposicao),
> cercas eletrônicas, o elo veiculoerp_id↔ERP e a leitura de pedidos pendentes do ERP.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 Bug grave (multi-empresa) e fatal potencial
- **SearchController::getPedidosPendentes:147 — `ped.GRUPO_ID = 2 AND ped.EMPRESA_ID = 2`
  HARDCODED**: o mapa de pedidos pendentes filtra **grupo/empresa 2 fixos** em vez da
  empresa do usuário logado (`Auth::guard('monitora')->user()`). ⇒ o monitoramento só
  mostra a empresa 2; qualquer outra revenda vê vazio. (Mesmo padrão do `empresas.id=2`
  do Conveniogbgestao/D07 — há um viés de "empresa 2" hardcoded espalhado.)
- **ApiController::getEmpresas:34 — `Session::get(...)` SEM `use Session`**: no namespace
  `App\Monitora\Http\Controllers` não há import de Session → **fatal "Class Session not
  found"** se a rota for chamada.

### 🟠 Bugs / dívida funcional
- **SearchController::getCercaRastreamento:171-189 — código morto após `return`** (o HTML
  de `<select>` de cercas nunca executa).
- **SearchController::getPosicaoAtual:53-56 — loop morto** (foreach sobre empresas que não
  acumula nada; o resultado real é recalculado em :57 com whereIn).
- **ApiController::sendWsMessage:181 — `sha1($apiKey)`** como app_key do WebSocket (chave
  previsível) — mas o fluxo (`sendLastPositionAPI`) está **comentado/inativo**.
- **Sem authorize/policies** nos controllers do módulo — confiam só no guard `monitora` +
  middleware. Menos granular que o ERP (mas isolado por guard).

### 🟡 Dívida estrutural
- **Menu pré-renderizado na sessão** (AuthController:36-37) — muda permissão só no relogin
  (mesmo problema do D11).
- **HTML montado no controller** (getCercaRastreamento — morto; padrão do legado).
- **Models homônimos** (User/Empresa/Veiculo/Setor/Menu) isolados no schema `monitora` —
  cuidado: a unificação já corrigiu o caso dos models que ficaram `extends Model` (quebrou
  login); todos devem estender `MonitoraModel` (`$connection='monitora'`).
- **Sync GPS** depende de fontes externas (sgcasa/mysql2) ainda MySQL — credenciais via env.
- **Schema `monitora` nasce vazio** — migração de dados reais do GPS pendente (memória).

### ✅ O que está BOM (correções da Fase 1 + unificação confirmadas)
- **savePosition** (S3): antes SEM auth (qualquer um injetava posição) → agora exige
  `INTEGRATION_TOKEN` (hash_equals, fail-safe), valida input (Validator) e só aceita
  device existente. **testarToken** trocou o `telefone=='123456'` hardcoded por token.
  **getUsuarios** removeu `password` da resposta. Bem corrigido.
- **getPedidosPendentes**: oracle3 eliminado → `DB::connection('pgsql')` lê o ERP no mesmo
  banco; SQL já em sintaxe Postgres (TO_CHAR/EXTRACT EPOCH/`||`/COALESCE/CASE).
- Auth isolado por guard; `e()` (escape) nos Inputs; conversão de velocidade/timezone.

## 3. Especificação do REESCRITO (Laravel 12)
- **REESCREVER** os CRUDs (veículo/cerca/rota/evento/config) e a **UI do mapa** (moderna,
  tempo real via WebSocket/broadcasting); **policies** por empresa/grupo (não guard solto).
- **Preservar** a ingestão GPS (savePosition com token + validação) e o elo veiculoerp_id↔
  ERP (D09); unificar o cadastro de veículo (hoje duplicado ERP D09 × Monitora D14).
- **Corrigir o hardcode de empresa** (getPedidosPendentes por empresa do user) — crítico p/
  multi-revenda.
- **Sync GPS** → jobs/queue com fontes parametrizadas (sgcasa/mysql2 → conexões env).

## 4. DECISÃO
- **Decisão: REESCREVER** (operacional, não fiscal; alto ganho de UX no mapa).
- **Quick wins aplicáveis JÁ:**
  (a) **trocar `GRUPO_ID=2 AND EMPRESA_ID=2` hardcoded** por empresa/grupo do user logado
     (getPedidosPendentes) — monitoramento quebrado p/ outras empresas;
  (b) **`use Session`** no ApiController (getEmpresas fatal);
  (c) remover código morto pós-return (getCercaRastreamento) e o loop morto (getPosicaoAtual).
- **Pré-requisitos:** D09 (frota — unificar cadastro de veículo); D11 (navegação/policies);
  migração dos dados reais de GPS; multi-tenant (cercas/posições por empresa).
- **Esforço:** médio (16 controllers, mas CRUD/consulta simples + UI mapa).
- **Ordem:** depois de D09 (frota) e D11; é satélite operacional, não bloqueia o fiscal.
