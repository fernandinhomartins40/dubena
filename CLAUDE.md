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
