# Contexto para agentes de IA

Leia isto antes de tocar no código. É o que economiza as horas que já foram
gastas descobrindo cada item aqui da forma difícil.

---

## O projeto em cinco linhas

Reescrita do ERP de uma revenda de GLP (disk-gás). O sistema legado
(`ctrl-web/`, Laravel 5.8) ainda roda em produção; o novo (`erp-novo/`,
Laravel 12 + React) está **completo em código** e rodando em homologação com os
dados reais migrados.

**O que falta não é programação** — são chaves externas (Firebase, certificado
A1, PIX), verificações físicas (passar um boleto num leitor) e o ensaio do
cutover. Ver [`docs/gauntlet/GUIA_DO_DONO.md`](docs/gauntlet/GUIA_DO_DONO.md).

---

## Onde está a verdade

| Pergunta | Onde |
|---|---|
| **O plano vigente (SaaS, F0–F10)** | `docs/01-vigente/PLANO_TRANSFORMACAO_SAAS.md` |
| **Em que pé está cada fase** | `docs/01-vigente/implementacao-saas/F*_PROGRESSO.md` e `F*_FECHAMENTO.md` |
| O que falta para virar (cutover) | `docs/gauntlet/GUIA_DO_DONO.md` |
| O que foi entregue e por quê | `docs/gauntlet/STATUS_FINAL.md` |
| Contrato de um módulo | `docs/01-vigente/IMPL_*.md` |
| Como o legado se comporta | `docs/02-auditoria-legado/` |

⚠️ **`docs/gauntlet/PLANO_PRODUCAO.md` está concluído.** As 46 tarefas dele
foram entregues; ele descreve o cutover da Dubena, não o SaaS. O plano em curso é
o `PLANO_TRANSFORMACAO_SAAS.md`.

⚠️ **`docs/00-ARQUIVO-HISTORICO/` não é fonte de verdade.** Contém planos
descartados (Filament) e auditorias já implementadas. Útil para entender *por
que* algo é como é — nunca para saber *o que* vale agora.

**A fonte de verdade final é o código.** Documento pode ter envelhecido; o teste
que passa, não.

---

## Como investigar o código

**Nunca leia um arquivo inteiro para mexer num método.** Três ferramentas
respondem a perguntas diferentes; usar a errada custa contexto à toa.

| Pergunta | Ferramenta |
|---|---|
| "me dá só este método" | **Serena** (`find_symbol`) |
| "o que quebra se eu mudar isto" | **Graphify** (`affected`) |
| "onde está a lógica que faz X" | **grep** nos comentários em pt-BR |

### Serena (MCP) — ler o trecho, não o arquivo

Language server (Intelephense/TS) exposto por MCP. Resposta verificável do
compilador, não similaridade estatística.

- `get_symbols_overview` — o que existe num arquivo (raso, ~60 bytes)
- `find_symbol` com `depth: 1, include_body: false` — todos os métodos da
  classe com as linhas de cada um, **sem os corpos**
- `find_symbol` com `include_body: true` — o corpo de um método só
- `find_referencing_symbols` — quem usa este símbolo, com o trecho de cada uso

Medido aqui, na tarefa "alterar o método que cria pedido":

| Caminho | Custo |
|---|---|
| Ler `PedidoService.php` inteiro | 18.578 bytes |
| Panorama da classe + só o método | 3.864 bytes (**4,8x menos**) |
| Já sabendo o nome do método | 1.713 bytes (**10,8x menos**) |

Reindexar após mudança estrutural grande: `serena project index` (2min20s para
1.116 PHP + 363 TS).

### Graphify — impacto de uma mudança

```bash
graphify affected "erp_novo_app_models_empresa_empresa"  # o que quebra
graphify explain "PedidoService"                         # quem chama, onde mora
graphify path "PedidoController" "Nfe"                   # como dois se ligam
node scripts/graph-map.js                                # visão geral (mapa abaixo)
```

Símbolo repetido dá "Ambiguous"/"No unique node match": a ferramenta imprime os
ids; repita com o id completo.

