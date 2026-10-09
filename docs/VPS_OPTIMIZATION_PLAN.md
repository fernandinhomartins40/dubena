# Plano de Otimização da VPS — erp-novo

Data: 2026-10-09. Protocolo: `PROTOCOLO_UNIVERSAL_OTIMIZACAO_VPS.md`.
Plano anterior (CPU do scheduler, 14/09): `docs/01-vigente/OTIMIZACAO_CPU_STATUS.md`
— já está no código e continua valendo.

## Estado atual

**Aplicação:** Laravel 12 / PHP 8.3-FPM + SPA React (Vite) servida por Nginx.
Sete contêineres por ambiente: `app` (php-fpm), `web` (nginx), `queue`,
`scheduler`, `reverb` (WebSocket), `db` (PostgreSQL 15), `redis` (fila, sessão,
cache, locks). Produção tem ainda `queue-longo` (ETL de 6 h). Todos têm função
comprovada; nenhum é candidato a remoção (ver relatório).

**VPS (72.60.10.108, compartilhada):** reinstalada em 14/09/2026. O dono
removeu o Dubena dela de propósito, para voltar otimizado. Hoje não há nenhum
contêiner, volume, vhost ou runner do erp-novo — e por isso o deploy de
`a3e9c55b` ficou um mês `queued`: ele dependia de um runner self-hosted que
não existe mais.

**Pipeline antes desta mudança:**
`push → CI (testes) → build-release (1 job, app e web em série, sem cache) →
GHCR → workflow_run → runner self-hosted NA VPS → pull → migrate → up`.

## Baseline

| Métrica | Valor | Fonte |
|---|---|---|
| vCPU / RAM do host | 4 / 15 GiB (4,8 GiB em uso, 10 GiB disponíveis) | MEASURED (`free`, `nproc`) |
| Disco do host | 49 GB de 194 GB (26%) | MEASURED (`df`) |
| Imagens Docker no host (todas as apps) | 84 imagens, 36,7 GB | MEASURED (`docker system df`) |
| Rotação de log do Docker no host | **nenhuma** (`/etc/docker/daemon.json` não existe) | MEASURED |
| Consumo do erp-novo em execução | NOT_MEASURED — não está implantado | — |
| Imagens erp-novo (tamanho/tempo de build) | ver relatório (build local antes/depois) | MEASURED local |

## Desperdícios encontrados

1. **Runner self-hosted residente na VPS** só para esperar job — e frágil: some
   com a reinstalação e o deploy trava em silêncio.
2. **Build sem cache e em série:** cada push recompilava gd/intl/soap/redis e
   reinstalava Composer + npm, para as duas imagens, uma depois da outra.
3. **A imagem `web` (nginx) dependia do estágio PHP inteiro** (`composer
   install`) só para copiar `public/`. Nada em `public/` vem do Composer (o
   build usa `--no-scripts`): era espera sem motivo.
4. **Logs sem teto:** sem `daemon.json`, cada contêiner grava json-file sem
   limite até encher o disco.
5. **Imagens antigas acumulam:** nada removia releases anteriores do erp-novo;
   o único jeito seria `prune -a`, proibido numa VPS compartilhada.
6. **Redis de homologação** recebia o `.env` inteiro da aplicação (`env_file:
   .env`, relativo à pasta) e tinha `appendonly yes` **sem volume** —
   persistência que morria a cada deploy.
7. **Defeito latente:** os wrappers não fixavam o nome do projeto Compose;
   homologação e produção derivavam o mesmo nome (`erp-novo`) da pasta, e o
   `--remove-orphans` de um derrubaria o outro.
8. **Pacotes `-dev` e `git` na imagem final** de runtime (headers de compilação
   usados só para construir as extensões).

## Plano

