# Rodada 3 — Agente C: Plataforma e acessos

**Data:** 2026-10-31 · **Partida:** `9fcfde5a` · **Perfis:** dono/administrador, superadmin, onboarding de revenda.
Estado declarado só para o que foi executado; o resto está em §6.

## 1. Ambiente

| Item | Valor |
|---|---|
| Banco | `pie_r3_c` (`create database pie_r3_c template pie_r3_base`), Postgres 15 local :55432 |
| API | `php artisan serve --port=8213`, `erp_app`, `SAAS_ENFORCE_LICENCA=true`, `SAAS_ENFORCE_TENANT_ENVELOPE=true` (script `scratchpad/c/api.ps1`, mesmas variáveis do `e2e/README.md`) |
| SPA | `npx vite --port 5213 --strictPort` |
| Suíte | sqlite, só arquivos/`--filter` (PHP portátil do winget) |
| E2E | `PIE_DB=pie_r3_c PIE_API_URL=http://127.0.0.1:8213 PIE_SPA_URL=http://localhost:5213/novo/app/ npx playwright test plataforma --project=desktop` |

A sessão caiu duas vezes por limite de uso; na última retomada a máquina estava saturada (fork do Git Bash falhando, `GET /api/health` em **20 s**), o que afetou o E2E e o vitest (§4.3).

## 2. Matriz perfil × jornada

| Perfil | Jornada | Estado | Elo que falta |
|---|---|---|---|
| Superadmin | login → 2FA obrigatório → painel | **IMPLEMENTADA, verificada na API** (feature test + runtime `curl`); E2E escrito, não verde por ambiente | 1/2 no navegador (E2E rodou com a API a 20 s/req) |
| Superadmin | lockout por conta; desativado perde token | **VERIFICADA** (feature tests, com e sem correção) | 9/10 em Postgres (login usa `platform_audit_logs` pelo runtime — conferir no portão) |
| Superadmin | listar, suspender, reativar, trocar plano | PARCIAL: código e testes da R2 (`SuperAdminSobRlsTest`); E2E escrito | navegador |
| Admin | criar usuário e papel; o novo usuário vê/não vê | IMPLEMENTADA NÃO VERIFICADA no navegador nesta rodada (spec `plataforma/acessos.spec.ts`) | E2E verde |
| Admin | desativar usuário corta o acesso | **VERIFICADA** (feature test: 200 → 401 com o mesmo token) | — |
| Admin | mensagens de validação | **VERIFICADA no runtime** (`POST /api/admin/usuarios` real, §3 R3-C-04) | — |
| Admin | sessão vencida no meio do formulário | IMPLEMENTADA, verificada em vitest (reenvio com token novo) | E2E verde |
| Admin | menu × licença | **VERIFICADA** (contrato backend + vitest) | — |
| Onboarding | instalação nova: admin do deploy consegue administrar | **VERIFICADA** (feature test) | Postgres: a fronteira SaaS (membership) da instalação nova continua dependendo do importador — ver §5 |
| Onboarding | criar revenda pelo produto; catálogo inicial | BLOQUEADA por decisão (§5) | — |
| Todos | rota inexistente | IMPLEMENTADA, verificada em vitest | E2E verde |

## 3. Achados

### R3-C-01 — Admin do deploy nasce sem papel (instalação nova não administra nada) — ALTA · CORRIGIDO
- `database/seeders/DeployAdminSeeder.php:47` marcava `support = true` como "bypass de RBAC"; desde o F2-05 o flag só habilita pedir break-glass (`User::acessoElevado`). O `RbacSeeder` roda depois e não atribuía papel a ninguém. Confirmado pelo coordenador no banco-base.
- **Correção:** `RbacSeeder::papelDoAdminDeBootstrap()` — atribui `Administrador` na empresa padrão ao admin de `ADMIN_SEED_EMAIL` **só se ele não tiver papel nenhum ali** (deploy seguinte não desfaz ajuste do dono).
- **Teste:** `PieR3PlataformaAcessosTest::test_admin_do_deploy_nasce_com_papel_administrador` (+ guarda `..._nao_sobrescreve_papeis_ajustados_do_admin`).

