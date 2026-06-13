# Fase 5 (Unificação Web + API) — Registro de Execução

> Execução da Fase 5 do `PLANO_MODERNIZACAO_ECOSSISTEMA.md`, validada contra o
> container Docker do ERP. **Produção intocada** (mudanças no clone do ctrl-web).
> Data: 2026-06-13.

> **Estratégia aprovada: Unificação estrutural segura.** A API (`api-app-gc`) passa a
> ser um módulo `App\Api` DENTRO do ERP (mesmo código, mesmo deploy, mesmo PostgreSQL),
> servindo as rotas do app sob `/api`. **Contratos dos apps publicados preservados**
> (retrocompatibilidade). As tabelas espelho `*_importacao` **permanecem por ora** — a
> eliminação/migração de dados é etapa posterior (precisa de dados reais para reconciliar).

---

## Descoberta-chave do diagnóstico

A API e o ERP **não eram duplicatas** — formavam um par **cliente-servidor por HTTP**:
- app mobile → `api-app-gc` (API)
- `api-app-gc` → chamava o ERP de volta via HTTP (`ApiResources` com `erpurl`/`erp_authorization`)
- o ERP já tinha `App\AppController` respondendo a essas chamadas, e uma conexão `sgcm_api` no config

A unificação **elimina essa ida-e-volta HTTP**: com a API rodando dentro do ERP, o acesso
aos dados é direto (mesma aplicação, mesmo banco).

---

## O que foi feito

### 1. Módulo `App\Api` criado no ERP
Estrutura nova em `ctrl-web/app/Api/`:
- `Models/` — 27 models portados (namespace `App\Api\Models`).
- `Http/Controllers/` — 21 controllers (namespace `App\Api\Http\Controllers`).
- `Http/Requests/` — 10 form requests.
- `Http/Middleware/Access.php` — middleware de log das rotas mobile.
- `Repository/` — 21 repositories.
- `Services/`, `Resources/` — services + `ApiResources`.
- `Models/ApiModel.php` (novo) — classe base que centraliza a conexão `sgcm_api`.

Todos os namespaces e referências (`use App\X` → `App\Api\Models\X`, repositories,
services, resources) reescritos. Controllers estendem o `Controller` base do ERP.

> **Detalhe técnico:** o `Set-Content` do PowerShell adicionava BOM UTF-8 antes do
> `<?php`, quebrando o `namespace`. Corrigido reescrevendo os 80 arquivos com
> `UTF8Encoding($false)` (sem BOM).

### 2. Rotas servidas pelo ERP (contrato preservado)
- `routes/api_mobile.php` (portado de `api-app-gc/routes/api.php`).
- `RouteServiceProvider::mapAppMobileApiRoutes()` carrega-as sob o prefixo `/api` com
  namespace `App\Api\Http\Controllers`, **antes** das rotas próprias do ERP (prioridade).
- Paths idênticos aos do app publicado (`/api/getToken`, `/api/video/get`,
  `/api/v2/order/root`, etc.) — **nada removido/renomeado**.
- Middleware `access` registrado no `Http/Kernel` do ERP.

### 3. Conexão de dados
- `config/database.php`: conexão `sgcm_api` agora com driver configurável
  (`DB_DRIVER_API`), apontando no dev unificado para o **mesmo PostgreSQL do ERP**.
- Models do módulo Api usam `sgcm_api` (via `ApiModel`/`$connection`).
- Tabelas espelho `*_importacao` **mantidas** (estratégia aprovada).

---

## Validação (contra container)
- **Suíte completa do ERP: 14 testes OK** (10 anteriores + 4 de unificação), 1 skip.
- `tests/UnificacaoFase5Test.php` prova: rota pública mobile responde pelo ERP;
  `getToken` rejeita app_key inválida com contrato preservado (404/NOK); classes do
  módulo `App\Api` autoloadáveis; models usam conexão `sgcm_api`.
- HTTP coexistindo no mesmo app: ERP `/` e `/login` → 200; API `/api/getToken` → 404
  (rejeita corretamente); API `/api/video/get` → 400 (tenta a tabela `videos`, que não
  existe no Postgres de dev — esperado, pois os DADOS das tabelas espelho ainda não
  foram migrados; o ROTEAMENTO e o CÓDIGO estão unificados e corretos).

---

## Pendências / próximos passos
- **Migração de dados das tabelas espelho** (`*_importacao`, `videos`, `users` do app)
  para o PostgreSQL — junto com a migração de dados reais do staging. Só então faz
  sentido **eliminar a duplicação** e reapontar os repositories para as tabelas do ERP.
- **Substituir as chamadas HTTP `ApiResources` ERP↔API por chamadas internas diretas**
  (agora que o código está junto) — otimização que remove latência de rede.
- **Aposentar o projeto `api-app-gc`** standalone quando a unificação for para produção
  (o app mobile passará a apontar para `https://erp.../api` em vez da API separada).
- **Versionamento explícito** (`/api/v1`, `/api/v2`): hoje os paths `v2/...` já existem
  como no original; formalizar a política de versionamento ao publicar novos endpoints.

## Como reproduzir
```bash
cd ctrl-web
docker compose exec app vendor/bin/phpunit --filter UnificacaoFase5Test   # 4 OK
curl http://localhost:8082/api/getToken?app_key=x                          # {"status":"NOK"} (contrato)
```

> Portão de saída da Fase 5: ✅ API servida pelo mesmo Laravel/Postgres do ERP, código
> portado e autoloadável, contratos preservados, suíte verde. Pronto para a **Fase 6
> (multi-tenant: tenant_id + RLS)**.
