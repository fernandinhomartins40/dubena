# Protocolo de varredura, correção e validação ponta a ponta

**Versão:** 1.0 · **Data:** 2026-10-31 · **Escopo:** `erp-novo` (API + SPA), `app-gas-em-casa`, `app-entregador`, `mobile-shared`
**Para:** agentes (e humanos) que vão revisar, corrigir e validar esta aplicação.
**Complementa:** `PROTOCOLO_UNIVERSAL_AUDITORIA_INTEGRAL_PIE_V2 (1).md` (o quê e por quê) e `CLAUDE.md` (armadilhas). Este documento diz **como**, nesta base, com os comandos que existem aqui.

> **A pergunta que guia tudo:** *esta funcionalidade faz, de verdade, o que a tela promete — para cada perfil, gravando e relendo o dado, com a permissão certa?*
> Código presente não é prova. Teste verde em sqlite não é prova de Postgres. Toast de "salvo" não é prova de gravação.

---

## 0. Por que este protocolo existe

As duas rodadas da auditoria PIE (`docs/pie/`) mostraram o padrão do risco: funcionalidades feitas **isoladas**, com cada camada correta sozinha e a costura entre elas quebrada. Exemplos reais desta base:

| Sintoma | Caso real | Por que nenhum teste pegou |
|---|---|---|
| A tela funciona, o servidor recusa | O app do consumidor recebia **422 em todo pedido** desde junho (P2-B-01) | Os testes do backend mandavam um payload que o app nunca mandou |
| O backend funciona, a tela não existe | NF-e em campo e pagamento por cartão: rota pronta, nenhuma tela (P2-C-15, P2-B-09) | Nada cruzava rota com consumidor |
| Funciona em sqlite, quebra no Postgres | Superadmin via 0 empresas; usuário novo levava 403 em tudo; login de suporte dava 500 (P2-E-01, D-02, D-01) | A suíte roda sem RLS |
| "Salvo" que não salva | Campos do perfil do app descartados (R2-15); `$fillable` esquecido (CLAUDE.md) | O teste conferia o 200, não o dado relido |
| Permissão por porta lateral | Mala direta exportava a base com `cliente.view`; o papel de Administrador vazava para todos (P2-E-03, D-03) | Cada porta testada sozinha |
| Regra inferida do texto | "Saiu para entrega" contém "entreg" e ia para o Histórico (P2-C-04) | Ninguém executou o fluxo com o nome real da situação |

O protocolo ataca **a costura**: inventário cruzado automático, execução real por perfil, prova de persistência e correção com teste que falha sem ela.

---

## 1. Princípios (inegociáveis)

1. **Pronto = provado em runtime.** Uma funcionalidade só é VERIFICADA quando foi executada pela interface real (ou pelo cliente HTTP real do app), o efeito foi **relido do banco** e o caso negativo de permissão foi tentado.
2. **Reproduzir antes de corrigir.** Todo defeito vira primeiro um teste (ou script Playwright) que falha. A correção só está feita quando esse teste passa **e** falha de novo com a correção retirada (`git stash push <arquivo>`).
3. **Postgres com RLS é o juiz.** Tudo o que toca tenant, permissão, policy ou grant é provado como `erp_app` no Postgres local. Sqlite serve para lógica pura.
4. **Sem maquiagem.** Proibido criar stub, mock na aplicação, `try/catch` que engole erro, ou relaxar teste para ficar verde. Mocks só dentro de teste, e identificados.
5. **Fail-closed em dinheiro e identidade** (regra do repositório). Na dúvida entre recusar e liberar, recusa e registra.
6. **Decisão de negócio não é do agente.** Regra comercial ambígua, retenção de dado, texto jurídico e custo novo vão para a **fila de decisões** (§9), com uma proposta. O agente não inventa a regra.
7. **"Não dá" e "não existe" precisam de prova** (CLAUDE.md, "Investigação"). `grep` confirma presença, nunca ausência; antes de declarar bloqueio, leia a tarefa item a item e confira a ferramenta no repositório.
8. **Guardião só vale se detecta.** Todo teste que varre arquivos tem piso de contagem (`assertGreaterThan`) e foi visto falhar com a regressão plantada.