**O que ele acha e o grep não:** medido em `PedidoService` — os dois acham 50
arquivos, mas 10 são **diferentes**. `AppPedidoController` não cita
`PedidoService` em lugar nenhum e depende dele via `PedidoMobileService` (2
saltos). Para dependência transitiva, grep dá falso negativo.

**O que ele NÃO economiza:** para achar quem cita um nome, `grep` custou 12,8 KB
e o grafo 7,4 KB — 1,7x, não as ordens de magnitude que a propaganda promete. O
valor dele é a dependência indireta, não o token.

### grep — ainda é a primeira escolha para intenção

O código aqui é comentado em português explicando o **porquê** (regra deste
repositório), então buscar por intenção em texto puro funciona: `fail-closed`
acha 24 arquivos, `sem credencial` acha 12. É por isso que **não** instalamos
índice vetorial: embeddings competiriam com algo que aqui já funciona, e
benchmarks independentes mostram busca semântica colapsando justamente em
consultas curtas por palavra-chave.

### Onde nenhuma das três ajuda

- **Fronteira HTTP.** `api.get('/pedidos')` na SPA e `Route::get('pedidos')` no
  Laravel não têm ligação sintática — e aqui é a fronteira mais movimentada.
  Use `php artisan api:manifest` e grep pela string.
- **O banco.** RLS, policies, grants e o efeito real de uma migration não estão
  em índice nenhum. Continua sendo pergunta para o Postgres.
- **O legado.** `ctrl-web/` (4.049 arquivos, 73% do código) está fora dos dois
  índices de propósito — ver `.graphifyignore` e `.serena/project.yml`. Para o
  legado, leia `docs/02-auditoria-legado/` ou o próprio `ctrl-web/`.

Também não serve `graphify query` em linguagem natural: casa nome de símbolo,
não intenção. Use `explain` / `affected` / `path`.

### Manter o mapa atualizado

O grafo se reconstrói sozinho a cada commit (hook do graphify). O mapa abaixo
**não** — escrever no `CLAUDE.md` durante o hook sujaria a árvore depois de todo
commit. Depois de mudança estrutural (módulo novo, pasta movida):

```bash
graphify update .                    # se o hook não rodou
node scripts/graph-map.js --write    # regenera SÓ o bloco entre os marcadores
```

O texto escrito à mão nunca é sobrescrito: `--write` só toca o que está entre
`graph-map:begin` e `graph-map:end`.

## Mapa da arquitetura

<!-- graph-map:begin -->
<!-- Gerado por scripts/graph-map.js. Nao editar a mao: rode `node scripts/graph-map.js --write`. -->

Grafo: 10783 nos, 32577 arestas (commit `edafcd75`).

| Area | Nos | Hub (maior propagacao de mudanca) |
|---|---|---|
| `erp-novo` | 9367 | `Empresa` (961 arestas) - `erp-novo/app/Models/Empresa.php:21` |
| `app-gas-em-casa` | 687 | `app-gas-em-casa/src/types/types.ts` (66 arestas) - `app-gas-em-casa/src/types/types.ts:1` |
| `app-entregador` | 421 | `app-entregador/src/constants/app.ts` (37 arestas) - `app-entregador/src/constants/app.ts:1` |
| `deploy` | 74 | `D-0 — a janela` (10 arestas) - `deploy/CUTOVER_RUNBOOK.md:57` |
| `mobile-shared` | 25 | `mobile-shared/package.json` (11 arestas) - `mobile-shared/package.json:1` |

**Acoplamento entre areas:**
- `app-gas-em-casa -> mobile-shared`: 15 arestas
- `app-entregador -> erp-novo`: 8 arestas
- `app-entregador -> mobile-shared`: 8 arestas

**Onde as coisas moram** (79 de 220 diretorios; corte em 30+ nos):

