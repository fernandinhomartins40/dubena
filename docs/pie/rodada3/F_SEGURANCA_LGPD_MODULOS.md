# Rodada 3 — Agente F: segurança, LGPD e módulos sem dono

**Datas:** 2026-10-31 a 2026-11-17 (quatro retomadas por limite de uso) · **Partida:** `9fcfde5a` · **Perfis:** anônimo/atacante, revenda B, empresa sem grant, papéis de A (BFLA).
O estado só é declarado para o que foi executado. O resto está em §7.

## 1. Ambiente

| Item | Valor |
|---|---|
| Banco | `pie_r3_f` (`create database pie_r3_f template pie_r3_base`), Postgres 15 local :55432. Em 31/10 o cluster caiu para todos e **eu o subi de novo** (`pg_ctl … start`). Não o derrubei ao final porque outros agentes dependem dele. |
| Atores extras | `erp-novo/e2e/seguranca/preparar-revenda-b.sql` (idempotente, roda como owner): **revenda B** (grupo 2, empresa 3, tenant 2, assinatura no mesmo plano; `adminb@`/`operadorb@pie.local`, papéis com as mesmas permissões dos homônimos de A); `emp2@pie.local` (tenant A, papel Administrador e grant **só** na empresa 2); `consumidor1@`/`consumidor2@` (app, clientes 1 e 2 da empresa 1). Senha `Pie-R3-RevendaB!2026`, só no banco local. |
| API | duas instâncias do mesmo backend/banco, `erp_app`, `SAAS_ENFORCE_LICENCA=true`, `SAAS_ENFORCE_TENANT_ENVELOPE=true`: **8216** (minha porta) e **5216** (porta da SPA, que não usei). Com `php -S … server.php` + `-d zend_extension=opcache`: o `artisan serve` levava **5 a 8 s** por requisição; com opcache, **0,2 s**. |
| Runner | `PIE_DB=pie_r3_f PIE_API_URL=http://127.0.0.1:8216,http://127.0.0.1:5216 node e2e/seguranca/matriz-autorizacao.mjs` (`PIE_DOC=<arquivo.md>` também grava a matriz em Markdown) |
| Suíte | sqlite, só arquivos (PHP portátil do winget) |

## 2. Matriz de autorização (o principal)

**Runner versionado:** `erp-novo/e2e/seguranca/matriz-autorizacao.mjs`, sem dependência além do Node 24.

1. Lê **todas** as rotas do `database/api-manifest.json` e casa cada uma com a action e o middleware do `route:list --json`.
2. A permissão esperada sai da **primeira** chamada `autorizar*/temPermissao` do método do controller. As permissões de cada papel vêm do banco.
3. Para cada rota com `{param}`, resolve um id **real** da revenda A (`TABELAS`: segmento → tabela e escopo). Na escrita, **clona a linha pelo owner** e mira o clone, para que nenhuma escrita toque em objeto que o runner não criou.
4. Atores:
   - sem token;
   - token de app (`role:entregador`) em `/api/admin`;
   - admin de B, sem e com `X-Empresa-Id: 1`;
   - `emp2`;
   - Administrador, Gerente, Operador e Entregador de A;
   - consumidor 1 contra o pedido e o endereço do consumidor 2;
   - superadmin e token do painel em `/api/superadmin`.
5. Nas listas sem id, procura **dado de A** no corpo da resposta de B (`empresa_id ∈ {1,2}`, `grupo_id = 1`, razão social, nomes e CPFs de clientes de A). Para `emp2`, procura `empresa_id = 1`.
6. Sai com código 1 se houver VIOLAÇÃO ou se varrer menos que o **piso de 560 rotas** (`PIE_PISO_ROTAS`).

### 2.1 Execução completa (31/10, 06:53 UTC, versão 1 da triagem)

