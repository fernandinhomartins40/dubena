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
| O que falta para virar? | `docs/gauntlet/GUIA_DO_DONO.md` |
| O que foi entregue e por quê? | `docs/gauntlet/STATUS_FINAL.md` |
| O plano técnico (46 tarefas) | `docs/gauntlet/PLANO_PRODUCAO.md` |
| Contrato de um módulo | `docs/01-vigente/IMPL_*.md` |
| Como o legado se comporta | `docs/02-auditoria-legado/` |

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

**Teste passa em sqlite e quebra em Postgres.** Colunas com `varchar` curto
(`cpf` = 11, `cor` = 7, `char(1)` para `especie`/`pagarreceber`) não são
validadas pelo sqlite. Confira o tamanho na migration antes de escrever seed.

**`whereBetween` em coluna datetime perde o último dia.** `'2026-08-19 00:00:00'
> '2026-08-19'` na comparação de string. Use `whereDate`.

### Testes

**`Event::fake()` global mata os model events do Eloquent** — e com eles o
`empresa_id` herdado por relação, deixando filhos órfãos de tenant. Use
`Event::fake([EventoEspecifico])`.

**Rota nova sem `php artisan api:manifest` quebra o `ApiContratoDriftTest`.** É
proposital: o manifesto é contrato.

**Campo novo em model = lembrar do `$fillable`.** Um campo de config foi
descartado silenciosamente por esquecimento disso.

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

php artisan test                      # 839 testes
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
| Testes | **839 verdes** |
| Invariantes do ETL | **71 OK / 0 falhas** |
| Endpoints | 490 |
| Domínios / controllers admin | 26 / 49 |
| Migrations | 95 |
| Policies RLS | 154 |
| Ambiente na VPS | **homologação** (produção é o cutover) |