---

## 2. Definição de "funcionalidade completa" (a fatia vertical)

Cada jornada é auditada como uma fatia de **12 elos**. Elo não comprovado = funcionalidade PARCIAL.

| # | Elo | Como se prova nesta base |
|---|---|---|
| 1 | **Entrada visível** para o perfil certo (menu, rota, botão) e **ausente** para o perfil errado | Playwright por perfil; `frontend/src/routes.tsx` × menu × permissão |
| 2 | **Estados da tela**: carregando, vazio, erro, sucesso, sessão vencida, sem rede | Playwright com `page.route` abortando e devolvendo 401/500 |
| 3 | **Validação no cliente** coerente com a do servidor (mesmos campos, mesmas regras, mensagens em pt-BR) | Comparar o form com o `validate()`/FormRequest; enviar o inválido |
| 4 | **Chamada HTTP** existe no backend com o mesmo método e caminho | `node scripts/pie-costura.mjs` (§4.1) |
| 5 | **Payload** que a tela manda é o que o backend espera (sem campo descartado em silêncio) | Capturar o request no Playwright × `validate()` × `$fillable` |
| 6 | **Cadeia de middleware**: `auth:sanctum` → `tenant` → `tenant.saas` → `licenca.rota` → `throttle` | `php artisan route:list --path=...` |
| 7 | **Autorização**: `$this->autorizar($request, 'modulo.acao')` na primeira linha, com a permissão **da ação** (escrita ≠ leitura) | Leitura + teste negativo por papel |
| 8 | **Regra de negócio** no `Domain/<Contexto>/*Service`, com transação e lock onde há dinheiro, estoque ou fiscal | Teste de domínio + leitura |
| 9 | **Persistência**: gravou o que devia, só o que devia, com `empresa_id`/`tenant_account_id` | Reler pelo owner depois do request; recarregar a tela |
| 10 | **Isolamento**: outro tenant, outra empresa e outro papel não leem nem escrevem | Teste no Postgres como `erp_app` (§5) |
| 11 | **Efeitos colaterais**: estoque, título, nota, job, push, evento de tempo real, auditoria | Conferir a tabela de destino e o `security_events`/`audit_logs` |
| 12 | **Prova reexecutável**: teste automatizado que falha sem a funcionalidade | Arquivo de teste no repositório + saída da execução |

---

## 3. Ambiente de runtime padronizado

Cada agente usa o **mesmo** ambiente. A receita foi validada na Rodada 2 (Frente D).

### 3.1 Backend e banco (Windows, sem Docker)
```powershell
# PHP portátil (fora do PATH)
$env:Path = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;$env:Path"
# Postgres 15 portátil: porta 55432, auth trust (ver memória "ambiente-de-teste-local")
# Banco PRÓPRIO por agente: createdb pie_<agente>; nunca use erp_teste para dado manual
$env:DB_CONNECTION='pgsql'; $env:DB_PORT='55432'; $env:DB_DATABASE='pie_<agente>'
$env:DB_USERNAME='erp_app'; $env:DB_PASSWORD='runtime_test_password_2026'
$env:DB_OWNER_USERNAME='postgres'; $env:DB_OWNER_PASSWORD='postgres'
php artisan migrate --database=pgsql_owner --force
php artisan db:seed --class=<seeders de demonstração>   # DemoGuarapuavaSeeder, RbacSeeder, PlanosSeeder…
php artisan saas:tenant:preencher-chave-operacional --apply
php artisan saas:tenant:proteger-configuracao-grupo --apply
php artisan serve --port=<porta livre>
```
- O runtime **tem** de ser `erp_app`; como superusuário a RLS não roda e o teste mente.
- Ligue os enforcements como na homologação: `SAAS_ENFORCE_LICENCA=true` e envelope de tenant.