```
2333  erp-novo/tests/Feature
 648  erp-novo/app/Http/Controllers/Api/Admin
 593  erp-novo/database/migrations
 383  erp-novo/app/Etl/Migrators
 302  erp-novo/tests/Domain
 267  app-gas-em-casa
 245  erp-novo/app/Console/Commands
 178  app-entregador
 125  erp-novo/frontend
 118  erp-novo
 114  erp-novo/app/Domain/Fiscal
 109  erp-novo/app/Http/Controllers/Api/Mobile
 106  erp-novo/frontend/src/components/ui
 104  erp-novo/frontend/src/features/superadmin
 103  erp-novo/frontend/src/features/satelites
  90  erp-novo/app/Models
  87  erp-novo/database/seeders
  87  erp-novo/app/Domain/Tenant
  87  erp-novo/app/Domain/Satelite
  84  erp-novo/tests/Migration
  83  erp-novo/app/Models/Saas
  82  erp-novo/app/Domain/Mobile
  75  app-gas-em-casa/src/types
  72  erp-novo/frontend/src/features/acessos
  61  erp-novo/app/Domain/Identidade
  60  erp-novo/app/Models/Cliente
  60  erp-novo/frontend/src/features/comodatos
  59  erp-novo/app/Domain/Saas
  59  erp-novo/frontend/src
  58  erp-novo/app/Domain/Financeiro
  58  app-entregador/src/services
  57  erp-novo/frontend/src/lib
  55  erp-novo/app/Domain/Logistica
  55  erp-novo/app/Domain/Cobranca/Drivers
  55  erp-novo/app/Domain/Monitora
  54  erp-novo/frontend/src/features/produtos
  54  erp-novo/frontend/src/features/clientes
  53  erp-novo/frontend/src/features/pedidos
  52  erp-novo/app/Http/Controllers/Api/SuperAdmin
  52  erp-novo/frontend/src/features/geografico
  51  erp-novo/app/Domain/Shared
  50  erp-novo/app/Etl/Support
  50  app-gas-em-casa/src/services
  47  erp-novo/app/Models/Apoio
  47  erp-novo/app/Models/Fiscal
  47  app-gas-em-casa/src/components/atoms
  47  app-entregador/src/app/(app)
  46  erp-novo/frontend/src/features/crm
  46  erp-novo/tests/Unit
  46  erp-novo/app/Models/Rh
  45  erp-novo/frontend/src/features/empresas
  44  erp-novo/app/Models/Satelite
  43  erp-novo/app/Domain/Geografico
  41  erp-novo/app/Http/Middleware
  40  erp-novo/app/Models/Monitora
  40  erp-novo/frontend/src/features/financeiro
  39  erp-novo/app/Models/Pedido
  39  erp-novo/app/Domain/Monitora/Drivers
  39  erp-novo/frontend/src/features/pagamentos
  38  erp-novo/frontend/src/features/gestao
  37  erp-novo/app/Models/Frota
  37  erp-novo/app/Http/Controllers/Api/Legado
  36  erp-novo/app/Models/Financeiro
  36  erp-novo/app/Domain/Venda
  36  deploy
  35  erp-novo/frontend/src/features/central-vendas
  34  erp-novo/app/Models/Crm
  34  app-entregador/src/helpers
  33  erp-novo/app/Domain/Caixa
  33  erp-novo/frontend/src/features/financeiro/tabs
  32  erp-novo/app/Domain/Relatorio
  32  erp-novo/app/Domain/Cobranca
  32  app-gas-em-casa/src/helpers
  31  erp-novo/app/Models/Geografico
  30  erp-novo/app/Domain/Pedido
  30  erp-novo/app/Models/Estoque
  30  erp-novo/app/Models/Logistica
  30  erp-novo/app/Domain/Apoio
  30  erp-novo/app/Domain/Auditoria
```

<!-- graph-map:end -->

---

## Regras deste repositório

**Não altere `ctrl-web/`.** É o legado, referência do comportamento original, e
será desligado no cutover. Correção vai para o `erp-novo`.

**Commit por fase, direto na `main`.** Não criar branch. Push na `main` dispara
deploy em produção — o que significa que **um push durante a janela de cutover
mata o ETL em andamento**.

**Português do Brasil** em comentários, mensagens de commit e conversa.

**Comentário explica o *porquê*, não o *o quê*.** O código já diz o que faz. O
comentário registra a decisão: por que este limite, por que esta ordem, o que
quebra se mudar.

---

## Armadilhas reais (cada uma custou tempo)

### Banco e tenancy