| ID | PRIORIDADE | ÁREA | PROBLEMA | EVIDÊNCIA | SOLUÇÃO | GANHO ESPERADO | RISCO | TESTE | ROLLBACK | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|
| V-1 | P0 | deploy | runner self-hosted inexistente; deploy parado | run `queued` desde 14/09; `systemctl` sem runner do Dubena | deploy no runner do GitHub via SSH; VPS só faz pull/up | zero processo residente; deploy volta a funcionar | baixo | deploy local ponta a ponta com registry local | reverter os 2 workflows | DONE |
| V-2 | P1 | build | build serial e sem cache | `build-release.sh` sem `--cache-from` | job por imagem em paralelo + cache no GHCR (`:buildcache`) | builds incrementais; app e web simultâneos | baixo | YAML validado; CI real no push | reverter o job | DONE |
| V-3 | P1 | docker | `web` esperava o Composer | `COPY --from=application /var/www/public` | `web` copia `public/` do contexto + saída do Vite | build da `web` independe do PHP | baixo | build local + diff do conteúdo servido | reverter o estágio | DONE |
| V-4 | P0 | logs | log sem teto | sem `daemon.json` | `x-logging` 3×10 MB em todos os serviços | teto de 30 MB por contêiner | nenhum | `compose config` | remover a âncora | DONE |
| V-5 | P1 | disco | imagens antigas acumulam | sem limpeza no deploy | limpeza ESCOPADA a `erpnovo-*`, retendo 3 releases + as em uso | disco estável por release | baixo | deploy local com 2 releases | `MANTER_RELEASES` maior | DONE |
| V-6 | P1 | segurança/persistência | Redis com `.env` da app e AOF sem volume | compose homolog | só `secrets` + volume `redis_data` | segredo exposto a menos; fila/sessão sobrevivem ao deploy | baixo | `compose config` | reverter compose | DONE |
| V-7 | P0 | deploy | nome de projeto compartilhado | wrappers sem `-p` | `--project-name` fixo por ambiente | elimina derrubada cruzada | baixo | `compose config` | reverter wrapper | DONE |
| V-8 | P2 | docker | `-dev`/`git` na imagem final | Dockerfile | purgar após compilar as extensões, mantendo as libs de runtime (padrão das imagens oficiais) | imagem menor, menos superfície | médio | build local + `ldd`/`php -m` no próprio build (regressão plantada detectada) | reverter o estágio | DONE |

## Ordem de execução

V-7 → V-6 → V-4 (compose, sem dependência) → V-3/V-8 (Dockerfile) → V-2 (CI) →
V-1/V-5 (deploy) → teste de deploy local ponta a ponta.

## Limpezas recomendadas que exigem autorização

| O quê | Por que é seguro | Ganho | Verificar antes | Comando | Recuperação |
|---|---|---|---|---|---|
| Rotação de log **global** no host (`/etc/docker/daemon.json` com `log-opts`) | só afeta contêineres recriados | teto de log para TODAS as apps | `ls /etc/docker/daemon.json` | criar o arquivo + `systemctl reload docker` (reinicia o daemon) | apagar o arquivo |
| Build cache do Docker no host (5,1 GB, de outras apps) | é cache | até 5 GB | `docker buildx du` | `docker builder prune --filter until=168h` | rebuild repopula |

Nenhuma das duas é do erp-novo; ambas mexem em outras aplicações da VPS
compartilhada — por isso ficam documentadas, não executadas.

## Métricas para comparação

Tamanho das imagens app/web; tempo de build (frio e com cache); RAM/CPU por
contêiner após o primeiro deploy (`docker stats --no-stream`); tempo do
deploy; disco do erp-novo após N releases.

## Bloqueios reais

1. **Secrets do deploy no GitHub** (`VPS_HOST`, `VPS_SSH_KEY`, `VPS_KNOWN_HOSTS`)
   — exigem acesso às configurações do repositório.
2. **Bootstrap do ambiente na VPS** (escrita em produção, exige ok do dono):
   env de homologação em `/opt/dubena-env/`, chave pública de deploy no
   `authorized_keys`, vhost Nginx + TLS de `gasemcasa.com`.