### R3-C-02 — P2-E-06: superadmin sem 2FA na prática, sem lockout, token sobrevive à desativação — ALTA · CORRIGIDO
- Reprodução no runtime: `POST /api/superadmin/login` com `sa@pie.local` só com senha → **200 com token** (2FA nunca cadastrável: não havia rota).
- **Correção:**
  - `SuperAdmin/AuthController`: sem 2FA cadastrado, o login responde **423** `{two_factor_setup_required, setup_token}`; o token de cadastro tem ability `superadmin:2fa-cadastro`, vale 15 min e só abre `POST superadmin/2fa/iniciar|confirmar`; a confirmação habilita, devolve 8 códigos de recuperação, apaga o token de cadastro e emite o de operação (`ability superadmin`). Código 2FA errado conta como falha da conta.
  - Middleware novo `plataforma.sessao` (`SessaoDeOperacaoDaPlataforma`) no grupo do painel: token sem a ability de operação → 423.
  - Lockout por **conta**: 5 falhas (senha ou código) em 15 min em `platform_audit_logs` → 429, inclusive com a senha certa.
  - `App\Domain\Seguranca\TokenDeContaAtiva` (registrado no `AppServiceProvider`): callback do guard do Sanctum recusa token cujo dono tem `ativo = false` — vale para admin da plataforma, usuário do painel e dos apps (os logins dos apps já exigem `ativo`).
  - `config/superadmin.php`: `SUPERADMIN_2FA_OBRIGATORIO` (padrão **true**, fail-closed).
  - SPA: `SaLoginPage` ganhou o cadastro (chave + link `otpauth://`, código, tela dos códigos de recuperação); `superadmin/api.ts` (`saIniciar2fa`, `saConfirmar2fa`), `auth.tsx` (`concluir`).
- **Testes:** `PieR3PlataformaAcessosTest` (token de cadastro não abre o painel; cadastro habilita e troca o token; recusa reconfigurar com 2FA ativo; lockout; admin desativado → 401; usuário da revenda desativado → 401). `SuperAdminTest::test_login_sem_2fa_emite_token` foi substituído por `..._so_emite_token_de_cadastro` e `..._com_obrigatoriedade_desligada` (o comportamento antigo era o defeito).
- **Fora do escopo, registrado:** papéis na plataforma (leitura × operação × migração) não foram criados — exige decidir quem são os operadores (§5). Anti-replay do TOTP do superadmin não existe (o do tenant usa `otp_consumidos` com FK para `users`).

### R3-C-03 — Usuário da revenda desativado continua operando por 24 h — ALTA · CORRIGIDO
- `UsuarioController::update` grava `ativo=false` e nada revoga tokens; `ativo` só era lido no login. Mesma correção do R3-C-02 (`TokenDeContaAtiva`). Teste: `test_usuario_da_revenda_desativado_perde_o_token_na_hora`.

### R3-C-04 — PIE-34: validação em `validation.*` no painel, na plataforma e nos apps — MÉDIA · CORRIGIDO
- Reprodução no runtime (antes): `POST /api/admin/usuarios {"name":"","email":"x","password":"123"}` →
  `{"message":"validation.required (and 3 more errors)","errors":{"name":["validation.required"],...}}`.
- Causa: `APP_LOCALE=pt_BR` no `.env`, mas não existia `lang/`; `config/app.php` caía em `en` sem env.
- **Correção:** `lang/pt_BR/{validation,auth,passwords,pagination}.php` + `lang/pt_BR.json` (inclui "(e mais N erros)"), nomes de campo comuns em `attributes`; `config/app.php` com padrão `pt_BR`.
- Depois (mesmo request, runtime): `{"message":"O campo nome é obrigatório. (e mais 3 erros)","errors":{"name":["O campo nome é obrigatório."],"email":["O campo e-mail precisa ser um e-mail válido."],"password":["A confirmação do campo senha não confere.","O campo senha precisa ter pelo menos 8 caracteres."]}}`.
- **Teste:** `test_mensagens_de_validacao_saem_em_portugues_no_painel_e_nos_apps` (painel `/api/login`, plataforma `/api/superadmin/login`, app `/api/app/v1/login`).