**A RLS é ignorada se a conexão for superusuário.** O runtime tem que conectar
como `erp_app` (`NOSUPERUSER NOBYPASSRLS`); migrations rodam como `pgsql_owner`.
Em dev o `.env` costuma apontar para `postgres` — por isso `golive:check` reprova
localmente, e está certo.

**`information_schema` só mostra objetos que a role possui.** Um dry-run em
produção reportou "0 tabelas referenciam clientes.id" com 40 mil linhas filhas
apontando para elas. Use **`pg_constraint`** para descobrir FKs, e aborte se a
lista vier vazia.

**`GRANT SELECT` não restringe — só `REVOKE` restringe.** A migration de grants
faz `ALTER DEFAULT PRIVILEGES ... GRANT SELECT, INSERT, UPDATE, DELETE`, então
**toda tabela nova nasce com escrita para `erp_app`**. Conceder `SELECT` numa
tabela que deveria ser só-leitura apenas reafirma o que já existe. Quatro tabelas
ficaram graváveis assim, e só a conferência no banco de homologação revelou — o
código dizia uma coisa e o banco fazia outra, e nenhum teste comparava os dois.

**Policy canônica + `empresa_id` nulo legítimo = escrita rejeitada em silêncio.**
`WITH CHECK (empresa_id IS NOT NULL AND app_tenant_can_operate(...))` com `FORCE
ROW LEVEL SECURITY` faz o Postgres recusar todo insert sem empresa. Onde o nulo é
um caso REAL — consumo da chave da plataforma, `login`/`init` antes de resolver
tenant —, use `WITH CHECK (empresa_id IS NULL OR app_tenant_can_operate(...))`:
a linha sem empresa não pertence a tenant nenhum, o `USING` continua escondendo-a
de toda revenda, e gravar linha de OUTRA segue barrado. Aconteceu em produção com
`integracao_consumos`, na tabela criada exatamente para enxergar esse caso — e
não acusou porque o registrador engole exceção e a suíte roda em sqlite.

**Antes de revogar escrita numa tabela, confira qual role escreve nela.**
`DB::table(...)` usa a conexão **default**, que é `erp_app` — só as *migrations*
rodam como `pgsql_owner`. Quase revoguei a escrita das `conversao_*` alegando que
"quem escreve é o console, como owner"; era falso, e teria quebrado o registro da
conversão **em silêncio**, porque toda escrita dele é protegida por `catch`. A
conversão rodaria inteira sem deixar registro, e o bundle de evidência sairia
vazio como se nada tivesse acontecido. Nenhum teste local pegaria: sqlite não tem
grants.

**Teste passa em sqlite e quebra em Postgres.** Colunas com `varchar` curto
(`cpf` = 11, `cor` = 7, `char(1)` para `especie`/`pagarreceber`) não são
validadas pelo sqlite. Confira o tamanho na migration antes de escrever seed.

**`whereBetween` em coluna datetime perde o último dia.** `'2026-08-19 00:00:00'
> '2026-08-19'` na comparação de string. Use `whereDate`.

**O cast `date` do Eloquent grava com HORA.** Ele serializa `'AAAA-MM-DD
00:00:00'`; o Postgres trunca ao gravar numa coluna `date`, o **sqlite não**.
Consequência: o mesmo relatório perdia o último dia do período **só na suíte** e
funcionava em produção — a pior forma da divergência, porque a suíte é onde se
confia. Vale o contrário também: defeito só-Postgres passa verde localmente.

### Testes

**Teste que toca RLS não usa `RefreshDatabase`.** O runtime (`erp_app`) não é
dono das tabelas, então recriar o schema falha com `must be owner of table
agencias`. Use `DatabaseTransactions`, como `RlsCoberturaTest` — ou nada, se o
teste só lê `information_schema`.

**Sob RLS, conferir a linha que você acabou de gravar é uma armadilha dupla.**
Pela conexão do runtime, o `USING` esconde a linha que não é do tenant; por
`pgsql_owner`, é conexão **separada** e não enxerga a transação ainda aberta do
teste. As duas dão zero por motivos diferentes do que se quer medir. Quando o
que importa é o banco ter *aceitado* a escrita, asserte o aceite.

