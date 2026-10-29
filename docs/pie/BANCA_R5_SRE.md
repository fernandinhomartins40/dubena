# Banca PIE V2 — R5 · SRE / VPS, infraestrutura e desempenho

**Revisor:** R5 · **Data:** 2026-10-28/29 (relógio da VPS em UTC: 2026-10-29 02:47–03:05)
**Commit auditado:** `48b29f72` (é também o commit em execução na homologação — imagem `erpnovo-app:48b29f72…`)
**Ambiente medido:** VPS `dubena-vps` (homologação, compartilhada com ~70 contêineres de outras aplicações), somente leitura.

---

## 0. Método e desvios que preciso declarar

- Acesso `ssh dubena-vps` funcionou (chave, `BatchMode=yes`). Comandos de leitura: `uptime`, `free`, `df`,
  `vmstat`, `/proc/pressure`, `docker ps/stats/inspect/logs/system df`, `journalctl`, `ss -tlnp`, `sshd -T`,
  `SELECT`s no Postgres como `erp` (superusuário) e `EXPLAIN (ANALYZE)` **só de SELECT**, dentro de
  `BEGIN TRANSACTION READ ONLY` + `SET LOCAL ROLE erp_app` + `set_config` do tenant (método do CLAUDE.md).
- Variáveis de ambiente foram filtradas por nome; nenhum segredo nem dado pessoal foi impresso (só contagens).
- **Desvios (transparência):**
  1. Copiei por `scp` um arquivo de consulta para `/tmp/r5_q1.sql` na VPS e o apaguei logo em seguida;
     depois disso passei a mandar o SQL por stdin. Nenhum dado da aplicação foi tocado.
  2. Rodei um contêiner efêmero `docker run --rm --entrypoint sh <imagem erpnovo-app> -c "ls/md5sum"`
     (sem volume, sem rede de app) para comparar o `bootstrap/cache` da imagem com o do volume.
  3. Um `find / -xdev …` meu (procurando dumps de backup) estourou o timeout local; parei o lado local, mas o
     processo remoto (só leitura) continuava rodando quando conferi. Tentei encerrá-lo com `pkill` e a
     ação foi **negada** pelo classificador — corretamente, porque o briefing proíbe `kill`. Ele termina sozinho.
  4. Os quatro `EXPLAIN ANALYZE` de contagem fizeram seq scan de verdade (até ~8 s cada) num banco ocioso.
- Sem Docker local: nada de build/medição de imagem fora da VPS.

---

## 1. Medições principais (VPS, 2026-10-29 ~02:50 UTC)

| Recurso | Valor medido | Comando |
|---|---|---|
| Uptime / carga | 12 d 12 h; load 0,93 / 0,88 / 0,96 em **4 vCPU** | `uptime`, `nproc` |
| RAM | 15.988 MB no total; 5.660 usados; **9.243 disponíveis**; buff/cache 8.090 | `free -m` |
| Swap | 2 GB, 300 MB em uso; si/so ≈ 0 | `free`, `vmstat 1 3` |
| Disco `/` | 194 G, 115 G usados (**60 %**), 79 G livres | `df -h` |
| Pressão de I/O | `some avg300=7,23 %`, `full avg300=6,35 %` (a VPS inteira) | `/proc/pressure/io` |
| Pressão de memória | 0,00 | `/proc/pressure/memory` |
| OOM no kernel (14 d) | 18 kills, **todos `CONSTRAINT_MEMCG`** (ffmpeg de outra aplicação, que tem limite); nenhum global | `journalctl -k` |
| Docker na VPS | 510 imagens / 94 GB (38 GB recuperáveis); 78 contêineres; volumes 11,7 GB | `docker system df` |
| Imagens do erp-novo | 3× app (1,05 GB) + 3× web (113 MB): retenção de 3 releases funcionando | `docker image ls` |

**Consumo por contêiner do erp-novo** (`docker stats --no-stream`):

