# Frente E — Módulos do erp-novo sem revisão adversarial (Rodada 2)

**Data:** 2026-10-30 · **Commit:** `133f588d` (main) · **Revisor:** Frente E
**Escopo:** INV-13..22 (produto, logística/missões, monitora/frota, geográfico, RH,
CRM/gestão, relatórios/dashboard, auditoria, telefonia, satélites) + superadmin/SaaS
(`Api/SuperAdmin`, `Domain/Saas`, `Domain/Tenant`).

**Ambientes de prova:** **S** = suíte sqlite `:memory:` (PHP 8.3.33 portátil);
**P** = PostgreSQL 15 local (`127.0.0.1:55432`, banco `erp_teste`, 172 migrations,
141 empresas), runtime `erp_app` com RLS real; **E** = leitura estática.
As sondas (`PieR2Sonda*Test.php`) foram escritas no scratchpad, copiadas para
`erp-novo/tests/Feature/`, executadas e **apagadas logo em seguida**. Depois de cada
execução, `git status --short tests` voltou vazio. Nenhum arquivo do projeto foi alterado.

---

## 1. Sumário dos achados

| ID | Título | Sev. | Estado | Esforço |
|---|---|---|---|---|
| P2-E-01 | O superadmin não enxerga nenhuma empresa sob RLS: lista 0, dashboard 0, suspender/assinatura dão 404 | ALTA | CONFIRMADO (P) | M |
| P2-E-02 | A licença tem furos: aliases `cobranca/*`, `crm/mala-direta` e `financeiro/dre` passam sem o módulo, e os recursos de app/marketplace/tempo real nunca são cobrados | ALTA | CONFIRMADO (S + E) | P |
| P2-E-03 | A mala direta exporta a base de clientes (nome, e-mail, endereço, nascimento) em CSV com `cliente.view`, sem `cliente.export*` e sem trilha | ALTA | CONFIRMADO (S) | P |
| P2-E-04 | Gerente lê a trilha de auditoria (antes/depois) por `relatorios/auditoria`, embora o papel exclua `auditoria.*` | MÉDIA | CONFIRMADO (S) | P |
| P2-E-05 | O Operador muda o preço de venda pelo cadastro do produto sem `produto.preco` | MÉDIA | CONFIRMADO (S) | P |
| P2-E-06 | 2FA do superadmin é "obrigatório" só no comentário: não há como habilitá-lo, não há lockout e não há separação de papéis na plataforma | ALTA | CONFIRMADO (E) | M |
| P2-E-07 | O webhook do PABX não funciona sob RLS (404) e só atende uma empresa da plataforma inteira (env global) | MÉDIA | CONFIRMADO (P) | M |
| P2-E-08 | A chave Google (Roads e Geocoding) vaza para o log, e o endereço do cliente também, em toda falha de conexão | MÉDIA | CONFIRMADO (sonda + E) | P |
| P2-E-09 | O replay de rota do veículo com De = Até (o padrão da tela: hoje) volta vazio | MÉDIA | CONFIRMADO (S; semântica conferida em P) | P |
| P2-E-10 | Ferramenta de migração e catálogo de cidades da plataforma sem trilha de auditoria; o CSV de descartes (PII do legado) sai sem registro | MÉDIA | CONFIRMADO (E) | P |
| P2-E-11 | RH: CPF na listagem, ASO (dado de saúde), família e comissões sob um único `colaborador.view`; comissão e ponto alteráveis sem trilha | MÉDIA | CONFIRMADO (E) | M |
| P2-E-12 | Empresa-casa × empresa ativa: monitora (criar veículo, últimas posições, sync) e carga de franqueado usam `user()->empresa_id` | BAIXA | FORTE INDÍCIO (E) | P |
| P2-E-13 | Tabelas-filhas sem RLS por migration (`checklist_perguntas`, `sorteio_numeros`): a proteção só existe depois de rodar o comando pós-carga | BAIXA | CONFIRMADO (P, banco local) | P |
| P2-E-14 | Pequenos furos de regra: telefonia `atender/rejeitar` com `pedido.view`; sorteio aceita número e troca de prêmio depois de sorteado; exclusões de subrecurso com `.edit` | BAIXA | CONFIRMADO (E) | P |

---

## 2. Mapa por módulo

Permissões levantadas por varredura de `autorizar(...)` em cada método público dos
controllers (`app/Http/Controllers/Api/Admin/*`). RLS consultada em `pg_class` e
`pg_policies` no banco local (P). Os testes rodaram com `phpunit --filter`, em lote
por módulo, e a saída está em `scratchpad/e/testes_<modulo>.txt`.