### R3-C-05 — P2-A-04: o menu nunca escondia nada por licença — MÉDIA · CORRIGIDO (sem mudar o que cada plano contém)
- Causa raiz nova, além dos 5 itens sem `feature`: `lib/auth.tsx::normalizarMe` **descartava** `features` do `/me`; `hasFeature` via `undefined` e liberava tudo. Mesmo um item com `feature: 'crm'` aparecia para quem não tem CRM.
- **Correção:** `normalizarMe` preserva `features`; `User::payloadAuth` manda `features: null` quando `SAAS_ENFORCE_LICENCA` está desligado (o backend libera tudo nesse modo, e o menu tem de concordar); `AppShell` ganhou `feature` em Pós-venda, Metas, Checklists (`crm`), Notas fiscais e Cupons (`nfce`) — exatamente o que o `RecursoPorRota` já exige.
- **Fonte única:** `tests/Feature/MenuLicencaContratoTest` lê o NAV do `AppShell.tsx`, associa cada item ao endpoint que a tela chama e exige `feature == RecursoPorRota::recursoDaRota(...)`; item novo no menu sem linha reprova; piso de 30 itens.
- Revendas atuais (`legacy-full`) não perdem nada: o plano tem todos os recursos.

### R3-C-06 — PIE-33: sessão vencida mostra "Unauthenticated." e perde o formulário — MÉDIA · CORRIGIDO (verificado em vitest)
- **Correção:** `lib/api.ts` — interceptor de resposta: 401 pausa a requisição, abre o diálogo `SessaoExpiradaDialog` (`lib/SessaoExpirada.tsx`, montado no `AppShell`, e-mail fixo da sessão, 2FA suportado), e **reenvia a mesma requisição** com o token novo; várias requisições vencidas esperam o mesmo diálogo; "Agora não" devolve o erro com "Sua sessão expirou. Entre novamente." (nunca "Unauthenticated."). O `/me` (revalida ao focar a janela) também pede o re-login em vez de mandar ao login e descartar a tela.
- **Testes:** `lib/pie-r3-plataforma.test.tsx` (401 → re-login → reenvio com `Bearer novo`; sem diálogo → mensagem pt-BR). O primeiro teste pegou um defeito real da própria correção (diálogo que conclui de forma síncrona perdia a promessa) — corrigido.

### R3-C-07 — P2-D-11: rota inexistente derruba o app — MÉDIA · CORRIGIDO
- `components/ui/button.tsx`: com `asChild`, o Slot recebia `[false, <Link/>]`. Agora `asChild` renderiza só o filho. Afeta também `SaMigracaoPage.tsx:422`. Comportamento sem `asChild` inalterado.
- **Teste:** vitest `Button asChild` renderiza o link.

### R3-C-08 — P2-A-08: marca "Dubena" no login de toda revenda e no superadmin — MÉDIA · CORRIGIDO
- `lib/marca.ts` (`NOME_DA_PLATAFORMA = VITE_APP_NAME || 'ERP'`) em `LoginPage`, `SaLoginPage`, `SaLayout`; subtítulo "Gás em Casa" → "Acesso da revenda". Guardião vitest varre a SPA (piso de 150 arquivos) e reprova "Dubena" fora de comentários e testes.

### R3-C-09 — R3-G-01 / R3-D-01: entregador criado no painel não entra no app, sem tela para resolver — ALTA · CORRIGIDO (repassado pelo coordenador em 02/11)
- O login do app de campo (`AppAuthController::concluirLoginDeCampo`) exige `colaboradores.user_id` ativo; o painel não tinha campo para ligar colaborador ↔ usuário, e a recusa dizia "Use o aplicativo do cliente" também ao funcionário.
- **Correção:**
  - `ColaboradorController`: aceita `user_id` (valida que o usuário é da MESMA rede e que não está ligado a outro colaborador ativo — com dois, `vinculoDe` pegaria um ao acaso); `show` devolve `user_id`/`user_label`. Não desfiz nada do que o F fez no arquivo.
  - SPA `features/rh/ColaboradoresPage.tsx`: campo "Usuário de acesso ao app" (AsyncSelect em `/lookups/usuarios`, que já existia).
  - `UsuarioController::serializar` devolve `colaborador` (nome do colaborador ativo ligado); `features/acessos/UsuariosTab.tsx` mostra o selo "Sem colaborador: não entra no app" para quem tem papel Entregador e nenhum colaborador.
  - `AppAuthController`: usuário com papel na revenda recebe "Seu usuário não tem cadastro de colaborador ativo. Peça ao administrador…" e `motivo: sem_colaborador`; quem não tem papel (consumidor) mantém a mensagem anterior.