| Contêiner | CPU | RAM | Restart policy | Limite mem/CPU | Healthcheck | Reinícios |
|---|---|---|---|---|---|---|
| erpnovo-db | 6,8 % | 302 MiB | unless-stopped | **nenhum** | sim (healthy) | 0 |
| erpnovo-scheduler | 1,4 % | 142 MiB | unless-stopped | **nenhum** | **não** | 0 |
| erpnovo-redis | 1,4 % | 11 MiB | unless-stopped | **nenhum** | sim | 0 |
| erpnovo-app (php-fpm) | 0,0 % | 99 MiB | unless-stopped | **nenhum** | **não** | 1 |
| erpnovo-reverb | 0,0 % | 49 MiB | unless-stopped | **nenhum** | **não** | 1 |
| erpnovo-queue | 0,2 % | 47 MiB | unless-stopped | **nenhum** | **não** | **300** (recicla de hora em hora) |
| erpnovo-web (nginx) | 0,0 % | 10 MiB | unless-stopped | **nenhum** | **não** | 2 |

Total do erp-novo: ~660 MiB de RAM. Todos com log `json-file` `max-size=10m`, `max-file=3` (V-4 do relatório de 09/10 **confirmado** na VPS).

**Banco** (`erpnovo-db`, PostgreSQL 15.14):

| Item | Valor |
|---|---|
| Tamanho | 3.678 MB (volume 4,6 GB); `public` 231 tabelas / 2,6 GB; **`legado` 127 tabelas / 1,06 GB** |
| `shared_buffers` | **128 MB** (default) · `effective_cache_size` 4 GB · `work_mem` 4 MB · `maintenance_work_mem` 64 MB |
| `max_connections` | 100; conexões vistas: só a minha (php-fpm não usa conexão persistente) |
| Cache hit acumulado | **93,89 %** (blks_hit 1,05 bi / blks_read 68 mi) |
| Arquivos temporários acumulados | **26.040 arquivos / 690 GB** (sem `stats_reset`; não variou nos 10 min observados → provavelmente da carga do ETL, HIPÓTESE) |
| Extensões | só `plpgsql` e `pg_trgm` — **sem `pg_stat_statements`** |
| Log de consulta lenta | `log_min_duration_statement = -1`, `track_io_timing = off` |
| Timeouts | `statement_timeout = 0`, `idle_in_transaction_session_timeout = 0` |
| Roles | `erp` superusuário/bypassrls (owner) · `erp_app` NOSUPERUSER NOBYPASSRLS ✓ (runtime conecta como `erp_app` ✓) |
| RLS | 189 policies; 189 tabelas `public` com RLS + FORCE; 42 sem RLS (globais: `users`, `sessions`, `jobs`, `cache`, `planos`, `tenant_*`… — análise de mérito é do R1) |
| Wraparound | `age(datfrozenxid)` = 740 mil (ok) |
| Índices não únicos nunca usados | 202 / 84 MB (banco quase ocioso: baixa confiança) |
| Tráfego real | 10 min de amostra: ~8 transações/s, **zero** seq scan ou escrita em tabela >1.000 linhas fora das minhas consultas → a homologação está ociosa |

**Agendador** (`docker logs --since 24h erpnovo-scheduler`): 2.880 × `monitora:sync-positions`,
1.440 × `pix:expirar`, 144 × `logistica:gerar-missoes`, 1× cada diário. **0 FAIL em 24 h.** Duração típica de
270–770 ms, no máximo 2 s (o `vendas:diaria` levou 3 s). Sem sobreposição: os ciclos são muito menores que o
intervalo de 30 s. Custo de CPU do scheduler: 1,4 % de um núcleo, quase todo boot de processo PHP
(opcache CLI + `file_cache` ativos).

**HTTP** (de dentro da VPS, `curl` em 127.0.0.1:3120, aquecido): `/up` 16–28 ms; `/api/health` 33–54 ms
(`{"status":"ok","checks":{"db":true}}`); primeira chamada a frio 0,98 s. Log de acesso do nginx do contêiner
(formato `rt=`/`urt=`): 1.235 linhas desde 14/10, **nenhum 5xx**; 465 `POST …/entregador/posicao` com média
de 38 ms; o restante é robô varrendo `/wp`, `/old` e similares, que chega ao PHP (0,4–1,4 s cada).