| Módulo | Rotas (manifesto) | Permissões | Model/RLS | Licença (`licenca.rota`) | Testes rodados (S) | Estado |
|---|---|---|---|---|---|---|
| **RH** (`colaboradores`, `departamentos`, `setores-org`, `unidades`) | 23 + 12 | `colaborador.view/create/edit/delete`; subrecursos (família, recesso, comissão, exame, turno, ponto): leitura com `.view`, escrita **e exclusão** com `.edit` | `Colaborador*` com `BelongsToTenant`; policy `app_tenant_can_read` em `colaboradores` e `colaborador_familias` | núcleo (sem recurso) | 45 testes / 128 asserções, **OK** (22 s) | P2-E-11 |
| **Superadmin/SaaS** (`superadmin/*`) | 30 | `auth:platform`, **sem papéis** | `planos`, `platform_*`, `migracoes`, `tenant_accounts` sem RLS (catálogo da plataforma); `empresas`, `assinaturas`, `recurso_overrides` **com** RLS | — | 81 / 224, **OK** (54 s) | P2-E-01, 06, 10 |
| **Monitora/Frota** (`monitora`, `veiculos`, `checklists`) | 20 + 18 + 5 | `monitora.view/edit`, `veiculo.*`, `checklist.*` | `monitora_posicoes` e `veiculos` com policy; expurgo em lote por `AutomacaoPorEmpresa` | `monitora/`→monitora, `veiculos`→frota | 103 / 238, **OK** (36 s) | P2-E-08, 09, 12 |
| **Logística/Missões** (`missoes`, `taxas-entrega`) | 9 + 5 | `missao.view/create/edit/aprovar`, `logistica.config` | `MissaoEvidencia` com tenant; foto validada `image|max:8192` | **sem recurso** (`missoes` fora do mapa) | 38 / 94, **OK** (30 s) | ver §4 |
| **Geográfico** (`geo`, `cidades`, `logradouros*`, `regioes`, `municipios-ibge`) | 5+4+8+4+4 | `cliente.*`, `cidade.*`, `empresa.view/edit` | `Cidade/Bairro/Rua/Regiao` com `BelongsToGrupo`; `logradouros_oficiais`, `municipios_ibge` são catálogos globais sem RLS | núcleo | 92 / 243, **OK** (36 s) | sem achado novo |
| **CRM/Gestão** (`pos-vendas`, `promocoes`, `sorteios`, `metas`, `checklists`, `crm/mala-direta`, `documentos`, `bens`, `mcmm`, `cupons-fiscais`) | ~45 | `posvenda.*`, `promocao.*`, `sorteio.*`, `meta.*`, `documento.*`, `bem.*`, `cupomfiscal.*`; **mala direta = `cliente.view`** | `Sorteio/Checklist/Promocao` com `BelongsToGrupo`; filhas sem RLS por migration | `crm` em 5 prefixos; **`crm/mala-direta` e `cupons-fiscais` fora** | 31 / 96, **OK** (24 s) | P2-E-02, 03, 13, 14 |
| **Relatórios/Dashboard** (`relatorios`, `dashboard`) | 13 + 2 | `relatorio.view`; `dashboard/resumo` sem permissão (só contagens); `dashboard/hoje` por bloco | consultas com `empresa_id` explícito | `relatorios/`→relatorios_avancados; **`financeiro/dre` fora** | 35 / 131, **OK** (2 min 39 s) | P2-E-02, 04 |
| **Auditoria** (`auditoria/*`) | 6 | `auditoria.view` (e `relatorio.view` pelo relatório) | `audit_logs` **sem RLS** (filtro `empresa_id` na aplicação, conferido nos 6 métodos) | núcleo | 39 / 150, **OK** (19 s) | P2-E-04 |
| **Telefonia** (`telefonia`, `pabx/chamada`) | 4 + 1 público | `pedido.view` em tudo, inclusive `atender/rejeitar` | `ChamadaEntrante` com tenant | núcleo | 17 / 35, **OK** (14 s) | P2-E-07, 14 |
| **Satélites** (`satelites`, `franqueados`) | 3 + 3 | `relatorio.view`, `monitora.view`, `empresa.view`; carga `estoque.view/edit` | colaborador filtrado por `user()->empresa_id` | núcleo | 21 / 62, **OK** (16 s) | P2-E-12 |
| **Produto** (`produtos`, `produto-config`, `produtos-precos`) | 6 + 8 + 2 | `produto.*`, `produto.config`, `produto.preco` (reajuste em massa) | `Produto` com tenant e auditável; custo com FLS | núcleo | 27 / 69, **OK** (18 s) | P2-E-05 |