**A RLS descarta, nem sempre lança.** Um insert barrado pelo `WITH CHECK` pode
voltar como `INSERT 0 0`, sem erro. Teste que espera exceção passa verde num
banco que não protege nada — asserte o efeito observável.

**O gate Postgres roda localmente** com o container da app + `--network
container:erpnovo-db`, passando `DB_*` como `-e` (o `phpunit.xml` fixa
`DB_CONNECTION=sqlite`, e `<env>` só perde para variável já definida no
ambiente — pôr no `.env` não adianta). ⚠️ Não altere a senha da role `erp_app`:
o Postgres é compartilhado com homologação.

**`Event::fake()` global mata os model events do Eloquent** — e com eles o
`empresa_id` herdado por relação, deixando filhos órfãos de tenant. Use
`Event::fake([EventoEspecifico])`.

**Rota nova sem `php artisan api:manifest` quebra o `ApiContratoDriftTest`.** É
proposital: o manifesto é contrato.

**Campo novo em model = lembrar do `$fillable`.** Um campo de config foi
descartado silenciosamente por esquecimento disso.

**Guardião só vale se você provar que ele detecta.** Antes de aceitar o verde,
plante a regressão que ele deveria pegar. Nesta base já houve: teste que varria
zero arquivos e passava; `assertSame(f($x), f($x))` na matemática CNAB; guardião
de `whereBetween` que não pegou a regressão nas duas primeiras versões. Todo
teste que varre arquivo precisa de `assertGreaterThan(N, $varridos)`.

**Teste pode passar pela verificação ERRADA.** `test_execucao_sem_desfecho`
passava — mas porque a verificação *seguinte* também reprovava aquele cenário, e
a mensagem dela por acaso continha a palavra asserida. Desativei a checagem que
o teste dizia cobrir e **nenhum teste falhou**. Quando um cenário viola várias
regras ao mesmo tempo, isole: satisfaça todas as outras e deixe só a que se quer
medir.

**Teste preso ao formato de serialização vira falso positivo.** Um teste fixava
`'2026-09-15T00:00:00.000000Z'`; ao corrigir o fuso da plataforma a data
continuou certa e só a representação mudou — mas o teste quebrou, fazendo a
correção parecer regressão. Asserte a **data**, não a string ISO.

### PHP / Laravel

**Não redeclare `$connection` em Job.** `Illuminate\Bus\Queueable` já declara a
propriedade sem tipo; redeclarar dá erro fatal. Use
`$this->onConnection(...)->onQueue(...)` no construtor.

**`Artisan::call` dentro de migration usa a conexão default**, não a da
migration. Se a migration roda em `pgsql_owner`, o comando chamado por ela roda
em `pgsql` — e escreve no lugar errado.

**`iconv('ASCII//TRANSLIT')` depende do locale.** No Windows devolve `?` para
acentos. Para normalizar texto, use tabela explícita de transliteração.

**dompdf: use `DejaVu Sans`.** É a única fonte embarcada com acentuação latina —
com a padrão, "endereço" sai "endere?o" no papel entregue ao cliente.

### Investigação

**"Não dá para fazer" precisa ser verificado como qualquer outra afirmação.**
Nesta base já declarei **seis** tarefas bloqueadas que tinham código pendente
dentro. O padrão do erro é sempre o mesmo: ler a exigência mais cara da lista e
concluir que a tarefa inteira depende dela. F7-03 tem sete exigências e só duas
precisam de staging; F9-08 tem sete cenários e eu disse "exige Playwright" sem
rodar um `grep` no `package.json` — que tem vitest, jsdom e testing-library.
Antes de escrever "bloqueado", leia a tarefa **item a item** e confira a
ferramenta no repositório.

**`grep` confirma presença, nunca ausência.** Concluí que não havia ingestão de
posição por rastreador porque `where('imei'` não retornava nada — e havia: o
`TraccarDriver` casa o `uniqueId` do provedor com o `imei` **em memória**, sem
`where` nenhum. A varredura por padrão acha o que você procura, não o que está
lá. "Não achei" só vira "não existe" depois de ler o fluxo completo.