| Medida | Valor |
|---|---|
| Rotas no manifesto / varridas | **617 / 617** |
| Requisições | **2.996** |
| OK | 2.714 |
| INFO (sem expectativa: escopo de grupo, legado) | 111 |
| PULADA (pública ou de sessão: login, logout, refresh, webhooks PIX/PABX, health) | 15 |
| Rotas com parâmetro **sem objeto de A** no banco (só anônimo e app medidos) | 125 |
| VIOLAÇÃO bruta → **após triagem** | 22 → **0 vazamentos** (abaixo) |
| ERRO (5xx) | 3 → **R3-F-01** |
| VALIDA-ANTES (422 para quem devia receber 403/404) | 85 → **R3-F-05** |
| DIVERGE | 60 → falsos positivos: GET de relatório sem filtro obrigatório (422 depois de autorizar) e slug literal inexistente |
| NEGA-INDEVIDO | 1 (`relatorios/auditoria` para o Gerente: correto pelo P2-E-04; o runner lê só a primeira permissão do método) |

**Triagem das 22 VIOLAÇÕES brutas.** Nenhuma devolveu dado de A ao ator errado:
- `cadastros/{tipo}`, `lookups/{tipo}`, `geo/{entidade}`, `fiscal/malha/{tipo}`, `geo/cep/{cep}`: o parâmetro é um **tipo** e não um objeto, então a rota é uma lista.
  - Para B, a lista veio vazia ou `null`.
  - Para `emp2`, veio a configuração do **próprio grupo** (mesma rede), o que é legítimo.
- `comodatos/vigilancia/{cliente}`, `convenios/{id}/fechamentos`, `empresas/{id}/cidades`: devolvem **200 com lista vazia** para B e para `emp2`. A RLS filtrou, mas a rota não recusa. Não é vazamento; é inconsistência de contrato (BAIXA, R3-F-06).

**Versão 2 da triagem** (no runner, depois desta execução):
- parâmetro só literal vira lista;
- 2xx sem nenhum dado de A e com `{"data":[]|null}` vira `VAZIO-200`;
- GET autorizado que para na validação vira `OK-EXIGE-FILTRO`.

A reexecução com essa versão foi iniciada em 02/11 e **não terminou registrada**: a sessão caiu e o scratchpad (JSON e Markdown gerados) foi limpo. O anexo `F_MATRIZ_AUTORIZACAO.md` traz a matriz consolidada por ator e o procedimento para regenerar a matriz linha a linha.

### 2.2 Resultado por ator (execução de 31/10)

| Ator | Esperado | Observado |
|---|---|---|
| Sem token | 401 em toda rota autenticada | 401 em todas; nenhuma rota autenticada respondeu 2xx |
| Token de app (`role:entregador`) em `/api/admin` | 403 | 403 em todas (middleware `painel`) |
| Admin de B em objeto de A (GET) | 403/404 | 404 nos objetos; 200 **vazio** nas 3 rotas do R3-F-06; **zero** respostas com dado de A |
| Admin de B com `X-Empresa-Id: 1` | igual ao anterior | O header é **ignorado** (`ResolveTenant` só troca para empresa que o usuário acessa). Mesmo resultado, sem dado de A |
| Admin de B em listas sem id (≈250 GETs, inclusive CSV e PDF) | nenhum dado de A | nenhum dado de A (sentinelas: `empresa_id`, `grupo_id`, razão social, nomes e CPFs de A) |
| `emp2` (mesmo tenant, sem grant na empresa 1) em objeto da empresa 1 | 403/404 | 404 ou vazio; configuração do grupo visível (legítimo) |
| Papéis de A sem a permissão (BFLA) | 403 | 403, exceto os 8 casos de FormRequest do R3-F-05 (422 antes do 403, sem escrita) e `caixa/{contaId}/baixar` (busca e valida antes de autorizar) |
| Consumidor 1 no pedido do consumidor 2 (`app/v1/pedidos/{id}`, `pix`, `cancelar`, `avaliar`, `rota-entregador`, `enderecos/{id}`) | 404 | 404, exceto `pagar` (422 de validação **antes** do escopo; não paga nem vaza) |
| Token do painel ou do app em `/api/superadmin` | 401/403 | 401/403 em todas |