---

## 3. Achados

### P2-E-01 — O superadmin não enxerga nenhuma empresa sob RLS
- **Categoria:** SaaS / operação da plataforma · **Severidade:** ALTA. É o único canal da plataforma para suspender uma revenda inadimplente (a correção do PIE-04), trocar o plano e sobrepor recursos, e ele não funciona com o banco como roda em produção. O isolamento continua íntegro (falha fechada), por isso não é CRÍTICA. · **Estado:** CONFIRMADO (P).
- **Arquivo:linha:** `app/Domain/Saas/SuperAdminService.php:20` (o comentário diz "sem variável = não filtra"), `:43`, `:70`, `:82`, `:101`, `:349-350`; `routes/api.php:1020` (`auth:platform`, sem nenhum middleware que dê contexto ao Postgres).
- **Rota:** `GET /api/superadmin/empresas`, `GET /dashboard`, `POST /empresas/{id}/suspender`, `PUT /empresas/{id}/assinatura`, `PUT /empresas/{id}/override`.
- **Reprodução:** sonda `PieR2SondaSuperAdminRlsTest` (P, `erp_app`):
  ```
  [sonda] role runtime=erp_app empresas visiveis ao owner=141
  [sonda] GET empresas status=200 itens=0
  [sonda] dashboard={"empresas_total":0,"empresas_ativas":0,"assinaturas_ativas":0,...}
  [sonda] POST suspender 1 status=404
  ```
  Conferido também por SQL: `SET LOCAL ROLE erp_app; SELECT count(*) FROM empresas` → 0. A policy é `grupo_id = current_setting('app.grupo_id')`, que exige a variável.
- **Esperado × observado:** listar as 141 empresas e suspender a de id 1 × lista vazia, dashboard zerado e 404.
- **Impacto:** a plataforma não consegue suspender uma revenda, mudar o plano, conceder override nem ver o dashboard. Isso nunca apareceu porque `SuperAdminTest` roda só em sqlite e nenhum teste do portão Postgres cobre `/superadmin`. É a mesma armadilha do `CLAUDE.md` ("Comando de console lê ZERO linhas sob RLS"), agora numa rota HTTP. **HIPÓTESE:** a escrita em `recurso_overrides`/`assinaturas` também seria barrada pelo `WITH CHECK` (não chegou a ser exercida, porque o `findOrFail` falha antes), e a ferramenta de migração tem o mesmo risco.
- **Correção proposta:** a camada da plataforma usa uma conexão explícita (`ConexaoDeOwner`/`EnxergaAtravesDaRls`) ou uma função `SECURITY DEFINER` restrita, com trilha. Corrigir o comentário do service.
- **Teste de aceite:** a sonda acima entra no `test:pgsql-rls` (lista > 0, suspender 200, e `empresas.ativo=false` lido pelo owner).
- **Esforço:** M.

### P2-E-02 — Licença com furos: aliases de rota e recursos de app não cobrados
- **Categoria:** Receita SaaS · **Severidade:** ALTA. Tira a diferença entre os planos: a cobrança registrada, o DRE e o CRM ficam disponíveis a quem não pagou. · **Estado:** CONFIRMADO (S para os aliases; E para os recursos de app).
- **Arquivo:linha:** `app/Http/Middleware/RecursoPorRota.php:38-56` (o mapa traz `boletos`, `remessas`, `mala-direta`, `relatorios/`, mas não `cobranca/`, `crm/`, `financeiro/dre`, `cupons-fiscais`, `missoes`) e `:84` (`if (! str_starts_with($caminho, 'api/admin/')) return null;`, ou seja, `app/v1/*` nunca é checado). `routes/api.php:495-500` (aliases `cobranca/*`), `:711` (`crm/mala-direta`), `:753` (`financeiro/dre`). O middleware `recurso:` (`bootstrap/app.php`) não é usado em nenhuma rota (`grep "recurso:" routes/api.php` → 0).
- **Reprodução:** sonda `test_licenca_aliases` (S, enforcement ligado, plano Essencial sem `cobranca`; recursos efetivos `["app_consumidor","app_entregador","nfce"]`):
  ```
  controle cobranca: GET /api/admin/boletos => 402
  alias cobranca: GET /api/admin/cobranca/boletos => 200
  alias cobranca: GET /api/admin/cobranca/remessas => 200
  controle crm: GET /api/admin/sorteios => 402
  crm sem mapa: GET /api/admin/crm/mala-direta => 200
  controle relatorios_avancados: GET /api/admin/relatorios/dre => 402
  alias DRE: GET /api/admin/financeiro/dre => 200
  ```