- **Testes:** `PieR3PlataformaAcessosTest::test_entregador_criado_no_painel_entra_no_app_depois_de_ligado_ao_colaborador` (cria pelo painel → 403 `sem_colaborador` → liga na ficha → 200 com token) e `..._nao_liga_a_dois_colaboradores_nem_a_outra_rede`. **Sem a correção:** 2 failed ("Failed asserting that two strings are identical", "200 is identical to 422") — prova feita neutralizando só as linhas novas e restaurando-as no lugar, sem sobrescrever o arquivo (outros agentes editavam o `AppAuthController` ao mesmo tempo).

### R3-C-10 — Teto de quantidade por item sem porta de configuração (repassado pelo A) — MÉDIA · CORRIGIDO
- `QuantidadeDoItem::CHAVE_TETO` (`pedido_quantidade_maxima_item`) era lido pelo domínio, mas faltava em `EmpresaConfigController::DADOS_EDITAVEIS`: salvar seria 422 e não havia campo.
- **Correção:** chave na lista + validação `nullable|numeric|gt:0|max:1000000`; campo "Quantidade máxima por item" na aba Pedido (`features/empresas/config/PedidoTab.tsx`).
- **Teste:** `test_dono_configura_o_teto_de_quantidade_por_item` — com: passed; sem (controller do commit): **failed**.

### R3-C-11 — R3-F-01: config, certificado e integrações da empresa dão 500 em revenda nova — ALTA · CORRIGIDO (repassado pelo F)
- `EmpresaConfigController::show|certificadoStatus|integracoes` faziam `firstOrCreate`; o insert saía sem `tenant_account_id` e a policy `app_tenant_can_operate(tenant_account_id, empresa_id)` recusava. Reproduzido em `erp_teste` como `erp_app`: `SQLSTATE[42501] new row violates row-level security policy for table "empresa_configs"`.
- **Correção:** os três GETs usam `firstOrNew` (leitura não grava); `EmpresaConfig::booted()` preenche `tenant_account_id` do envelope ao criar (a chave segue fora do `$fillable`), o que cobre o PUT e as demais escritas.
- **Testes:** Pg `EmpresaConfigSobRlsTest` (GET config/certificado/integrações 200 → PUT → linha relida pelo owner com `tenant_account_id` do tenant) — **com: passed; sem: failed** (`500 is identical to 200`); incluído no `test:pgsql-rls`. Sqlite `test_ler_configuracao_da_empresa_nao_grava_linha` — com: passed; sem: failed.

### R3-C-12 — R3-F-05: `POST /empresas` responde 422 antes do 403 — BAIXA · CORRIGIDO
- `EmpresaRequest::authorize()` devolvia `true`; a validação rodava antes do `autorizar()` do controller e entregava o formato do recurso a quem não tem permissão.
- **Correção:** `authorize()` consulta o Gate (`empresa.create` no POST, `empresa.edit` no PUT) e `failedAuthorization` mantém o contrato `403 "Sem permissão."`. A edição continua checando a empresa alvo no controller.
- **Teste:** `test_criar_empresa_sem_permissao_e_403_antes_da_validacao` (usuário `semPapel()`) — com: passed; sem: failed.

## 4. Correções — prova