TLS: Let's Encrypt válido até **07/01/2027**, `certbot.timer` ativo; HSTS, CSP, XFO e nosniff presentes.
Portas do erp-novo só em `127.0.0.1` (3120/3121); **Postgres e Redis não estão expostos** ✓.

---

## 2. Achados

### R5-01 — O backup "obrigatório" do deploy de produção copia o banco da HOMOLOGAÇÃO
- **Categoria:** backup / perda de dados · **Severidade:** ALTA · **Estado:** CONFIRMADO (estático, com nomes reais dos contêineres na VPS)
- **Arquivo:linha:** `deploy/remote-deploy.sh:79-82` chama `bash deploy/backup/backup.sh` **sem** passar nenhuma variável;
  `deploy/backup/backup.sh:35-39` assume por padrão `CONTAINER_DB=erpnovo-db`, `CONTAINER_APP=erpnovo-app` e
  `VOLUME_STORAGE=erp-novo_app_storage`. O compose de produção cria `erpnovo-prod-db` e `erpnovo-prod-app`
  (`erp-novo/docker-compose.producao.yml:58,149`), e os volumes reais seguem o projeto `erpnovo-homolog_*` ou `erpnovo-producao_*`.
  O mesmo vale para `restore.sh:29-30`.
- **Rota/serviço:** job `deploy-producao` do `.github/workflows/erp-novo.yml:359+` → `remote-deploy.sh producao`.
- **Reprodução segura:** `grep -n CONTAINER_ deploy/backup/backup.sh deploy/remote-deploy.sh`; `docker ps` na VPS mostra
  `erpnovo-db` (homologação) rodando. Com a homologação de pé, as pré-condições do `backup.sh`
  (`docker inspect erpnovo-db`) passam.
- **Esperado × observado:** esperado: dump de `erpnovo-prod-db` e do storage de produção (certificados A1) antes da
  migration. Observado: o script faria o dump de `erpnovo-db` (homologação) e o tar do storage de `erpnovo-app`
  (homologação), com sucesso e checksum OK. O backup sai "verde" e não protege nada de produção.