## 3. Achados

### R3-F-01 — Configuração da empresa dá 500 em revenda sem `empresa_configs` (GET que grava sob RLS) · ALTA · CONFIRMADO (P) · **dono: C**
- **Onde:** `EmpresaConfigController.php:100,135,154,171,190,290,323,344`. O código chama `EmpresaConfig::firstOrCreate(['empresa_id' => …])` até em GET. `EmpresaConfig` **não** usa `BelongsToTenant`, então o insert sai sem `tenant_account_id`, e a policy `tenant_isolation` (`WITH CHECK app_tenant_can_operate(tenant_account_id, empresa_id)`) recusa.
- **Reprodução:** no `pie_r3_f` (sem nenhuma linha em `empresa_configs`, como o banco-base), o Administrador de A faz `GET /api/admin/empresas/1/config`, `/certificado` e `/integracoes`.
  - **Esperado:** 200 com a configuração vazia.
  - **Observado:** **500** `new row violates row-level security policy for table "empresa_configs"`.
- **Impacto:** toda revenda nova (e todo banco sem a carga do legado) não abre Configurações, Certificado nem Integrações. O defeito é só do Postgres: a suíte sqlite não o vê.
- **Correção proposta:** GET usa `firstOrNew` (sem gravar). A escrita preenche a chave do tenant (`BelongsToTenant` no model, ou `tenant_account_id` explícito), mais um teste no portão Postgres.

### R3-F-02 — RH: CPF de todo o quadro na listagem (P2-E-11, LGPD) · MÉDIA · **CORRIGIDO**
- `ColaboradorController::linha()` devolve `***.456.789-**`. O CPF completo continua no `show`, porque o formulário de edição precisa dele.
- **Runtime (Postgres, 02/11):** `GET /api/admin/colaboradores` como Gerente devolve `"cpf":"***.704.673-**"`.

### R3-F-03 — RH: ponto de dia passado reescrevível sem rastro; folha e saúde sem trilha (P2-E-11) · MÉDIA · **CORRIGIDO**
- **Regra nova em `ColaboradorService::registrarPonto`:**
  - a janela livre é hoje mais `empresa_configs.dados.rh.ponto_dias_edicao_livre` dias. É configuração da empresa: o padrão 0 deixa livre só o dia corrente, e o teto é 31;
  - fora da janela, criar ou alterar exige `motivo`, que fica gravado em `colaborador_pontos.motivo_ajuste` (migration `2026_10_31_1f0100`, coluna nova e anulável);
  - dia futuro é recusado;
  - o upsert ficou sob `lockForUpdate` e usa `whereDate` (no sqlite, o `updateOrCreate` duplicava o dia).
- **Trilha:** `Auditavel` em `ColaboradorPonto`, `ColaboradorComissao`, `ColaboradorExame` (ASO), `ColaboradorRecesso` e `ColaboradorFamilia`.
- `removerFamiliar` usava `whereKey()->delete()` em massa, que não dispara o evento de auditoria e respondia **200 mesmo sem apagar nada** quando o familiar era de outro colaborador. Agora usa `findOrFail()->delete()`: grava a trilha e responde 404.
- **SPA:** `rh/tabs/PontoTab.tsx` ganhou o campo "Motivo (dia já fechado)" e a coluna "Motivo do ajuste".
- **Runtime (Postgres, 02/11, Gerente, colaborador 1):**
  - `POST pontos {data: 2026-10-20}` → 422 "O ponto deste dia já está fechado. Informe o motivo do ajuste.";
  - com `motivo` → 201;
  - releitura pelo owner: `2026-10-20|08:00:00|Ajuste prova R3-F|empresa 1|tenant 1`;
  - `audit_logs`: `criado|user 2|empresa 1|Ajuste prova R3-F`.