- **Esperado × observado:** 402 em todo módulo não contratado × 200 nos aliases. Os recursos `marketplace`, `app_consumidor`, `app_entregador` e `tempo_real` estão no catálogo (`RecursoCatalogo.php`) e nos planos, mas nenhuma rota os exige.
- **Impacto:** o plano mais barato leva cobrança CNAB (a SPA usa o alias `cobranca/*`), DRE e mala direta. Vender app ou marketplace como item pago não tem efeito técnico.
- **Correção proposta:** (1) trocar o mapa por prefixo pela **declaração do recurso na própria rota** (grupo `Route::middleware('recurso:cobranca')`), ou ao menos incluir `cobranca/`, `crm/`, `financeiro/dre`, `cupons-fiscais`; (2) aplicar `recurso:app_consumidor`/`app_entregador`/`marketplace` nos grupos `app/v1`; (3) um guardião que percorra o manifesto e exija um recurso declarado (ou núcleo explícito) para toda rota de módulo opcional, com piso de contagem.
- **Teste de aceite:** a sonda acima passa (402 nos 4 aliases), mais um caso de app do entregador com plano sem `app_entregador` → 402.
- **Esforço:** P.

### P2-E-03 — A mala direta extrai a base de clientes sem permissão de exportação
- **Categoria:** Segurança / LGPD · **Severidade:** ALTA. Contorna o controle desenhado pela própria base: `cliente.export.completo` foi separado "sob a LGPD" e tirado até do Gerente (`RbacSeeder.php:53-57`). O Operador, papel do dia a dia, tem `cliente.view`. · **Estado:** CONFIRMADO (S).
- **Arquivo:linha:** `app/Http/Controllers/Api/Admin/MalaDiretaController.php:30` (`autorizar('cliente.view')`), `:43-56` (sem limite, todos os ativos), `:68-73` (CSV). Comparar com `ClienteExportacaoController.php:44/72/86` (`cliente.export.completo` + `AuditoriaSeguranca`).
- **Rota:** `GET /api/admin/crm/mala-direta?formato=csv`.
- **Reprodução:** sonda `test_operador_exporta_base_pela_mala_direta` (papel real Operador, via `RbacSeeder`):
  `POST clientes/exportacao => 403; GET crm/mala-direta csv => 200 linhas=4` (cabeçalho + 3 clientes).
- **Impacto:** qualquer operador baixa nome, e-mail, endereço e data de nascimento de toda a base ativa, sem rastro, e ainda passa sem licença de CRM (P2-E-02).
- **Correção proposta:** exigir `cliente.export` (ou uma permissão `crm.maladireta`) mais o recurso `crm`; registrar a extração em `AuditoriaSeguranca`, como na exportação oficial; limitar as colunas ao canal (e-mail **ou** endereço).
- **Teste de aceite:** Operador → 403; Administrador → 200 com evento de auditoria gravado.
- **Esforço:** P.

### P2-E-04 — Gerente lê a trilha de auditoria pelo relatório
- **Categoria:** Segurança / RBAC · **Severidade:** MÉDIA. A trilha traz o antes/depois de cadastros (PII de clientes e colaboradores, mudanças de usuário). O desenho exclui `auditoria.*` do Gerente de propósito. · **Estado:** CONFIRMADO (S).
- **Arquivo:linha:** `app/Http/Controllers/Api/Admin/RelatorioController.php:279-307` (`autorizar('relatorio.view')`, devolve `antes`/`depois`/`ip`); `database/seeders/RbacSeeder.php:66`.
- **Reprodução:** sonda `test_gerente_le_trilha_por_relatorio`: `gerente auditoria/trilha => 403; relatorios/auditoria => 200`.
- **Correção proposta:** `autorizar('auditoria.view')` em `RelatorioController::auditoria` (ou exigir as duas).
- **Teste de aceite:** Gerente → 403 nos dois caminhos; entra na `MatrizPapeisReaisTest`.
- **Esforço:** P.