### 3.2 SPA
`cd erp-novo/frontend && npx vite --port <porta>`. Confira o proxy em `vite.config.ts` (P2-D-18: `npm run dev` sem proxy não fala com a API).

### 3.3 Navegador
Playwright instalado **numa pasta de trabalho fora do repositório** (ou, quando a suíte E2E for versionada, como devDependency do `frontend`), com a versão casada com o Chromium de `%LOCALAPPDATA%\ms-playwright`.

### 3.4 Apps
- **Permitido:** `npx tsc --noEmit`, `npx vitest run` (os dois apps têm runner), testes de contrato no backend com o **payload literal** do app.
- **Proibido nesta máquina:** emulador, `adb`, Gradle, build de APK (derrubam o login; ver memória "encerrar-emulador-e-builds").
- **Quando houver ambiente para aparelho** (CI com EAS ou máquina dedicada): Maestro com fluxos YAML por perfil. Esse é o caminho recomendado pela documentação da Expo para E2E de app nativo. Até lá, a jornada do app é PARCIAL por definição.

### 3.5 Ao terminar
Encerrar `php serve`, Vite, navegadores e o Postgres **se foi o agente quem subiu**. Apagar o banco próprio ou registrar que ficou. `git status` limpo de arquivos temporários.

---

## 4. Fase 1 — Inventário cruzado automático (onde a costura rasga)

Roda **antes** de qualquer leitura manual. Cada saída vira uma lista de candidatos a confirmar.

### 4.1 Rota × consumidor
```bash
php artisan api:manifest          # dentro de erp-novo, se mexeu em rota
node scripts/pie-costura.mjs      # na raiz
```
- **Chamada sem rota** → 404 garantido. Corrigir sempre.
- **Método divergente** → corrigir sempre.
- **Rota sem consumidor visível** → CANDIDATO. Para cada uma, decida: (a) a tela existe e chama por variável (falso positivo: anote); (b) a funcionalidade ficou pela metade (falta a tela ou o fluxo do app: é achado); (c) a rota é legado ou integração (anote quem consome).
- Medição em 31/10: 617 rotas, 451 chamadas, **0 chamadas sem rota, 89 rotas sem consumidor visível**. Entre elas estão `entregador/fiscal/emitir` (NF-e em campo sem tela), `pedidos/{id}/pagar` (cartão sem fluxo no app) e as operações de caixa `contas`, `transferencias`, `estornar`, `lancar-fechado` e `baixar-titulos`.

### 4.2 Tela × servidor (o "salvo que não salva")
Para cada formulário da SPA e dos apps:
1. Liste os campos que o form envia (Playwright capturando o `request.postDataJSON()`, ou leitura do `mutationFn`).
2. Liste o que o backend valida (`$request->validate`/FormRequest) e o que o model aceita (`$fillable`).
3. **Campo enviado e não validado** → descartado em silêncio. **Campo validado e ausente do `$fillable`** → descartado em silêncio. **Campo obrigatório no servidor e ausente no form** → 422 garantido (P2-B-01, P2-D-10).

### 4.3 Menu × permissão × licença × backend
Para cada item de menu da SPA: qual permissão o esconde, qual recurso de licença o protege (`RecursoPorRota::MAPA`) e qual `autorizar()` o controller usa. Divergência é achado: o menu mostra o que o backend nega (P2-A-04) ou o backend libera o que o menu esconde (porta lateral).