- **RLS e grant da migration:** a migration **não cria tabela**. `colaborador_pontos` já tem `relrowsecurity = t`, `relforcerowsecurity = t` e a policy `tenant_isolation` (ALL). `erp_app` tem INSERT e UPDATE na coluna nova (`has_column_privilege` = t, conferido no `pie_r3_f`).

### R3-F-04 — CC-e numerada por `max+1` sem trava e SEFAZ dentro da transação (pendência da frente B, PIE-25 e PIE-23) · ALTA · **CORRIGIDO**
- **Onde:** `FiscalService::cartaCorrecao` (código original: `:331-344`).
- **O defeito:** duas correções simultâneas liam o mesmo máximo e mandavam o **mesmo `nSeqEvento`** à SEFAZ. A segunda batia no unique `(nota_fiscal_id, sequencia)` **depois** de a SEFAZ responder: o evento ficava registrado lá e perdido aqui, com 500. A chamada de rede também segurava a transação aberta.
- **Correção:**
  1. reserva o número numa transação curta, sob `lockForUpdate` da nota, gravando a carta como "Aguardando resposta da SEFAZ";
  2. chama a SEFAZ **fora** da transação;
  3. grava o desfecho.
  Em falha de comunicação, a reserva é apagada e o número volta a ficar livre.

### R3-F-05 — Validação antes da autorização (FormRequest) · BAIXA · ABERTO · **donos: A, B, C**
- **Sintoma:** `POST clientes`, `POST pedidos`, `POST`/`PUT produtos` (Entregador), `POST empresas` (Gerente, Operador e Entregador) respondem **422 em vez de 403**.
- **Causa:** o `FormRequest` valida antes do `autorizar()` do controller, e o `authorize()` do request devolve `true`.
- **Risco e correção:** não grava nada, mas expõe as regras de validação a quem não tem a permissão. A correção é `authorize()` no FormRequest chamar o mesmo Gate.
- **Casos parecidos:**
  - `CaixaController::baixar` (dono B) faz `findOrFail` e `validate` **antes** do `autorizar`;
  - `AppPedidoController::pagar` (dono E) valida antes de resolver o escopo do cliente.

### R3-F-06 — Subrecursos que respondem 200 vazio a quem não enxerga o pai · BAIXA · ABERTO
- **Rotas:** `comodatos/vigilancia/{cliente}`, `convenios/{id}/fechamentos`, `empresas/{id}/cidades`.
- **Comportamento:** para a revenda B e para `emp2`, respondem 200 com lista vazia. A RLS protege o dado, mas o contrato diverge do resto da API, que responde 404.
- **Correção:** `findOrFail` do pai antes de listar os filhos.
- **Donos:** `comodatos` é meu (Contrato/comodato), mas não houve tempo para corrigir; `convenios` é de A ou B; `empresas` é de C.

### R3-F-07 — P2-E-14: telefonia e sorteio · BAIXA · **CORRIGIDO**
- **Telefonia:** `TelefoniaController::atender`/`rejeitar` passam a exigir `pedido.create` (antes, `pedido.view`). O Entregador descartava a chamada que tocava na central.
- **Sorteio:** com o sorteio já `sorteado`, `SorteioService::atualizar` e `::incluirNumero` (sob o mesmo lock do `sortear`) recusam a troca de prêmio e a inclusão de número, com 422.
- **Fica pendente:** a exclusão de subrecurso com `.edit` (22 métodos) depende de decisão (§6).

### R3-F-08 — P2-E-12: empresa-casa no lugar da empresa ativa · BAIXA · **CORRIGIDO, sem teste dedicado**
- `MonitoraController` passou a usar `TenantContext::requireEmpresaId()/requireGrupoId()`:
  - criar veículo, últimas posições, criar cerca, sync;
  - continua em `:161` um `grupo_id` do usuário, que é inofensivo porque o grupo é o mesmo.