### 4.1 Backend (sqlite)
| Teste | Com a correção | Sem a correção (arquivo do commit gravado, depois restaurado) |
|---|---|---|
| `PieR3PlataformaAcessosTest` (8 + 1 de pt-BR) | 9 passed | **7 failed, 1 passed** (o que passa é a guarda "não sobrescreve") — sem `RbacSeeder`, `SuperAdmin/AuthController` e `TokenDeContaAtiva` |
| `..._mensagens_de_validacao_saem_em_portugues...` | passed | **failed** ("Failed asserting that two strings are identical") com `lang/` retirado |
| `MenuLicencaContratoTest` (37) | 37 passed | **5 failed**: `/fiscal`, `/cupons-fiscais` (exige `nfce`), `/pos-venda`, `/metas`, `/checklists` (exige `crm`) com o `AppShell.tsx` do commit |
| `SuperAdminTest` + novos | 17 + 8 passed (rodada anterior à queda) | — |

Lote do módulo (`--filter="Usuario|Papel|SuperAdmin|Licenca|Rbac|Abac|Seguranca|Auth|Empresa|PieR3Plataforma|MenuLicenca|ApiContratoDrift|EscaladaParaPlataforma|FaseF1"`): ver §4.4.

### 4.2 SPA
| Teste | Com | Sem (button, auth.tsx, LoginPage, SaLayout, SaLoginPage do commit) |
|---|---|---|
| `lib/pie-r3-plataforma.test.tsx` | 5 passed | **3 failed** (asChild, guardião da marca, `normalizarMe`) |
| PIE-33 (2 testes) | passed | sem a correção o `lib/api.ts` não exporta o mecanismo — falha na importação (não é prova isolada) |

`layouts/navigation.test.tsx` fixa a lista menu → permissão → feature; atualizei as 5 linhas que mudaram de propósito.

### 4.3 E2E (`e2e/plataforma/acessos.spec.ts`, 6 jornadas)
Escritas: marca no login + axe; 404; validação pt-BR; admin cria usuário → relido no banco (`users`, `tenant_memberships`, `tenant_company_grants`) → papel Operador → o novo usuário vê Pedidos e **não** vê Usuários/Histórico, API 403 → desativado → token 401; sessão vencida no meio do formulário → re-login → rascunho salvo; superadmin cadastra 2FA (TOTP calculado no Node) → 8 códigos → suspende e reativa a empresa 2, relido em `empresas.ativo`.
**Resultado:** a execução de 31/10 ~03h falhou em todas por ambiente — o login do painel não saiu de `/login` em 10 s com a API respondendo `GET /api/health` em 20 s (máquina saturada por 7 agentes; `fork: Resource temporarily unavailable`). **Não conta como verificação nem como falha do produto.** Reexecutar com a máquina livre; o teste do superadmin só roda uma vez por banco recém-clonado (o 2FA fica cadastrado; há `test.skip` explícito).

### 4.4 Lote do módulo e SPA completa
Números reais, sqlite, 17/11 (máquina livre):
- Lote amplo de 31/10 (`Usuario|Papel|SuperAdmin|Licenca|Rbac|Abac|Seguranca|Auth|Empresa|…`): 432 passed, 11 skipped, **1 failed** — `LicenciamentoSaasTest::test_me_inclui_features_da_empresa`, causado por esta frente (o `payloadAuth` mandava `features: null` com a licença desligada). Corrigido: a lista volta a vir sempre e o sinal novo é `licenca_ativa`.
- Lote final (`PieR3PlataformaAcessosTest|MenuLicencaContratoTest|SuperAdminTest|LicencaEnforcementTest|PapelGlobalTest|LicenciamentoSaasTest|ColaboradorTest|AppAuthHardeningTest|EmpresaTest|EmpresaCertificadoTest|EmpresaAlvoAutorizacaoTest|IntegracaoTenantTest|ApiContratoDriftTest`): **160 passed (531 assertions), 0 falhas**.
- Portão Postgres (`erp_teste`, `erp_app`): `PlataformaAcessosSobRlsTest` 2 passed + `EmpresaConfigSobRlsTest` 1 passed; sem as correções, 3 failed. Os dois estão no `composer.json test:pgsql-rls`. O portão inteiro não foi rodado por mim.
- SPA: `npx tsc --noEmit` **limpo** (exit 0); `npx vitest run` **47 arquivos, 181 testes, 0 falhas**. O `node_modules` do `frontend` tinha sumido entre as sessões; reinstalei com `npm ci` (sem mudar `package*.json`). As falhas por timeout de 31/10 eram carga: com a máquina livre, `duas-abas` e `navigation` passaram.
- `components/ui/button.tsx`: a versão que quebrava o `tsc` (prop `disabled` no `Slot`) foi corrigida; o ramo `asChild` só repassa as props.