### 4.4 Varreduras estáticas (rodar e triar; nada aqui é veredito sozinho)
| Pergunta | Ferramenta |
|---|---|
| Controller sem `autorizar` na primeira linha; ação de escrita checando permissão de leitura | grep + leitura; guardião `test_controllers_nao_chamam_tem_permissao_direto` |
| Model de empresa sem `BelongsToTenant`; tabela nova sem policy RLS | `RlsCoberturaTest` (Postgres) + grep nas migrations |
| `DB::table(...)->whereKey(` | `SemWhereKeyNoQueryBuilderTest` |
| `whereBetween`/`<=` com data pura em coluna datetime | `SemanticaTemporalTest` + grep `where\('.*_em', '<=` |
| Erro engolido (`catch` sem log nem relançamento), `TODO`, `FIXME`, "não implementado", `return []` provisório | grep |
| Botão sem ação, `onClick` vazio, toast de sucesso sem `mutate` | grep em `features/` |
| Export, arquivo ou tipo morto no TS | **Knip** (`npx knip`), na SPA e nos apps |
| Tipos e chamadas inválidas no PHP | **Larastan/PHPStan** em nível progressivo; o baseline congela o legado |
| Injeção, XSS, SSRF, autorização | **Semgrep** (`semgrep --config=auto`) + regras próprias para os padrões desta base |
| Segredo no repositório | **gitleaks** |
| Dependência vulnerável | `npm audit --omit=dev`, `composer audit` |
| Contrato da API | **Schemathesis** contra `docs/01-vigente/openapi-api-admin.yaml`, com token real e modo stateful, para achar 500 e respostas fora do schema. É apoio: o 500 que ele acha precisa de triagem manual |

---

## 5. Fase 2 — Matriz perfil × jornada, executada

### 5.1 Perfis (uma conta de teste por perfil, em **duas** revendas: A e B)
| Perfil | Onde trabalha | Jornadas críticas (mínimo) |
|---|---|---|
| **Dono/Administrador** | SPA | criar usuário e papel; configurar produto, preço, condição de pagamento, taxa e setor; ver painel do dia; exportar clientes |
| **Gerente** | SPA | alçada de desconto; relatórios; fechar caixa; **não** administrar acesso nem ler a trilha |
| **Atendente/Operador** | SPA | receber ligação → achar cliente (nome sem acento, telefone) → novo pedido → despachar; cadastrar cliente novo no meio do pedido |
| **Caixa** | SPA | abrir caixa → receber título (total, parcial, com vírgula e milhar) → malote → fechar |
| **Entregador** | app | entrar → iniciar jornada → ver entregas → iniciar rota → entregar (forma de pagamento, troco, vasilhame, comprovação) → ocorrência → sem rede → acerto |
| **Consumidor** | app | escolher revenda → SMS → cadastro → endereço → pedido → pagar (PIX/dinheiro) → acompanhar → repetir pedido → **excluir conta** |
| **Superadmin** | SPA superadmin | criar revenda; plano; suspender e reativar; ver saúde |
| **Anônimo/atacante** | API | chamar tudo sem token, com token de app no painel, com token de outra revenda |

### 5.2 Para cada jornada, o roteiro de execução
1. **Caminho feliz** pela interface, capturando: requests (`page.on('request')`), respostas ≥ 400, erros de console, tempo até a tela ficar utilizável e capturas por passo.
2. **Prova de persistência:** recarregar a página **e** reler o registro pelo owner no banco (`DB::connection('pgsql_owner')`). Conferir colunas de tenant preenchidas.
3. **Efeitos colaterais:** estoque, título, nota, job, push, auditoria — cada um relido.
4. **Casos negativos obrigatórios:** campo vazio; texto em campo numérico; "5%", "R$ 1.000", "1.000,50", "7.5"; quantidade 0, 1,5 e 1.000.000; CPF inválido; texto de 5 mil caracteres; **duplo clique** no salvar; Esc, back e refresh no meio; **duas abas** alterando o mesmo registro; sessão vencida no meio do formulário; rede caindo (`page.route('**', r => r.abort())`); viewport 390×844.
5. **Matriz de autorização** (OWASP API Top 10: BOLA/BFLA):
   - o mesmo request com o token de **cada outro perfil** → só os permitidos passam;
   - trocar o id do objeto por um da **revenda B** → 404/403, nunca o dado;
   - trocar o id por um de **outra empresa do mesmo tenant** sem grant → negado;
   - token de app em `/api/admin` → 403; sem token → 401;
   - módulo não contratado → 402 em **todos** os caminhos, inclusive os aliases;
   - campos que o perfil não pode alterar (preço, custo, `support`, `empresa_id`) enviados no payload → ignorados ou recusados.