- `CargaFranqueadoController::localizar` também passou a usar a empresa ativa.
- A regressão dos lotes Monitora e Franqueado está verde (§4). Falta o teste com usuário multiempresa e `X-Empresa-Id` (§7).

### R3-F-09 — Segredos versionados · MÉDIA · ABERTO · **dono: coordenador / dono do produto**
- **Varredura:** 7.467 arquivos versionados, padrões `AIza…`, `sk_live_`/`sk_test_`, `-----BEGIN … PRIVATE KEY-----`, `AKIA…`, `ghp_…`, `xox?-`, mais `.env`, `.pem`, `.p12`, `.jks`, `google-services.json`.
- **Chave do Google (`AIza…`, 35 caracteres) em 4 arquivos:**
  - `ctrl-web/app/Api/Http/Controllers/PedidoController.php`;
  - `ctrl-web/app/Http/Resources/Classes/AppConfig.php`;
  - `legado-nfweb/src/helper/Constants.js`;
  - `app-gas-em-casa/ANALISE_TECNICA_APP-GAS-EM-CASA.md`.

  O valor não é reproduzido aqui. **Recomendação:** tratar como vazada: rotacionar no Google Cloud e restringir por referer e pacote. Apagar do arquivo não a tira do histórico. O `ctrl-web` não foi tocado.
- **`ctrl-web/.env.docker`:** tem `DB_PASSWORD` e `ADMIN_SEED_PASSWORD` com valor. Parece ambiente docker de desenvolvimento, mas confirmar que não é a senha usada em nenhum servidor.
- **Sem segredo real:** os `.env*.example` do `erp-novo` (só variáveis vazias ou referências) e o `PerfisCampoTesteSeeder` (senha de teste).

### R3-F-10 — Dependências (só leitura) · informativo
| Projeto | Resultado |
|---|---|
| `erp-novo` (`composer audit`) | **1 alta:** `symfony/yaml`, CVE-2026-109413 (recursão ilimitada no lexer inline: DoS ao parsear YAML não confiável). Uso no runtime não verificado. Atualizar no próximo `composer update` |
| `erp-novo/frontend` (`npm audit --omit=dev`) | 0 vulnerabilidades |
| `mobile-shared` | 2 altas |
| `app-gas-em-casa` | há vulnerabilidades (o resumo não foi capturado; já registrado em P2-B-20) |
| `app-entregador` | a execução não devolveu saída a tempo; não medido |

## 4. Correções e prova (com e sem a correção)

O procedimento foi o do briefing: copiar o arquivo corrigido para o scratchpad, gravar `git show HEAD:./<arquivo>`, rodar o teste, restaurar e conferir com `git diff --stat`.

**Atenção a um tropeço na primeira tentativa (31/10).** Usei `git show HEAD:<caminho>` sem `./`. O comando falhou e o redirecionamento **zerou os arquivos**, então as falhas daquela rodada foram por arquivo vazio e foram **descartadas**. A prova válida é a refeita com `./`, e os arquivos foram restaurados do backup (`git diff --stat` conferido depois).

| Correção | Arquivos | Teste de aceite | Sem a correção | Com a correção |
|---|---|---|---|---|
| R3-F-02 e R3-F-03 | `ColaboradorController`, `ColaboradorService`, 5 models `Rh/*`, migration `1f0100`, `PontoTab.tsx` | `RhLgpdTrilhaTest` (6) | **5 falham:** CPF sem máscara; ponto passado 201 em vez de 422; o dia de hoje duplica (2 linhas); comissão sem `audit_logs` (erro); exclusão de familiar alheio 200 em vez de 404. O 6º (janela configurável) é guarda: passa nos dois casos | 6 OK |
| R3-F-07 | `TelefoniaController`, `CrmController`, `SorteioService` | `RegrasModulosSemDonoTest` (2) | **2 falham:** `rejeitar` 200 em vez de 403; troca de prêmio 200 em vez de 422 | 2 OK, 11 asserções |
| R3-F-04 | `FiscalService` | `CartaCorrecaoSequenciaTest` (3) | **2 falham:** `UniqueConstraintViolationException` na correção concorrente; SEFAZ chamada no nível de transação 2 em vez de 1. O 3º (falha libera o número) é guarda | 3 OK, 7 asserções |
| Regressão | — | `ColaboradorTest` (ajustado: o ponto do teste antigo passou a ser do dia), `FaseF09FiscalTest` + `F14RastreabilidadeTest` (10), `CargaFranqueadoTest` (9), `CrmTest` (8), `MonitoraApiTest` (5), `MonitoraTest` (6), `TelefoniaTest` (17) | — | todos OK |