## 5. Para outros donos e decisões pendentes

**Para outros donos**
- **A:** `ClienteFormPage.tsx:55` (`setTelefone`) já está em uso no estado atual; o `tsc` passou limpo (02/11).
- **D/E (apps):** com `TokenDeContaAtiva`, token de usuário com `ativo=false` passa a receber 401 em qualquer rota (antes valia até expirar). Os logins dos apps já exigem `ativo`; o efeito é só sobre token antigo de conta desativada — desejado, mas o app precisa tratar 401 como sessão encerrada.
- **Todos com spec de superadmin (G):** `POST /api/superadmin/login` sem 2FA cadastrado agora devolve **423 + `setup_token`**, não token. Use o fluxo `2fa/iniciar|confirmar` (exemplo em `e2e/plataforma/acessos.spec.ts`, função `totp`) ou `SUPERADMIN_2FA_OBRIGATORIO=false` só no ambiente de teste.
- **Coordenador:** rodei `php artisan api:manifest` (619 endpoints, +2 rotas `superadmin/2fa/*`). Arquivos compartilhados tocados: `routes/api.php` (bloco superadmin), `bootstrap/app.php` (alias `plataforma.sessao`), `app/Providers/AppServiceProvider.php` (1 linha), `config/app.php` (locale padrão), `app/Models/User.php` (`payloadAuth.features`), `components/ui/button.tsx` (só o ramo `asChild`).
- **Portão Postgres:** teste novo `PlataformaAcessosSobRlsTest` (lockout do superadmin lendo `platform_audit_logs` pela conexão do login; token de admin desativado → 401), **já incluído** no `composer.json test:pgsql-rls`. Executado em `erp_teste` como `erp_app`: **2 passed**; sem a correção (lockout e `TokenDeContaAtiva` neutralizados): **2 failed** (`200 is identical to 429`, `200 is identical to 401`). O caso do usuário de revenda desativado sob RLS não ganhou teste Pg (coberto em sqlite).
- **D (app do entregador):** a recusa do login agora traz `motivo: sem_colaborador` | `nao_colaborador`; o app pode mostrar a mensagem do servidor como está.
- **Onboarding (pendente, do A):** condições de pagamento padrão para revenda nova (o `pie_r3_base` tem 0 e o Novo pedido não fecha) — faz parte do P2-A-07 (catálogo inicial), item 3 abaixo; não implementado.

**Decisões pendentes (com recomendação)**
1. **Recursos de app sem porta** (`app_consumidor`, `app_entregador`, `marketplace`, `tempo_real`): avaliado, **não implementado**. `RecursoPorRota` só olha `api/admin/`; ligar `app/v1/*` sem decisão muda o comportamento dos apps em campo, e `LicencaEnforcementTest.php:414` fixa hoje que `api/app/v1/pedidos` fica fora. Recomendação: gate no **login** dos apps (`AppAuthController`, áreas D/E) por `app_entregador`/`app_consumidor`, `marketplace` em `MarketplaceService::empresasNoPonto`, `tempo_real` no canal de broadcast — todas as revendas atuais estão em `legacy-full`, então nada cai; exige decidir o que o consumidor vê quando a revenda não tem o app (mensagem e loja).
2. **P2-A-01 (criar revenda pelo produto):** não implementado. Depende de decisões abertas: trial/assinatura inicial (P2-A-05), assinatura por tenant × por empresa (P2-A-06), convite do dono por e-mail (provedor) e quem pode criar (papéis da plataforma). Recomendação: `TenantProvisioningService` como `pgsql_owner`, uma transação (tenant + empresa + grupo + papéis do `RbacSeeder` + membership OWNER do dono + plano), exposto em `POST /superadmin/revendas`.
3. **P2-A-07 (catálogo inicial / checklist):** não implementado. Preço e grade de produtos são decisão comercial por revenda; recomendo catálogo com preço **zero** e o checklist "pronto para vender" lendo o que já existe (fiscal, preço > 0, saldo, entregador, área, situação `EM_ROTA`).
4. **Papéis na plataforma** (leitura × operação × migração) para `platform_admins`.
5. **Nome comercial do produto** para o login (`VITE_APP_NAME`; padrão neutro "ERP").
6. **Instalação nova com envelope SaaS ligado:** o admin do deploy agora tem papel, mas ainda precisa de `tenant_memberships`/`tenant_company_grants`, que só o importador (`saas:tenant:importar-*`) cria. Resolvido junto com o item 2.

