# Relatório Final de Otimização — erp-novo

Data: 2026-10-09. Plano: `docs/VPS_OPTIMIZATION_PLAN.md`.

## Resumo

O erp-novo foi removido da VPS de propósito para voltar otimizado. Esta rodada
mudou **como ele chega** (build fora da VPS, deploy sem processo residente) e
**o que chega** (imagem menor, logs com teto, limpeza de releases escopada). A
aplicação ainda não foi reimplantada: faltam os secrets do GitHub e o
bootstrap do ambiente na VPS, que exigem o dono (ver "Bloqueios").

Tudo abaixo marcado MEASURED foi medido localmente (Docker Desktop, mesmo
Dockerfile e compose), rodando o script de deploy de verdade contra um registry
local no lugar do GHCR.

## Antes

| | Valor | Tipo |
|---|---|---|
| Imagem `erpnovo-app` | 1,21 GB | MEASURED |
| Imagem `erpnovo-web` | 105 MB | MEASURED |
| Build frio da app (local) | 328 s | MEASURED |
| Build da web | dependia do estágio PHP (composer install) | MEASURED (Dockerfile) |
| Build no CI | 1 job, app → web em série, sem cache | MEASURED (workflow) |
| Deploy | runner self-hosted residente na VPS | MEASURED (workflow) |
| Rotação de log | nenhuma (sem `daemon.json`, sem `logging` no compose) | MEASURED |
| Consumo em execução na VPS | NOT_MEASURED (não implantado) | — |

## Alterações realizadas

| ID | Mudança | Arquivo |
|---|---|---|
| V-1 | deploy no runner do GitHub, entrando na VPS por SSH com host verificado; a VPS só faz pull/migrate/up | `.github/workflows/deploy-erp-novo-*.yml`, `deploy/remote-deploy.sh` |
| V-2 | um job de build por imagem, em paralelo e em paralelo aos testes, com cache de camadas no GHCR | `.github/workflows/ci-erp-novo.yml` |
| V-3 | `web` constrói só do Vite + `public/`, sem esperar o Composer | `erp-novo/docker/php/Dockerfile` |
| V-4 | logs 3×10 MB em todos os serviços | `docker-compose.homolog.yml`, `docker-compose.producao.yml` |
| V-5 | limpeza de imagens/bundles só do erp-novo, retendo 3 releases + as em uso | `deploy/remote-deploy.sh` |
| V-6 | Redis de homologação sem o `.env` da app e com volume | `docker-compose.homolog.yml` |
| V-7 | nome de projeto Compose fixo por ambiente | `docker/compose-*.sh` |
| V-8 | extensões PHP compiladas em estágio próprio; imagem final sem `-dev`/`git`, com gate `ldd` + `php -m` | `erp-novo/docker/php/Dockerfile` |

## Serviços removidos

Nenhum. Os sete contêineres têm função comprovada: `app` (php-fpm), `web`
(nginx), `queue` (jobs de push, geocodificação e atribuição), `scheduler` (11
agendamentos, entre eles PIX e GPS), `reverb` (tempo real dos apps), `db` e
`redis` (fila, sessão, cache e locks do `withoutOverlapping`).

## Docker e imagens

- **app: 1,21 GB → 1,04 GB (−170 MB, −14%)** — MEASURED. O compilador (gcc/g++)
  continua lá porque vem da imagem base oficial do PHP; tirá-lo exigiria trocar
  de base, o que fica fora desta rodada.
- **web: 105 MB → 105 MB**; o ganho dela é de tempo, não de tamanho: deixou de
  depender do estágio PHP.
- Gate do build **provado**: plantei a remoção de `libpq5` e o build reprovou
  com `libpq.so.5 => not found`.

## RAM e CPU

Em repouso, logo após o deploy (MEASURED, local):

| Contêiner | RAM | CPU |
|---|---|---|
| scheduler | 121,5 MiB | 1,0% |
| app | 67,2 MiB | 0,0% |
| queue | 54,9 MiB | 0,1% |
| reverb | 54,9 MiB | 0,0% |
| db | 53,8 MiB | 1,4% |
| web | 7,7 MiB | 0,0% |
| redis | 3,6 MiB | 1,2% |
| **total** | **~363 MiB** | |

`opcache.enable_cli=On` com `file_cache`, e config/rotas/eventos em cache
(otimizações de 14/09, que só agora foram exercitadas numa imagem real):
`pix:expirar` roda em **111 ms** e `monitora:sync-positions` em 248 ms dentro
do scheduler. Sem número anterior comparável: a imagem antiga nunca rodou com
essas mudanças.