**Prova em runtime no Postgres** (a jornada E2E deste perfil): R3-F-02 e R3-F-03 pela API 8216 como `erp_app`, relidos pelo owner (§3).

**Testes Postgres novos para o portão:** nenhum. As correções desta rodada são de regra e não de policy, e foram provadas por API real no Postgres. Fica recomendado (§7) um teste de portão para o R3-F-01.

## 5. Para outros donos

| Dono | Item | Reprodução |
|---|---|---|
| **C** | R3-F-01: 500 em `GET empresas/{id}/config`, `/certificado`, `/integracoes` sem `empresa_configs` | §3; `EmpresaConfigController.php:100` e seguintes |
| **C** | `POST empresas`: 422 antes do 403 para Gerente, Operador e Entregador (R3-F-05) | `EmpresaRequest::authorize()` |
| **A** | `POST clientes`, `POST pedidos`: 422 antes do 403 para o Entregador (R3-F-05) | `ClienteRequest`, `PedidoRequest` |
| **A/C** | `POST`/`PUT produtos`: 422 antes do 403 (R3-F-05) | `ProdutoRequest` |
| **B** | `CaixaController::baixar:174`: `findOrFail` e `validate` antes do `autorizar` | papel sem `financeiro.baixar` recebe 422 |
| **E** | `AppPedidoController::pagar:89`: valida antes do escopo do cliente | consumidor 1 em `POST app/v1/pedidos/{pedido do 2}/pagar` recebe 422 em vez de 404 |
| **A/B, C** | R3-F-06: `convenios/{id}/fechamentos`, `empresas/{id}/cidades` respondem 200 vazio | admin de B nos ids de A |
| **Coordenador** | R3-F-09: chave Google versionada (rotacionar); `ctrl-web/.env.docker` | §3 |
| **Coordenador** | `frontend/src/features/rh/ColaboradoresPage.tsx` e `app/Domain/Identidade/DocumentoFiscal.php` aparecem alterados ou novos na árvore e **não são meus**: não os toquei nem revertí | `git status` |

## 6. Decisões pendentes (com recomendação)

1. **Permissões próprias para saúde e remuneração no RH** (P2-E-11, a parte que mexe no `PermissaoCatalogo`/`RbacSeeder`, do Agente C).
   - **Proposta:** `colaborador.saude.view` (exames e ASO) e `colaborador.remuneracao.view`/`.edit` (comissões e salário-base do cargo).
   - **Migração:** atribuir as duas ao Administrador e **não** ao Gerente por padrão. A revenda concede pelo painel. Até lá, ASO e comissão continuam sob `colaborador.view`.
2. **Exclusão de subrecurso com `.edit`** (22 métodos, P2-E-14). Hoje o Gerente exclui o que o desenho diz que não exclui.
   - **Recomendação:** `.delete` nas exclusões de dinheiro e histórico (comissão, recesso, pneu, troca de óleo, cerca); `.edit` no resto.
   - Muda o papel de revendas atuais, por isso é decisão do dono.