6. **Acessibilidade:** axe (`@axe-core/playwright`, tags `wcag2a`, `wcag2aa`, `wcag21aa`, `wcag22aa`) na tela **e** nos estados abertos (modal, menu, erro); navegação só por teclado na jornada inteira. O axe pega parte dos problemas; foco, ordem de leitura e clareza da mensagem exigem passada manual.

### 5.3 Onde guardar
- Os scripts de jornada vão para **`erp-novo/e2e/<perfil>/<jornada>.spec.ts`**, para serem reexecutados. Hoje a SPA não tem suíte E2E versionada; criá-la é parte do trabalho.
- Cada defeito encontrado vira **também** um teste de feature no backend (ou unitário na SPA ou no app), para a regressão ser pega sem navegador.

---

## 6. Fase 3 — Segurança e LGPD (checklist desta base)

Referências: OWASP ASVS 5.0, OWASP API Security Top 10 (2023), WSTG e ANPD (Resolução CD/ANPD nº 2/2022 e guia de segurança para agentes de pequeno porte).

| Área | O que provar | Já houve defeito? |
|---|---|---|
| Isolamento | Revenda A não lê nem escreve B em nenhuma rota, export, arquivo, job, canal de tempo real ou cache | Rodada 1/2: RLS ok; rotas de plataforma cegas (P2-E-01) |
| Autorização | Matriz §5.2; escrita com permissão de escrita; nenhuma porta lateral (relatório, export, alias) | P2-E-03, E-04, E-05, D-03 |
| Receita | Licença em todo caminho do módulo; plano suspenso bloqueia; recursos de app exigidos por rota | P2-E-02; recursos de app pendentes |
| Identidade | Login, lockout, 2FA, revogação de token ao desativar, renovação de sessão nos apps | P2-E-06; RC7 |
| Dados de entrada | Mass assignment (`$fillable`), `orderBy`/`whereRaw` com entrada do usuário, upload (tipo, tamanho, caminho) | — |
| Segredos | Nada de chave em log, `failed_jobs`, resposta de erro ou bundle de app | P2-E-08 (corrigido), P2-B-11 |
| Webhooks | PIX e PABX: autenticidade, idempotência, fora de ordem, sem tenant resolvido | P2-E-07 |
| **LGPD — inventário** | Cada dado pessoal: onde nasce, finalidade, quem lê (papel), onde sai (export, log, backup, terceiros) | — |
| **LGPD — titular** | Acesso e **eliminação real** (anonimizar, não "desativar"); prazo; o que se retém por obrigação legal, documentado | P2-B-05 |
| **LGPD — minimização** | Campo coletado sem uso é removido (R2-15); RH: CPF, salário e ASO só para quem precisa | P2-E-11 |
| **Lojas** | Google Play exige exclusão de conta **no app e por link web**; desativar não conta. Data Safety coerente com o que o app coleta. Localização em segundo plano declarada e justificada | P2-B-05, B-07, C-07 |

Teste negativo de segurança só com contas de teste, nas revendas A e B do banco local. Nunca em dado real nem na homologação.

---

## 7. Fase 4 — Produto e simplicidade para o usuário leigo

### 7.1 Método
1. **Análise de tarefa:** para cada jornada do §5.1, escreva a sequência real (cliques, campos, telas, esperas) **medida no Playwright**, não estimada.
2. **Passada cognitiva** (cognitive walkthrough), em cada passo, com quatro perguntas: o usuário sabe o que fazer aqui? Vê o controle certo? Entende que ele faz o que quer? Percebe que deu certo?
3. **Heurísticas de Nielsen** na tela inteira: status visível, linguagem do usuário (pt-BR, sem `validation.*`), controle e saída (Esc não descarta sem perguntar), consistência, prevenção de erro, reconhecimento em vez de memória, flexibilidade (atalhos para o atendente), estética, recuperação de erro, ajuda.
4. **Regra dos 3 passos:** a tarefa principal de cada perfil cabe em até 3 passos? Se não, aplicar **ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR → CRIAR**, nessa ordem, sem sacrificar controle de dinheiro nem segurança.