### P2-E-05 — Operador altera o preço de venda sem `produto.preco`
- **Categoria:** Segurança / receita da revenda · **Severidade:** MÉDIA. O preço é o controle de margem. A base criou `produto.preco` para isso, mas só o reajuste em massa a exige. · **Estado:** CONFIRMADO (S).
- **Arquivo:linha:** `app/Http/Controllers/Api/Admin/ProdutoController.php:59-66` e `:78-86` (`dadosPermitidos` remove só o custo; `preco_venda`, `preco_venda_minimo` e `preco_gasdopovo` passam com `produto.edit`); `app/Http/Requests/ProdutoRequest.php:35-39,72-76`; `RbacSeeder.php` (o Operador recebe `produto.edit`).
- **Reprodução:** sonda `test_operador_altera_preco_sem_produto_preco`: `PUT produtos-precos/aplicar => 403; PUT produtos/{id} => 200; preco 100 -> 1`.
- **Mitigação existente:** `Produto` é auditável, então a mudança fica na trilha.
- **Correção proposta:** em `dadosPermitidos`, retirar os campos de preço quando o usuário não tem `produto.preco` (como já se faz com o custo); ou passar a usar FLS `produto.preco.edit`.
- **Teste de aceite:** Operador → preço inalterado (campo ignorado ou 403); Administrador altera.
- **Esforço:** P.

### P2-E-06 — Superadmin: 2FA não habilitável, sem lockout, sem papéis
- **Categoria:** Segurança / plataforma · **Severidade:** ALTA. É o acesso que cruza todas as revendas ("senha fraca aqui vaza TODAS as revendas", `SuperAdminSeeder.php:16`). Hoje o P2-E-01 limita o que ele vê, mas corrigir o P2-E-01 sem corrigir isto abre a porta. · **Estado:** CONFIRMADO (E).
- **Arquivo:linha:** `app/Http/Controllers/Api/SuperAdmin/AuthController.php:42` (só exige OTP `if ($admin->twofa_habilitado)`); `database/seeders/SuperAdminSeeder.php:13` ("o admin configura no 1º acesso"). Não há rota para configurar o 2FA: em `routes/api.php:1017-1065` só existem `login`/`logout`/`me`, e `grep twofa frontend/src/features/superadmin` acha apenas o campo do login. Proteção contra força bruta: só `throttle:login` (10/min por IP, `AppServiceProvider.php:323`), sem o lockout por conta que o login de tenant tem. O token continua válido por 24 h (`sanctum.expiration`) mesmo se o admin for desativado (`ativo` só é lido no login). Não há papéis em `platform_admins`: todo admin cria planos, suspende e roda migração.
- **Correção proposta:** fluxo de cadastro do TOTP (`/superadmin/2fa/iniciar|confirmar`) com **2FA obrigatório antes de emitir token**; lockout por conta; checar `ativo` no guard; papéis mínimos (leitura × operação × migração).
- **Teste de aceite:** login sem 2FA configurado → 423 com instrução de cadastro; 5 senhas erradas → conta travada; admin desativado → 401 com token antigo.
- **Esforço:** M.

### P2-E-07 — Webhook do PABX quebrado sob RLS e preso a uma empresa da plataforma
- **Categoria:** Integração / SaaS · **Severidade:** MÉDIA. A bina não funciona em produção. E, como produto, só uma revenda da plataforma inteira pode ter telefonia (`PABX_EMPRESA_ID` é global, em env). · **Estado:** CONFIRMADO (P).
- **Arquivo:linha:** `app/Http/Controllers/Api/PabxWebhookController.php:35-36` (segredo e empresa vêm de `config('services.pabx.*')`), `:64` (`Empresa::query()->findOrFail(...)` sem contexto de tenant); `routes/api.php:101` (rota pública, sem `throttle`).
- **Reprodução:** sonda `PieR2SondaPabxRlsTest` (P, `erp_app`, segredo e empresa configurados): `POST /pabx/chamada => 404`.
- **Impacto:** a chamada entrante não chega à fila; `TelefoniaTest` (17 verdes em S) não detecta porque o sqlite não tem RLS. **HIPÓTESE:** mesmo achando a empresa, a busca por telefone e o insert de `ChamadaEntrante` também precisariam do envelope.
- **Correção proposta:** credencial por empresa (`IntegrationAccount`, como o comentário já prevê), resolvendo o tenant pelo token e executando em `AutomacaoPorEmpresa::naEmpresa()`; acrescentar `throttle`.
- **Teste de aceite:** a sonda entra no portão Postgres (201 e chamada visível na fila da empresa).
- **Esforço:** M.

