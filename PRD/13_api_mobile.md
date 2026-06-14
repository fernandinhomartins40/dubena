# PRD — API Mobile (App\Api)  ·  D13

- **Status:** ✅ pronto
- **Criticidade:** 🟠 (contratos consumidos por apps PUBLICADOS — não pode quebrar)
- **Decisão:** **REFATORAR** (já é módulo isolado; modernizar sem quebrar contrato)

---

## 1. Escopo
- Módulo `App\Api` (ex-api-app-gc), schema `api`. Já unificado dentro do ctrl-web
  (Fase 5). `app/Api/{Models,Http/Controllers,Repository,Resources,Services}`.
- Rotas `routes/api_mobile.php` sob `/api` (getToken, v2/order/root, client/getById,
  produtos, video, etc.).

## 2. O que o módulo FAZ
- Backend dos apps mobile (cliente/entregador): autenticação por token, pedidos,
  clientes, produtos, vídeos, notificações.
- Contratos **versionados e retrocompatíveis** — apps publicados nas lojas
  dependem dos endpoints exatos.

## 3. Como FAZ hoje
- Módulo isolado (schema api), conexão `sgcm_api`. Token via `APP_TOKEN_KEY`
  (Fase 1, hash_equals). Repositories com SQL (alguns whereRaw já no inventário C).
- Tabelas espelho `*_importacao` ainda existem (sincronização redundante com o ERP).

## 4. Gambiarras / dívida técnica
- [ ] **Tabelas espelho `*_importacao`**: duplicam dados do ERP (sincronização) em
      vez de ler direto — dívida da Fase 5 (Frente G do plano de fechamento).
- [ ] `whereRaw` interpolado em alguns repositories (Frente C).
- [ ] DATE_FORMAT já convertido p/ to_char (Postgres).

## 5. Riscos de tocar
- **Contrato**: qualquer mudança de payload/rota quebra apps já instalados. Só
  ADICIONAR versões; nunca alterar/remover o que existe.
- Token/auth: segurança (já endurecida na Fase 1).

## 6. Estado de compatibilidade Postgres
- ✅ schema api migrado (32 tabelas); /api responde. DATE_FORMAT convertido.
- 🟡 whereRaw a triar (Frente C); *_importacao a eliminar (Frente G).

## 7. Visão REESCRITA/REFATORADA (Laravel 12)
- **REFATORAR**, não reescrever: já é módulo isolado e os contratos são sagrados.
- Substituir `*_importacao` por **leitura direta** das tabelas do ERP (mesmo banco
  agora) — fim da sincronização (Frente G).
- API Resources/FormRequests padronizados; autenticação via Sanctum/Passport por
  cliente com escopos (evolução do token atual, mantendo contrato antigo).
- Versionamento: manter v1/v2 atuais; novas features em /v3.
- Validar com **build de staging do app** apontando para a API unificada.

## 8. DECISÃO e justificativa
- **Decisão: REFATORAR.**
- **Por quê:** já modernizado estruturalmente (módulo/schema); o valor agora é
  eliminar a duplicação (*_importacao) e endurecer auth SEM quebrar apps publicados.
- **Pré-requisitos:** Frente G (ler ERP direto); Frente C (whereRaw); teste com app
  real de staging.
- **Esforço:** médio.
- **Ordem:** após os domínios-fonte (cliente/pedido/produto) estarem estáveis, para
  apontar a leitura direta com segurança.