### 7.2 Candidatos já identificados nesta base (validar e medir)
| Tarefa | Hoje | Proposta |
|---|---|---|
| Pedido por telefone | Buscar cliente (sem telefone na listagem, sensível a acento), sair da tela para cadastrar cliente novo, montar itens | **JUNTAR** busca única (nome sem acento, telefone, CPF) + cadastro rápido dentro do Novo pedido; **ANTECIPAR** cliente da bina e "repetir último pedido" → 2 passos |
| Pedido no app | Login com nova verificação por SMS a cada 24 h; endereço não confirmado no checkout | **ELIMINAR** a re-verificação (renovar token); "Entregar em: …" no carrinho; "Pedir de novo" na Home |
| Entrega em campo | Concluir sem registrar pagamento; ações irrelevantes em pedido encerrado | **JUNTAR** concluir + pagamento + vasilhame numa tela; esconder o que não se aplica |
| Onboarding de revenda | ~35 cadastros de apoio sem guia; sem catálogo; sem a situação "Em Rota" | **AUTOMATIZAR** catálogo inicial de GLP; checklist "pronto para vender" que leva a cada pendência |
| Caixa | Conta nova "aberta" sem como fechar; recebimento só integral | Um estado claro por conta; recebimento parcial |

Cada proposta registra: impacto (quem ganha e quanto tempo), esforço, risco e se é **fundamentada** (medida no runtime) ou **hipótese a validar** com revendas.

---

## 8. Fase 5 — Ciclo de correção (o mesmo para todo defeito)

```
reproduzir no runtime ──► teste que falha (feature/unit/e2e)
        │                          │
        ▼                          ▼
  causa raiz (ler o fluxo)   corrigir no lugar certo (Service, não controller; servidor, não só tela)
                                   │
                                   ▼
        teste passa ─► git stash do arquivo corrigido ─► teste FALHA ─► git stash pop
                                   │
                                   ▼
  rodar de novo a jornada no navegador ─► rodar o lote de regressão do módulo
                                   │
                                   ▼
              registrar: achado, arquivo:linha, teste, saída real
```

Regras:
- **Corrigir a classe, não o caso:** se o defeito tem padrão (texto em vez de marca, `fillable` esquecido, rota de plataforma sob RLS), procure as outras ocorrências e escreva o guardião.
- **Servidor primeiro:** validação só na tela não é validação. Toda regra de dinheiro, permissão e tenant vive no backend; a tela só melhora a experiência.
- **Teste que toca RLS:** sem `RefreshDatabase`; cenário gravado pelo owner (`tests/Feature/Concerns/RevendaSobRls.php`); entra no `composer test:pgsql-rls`.
- **Migration:** expand antes de contract; nada destrutivo no mesmo deploy da feature.
- **Rota nova:** `php artisan api:manifest` (o `ApiContratoDriftTest` exige).
- **Commit por fase, direto na `main`, sem push** até o dono autorizar: push na `main` dispara deploy.
- **Não alterar `ctrl-web/`.**

---

## 9. Fila de decisões (o que o agente não decide)

Quando a correção depende de regra de negócio, retenção de dado, texto jurídico, custo ou mudança de comportamento para usuários atuais:
1. pare **só aquele item** e continue os outros;
2. registre em `docs/pie/DECISOES_PENDENTES.md`: contexto, evidência, opções, recomendação e impacto de cada opção;
3. implemente o que for **independente** da decisão (por exemplo, o campo configurável, desligado por padrão).

Decisões abertas hoje estão em `docs/pie/rodada2/CONSOLIDADO_RODADA2.md` §5 (exclusão de conta, formas de pagamento no app, pagamento na entrega, modelo dos apps, empacotamento, faturamento e termos).