Sem limites de memória no compose, de propósito: o protocolo proíbe derivá-los
de consumo em repouso, e não há medição de pico ainda.

## Banco

Sem mudança. A suspeita de que o `config:cache` do entrypoint impediria a
migration de criar a role `erp_app` (ela usa `env()`) foi **descartada no
teste**: as variáveis chegam como ambiente real do processo, e a role nasceu
`rolsuper=f`, `rolbypassrls=f`, com a aplicação conectando por ela.

## Storage e disco

- Logs com teto de 30 MB por contêiner (antes, sem limite).
- Redis de homologação passou a ter volume: um dado gravado antes do 2º deploy
  **sobreviveu** à recriação (MEASURED). Antes, `appendonly` sem volume perdia
  sessões e jobs na fila a cada deploy.
- Limpeza por release provada: na 4ª release, exatamente 1 imagem (a que saiu
  da janela) e 1 bundle foram removidos; nada fora do erp-novo foi tocado.

## Build e deploy

- O build saiu da VPS por completo, e a VPS não mantém mais processo para
  esperar job.
- No CI, app e web constroem simultaneamente e em paralelo aos testes; o deploy
  só dispara se o workflow inteiro passar. MEASURED no run de `18685d3b`, com
  cache: build da app **115 s** e da web **79 s** (incluindo Trivy e SBOM),
  correndo junto com o `test` (172 s). O workflow fecha no tempo do job mais
  lento, não na soma.
- O deploy desse run disparou e parou no primeiro passo (`secret VPS_* ausente`),
  como projetado.
- Deploy medido localmente: **primeiro deploy ~25 s** (com o pull das imagens
  base) e **redeploy em 16 s**, migration idempotente.
- Bundle enviado à VPS: só os arquivos de compose/deploy (KB), nunca o
  repositório.
- O token do GHCR viaja pela entrada padrão (não aparece em `ps`) e vive num
  `DOCKER_CONFIG` temporário apagado logo após o pull.
- A identidade da VPS é conferida (`StrictHostKeyChecking yes` +
  `VPS_KNOWN_HOSTS`).

## Jobs e processos

Sem mudança nesta rodada; a frequência do GPS (30 s) segue como decisão do dono
(ver `OTIMIZACAO_CPU_STATUS.md`).

## Testes

| Teste | Resultado |
|---|---|
| Deploy ponta a ponta (`remote-deploy.sh` real, registry local) | 4 execuções; a 1ª pegou um CRLF da cópia local do Windows (o índice do git está correto em LF); as 3 seguintes passaram |
| Idempotência (redeploy) | passou |
| Persistência do Redis entre deploys | passou |
| Limpeza escopada | passou |
| Gate `ldd`/`php -m` com regressão plantada | detectou |
| `docker compose config` dos dois ambientes | passou |
| YAML dos workflows, `bash -n`/`sh -n` dos scripts | passou |
| Suíte PHP (antes e depois da atualização de dependências) | 1832 passes, 16 skips, 0 falhas |
| CI no GitHub (`18685d3b`) | test, test-postgres, frontend, build app, build web: todos verdes |

## Ações que exigem autorização

1. Secrets `VPS_HOST`, `VPS_SSH_KEY`, `VPS_KNOWN_HOSTS` no GitHub.
2. Chave pública de deploy no `authorized_keys` da VPS.
3. Bootstrap do env de homologação em `/opt/dubena-env/`.
4. Vhost Nginx + TLS do domínio.
5. Rotação de log global do host e limpeza do build cache de outras apps (ver
   plano): afetam aplicações vizinhas.

## Bloqueios

Os itens 1 a 4 acima. Sem eles, o deploy reprova logo no primeiro passo com a
mensagem `secret VPS_* ausente`, e não faz nada pela metade.

## Riscos restantes

- A base `php:8.3-fpm` (Debian) carrega toolchain de compilação. Uma base menor
  exigiria revalidar extensões e fontes do dompdf.
- O scanner Trivy agora roda por imagem no CI; uma CVE crítica nova reprova o
  build da imagem afetada, como antes.
- `deploy-ctrl-web.yml` ainda usa runner self-hosted, que não existe mais. O
  legado não é implantado nesta VPS e o arquivo fica intocado (regra do repo).

## Recomendações futuras

- Medir pico (`docker stats` sob uso real) antes de definir `mem_limit`.
- Avaliar `pm.max_children` do php-fpm com carga real.