3. **PABX multi-revenda** (P2-E-07). Exige credencial por empresa (`IntegrationAccount`) no lugar de `PABX_EMPRESA_ID` em env, e o tenant resolvido pelo token. Não implementado nesta rodada, e o webhook continua 404 sob RLS. Antes, decidir se a telefonia é produto vendável.
4. **Janela de ponto:** o padrão 0 (só o dia corrente é livre) é conservador. Confirmar com o dono e expor `rh.ponto_dias_edicao_livre` na tela de configuração da empresa (dono C).

## 7. Não testado (e por quê)

- **A reexecução da matriz com a triagem v2** começou em 02/11 e não deixou resultado: a sessão caiu e o scratchpad foi limpo. Os números de §2 são da execução de 31/10 (v1). Para regenerar: §1, com `PIE_DOC=docs/pie/rodada3/F_MATRIZ_AUTORIZACAO_LINHAS.md`.
- **125 rotas com parâmetro sem objeto de A** no banco-base (boletos, cheques, CC-e, NF de entrada, missões e outras): para elas só foram medidos o anônimo e o token de app, e BOLA e BFLA não foram medidos. Popular o banco-base com um objeto de cada fecha a lacuna.
- **Escrita autorizada** (papel com a permissão) não é exercida pelo runner, de propósito.
- **IDOR por id no corpo** (`cliente_id`, `pedido_id` no payload) não é coberto pelo runner, que só troca ids do caminho.
- **P2-E-12:** falta teste com usuário multiempresa.
- **P2-E-13** (RLS das filhas `checklist_perguntas` e `sorteio_numeros` por migration), **P2-E-10** (trilha dos atos da plataforma nos meus módulos) e **PIE-26:** não implementados por falta de tempo.
- **Tempo real** (Reverb), **cache** e **jobs entre revendas:** fora do alcance do runner HTTP.
- **Navegador:** a tela de ponto e a listagem mascarada não foram abertas no Playwright. A prova é da API e do banco.
- **`npm audit` do `app-entregador`:** sem saída a tempo.

## 8. Estado deixado

- **Arquivos meus, sem commit:**
  - `app/Http/Controllers/Api/Admin/{ColaboradorController,CrmController,TelefoniaController,MonitoraController,CargaFranqueadoController}.php`;
  - `app/Domain/{Rh/ColaboradorService,Crm/SorteioService,Fiscal/FiscalService}.php`;
  - `app/Models/Rh/{ColaboradorPonto,ColaboradorComissao,ColaboradorExame,ColaboradorRecesso,ColaboradorFamilia}.php`;
  - `database/migrations/2026_10_31_1f0100_motivo_ajuste_no_ponto.php`;
  - `frontend/src/features/rh/tabs/PontoTab.tsx`;
  - `tests/Feature/{RhLgpdTrilhaTest,RegrasModulosSemDonoTest,CartaCorrecaoSequenciaTest}.php` e `tests/Feature/ColaboradorTest.php` (ajustado);
  - `e2e/seguranca/{matriz-autorizacao.mjs,preparar-revenda-b.sql}`.
- **Rotas:** nenhuma alterada, então não foi preciso rodar `api:manifest`.
- **Banco `pie_r3_f`:** mantido, com a revenda B, os atores extras, os clones da matriz e a migration `1f0100` aplicada. Pode ser apagado (`drop database pie_r3_f`).
- **Processos:** em 17/11, nenhuma porta minha está escutando (nem 8216, nem 5216), e o Postgres :55432 também não. Eu o tinha subido em 31/10, depois que o cluster caiu para todos; ele parou com a máquina. Não há processo meu aberto.
- **Lote focado reexecutado em 17/11 (sqlite):**
  - `RhLgpdTrilhaTest`: 6 testes, 33 asserções;
  - `RegrasModulosSemDonoTest`: 2 testes, 11 asserções;
  - `CartaCorrecaoSequenciaTest`: 3 testes, 7 asserções;
  - `ColaboradorTest`: 8 testes, 37 asserções.

  Todos OK.