---

## 10. Orquestração de agentes

| Papel | Faz | Não faz |
|---|---|---|
| **Inventariante** | Fase 1 inteira; entrega a lista de candidatos por módulo | corrigir |
| **Executor de perfil** (um por perfil do §5.1) | Fase 2 para o seu perfil, nas revendas A e B; escreve os specs E2E | corrigir código de produto |
| **Segurança/LGPD** | Fase 3; matriz de autorização entre perfis e revendas | — |
| **Produto** | Fase 4; mede passos no runtime; propõe ELIMINAR→…→CRIAR | implementar funcionalidade comercial nova sem decisão |
| **Corretor** (um por módulo) | Fase 5 nos achados do seu módulo | mexer em módulo de outro corretor ao mesmo tempo |
| **Verificador independente** | Reexecuta do zero os testes e jornadas que o corretor declarou resolvidos; tenta reprovar | aceitar evidência sem rodar |

- **Independência:** os revisores recebem o mesmo briefing e **não** veem as conclusões uns dos outros; o consolidador contesta contradições com nova verificação direcionada, nunca por votação.
- **Escrita concorrente:** um corretor por módulo, ou worktrees separados; banco Postgres próprio por agente.
- **Handoff:** cada agente entrega ID, arquivo:linha, reprodução, esperado × observado, evidência (comando e saída real, ou captura), teste de aceite e estado. Não entrega "parece ok".
- **Anti-padrões já vistos nesta base** (CLAUDE.md): declarar bloqueio sem ler a tarefa item a item; concluir ausência por `grep`; aceitar teste verde que passa pela verificação errada; teste preso ao formato da string; acreditar no `golive:check` local com superusuário.

---

## 11. Entregáveis e critério de aceite

Em `docs/pie/<rodada>/`:
1. `INVENTARIO_CRUZADO.md`: saídas da Fase 1 com a triagem de cada candidato;
2. `MATRIZ_PERFIL_JORNADA.md`: cada jornada × perfil × estado (VERIFICADA / PARCIAL / FALHA REPRODUZIDA / IMPLEMENTADA NÃO VERIFICADA / BLOQUEADA), com o elo do §2 que falta;
3. `MATRIZ_AUTORIZACAO.md`: rota × perfil × revenda, com o resultado esperado e o observado;
4. `ACHADOS_E_CORRECOES.md`: por causa raiz, com teste de aceite e a prova "falha sem a correção";
5. `PRODUTO_SIMPLIFICACAO.md`: tarefa × passos medidos hoje × proposta;
6. `DECISOES_PENDENTES.md`;
7. `VEREDITO.md`: APROVADO / REPROVADO / NÃO COMPROVADO, com escopo, commit, ambiente e o que **não** foi testado.

Versionados no código: specs E2E (`erp-novo/e2e/`), testes de feature e unitários novos, e os guardiões.

**A rodada só se declara concluída quando:**
- [ ] `node scripts/pie-costura.mjs` sai com 0 chamadas sem rota e todo candidato está triado;
- [ ] cada jornada do §5.1 tem estado e evidência para **cada** perfil;
- [ ] a matriz de autorização foi executada nas duas revendas;
- [ ] todo achado corrigido tem teste que falha sem a correção;
- [ ] estão verdes: suíte backend (`php artisan test`), portão Postgres (`composer test:pgsql-rls`), SPA (`npx vitest run` e `npx tsc --noEmit`) e os dois apps (`tsc` e `vitest`);
- [ ] pendências e decisões estão explícitas, com o motivo;
- [ ] nenhum segredo nem dado pessoal real aparece nos artefatos.

**Proibido declarar:** "tudo funciona", "100% seguro", "conforme LGPD", "aprovado na loja". Declarar o que foi provado, onde e como.

---

## 12. Fontes consultadas