### P2-E-08 — Chave do Google e endereço do cliente vão para o log
- **Categoria:** Segredo em log / LGPD · **Severidade:** MÉDIA. A chave de cada revenda é guardada cifrada (`encrypted`) e sai em claro no `laravel.log` e em `failed_jobs`. · **Estado:** CONFIRMADO: formato provado por sonda, caminho pelo código.
- **Arquivo:linha:** `app/Domain/Monitora/Drivers/RoadsApiAjustador.php:53-57` (`key` na query) e `:86` (`Log::warning(... $e->getMessage())`); `app/Domain/Cliente/GeocodificarClienteJob.php:97-100` (endereço + `key` na query), `:137` (log) e `:140` (`throw $e`, mensagem em `failed_jobs`).
- **Reprodução:** `scratchpad/e/guzzle_msg.php`, com o cliente HTTP do Laravel e chave fictícia:
  `Illuminate\Http\Client\ConnectionException: cURL error 28: ... for http://127.0.0.1:1/v1/snapToRoads?path=-23.5%2C-46.6&key=CHAVE-FICTICIA-DA-SONDA`.
  A falha de conexão embute a URL completa na mensagem. O `GoogleRoutesDriver` usa o header `X-Goog-Api-Key` e não tem o problema.
- **Correção proposta:** mandar a chave em header (`X-Goog-Api-Key`, aceito pelas duas APIs) ou sanitizar a mensagem (`preg_replace('/key=[^&\s]+/', 'key=***')`) antes de logar ou relançar; não pôr o endereço na mensagem.
- **Teste de aceite:** `Http::fake` lançando `ConnectionException` com a URL; `Log::spy` mostra que nenhuma linha contém `key=`.
- **Esforço:** P.

### P2-E-09 — Replay de rota do dia volta vazio
- **Categoria:** Qualidade / dado temporal · **Severidade:** MÉDIA. A tela abre com De = Até = hoje (`frontend/src/features/satelites/RotaTab.tsx:70-71`) e mostra "sem posições", e o operador conclui que o veículo não rodou. · **Estado:** CONFIRMADO (S); a semântica no Postgres foi conferida: `'2026-06-10 08:00'::timestamp <= '2026-06-10'` → `f`, com coluna `timestamp without time zone`.
- **Arquivo:linha:** `app/Http/Controllers/Api/Admin/MonitoraController.php:176-177` (`where('registrado_em', '<=', $ate)` com `ate` em data pura).
- **Reprodução:** sonda `PieR2SondaHistoricoTest`: posições às 08:00 e 15:30 de 2026-06-10, `?de=2026-06-10&ate=2026-06-10` → `200 posicoes=0`.
- **Por que passou:** `MonitoraApiTest::test_historico_filtra_por_periodo` usa datas espaçadas, e o guardião `SemanticaTemporalTest` só olha `whereBetween`, não `where('<=', data)`.
- **Correção proposta:** `PeriodoPorDia::aplicar()` (ou `Carbon::parse($ate)->endOfDay()`), e estender o guardião a `where(<coluna datetime>, '<=', <data>)`.
- **Teste de aceite:** a sonda passa (2 posições).
- **Esforço:** P.

### P2-E-10 — Atos da plataforma sem trilha (migração, cidades, descartes)
- **Categoria:** Auditoria da plataforma · **Severidade:** MÉDIA. A ferramenta de migração lê bancos externos inteiros, cria empresas e grava credenciais. Nada disso fica em `platform_audit_logs`, ao contrário do que o service afirma ("Nenhuma operação cross-tenant existe fora deste service", `SuperAdminService.php`). · **Estado:** CONFIRMADO (E).
- **Evidência:** `grep "auditoria->registrar"` em `app/` encontra 10 chamadas em `SuperAdminService` e 3 no `AuthController` do superadmin. Há **zero** em `SuperAdmin/MigracaoController.php`, `Jobs/ExecutarMigracaoJob.php`, `Services/Migracao/*` e `Domain/Saas/CidadeService.php`. `descartesCsv` (`MigracaoController.php:227-247`) baixa as linhas cruas do legado (PII) sem registro. A trilha também não registra leituras (quem consultou o quê).
- **Observações menores:** `conectar`, `diagnosticar` e `simular` devolvem `$e->getMessage()` ao cliente (`:91,123,200`). Com host e porta livres, isso permite sondar a rede interna (SSRF limitado a superadmin, BAIXA).
- **Correção proposta:** `AuditoriaPlataforma::registrar` em `store/conectar/executar/descartesCsv` e no CRUD de cidades; mensagem de erro genérica, com o detalhe só no log.
- **Teste de aceite:** cada ação gera uma linha em `platform_audit_logs` com o `platform_admin_id`.
- **Esforço:** P.