- **Impacto:** a única rede de proteção contra migration destrutiva em produção (o `rollback.sh:4` diz "nesse caso, usar
  restore") restauraria o banco errado. O fallback por volume também está errado (`erp-novo_app_storage` não existe).
- **Correção proposta:** em `remote-deploy.sh`, exportar `CONTAINER_DB`, `CONTAINER_APP` e `VOLUME_STORAGE` por ambiente
  (como já faz com `CONTAINER_APP` para o health); no `backup.sh`, eliminar o default e falhar se não vierem; gravar no
  nome do arquivo o contêiner de origem.
- **Teste de aceite:** teste de shell (bats ou script em CI) que roda `remote-deploy.sh producao` com `docker` simulado e
  verifica que o `pg_dump` foi chamado em `erpnovo-prod-db`; o `backup.sh` sem `CONTAINER_DB` tem de sair com código ≠ 0.

### R5-02 — Não existe backup nenhum do banco da homologação, que guarda os dados reais migrados; o restore nunca foi ensaiado na VPS
- **Categoria:** backup / disponibilidade · **Severidade:** ALTA · **Estado:** CONFIRMADO (medição)
- **Evidência:** `crontab -l` do root tem só `rapidinho` e `makucho-studio`; `/etc/cron.d` não tem nada do erp-novo;
  `/opt/backups-erpnovo` **não existe**, `/var/log/erpnovo-backup.log` **não existe**, não há `/etc/logrotate.d/erpnovo-backup`.
  `deploy/backup/instalar-cron.sh:13` aponta `DIR_DEPLOY=/opt/dubena-deploy`, que **não existe** na VPS (o código fica em
  `/opt/dubena/erp-novo/releases/<sha>`), então o cron instalado com o default quebraria em silêncio. O deploy de
  homologação não faz backup (`remote-deploy.sh:79`, só em `producao`), mas roda `migrate --force` (`:88`) sobre os 3,6 GB de dados reais.
- **Impacto:** um `docker volume rm`, um disco corrompido ou uma migration ruim na homologação perdem a carga do ETL
  (400 mil pedidos, 443 mil títulos, 241 mil NF-e) e o tempo de refazê-la. Como nenhum backup foi produzido aqui,
  `restore.sh` também nunca rodou contra um dump real desta VPS. `BACKUP_REMOTO` é só um aviso (`backup.sh:139-146`):
  mesmo instalado, a cópia ficaria no mesmo disco.
- **Correção:** instalar o cron com `DIR_DEPLOY` apontando para `/opt/dubena/erp-novo/releases/homolog-current` e com as
  variáveis de contêiner/volume corretas (ver R5-01); definir `BACKUP_REMOTO`; ensaiar `restore.sh` em modo teste e
  registrar data e duração.
- **Teste de aceite:** `ls -l /opt/backups-erpnovo` com um dump de menos de 24 h e checksum válido; um registro de restore de
  teste com contagens batendo (`pedidos`, `financeiros`).

### R5-03 — Corrida no `bootstrap/cache` compartilhado: o worker recicla a cada hora, reescreve o cache de rotas e derruba processos vizinhos
- **Categoria:** disponibilidade / deploy · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (logs)
- **Arquivo:linha:** `erp-novo/docker/php/entrypoint.sh:101-104` roda `config:cache`, `route:cache` e `event:cache` **em toda
  partida de contêiner**. `docker-compose.homolog.yml:30-32` monta o **mesmo** volume `app_bootstrap_cache` em app,
  queue, scheduler e reverb. `queue:work --max-time=3600` (`:70`) faz o contêiner sair e reiniciar de hora em hora
  (`RestartCount=300`; 72 partidas em 72 h). O `route:cache` apaga e reescreve `routes-v7.php` (905 KB) sem escrita atômica.
- **Evidência:** `laravel.log` tem **45** `require(/var/www/bootstrap/cache/routes-v7.php): Failed to open stream` (trace em
  `Console/Kernel` → processos do scheduler) e **1** `ParseError: Unclosed '(' … at /var/www/bootstrap/cache/routes-v7.php:5831`
  (16/10 10:52), ou seja, outro processo leu o arquivo pela metade. Há dois `Scheduled command … monitora:sync-positions failed`
  no mesmo segundo de erros de rota (14/10 19:23:30 e 22/10 11:58:00). Os timestamps do `config.php` e do `routes-v7.php` no volume
  (02:05:30–31) coincidem com a última partida do `erpnovo-queue` (02:05:30).
- **Impacto:** falha intermitente de comando agendado (PIX, missões, GPS) e, em menor escala, de requisição HTTP. No deploy, os
  quatro contêineres regeneram o cache ao mesmo tempo. Risco latente também: `packages.php` e `services.php` no volume são de
  **09/10** (a imagem atual é de 14/10). Hoje o md5 é igual, mas um volume nomeado esconde o `bootstrap/cache` da imagem, e uma
  release que traga pacote novo com provider auto-descoberto continuaria com a lista velha (FORTE INDÍCIO; não reproduzido).
- **Correção:** gerar `config/route/event:cache` **no build da imagem**, sem segredos (ou só no contêiner `app`, com escrita
  atômica `tmp`+`rename`), e tirar `bootstrap/cache` de volume nomeado, já que ele é imutável por release. Alternativa mínima: só o
  `app` roda os `*:cache`, e queue/scheduler/reverb não.
- **Teste de aceite:** 72 h sem `routes-v7.php` em `laravel.log` com o queue reciclando; `docker inspect` sem volume em `/var/www/bootstrap/cache`.

### R5-04 — Listagens paginadas pagam uma contagem completa sob RLS: 3,4–8 s em `/pedidos`
- **Categoria:** desempenho / banco · **Severidade:** MÉDIA · **Estado:** CONFIRMADO para a consulta (EXPLAIN ANALYZE como `erp_app`); FORTE INDÍCIO para a latência do endpoint (homologação ociosa, sem tráfego real em `/api/pedidos` no log)
- **Arquivo:linha:** `app/Http/Controllers/Api/Admin/PedidoController.php:50-56` (`->paginate()` gera `select count(*) from pedidos
  where empresa_id = ?`); o mesmo padrão aparece em 13 controllers (`ClienteController:38-50`, `FinanceiroController`…). A policy
  `app_tenant_can_read(tenant_account_id, empresa_id)` é `SECURITY DEFINER` com `SET search_path`, não é inlinável e roda um `EXISTS`
  por linha.
- **Medido (tenant 1 / empresa 2, que tem todas as linhas):**

  | Consulta | Como `erp_app` (RLS) | Como superusuário |
  |---|---|---|
  | `count(*)` de `pedidos where empresa_id=2` (count do paginate) | **3.468 ms** (a frio: 7.970 ms) | — |
  | página 1 de `pedidos` (index scan backward) | 0,7 ms | — |
  | `count(*)` de `financeiroparcelas` | **7.758 ms** | — |
  | `count(*)` de `clientes` com busca `ilike` em 4 colunas | 409 ms | **27 ms** (~15×) |
  | `count(*)` de `clientes` sem busca | 386 ms | — |

- **Contexto:** o CLAUDE.md já registra a armadilha ("count(*) dos 400 mil pedidos levou 3,2 s"). O que é novo: as listagens
  principais **continuam** usando `paginate()`, então o problema não foi mitigado nesses pontos. Com `shared_buffers` de 128 MB
  (R5-05), a primeira consulta lê do disco e passa de 8 s.
- **Correção:** `simplePaginate()` (ou cursor) nas listagens de tabelas grandes, com o total vindo de `TenantCache` ou estimativa;
  em segunda frente, avaliar uma policy inlinável (função `LANGUAGE sql STABLE` sem `SECURITY DEFINER`, com a checagem de grant
  materializada num array de sessão).
- **Teste de aceite:** `EXPLAIN ANALYZE` como `erp_app` do `GET /api/admin/pedidos` sem `count(*)` sobre a tabela inteira; p95 abaixo de 300 ms na homologação.

### R5-05 — Postgres no default de fábrica e sem nenhuma visibilidade de consulta lenta
- **Categoria:** desempenho / observabilidade · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (`pg_settings`, `pg_extension`)
- **Evidência:** `shared_buffers=128MB` para um banco de 3,6 GB num host com 9 GB disponíveis; cache hit acumulado de 93,89 %;
  `work_mem=4MB` com 690 GB / 26.040 arquivos temporários acumulados (atribuição ao ETL é HIPÓTESE); `jit=on` (o JIT aparece nos
  planos de contagem, pagando 10–18 ms de compilação em consultas OLTP); `random_page_cost=4`; sem `pg_stat_statements`;
  `log_min_duration_statement=-1`; `statement_timeout=0` e `idle_in_transaction_session_timeout=0`. O compose
  (`docker-compose.homolog.yml:96-121`, `producao.yml:147-168`) não passa nenhum `-c`.
- **Impacto:** não há como responder "qual a consulta mais cara" na produção; uma transação esquecida aberta segura locks e
  impede vacuum indefinidamente; contagens sob RLS pioram a frio.
- **Correção (medir antes/depois):** `command: postgres -c shared_buffers=1GB -c effective_cache_size=3GB -c work_mem=16MB
  -c jit=off -c random_page_cost=1.1 -c shared_preload_libraries=pg_stat_statements -c log_min_duration_statement=500
  -c idle_in_transaction_session_timeout=60s`, dimensionado para a VPS compartilhada e acompanhado de `mem_limit` (R5-06).
- **Teste de aceite:** `SELECT * FROM pg_stat_statements ORDER BY total_exec_time DESC LIMIT 10` disponível; o count de pedidos a
  frio cai (medir com o mesmo comando deste relatório).

### R5-06 — Nenhum limite de memória ou CPU nos contêineres do erp-novo, numa VPS com ~75 contêineres
- **Categoria:** isolamento / capacidade · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (`docker inspect`: `Memory=0`, `NanoCpus=0` nos 7)
- **Arquivo:linha:** `docker-compose.homolog.yml` e `docker-compose.producao.yml` inteiros (não há `mem_limit`, `cpus` nem
  `deploy.resources`). `docker/php/php.ini:2` usa `memory_limit=1024M` e `zz-fpm-pool.conf` usa `pm.max_children=16`, o que dá
  teto teórico de 16 GB só no php-fpm, igual à RAM do host.
- **Evidência complementar:** outra aplicação da VPS tem limite (os 18 OOM kills em 14 dias foram `CONSTRAINT_MEMCG`, ffmpeg). Sem
  limite no erp-novo, um relatório guloso ou um vazamento vira OOM **global**, e o kernel pode escolher o Postgres de qualquer
  aplicação da VPS.
- **Correção:** `mem_limit`/`cpus` por serviço, por exemplo db 2 GB, app 1,5 GB, queue/scheduler/reverb 512 MB e web 128 MB, a
  ajustar com `docker stats` de produção; `memory_limit` do FPM em 256–512 M, com exceção explícita para as rotas de exportação.
- **Teste de aceite:** `docker inspect -f '{{.HostConfig.Memory}}'` maior que 0 em todos; carga de teste sem OOM.

### R5-07 — Sem healthcheck em app/web/queue/scheduler/reverb na homologação, e em queue/scheduler/reverb na produção
- **Categoria:** disponibilidade · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (`docker inspect` sem `Config.Healthcheck`; `producao.yml:94-145`)
- **Impacto:** um scheduler travado (laço preso, lock que não solta) ou um Reverb que não aceita conexão continuam "Up". O
  `restart: unless-stopped` só cobre o processo que morre. PIX que não expira ou mapa congelado só seriam percebidos pelo usuário.
- **Correção:** healthcheck do scheduler por *heartbeat* (cada `schedule:run` grava um timestamp, e o check falha se ele tiver mais de
  3 min); queue via `queue:monitor` ou heartbeat; reverb com TCP em 8080; replicar na homologação os healthchecks app/web da produção.
- **Teste de aceite:** com `docker pause` do processo num ambiente de teste, o contêiner vira `unhealthy` em menos de 5 min.

### R5-08 — Falhas recorrentes sem alerta: 135 jobs falhados e ~28 erros por dia que ninguém viu
- **Categoria:** observabilidade · **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- **Evidência:** `failed_jobs` = 135 (133 × `NotificarEstoqueBaixoJob` entre 10/10 e 28/10, mais 2 × `GeocodificarClienteJob`), todos
  com `TenantAccessDeniedException: Empresa ativa aprovada sem grupo legado válido` (`app/Domain/Tenant/TenantEnvelopeRuntime.php:73-75`).
  O `laravel.log` tem 405 ocorrências dessa mensagem, num ritmo constante de 28 linhas por dia. O log é um arquivo único sem rotação
  (`config/logging.php:57`, `LOG_STACK` default `single`; hoje 3,7 MB). O scheduler joga a saída de cada comando em `/dev/null`
  (padrão do Laravel; só o exit code aparece). O `RestartCount=300` do queue é esperado, por causa do `--max-time`, e por isso não
  serve como sinal de crash.
- **Impacto:** o alerta de estoque baixo, que é função de negócio, não funciona há 18 dias e nada avisou. O mérito funcional é do R3/R4;
  aqui o achado é a ausência de alerta. Não há monitor externo, alerta de disco ou e-mail de `failed_jobs`.
- **Correção:** `LOG_STACK=daily` (com `LOG_DAILY_DAYS`), alerta sobre o crescimento de `failed_jobs` (comando agendado que notifica o
  dono da plataforma), uptime check externo em `/api/health`, alerta de disco acima de 80 %.
- **Teste de aceite:** um job falhando gera notificação em menos de 15 min; `storage/logs` com arquivos diários.

### R5-09 — Root com login por senha, sem firewall de host nem fail2ban, e o CI entra assim
- **Categoria:** segurança de infraestrutura · **Severidade:** ALTA · **Estado:** CONFIRMADO (configuração)
- **Evidência:** `sshd -T` mostra `permitrootlogin yes`, `passwordauthentication yes`, `maxauthtries 6`; `ufw status` = inactive;
  `fail2ban` inactive. `deploy/ci-ssh-setup.sh` configura `User root`, `PreferredAuthentications password`, `PubkeyAuthentication no` e
  usa `sshpass`. Portas públicas na VPS: 22, 80, 443, mais 1936, 3001, 3050, 3060 e 3095 de **outras** aplicações (o erp-novo só escuta em
  127.0.0.1 ✓). Só 2 senhas erradas em 24 h, então pode haver filtro do provedor (não verificado).
- **Impacto:** quem obtiver o segredo do GitHub (ou acertar a senha) vira root numa VPS compartilhada que guardará os dados fiscais e
  financeiros de produção e os certificados A1. Isso afeta todas as aplicações da VPS.
- **Correção:** chave dedicada de deploy (ed25519) com `command=`/usuário não-root no grupo `docker` (ou restrito ao script de deploy),
  `PasswordAuthentication no`, `PermitRootLogin prohibit-password`, fail2ban. Antes do cutover, decidir com o dono porque afeta as outras aplicações.
- **Teste de aceite:** `sshd -T | grep -E 'passwordauthentication no|permitrootlogin (no|prohibit-password)'`; o workflow faz deploy com chave.

### R5-10 — O ETL pela API não roda na homologação, e nada técnico impede um push no meio do cutover
- **Categoria:** deploy / jobs · **Severidade:** MÉDIA · **Estado:** FORTE INDÍCIO (código + compose; não executado)
- **Evidência:** `ExecutarMigracaoJob` usa `onConnection('redis-longo')->onQueue('migracao')` (linha 49 do job). O compose de homologação
  não tem o serviço `queue-longo` (que só existe em `producao.yml:111-119`), e o `erpnovo-queue` só consome a fila `default`. Uma migração
  disparada pela tela na homologação ficaria parada para sempre. Além disso, todo push na `main` que toque `erp-novo/**` ou `deploy/**`
  dispara `deploy-homolog` (`erp-novo.yml:23-37`, `:293-301`), que recria os workers (`remote-deploy.sh:89`). A regra "não dar push durante
  a janela" existe só no CLAUDE.md; o deploy não tem trava, por exemplo um arquivo-sentinela ou o registro de migração `em_execucao`.
  A produção é manual (`workflow_dispatch`) ✓.
- **Correção:** adicionar `queue-longo` à homologação (ou documentar que lá o ETL só roda via CLI); no `remote-deploy.sh`, abortar se houver
  `migracoes` em execução ou o sentinela `/opt/dubena/CUTOVER_EM_ANDAMENTO`.
- **Teste de aceite:** deploy com o sentinela presente sai com código ≠ 0 sem recriar contêineres.

### R5-11 — Schema `legado` (1,06 GB, sem índices) dentro do banco de runtime
- **Categoria:** capacidade · **Severidade:** BAIXA · **Estado:** CONFIRMADO (tamanho) / HIPÓTESE (impacto)
- **Evidência:** 127 tabelas, 6,67 mi de linhas, nenhum `idx_scan`; 3,46 bilhões de tuplas lidas por seq scan durante a carga (último
  autovacuum em 10/10). Hoje ninguém lê esse schema. Ele infla backup e restore em cerca de 30 % e disputa `shared_buffers` se for consultado.
- **Correção:** depois da validação do cutover, `pg_dump -n legado` para arquivo e `DROP SCHEMA` numa migration **separada** (não destrutiva
  no mesmo deploy de feature, como manda o CLAUDE.md).

### R5-12 — Retenção do backup: storage e checksums não acompanham os dumps semanais e mensais
- **Categoria:** backup · **Severidade:** BAIXA · **Estado:** CONFIRMADO (leitura de `deploy/backup/backup.sh:160-182`)
- Só o `.dump` é renomeado para `_semanal`/`_mensal`. O `_storage.tar.gz` é podado em 7 e o `.sha256`, por contagem global. O dump mensal
  sobrevive sem o storage (certificados A1) do mesmo dia e, com o tempo, sem o próprio checksum.
- **Correção:** renomear e podar o conjunto (dump + storage + sha) pelo mesmo prefixo.

### Observações sem achado (conferidas e OK)
- Agendador: durações de 0,3–3 s, 0 FAIL em 24 h, `withoutOverlapping(5)` no sync de GPS, sem sobreposição. Custo baixo.
- Opcache ativo em FPM e CLI (`opcache.enable_cli=On`, `file_cache`), JIT tracing; a homologação usa o perfil mutável, por desenho.
- Logs do Docker com teto (3×10 MB) e retenção de 3 releases de imagem: os itens V-4, V-5 e V-6 do `VPS_OPTIMIZATION_REPORT.md` foram reconfirmados.
- Redis: AOF ligado com volume, `noeviction`, 1,5 MB, fila vazia. Postgres/Redis sem porta publicada. TLS válido com renovação automática.
- O deploy roda a migration **antes** de subir os workers novos (`remote-deploy.sh:84-89`), confere o digest e faz health antes de promover
  o symlink ✓. Rollback por manifesto com digest ✓. Só não desfaz migration (declarado em `rollback.sh:4`), o que torna R5-01 e R5-02 críticos para o rollback real.
- Health HTTP: `/up`, `/novo/up`, `/novo/app/` e `/api/health` respondem 200.

---

## 3. Inspecionado / testado / não testado

- **Inspecionado:** compose de homologação e produção, `entrypoint.sh`, `php.ini`/opcache/pool FPM, `remote-deploy.sh`, `rollback.sh`,
  `backup.sh`/`restore.sh`/`instalar-cron.sh`, `ci-ssh-setup.sh`, workflow `erp-novo.yml`, `routes/console.php`, `config/{queue,logging,cache}`,
  `PedidoController`/`ClienteController`, `TenantEnvelopeRuntime`, policy `app_tenant_can_read` no banco.
- **Medido na VPS:** recursos, contêineres, limites, healthchecks, reinícios, logs (scheduler, queue, web, db, laravel.log), parâmetros e
  estatísticas do Postgres, EXPLAIN ANALYZE sob RLS, Redis, portas, sshd, cron, backups, TLS, health HTTP, OOM do kernel.
- **Não testado:** carga concorrente (a homologação está ociosa, sem baseline de p95 real dos endpoints); restore de backup (não há dump);
  build de imagem e tempo de deploy; ambiente de produção (não existe na VPS: nenhum contêiner `erpnovo-prod-*`); filtro de rede do provedor;
  consumo do Reverb com clientes conectados (0 linhas de log em 24 h).
- **Limitações:** as estatísticas do Postgres acumulam desde antes do reinício de 16/10, incluindo o ETL; os números de temp files, hit ratio
  e índices não usados misturam carga e uso. Sem `pg_stat_statements`, não há ranking de consultas mais caras.

## 4. Parecer da área (infra/SRE)

**REPROVADO para produção/cutover.** A infraestrutura de homologação está estável: carga baixa, 0 falha de agendamento, logs com teto,
TLS ok e banco não exposto. Há porém dois bloqueadores confirmados de **proteção contra perda de dados**: o backup pré-deploy de produção
mira o banco errado (R5-01), e não há backup nem restore ensaiado do banco que guarda a carga real (R5-02). Isso se soma a root por senha
na VPS compartilhada (R5-09). Os itens R5-03 a R5-08 e R5-10 são ressalvas que devem entrar antes ou junto do go-live: corrida do cache,
contagem sob RLS, Postgres sem tuning nem observabilidade, ausência de limites, healthchecks e alertas.