- Skills e práticas de agentes: [anthropics/skills — webapp-testing](https://www.mdskills.ai/de/skills/webapp-testing); [obra/superpowers — verification-before-completion](https://claudemarketplaces.com/skills/obra/superpowers/verification-before-completion); [Skills de teste para agentes](https://claudemarket.ai/blog/best-testing-skills-for-ai-agents)
- Playwright Test Agents (planner, generator, healer — v1.56): [Currents — estado do ecossistema em 2026](https://currents.dev/posts/state-of-playwright-ai-ecosystem-in-2026); [Bug0](https://bug0.com/blog/playwright-test-agents)
- Acessibilidade automatizada: [axe-core + Playwright](https://dev.to/liubyvyi/accessibility-testing-with-playwright-and-axe-core-3n6b)
- Contrato e fuzzing de API: [Schemathesis](https://github.com/elsurudo/schemathesis)
- E2E de app nativo: [Expo — E2E com Maestro](https://docs.expo.dev/tutorial/cicd/e2e-tests.md); [Guia Maestro para React Native](https://dev.to/peakiqofficial/e2e-testing-react-native-with-maestro-a-practical-guide-1g79)
- Código morto em TS: [Knip — unused exports](https://knip.dev/typescript/unused-exports)
- PHP/Laravel: [Larastan, Pest arch e mutação — skill laravel-quality](https://skills.sh/leeovery/claude-laravel/laravel-quality); [Pest 3 (mutação, arch presets)](https://pestphp.com/docs/pest3-now-available/llms.txt); [Semgrep — suporte a PHP](https://semgrep.dev/blog/2022/announcing-php-ga-support)
- Segurança de API: [OWASP API Top 10 — guia de testes de BOLA](https://www.decryptiondigest.com/blog/api-security-testing-owasp-api-top10-guide). Para o checklist oficial, use os textos da OWASP (ASVS 5.0, API Top 10 2023, WSTG)
- Lojas: [Exigência de exclusão de conta da Google Play](https://learn.microsoft.com/en-us/answers/questions/2140637/i-have-a-question-about-google-play-s-app-account); [Android Central](https://www.androidcentral.com/apps-software/google-play-account-data-deletion-policy)
- LGPD: [Resolução CD/ANPD nº 2/2022](https://okai.com.br/documento/2022-01-28/resolucao-cd-anpd-nº-2-de-27-de-janeiro-de-2022); [Guia de segurança da ANPD para pequeno porte](https://www.bmalaw.com.br/conteudo/direito-digital-protecao-de-dados-e-cybersecurity/artigos-e-noticias/anpd-publica-guia-de-seguranca-da-informacao-para-agentes-de-tratamento-de-pequeno-porte/pdf)
- Usabilidade: [Cognitive walkthrough](https://en.wikipedia.org/wiki/Cognitive_walkthrough); [Walkthrough × heurística × teste com usuário](https://uxplanet.org/cognitive-walkthrough-vs-heuristic-evaluation-vs-usability-testing-stop-confusing-these-methods-dfe0017e4543)

Várias fontes são blogs e diretórios de terceiros; antes de adotar uma ferramenta, confira a versão e a sintaxe na documentação oficial dela.

---

## 13. Prompt curto para disparar uma rodada

```text
Leia e siga integralmente PROTOCOLO_VARREDURA_PONTA_A_PONTA.md e o CLAUDE.md.
Modo: varredura + correção + validação em runtime.
1) Rode a Fase 1 (node scripts/pie-costura.mjs e o resto do §4) e trie os candidatos.
2) Suba o ambiente do §3 (Postgres com erp_app, duas revendas, um usuário por perfil).
3) Execute as jornadas do §5.1 por perfil no navegador, com prova de persistência e matriz de autorização.
4) Corrija pelo ciclo do §8: teste que falha → correção → teste passa → falha de novo sem a correção.
5) Decisões de negócio vão para a fila do §9; não as invente.
6) Entregue os artefatos do §11 e o veredito, dizendo o que NÃO foi testado.
Não faça push. Não altere ctrl-web/. Não use emulador nem build de APK nesta máquina.
```