### P2-E-11 — RH: PII e dado de saúde sem segregação; comissão e ponto sem trilha
- **Categoria:** LGPD / auditoria · **Severidade:** MÉDIA. O ASO (apto/inapto + observação médica) é dado sensível (LGPD art. 11). Comissão e ponto são dinheiro da folha. · **Estado:** CONFIRMADO (E).
- **Arquivo:linha:** `ColaboradorController.php:365` (CPF em **toda** linha da listagem paginada), `:290-294` (exames com `colaborador.view`), `:181-185` (comissões com `colaborador.view`), `:344-356` (`registrarPonto` faz `updateOrCreate` por data e **reescreve** o ponto de qualquer dia passado). `Models/Rh/ColaboradorComissao.php` e `ColaboradorPonto.php` não são auditáveis; só `Colaborador.php` é. O Gerente herda tudo (tem `colaborador.*` menos `.delete`).
- **Correção proposta:** retirar o CPF da listagem (mascarar); FLS ou permissão própria para exames (`colaborador.saude.view`) e remuneração; tornar `ColaboradorComissao` e `ColaboradorPonto` auditáveis; o ponto de dia fechado só muda com motivo.
- **Teste de aceite:** um papel com `colaborador.view` sem a FLS não recebe CPF nem exames; editar a comissão gera `audit_logs`.
- **Esforço:** M.

### P2-E-12 — Empresa-casa no lugar da empresa ativa
- **Categoria:** Multi-empresa · **Severidade:** BAIXA. Não vaza entre tenants (a RLS e o escopo global cruzam os filtros), mas o usuário multiempresa opera na empresa errada ou recebe 404. É o mesmo defeito "S-2" corrigido em `MissaoController::evidencia`. · **Estado:** FORTE INDÍCIO (E).
- **Arquivo:linha:** `MonitoraController.php:57` (cria o veículo na empresa-casa), `:305` (últimas posições), `:377`, `:532` (sync); `CargaFranqueadoController.php:98` (`localizar`).
- **Correção proposta:** `TenantContext::requireEmpresaId()` em todos.
- **Esforço:** P.

### P2-E-13 — Filhas de configuração por grupo sem RLS por migration
- **Categoria:** Isolamento (defesa em profundidade) · **Severidade:** BAIXA. O acesso pela aplicação passa sempre pelo pai com escopo (`SorteioService.php:40,48`), então não há IDOR demonstrado. · **Estado:** CONFIRMADO no banco local.
- **Evidência (P):** `checklist_perguntas` e `sorteio_numeros` com `relrowsecurity = f` e 0 policies (assim como `condicaopagamento_parcelas`). A proteção só nasce em `TenantLegacyGroupConfigurationProtector.php:130-135`, executado pelo comando pós-carga `saas:tenant:proteger-configuracao-grupo`. Uma revenda nova, sem carga legada, fica sem essa barreira se o comando não rodar. A homologação não foi consultada.
- **Correção proposta:** migration que habilite RLS nas filhas pela chave do pai, independente do pós-carga; incluir as filhas no `RlsCoberturaTest`.
- **Esforço:** P.

### P2-E-14 — Pequenos furos de regra
- **Severidade:** BAIXA · **Estado:** CONFIRMADO (E).
- `TelefoniaController.php:37-50`: `atender`/`rejeitar` (escrita) com `pedido.view`. O papel Entregador tem `pedido.view` e consegue descartar a chamada que toca na central.
- `CrmController.php:107-132`: com o sorteio já `sorteado`, ainda dá para trocar o prêmio e a descrição (`sorteioSalvar`) e incluir números (`sorteioNumero`), o que mina a legitimidade que o `SorteioService` protege.
- Exclusão de subrecurso com `.edit` (22 métodos, por exemplo `deleteComissao`, `delFamilia`, `excluirCerca`, `RegiaoController::destroy` com `empresa.edit`): o Gerente exclui o que o desenho diz que não exclui. Aceitável se for decisão; hoje não está documentado.
- `CargaFranqueadoService.php:45/165-183`: o setor "Em poder de" é criado fora da transação, e duas cargas simultâneas criam dois setores.

---

## 4. Verificado sem achado

- **Missões (logística):** `logistica:gerar-missoes` é idempotente (`temMissaoEmExecucao` considera `atribuida`/`em_andamento`, mais `withoutOverlapping`); a evidência fotográfica é servida por um model com tenant (`MissaoController.php:149-163`); o upload usa `image|max:8192`.
- **Expurgo de posições:** em lote de 1000, por empresa, dentro de `AutomacaoPorEmpresa`, com `withoutOverlapping(60)` (`MonitoraExpurgarPosicoes.php`). O agendamento das 5 automações do escopo usa o envelope (`AutomacaoPorEmpresa`/`EnxergaAtravesDaRls`).
- **Traccar:** basic auth fora da URL, timeouts de 3/5 s, falha logada sem segredo (`TraccarDriver.php:70-77,156-159`).
- **Injeção:** nenhum `orderBy`/`whereRaw`/`DB::raw` com entrada do usuário nos módulos. `LookupController` interpola só colunas de uma lista fixa, e `GasDoPovoService` só uma expressão constante.
- **`whereBetween` em datetime** nos relatórios, malote e monitora: todos com `startOfDay/endOfDay`. O defeito encontrado é o `where('<=')` do P2-E-09.
- **Auditoria:** os 6 endpoints filtram `empresa_id` da empresa ativa; `registro/{entidade}` restringe a entidade ao catálogo (`AuditoriaController.php:151-153`).
- **Override de recurso:** valida a chave no catálogo e exige motivo (`SuperAdminService.php:192-193`).
- **Extrato de remuneração (app):** sempre do próprio `user()` (`AppSolicitacaoController.php:177-195`).
- **Dashboard sem permissão:** só contagens agregadas (`RelatorioService.php:73-88`). Aceitável.

