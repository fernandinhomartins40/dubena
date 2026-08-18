# Dubena — Ecossistema "Gás em Casa"

Monorepo **privado** da reescrita do ERP de uma revenda de GLP (disk-gás).
Versionado de forma **independente dos repositórios originais** — o trabalho é
feito sobre clones, sem alterá-los.

> **Estado em 2026-08-18:** a reescrita está **completa em código**. O
> `erp-novo` roda em homologação com os dados reais migrados. O que falta para
> a virada não é programação — são chaves externas, verificações físicas e o
> ensaio do cutover.
>
> 👉 **[docs/gauntlet/GUIA_DO_DONO.md](docs/gauntlet/GUIA_DO_DONO.md)** — o que
> falta, em linguagem comum, na ordem de execução.

---

## Os sistemas

| Pasta | O que é | Stack | Estado |
|---|---|---|---|
| **`erp-novo/`** | **O sistema novo** — ERP completo + API + SPA administrativa | Laravel 12 · PHP 8.2+ · PostgreSQL · React 18 + Vite | ✅ **ativo — é aqui que se trabalha** |
| `ctrl-web/` | O ERP **legado**, ainda em produção | Laravel 5.8 · PHP 7.4 · PostgreSQL | 🗄️ a ser aposentado no cutover |
| `app-gas-em-casa/` | App do consumidor | React Native / Expo | ✅ ativo |
| `app-entregador/` | App do entregador | React Native / Expo | ✅ ativo |
| `mobile-shared/` | Código comum aos dois apps | TypeScript | ✅ ativo |

> ⚠️ **Não altere `ctrl-web/`.** Ele é a referência do comportamento original e
> será desligado. Correções vão para o `erp-novo`.

### Dentro do `erp-novo`

| Camada | Onde | Escala |
|---|---|---|
| Domínio (regra de negócio) | `app/Domain/` | 26 domínios |
| API administrativa | `app/Http/Controllers/Api/Admin/` | 49 controllers |
| API dos apps | `app/Http/Controllers/Api/Mobile/` | — |
| ETL do legado | `app/Etl/` | 28 migrators + invariantes |
| SPA administrativa | `frontend/src/features/` | 27 features |
| Banco | `database/migrations/` | 95 migrations |
| Testes | `tests/` | 147 arquivos, **839 testes verdes** |

**490 endpoints** no manifesto (`database/api-manifest.json`), que é validado por
teste — rota nova sem regenerar o manifesto quebra o `ApiContratoDriftTest`.

---

## Decisões de arquitetura que valem conhecer antes de mexer

**Multi-tenant por RLS do PostgreSQL, não por filtro na aplicação.**
154 policies isolam por `empresa_id`/`grupo_id`. A conexão de runtime usa a role
`erp_app` (`NOSUPERUSER NOBYPASSRLS`); migrations rodam como `pgsql_owner`.
**Se a app conectar como superusuário, o Postgres ignora a RLS** — o
`golive:check` verifica isso.

**Segredos resolvidos em três camadas, fail-closed.**
Empresa → grupo → plataforma. Sem credencial da empresa, o sistema **não cobra**
— de propósito. Ver `docs/01-vigente/INTEGRACOES_MULTITENANT.md`.

**Drivers com gate.** Firebase, FCM, fiscal e cobrança têm implementação `Fake`
para dev/CI. Em produção, driver fake **lança exceção** em vez de fingir que
funcionou.

**O ETL preserva os ids do legado.** A recarga é idempotente por upsert. Isso
tem consequência: re-rodar `etl:run` depois do cutover **sobrescreveria** dados
criados no sistema novo — por isso existe uma trava que detecta e recusa.

**Saldo é derivável.** `Σ movimentos = saldo materializado` é invariante
verificada pelo `cutover:check`. (Com uma ressalva importante herdada do legado:
ver `docs/gauntlet/T5.1_ACHADOS.md` §4.)

---

## Comandos que importam

```bash
cd erp-novo

# Desenvolvimento
php artisan test                      # 839 testes
php artisan api:manifest              # regerar após criar/alterar rota
cd frontend && npx tsc --noEmit       # typecheck da SPA

# Portões (read-only, seguros de rodar)
php artisan cutover:check             # invariantes do ETL — 71 OK / 0 falhas
php artisan golive:check --strict     # prontidão de produção
php artisan banco:producao-check      # banco pronto para receber o ETL

# Migração de dados
php artisan etl:run --dry-run         # simula, não grava
php artisan etl:run --check           # carga + invariantes
```

**Deploy:** push na `main` dispara deploy automático em produção (runner
self-hosted). Durante a janela de cutover, **congele a branch** — um push mata o
ETL em andamento.

---

## Documentação

Tudo em **[`docs/`](docs/)** — comece pelo [índice](docs/README.md).

| Pasta | O que é |
|---|---|
| [docs/gauntlet/](docs/gauntlet/) | **Fonte de verdade atual** — auditoria, plano de produção, guia do dono |
| [docs/01-vigente/](docs/01-vigente/) | Contratos de implementação por módulo (`IMPL_*.md`) e specs ativas |
| [docs/06-runbooks/](docs/06-runbooks/) | Procedimentos operacionais |
| [docs/02-auditoria-legado/](docs/02-auditoria-legado/) | PRDs fiéis do legado (referência do comportamento original) |
| [docs/00-ARQUIVO-HISTORICO/](docs/00-ARQUIVO-HISTORICO/) | 🗄️ Fases concluídas ou abandonadas — **não é fonte de verdade** |

Também: [`deploy/CUTOVER_RUNBOOK.md`](deploy/CUTOVER_RUNBOOK.md) (o roteiro da
virada) e `*/README.docker.md` (como subir cada sistema).

---

## Como rodar

```bash
cd erp-novo
docker compose up -d --build
docker compose exec app php artisan migrate --seed   # popula com massa demo
```

O seed cria uma base realista (Guarapuava/PR) para desenvolvimento. **Ele não
roda em produção** — há gate de ambiente.

---

## ⚠️ Segredos

Este repositório **não contém segredos reais** — o `.gitignore` os exclui. Os do
ambiente original ficam em `SEGREDOS_LOCAIS.md`, **apenas localmente** (fora do
git), e **devem ser rotacionados** antes de qualquer ambiente exposto. Os
`.env.docker` versionados têm apenas valores fake de desenvolvimento.