## 6. Não testado e por quê
- E2E no navegador (§4.3): ambiente saturado; specs prontas.
- Trocar plano e conferir o efeito na revenda (402 no módulo) pelo navegador: não executado; coberto só por `SuperAdminTest`/`LicencaEnforcementTest` existentes.
- Casos negativos §5.2 de formulário (duplo clique, duas abas, 5 mil caracteres) nas telas de Acessos: não executados.
- axe nas telas de Acessos e do superadmin: só previsto no spec (login e cadastro do 2FA).
- Em Postgres: só o lockout e o token do superadmin (`PlataformaAcessosSobRlsTest`). Ligação colaborador ↔ usuário e login do app sob RLS ficaram em sqlite.
- A ligação colaborador ↔ usuário não foi exercida no navegador (só API + tsc/vitest).

## 7. Estado deixado
- Banco `pie_r3_c` mantido (drop manual: `drop database pie_r3_c`). API 8213 e Vite 5213 não estão mais no ar (caíram com as sessões; nada meu escutando nessas portas).
- **Postgres local (55432):** estava parado em 17/11; subi para rodar o portão (houve recuperação de crash do encerramento anterior, sem erro). Tentei pará-lo ao final e o comando foi **negado pelo controle de permissões** (o servidor é compartilhado com os outros agentes): **ficou no ar**. Para parar: `pg_ctl -D %LOCALAPPDATA%\dubena-tools\pgdata stop -m fast`.
- `erp_teste` recebeu `migrate --database=pgsql_owner` (migrations pendentes de outros agentes) e linhas de teste do `PlataformaAcessosSobRlsTest` (marcadas por uuid, como os demais testes Pg).
- Arquivos novos: `app/Domain/Seguranca/TokenDeContaAtiva.php`, `app/Http/Middleware/SessaoDeOperacaoDaPlataforma.php`, `config/superadmin.php`, `lang/pt_BR/*`, `lang/pt_BR.json`, `frontend/src/lib/{marca.ts,SessaoExpirada.tsx,pie-r3-plataforma.test.tsx}`, `tests/Feature/{PieR3PlataformaAcessosTest,MenuLicencaContratoTest,PlataformaAcessosSobRlsTest,EmpresaConfigSobRlsTest}.php`, `e2e/plataforma/acessos.spec.ts`.
- Alterados: `SuperAdmin/AuthController.php`, `Admin/{UsuarioController,ColaboradorController,EmpresaConfigController}.php`, `Requests/EmpresaRequest.php`, `Models/EmpresaConfig.php`, `Mobile/AppAuthController.php` (só a mensagem de recusa), `DeployAdminSeeder.php`, `RbacSeeder.php`, `User.php` (`licenca_ativa` no payload), `AppServiceProvider.php`, `bootstrap/app.php`, `config/app.php`, `routes/api.php`, `database/api-manifest.json`, `composer.json` (portão Pg), `tests/Feature/SuperAdminTest.php`, `frontend/src/{lib/api.ts,lib/auth.tsx,layouts/AppShell.tsx,layouts/navigation.test.tsx,components/ui/button.tsx,features/auth/LoginPage.tsx,features/superadmin/{SaLoginPage,SaLayout,api,auth},features/acessos/{UsuariosTab,api},features/rh/ColaboradoresPage,features/empresas/config/PedidoTab}`.
- `payloadAuth`: a lista `features` continua sempre presente (o `LicenciamentoSaasTest` depende dela); o sinal novo `licenca_ativa` é que diz à SPA se deve esconder por licença.