**Corrija o registro quando o diagnóstico mudar.** O documento de fase tinha uma
seção afirmando que aquilo era trabalho futuro; ela foi **reescrita explicando o
erro**, não apagada — quem ler depois precisa saber por que a conclusão mudou.

### Windows (ambiente de dev)

O shell é Git Bash. Heredoc com PHP dentro quebra por causa das aspas — use a
ferramenta Write. `print()` de Python com emoji/`✓` dá `UnicodeEncodeError`;
prefira ASCII na saída de script.

---

## Padrões a seguir

**Backend:** `app/Domain/<Contexto>/<Nome>Service.php` concentra a regra;
controller valida e delega. Enum para máquina de estados (`SituacaoNota`,
`EfeitoPedido`), não string solta.

**API:** `Route` em `routes/api.php` → controller em `Api/Admin/` (ou
`Api/Mobile/`) → `$this->autorizar($request, 'modulo.acao')` na primeira linha.
Resposta sempre `{data: ...}`.

**Frontend:** `frontend/src/features/<modulo>/` com `api.ts` (hooks React Query)
+ `<Modulo>Page.tsx` + `tabs/`. Componentes de UI vêm de `@/components/ui` —
não crie botão novo. Helpers compartilhados em `@/lib` (ex.: `pdf.ts` para abrir
PDF por blob, porque o Bearer viaja no header e link direto chega sem auth).

**Multi-tenant:** todo model com dado de empresa usa `BelongsToTenant`, e a
tabela precisa de policy RLS na migration. Migration que cria tabela nova deve
incluir `ENABLE ROW LEVEL SECURITY` + policy + `GRANT` para `erp_app` — a
descoberta automática só varre uma vez e não alcança tabelas criadas depois.

**Fail-closed em dinheiro e identidade.** Sem credencial da empresa, não cobra e
não autentica — não caia para um default da plataforma.

---

## Comandos

```bash
cd erp-novo

php artisan test                      # 1720 testes
php artisan api:manifest              # após criar/alterar rota
cd frontend && npx tsc --noEmit       # typecheck da SPA

# Portões — read-only, seguros
php artisan cutover:check             # invariantes do ETL (71 OK / 0 falhas)
php artisan golive:check --strict     # prontidão de produção
php artisan banco:producao-check      # banco pronto para o ETL

php artisan etl:run --dry-run         # simula a migração, não grava
```

---

## O que NÃO fazer

- ❌ Rodar `etl:run` sem `--dry-run` num banco com dados criados no sistema novo
  (existe trava, mas entenda por que ela existe: o upsert por id **sobrescreve**
  edições feitas aqui)
- ❌ Aplicar `throttle` no webhook PIX — o PSP chama com volume legítimo e a
  segurança já é tripla
- ❌ Apagar os drivers `Fake*` — o CI depende deles
- ❌ Remover o flag `support` do sistema; ele é o mecanismo legítimo de suporte
  (só não pode estar no `$fillable`)
- ❌ Criar migration destrutiva no mesmo deploy de uma feature — quebra o
  rollback
- ❌ Tratar documento de `00-ARQUIVO-HISTORICO/` como especificação vigente

---

## Estado atual em números

| | |
|---|---|
| Testes (backend) | **1720 verdes** |
| Testes (SPA) | **47 verdes** |
| Invariantes do ETL | **71 OK / 0 falhas** |
| Endpoints | 600 |
| Domínios / controllers admin | 26 / 49 |
| Migrations | 162 |
| Policies RLS | 154 |
| Ambiente na VPS | **homologação** (produção é o cutover) |

⚠️ **O objetivo mudou de escopo.** O alvo não é mais só virar o cutover da
Dubena, e sim **transformar isto num SaaS para N revendas**. O plano é
`docs/01-vigente/PLANO_TRANSFORMACAO_SAAS.md` (F0–F10); o estado de cada fase
está em `docs/01-vigente/implementacao-saas/F*_PROGRESSO.md` e `F*_FECHAMENTO.md`.

O que isso muda na prática: **convenção de uma revenda não é regra do produto**.
Preço, teto, grade e limiar de negócio são configuração do tenant, editáveis no
painel — nunca constante no código.