---

## 5. Regras de negócio relevantes sem teste

1. Superadmin e PABX sob RLS (nenhum teste do portão Postgres cobre `/superadmin` ou `/pabx`): é a origem do P2-E-01 e do P2-E-07.
2. Completude do mapa de licença contra o manifesto de rotas (P2-E-02).
3. Matriz de papéis para mala direta, `relatorios/auditoria` e preço (P2-E-03..05). A `MatrizPapeisReaisTest` só cobre clientes, usuários e papéis.
4. Histórico de veículo no mesmo dia (P2-E-09).
5. 2FA do superadmin: só existe teste para "habilitado exige OTP", nunca para "como se habilita".

## 6. Execução

| Lote (`--filter`) | Testes | Asserções | Resultado | Tempo |
|---|---|---|---|---|
| RH | 45 | 128 | OK | 22 s |
| Superadmin/SaaS | 81 | 224 | OK | 54 s |
| Monitora/Frota | 103 | 238 | OK | 36 s |
| Logística | 38 | 94 | OK | 30 s |
| Geográfico | 92 | 243 | OK | 36 s |
| CRM/Gestão | 31 | 96 | OK | 24 s |
| Relatórios/Dashboard | 35 | 131 | OK | 2 min 39 s |
| Auditoria | 39 | 150 | OK | 19 s |
| Telefonia | 17 | 35 | OK | 14 s |
| Satélites | 21 | 62 | OK | 16 s |
| Produto | 27 | 69 | OK | 18 s |

Todos os lotes com "PHPUnit Deprecations: 6", as mesmas da suíte base.

**Sondas (todas falharam como previsto, provando o defeito, e foram apagadas):**
`PieR2SondaSuperAdminRlsTest` (P), `PieR2SondaPabxRlsTest` (P), `PieR2SondaModulosTest` (S, 4 testes, 4 falhas),
`PieR2SondaHistoricoTest` (S), `guzzle_msg.php` (script isolado no scratchpad).

## 7. Inspecionado, não testado, limitações

- **Inspecionado:** rotas do manifesto (619 linhas), os 59 controllers admin (varredura de `autorizar`), os 5 do superadmin, `SuperAdminService`, `RecursoPorRota`, `RecursoCatalogo`, `PlanosSeeder`, `RbacSeeder`, `AutomacaoPorEmpresa`, o agendamento e o RLS de todas as tabelas `public` no banco local.
- **Não testado:** a homologação (o P2-E-01 e o P2-E-07 foram provados no Postgres local com o mesmo schema; a conexão `erp_app` da homologação foi registrada pela Rodada 1 e não foi reconferida aqui); a SPA no navegador; integrações reais (Traccar, Google, PABX); a concorrência real; o desempenho de `paginate` nas tabelas grandes destes módulos (o PIE-28 já cobre o padrão); o `Domain/Tenant` além do que os testes existentes exercem.
- **Limitação:** o banco local nunca rodou os comandos pós-carga, então as policies de grupo e das filhas diferem das da homologação (P2-E-13).

## 8. Parecer

**REPROVADO** para a Frente E.

Os testes existentes dos módulos estão verdes (529 no total nos 11 lotes),
mas quatro defeitos de nível ALTA foram provados por sonda ou por leitura direta do
código, e a suíte não pega nenhum deles:

- a plataforma não consegue operar as revendas sob RLS (P2-E-01);
- a licença deixa passar cobrança, DRE e CRM, e não cobra os apps (P2-E-02);
- o operador extrai a base de clientes pela mala direta (P2-E-03);
- o acesso cross-tenant do superadmin depende só de senha (P2-E-06).

Os três primeiros têm correção de esforço P/M e teste de aceite pronto, que são as
sondas deste relatório transformadas em teste do portão.
