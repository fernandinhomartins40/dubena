# Auditoria e plano — consumo de CPU na VPS

**Data:** 2026-09-14
**Gatilho:** a Hostinger limitou a CPU do VPS 953800 a 20%, apontando processos
da nossa aplicação. Load average observado: **293** para 4 vCPUs.

---

## Resumo em cinco linhas

O diagnóstico da Hostinger identificou os sintomas certos, mas a **causa que ele
atribui está errada** — e seguir a receita dele não resolveria nada. Não há
scheduler duplicado, não há cron concorrente, e `withoutOverlapping()` já está em
**todas** as 11 tarefas agendadas desde sempre.

A causa real é outra, e tem duas camadas: a VPS hospeda **87 containers de 9
projetos diferentes** em 4 vCPUs, e o nosso scheduler roda **sem cache de opcode
e sem cache de configuração**, o que torna cada tick 5 a 10x mais caro do que
precisaria ser.

---

## Parte 1 — Onde o diagnóstico da Hostinger erra

Cada afirmação foi verificada na VPS por SSH (leitura apenas). Isso importa
porque as ações recomendadas por ela são, na maioria, trabalho sobre um problema
que não existe.

| Afirmação da Hostinger | Verificado | Realidade |
|---|---|---|
| "duas ou mais execuções simultâneas do Laravel Scheduler" | ❌ falso | Existe **um** container `erpnovo-scheduler`, no ar há 2 dias (`175391s` de uptime do processo `schedule:work`) |
| "mais de um cron executando `php artisan schedule:run`" | ❌ falso | `crontab -l` e `grep -rl 'schedule:run' /etc/cron* /var/spool/cron` não retornam **nada** relativo ao scheduler |
| "scheduler executado por cron, Supervisor, systemd ou Docker ao mesmo tempo" | ❌ falso | Só Docker. Nenhum serviço systemd, nenhum supervisord |
| "ausência de bloqueio contra sobreposição" | ❌ falso | `withoutOverlapping()` está nas **11** tarefas em [routes/console.php](erp-novo/routes/console.php) |
| "múltiplos workers executando a mesma sincronização" | ❌ falso | Um `monitora:sync-positions` por vez; o lock funciona |
| "os processos `du` são varreduras nossas" | ❌ falso | `du -xhd1 /`, `du -xhd2 /var/lib/docker` são do **agente de telemetria da própria Hostinger** (`/.hstgr-1789405962.usage-telemetry.py`), rodando há 2 horas |
| "`git clone` pode ser deploy em loop" | ❌ falso | É o **self-hosted runner do GitHub Actions** se auto-atualizando (`tar -xzf /opt/actions-runner/_work/_update/runner1.tar.gz`) |
| "tarefas agendadas demorando mais que o intervalo" | ✅ **verdadeiro** | Um `schedule:run` foi flagrado vivo há **99 segundos** — deveria levar 1–2s |

**O que o `top` dela capturou** foram vários `schedule:run` simultâneos. Isso é
real, mas não é duplicação de configuração: é **um** scheduler cujos ticks estão
demorando tanto que se empilham. Tratar o sintoma como "desligue o cron extra"
não tem efeito, porque não existe cron extra para desligar.

**Onde ela acerta:** o load está insustentável, os ticks estão lentos demais, e a
frequência de `monitora:sync-positions` merece revisão. As três coisas são
verdade — só não pelos motivos apontados.

---

## Parte 2 — A causa real

### C-1 (P0) — `opcache.enable_cli = 0`: todo tick recompila o Laravel inteiro

[docker/php/php.ini:9](erp-novo/docker/php/php.ini#L9) desliga o opcache para
CLI. Todo processo `artisan` — e o scheduler é 100% CLI — **recompila do zero**
todo o PHP que carrega, a cada execução, sem reaproveitar nada.

O que se recompila a cada tick, medido no `composer.json`:

- `laravel/framework` completo
- `nfephp-org/sped-nfe` + `sped-da` (emissão fiscal, pesadíssimo)
- `phpoffice/phpspreadsheet` e `openspout/openspout`
- `kreait/firebase-php`
- `barryvdh/laravel-dompdf`

Isso acontece **a cada segundo**, porque `everyThirtySeconds()` obriga o
`schedule:work` a acordar em intervalo de 1s para não perder a janela dos 30s.

Com `opcache.enable_cli=1` e um `opcache.file_cache` persistente, o mesmo
bootstrap reaproveita bytecode já compilado. É a correção de maior efeito e a de
menor risco do plano inteiro.

### C-2 (P0) — nenhum `config:cache` / `route:cache` em lugar nenhum

[docker/php/entrypoint.sh:79](erp-novo/docker/php/entrypoint.sh#L79) roda
`php artisan config:clear` e **nunca** roda `config:cache`. Um `grep` por
`config:cache|route:cache|event:cache|optimize` nos dois workflows de deploy e no
entrypoint não retorna nada.

Consequência, a cada tick do scheduler:

- 16 arquivos de `config/*.php` lidos e interpretados do disco;
- **1055 linhas** de `routes/api.php` parseadas e registradas;
- 98 linhas de `routes/channels.php`, mais os service providers descobertos.

Nada disso é necessário para rodar `pix:expirar`, e nada disso muda entre um tick
e o seguinte.

⚠️ **`config:cache` exige que nenhum `env()` seja chamado fora de `config/`.** Isso
precisa ser verificado antes de ligar, senão variáveis passam a voltar `null` em
produção — silenciosamente. É a única tarefa deste plano com risco real, e por
isso ela tem um passo de verificação próprio (T-2).

### C-3 (P1) — `everyThirtySeconds` custa o dobro e entrega pouco

[routes/console.php:23](erp-novo/routes/console.php#L23) sincroniza GPS a cada
30s. O comentário no código justifica: o mapa ao vivo é a tela que o dono deixa
aberta. A intenção é legítima, mas o custo não está sendo pago pelo benefício:

1. cada ciclo faz **2 chamadas HTTP** ao Traccar (`/api/devices` e
   `/api/positions`), com timeout de 20s cada — e o comentário do próprio código
   diz que o provedor devolve a última posição "tendo ela mudado ou não";
2. `MonitoraSyncService::sincronizar()` varre **todas** as empresas ativas em
   laço, uma query por empresa, mesmo que apenas 1 das 12 opere de fato;
3. `jaGravada()` faz **uma query por veículo por ciclo** (`ultimaPosicao()->first()`)
   — N+1 clássico, hoje disfarçado porque a frota é pequena;
4. `registrarPosicao()` abre **uma transação por posição**.

Com o Traccar lento ou fora do ar, os 20s de timeout × 2 chamadas × N empresas
ultrapassam folgadamente os 30s da janela — e o tick de 99s que flagramos é
exatamente esse cenário. O `withoutOverlapping` impede a duplicação da *tarefa*,
mas não impede que os **processos `schedule:run`** que a disparariam se acumulem.

### C-4 — ~~`CACHE_STORE=database`~~ **DIAGNÓSTICO ERRADO, corrigido**

**O que eu afirmei:** que o lock do `withoutOverlapping()` ia para o Postgres,
porque `config/cache.php` tem default `database` e o `.env.example` repete
`CACHE_STORE=database`.

**O que é verdade:** li o `.env.example`, não o `.env` que roda. Conferido em
`/opt/dubena-env/erp-novo-homolog.env` na VPS:

```
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

**Já estava tudo em Redis.** Não havia carga de lock no Postgres e a tarefa T-3
não existe — foi removida do plano.

Fica registrado em vez de apagado porque o erro tem uma lição reaproveitável:
`.env.example` é documentação, não configuração. O que vale é o arquivo do
ambiente, e ele não está no repositório — tem que ser lido no servidor.

### C-5 (P2) — `monitora_posicoes` cresce sem expurgo

[database/migrations/0012_01_01_000000_create_monitora_tables.php:31](erp-novo/database/migrations/0012_01_01_000000_create_monitora_tables.php#L31)
cria a tabela com índice correto, mas **não existe nenhuma rotina de retenção** —
`grep` por `purge|prune|limpar|retencao|expurgo` em `routes/console.php` e nos
commands não retorna nada para posições.

O próprio comentário do código registra que em produção deram "27.891 linhas num
dia para 3.749 posições reais". A deduplicação já corrige o pior, mas o que
sobra cresce para sempre — e o disco está em **81% (161 GB de 200 GB)**, que é
justamente o que faz o agente da Hostinger rodar `du` sem parar.

### C-6 (P0, fora da aplicação) — 87 containers de 9 projetos em 4 vCPUs

Este é o fator que, sozinho, explica a maior parte do load 293. A VPS hospeda:

| Projeto | Situação |
|---|---|
| **dubena** (`erpnovo-*` + `ctrl-web-*`) | 8 containers — o nosso |
| `ultrazend` | `vite build` consumindo **91.8% de CPU** no momento da captura |
| `aprenderia` | 4 containers, 3 deles *unhealthy* |
| `rapidinho` | 6 containers, todos *unhealthy* |
| `marcela` | 5 containers |
| `m2centerauto` | 5 containers, `backend` em **`Restarting (128)`** — crash-loop |
| `rebequi`, `fusehotel`, `digiurban` | 10 containers, quase todos *unhealthy* |

Mais: 2 runners do GitHub Actions como serviço, PM2, um PostgreSQL 14 nativo
*além* dos containerizados, BIND, OpenDKIM, fail2ban.

**No instante da captura da Hostinger, o maior consumidor não era nosso** — era o
`vite build` do ultrazend a 91.8%. Um container em crash-loop (`m2centerauto-backend`,
reiniciando há 35 min) queima CPU continuamente sem entregar nada.

Nenhuma otimização no erp-novo devolve a máquina sozinha enquanto isso continuar.
Essa é a parte honesta do diagnóstico: **o nosso código contribui, mas não é o
dono do problema.**

---

## Parte 3 — O que foi implementado

Status em 2026-09-14. Tudo abaixo está **no código, com a suíte verde** — falta
apenas subir (a mudança só passa a valer quando a imagem for reconstruída e o
container recriado, porque `php.ini` e `entrypoint.sh` vivem dentro da imagem).

### ✅ T-1 — opcache ligado no CLI
`erp-novo/docker/php/php.ini`

```ini
opcache.enable_cli = 1
opcache.file_cache = /tmp/opcache
opcache.file_cache_only = 0
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
```

O `file_cache` é o que faz a economia existir de fato: a memória do opcache morre
com o processo, e todo processo do scheduler é efêmero. Sem cache em **disco**,
ligar `enable_cli` sozinho quase não ajudaria. O diretório é criado no entrypoint
com `chmod 1777` (scheduler roda como root, php-fpm como www-data).

`memory_consumption` subiu para 128M porque com os 64M padrão a tabela satura
com `sped-nfe` + `phpspreadsheet` e passa a despejar classes — paga-se o custo do
cache e recompila-se assim mesmo.

**Observação para depois (não alterei):** o entrypoint só aplica o perfil
`opcache-production.ini` (`validate_timestamps=0`) quando `APP_ENV=production`.
Homologação fica com `validate_timestamps=1`, ou seja, um `stat` por arquivo a
cada acesso para conferir se mudou. É trabalho inútil num container cuja imagem
é imutável por release — mas mexer nisso muda o comportamento de homologação, e
não é necessário para resolver a CPU. Deixo apontado como ganho posterior, se
alguém quiser.

### ✅ T-2 — cache de config, rotas e eventos
`erp-novo/docker/php/entrypoint.sh`

```sh
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "homologation" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
else
    php artisan config:clear
fi
```

Esta foi a tarefa que mais rendeu descobertas, todas de defeito silencioso:

**a) `route:cache` não funcionava.** Havia duas rotas com **closure de ação** —
`/api/me` e `/` — e closure de ação faz `route:cache` falhar. Ambas viraram
controller: `AuthController@me` e o novo `WelcomeController@index`. Só depois
disso `php artisan route:cache` passou a completar.

**b) `APP_ENV` na VPS é `homologation`, não `homolog`.** Eu havia escrito
`homolog` na condição. Com essa grafia o cache **nunca teria ligado em
homologação** e nada acusaria — tudo funcionaria, só que lento. Conferido no
`.env` real do servidor, e alinhado à condição que o próprio arquivo já usava
duas vezes acima.

**c) o pré-requisito do `env()`.** A varredura achou 6 ocorrências e apenas
**uma** era problema real: `IBPT_CSV_URL` em `IbptAtualizar.php`. Movida para
`config/services.php` (`services.ibpt.csv_url`). As outras cinco são comentários
avisando para não usar `env()`, mais migration e seeder, que `config:cache` não
afeta. Com `config:cache` ligado, `env()` em runtime devolve vazio — e o gate do
próprio comando ("só roda se a URL estiver configurada") faria a falha parecer
desligamento proposital.

**d) guardião novo:** `tests/Feature/RotasCacheaveisTest.php` reprova qualquer
closure de ação nova. Sem ele, uma closure adicionada hoje só apareceria no
próximo deploy — com o container não subindo, longe da mudança que a causou.
Ignora as três rotas do próprio framework (`up`, `storage/{path}`), que o Laravel
sabe serializar, e tem piso de 400 rotas para nunca passar varrendo nada.

### ❌ T-3 — Redis: **não era necessária**
Ver C-4 acima. A VPS já usa Redis para cache, fila e sessão. Diagnóstico meu
baseado no `.env.example` em vez do `.env` real.

### ⏸️ T-4 — frequência do GPS: **só a metade segura**
`erp-novo/routes/console.php`

Apliquei **apenas o TTL do lock**, que é correção real e independente:

```php
Schedule::command('monitora:sync-positions')->everyThirtySeconds()->withoutOverlapping(5);
```

`withoutOverlapping()` sem argumento usa **24 horas**. Se o processo morre sem
soltar o lock — OOM, container recriado, deploy no meio do ciclo — a sincronização
fica travada o dia inteiro e o mapa congela, sem erro em lugar nenhum.

**Os 30s foram mantidos.** O comentário no código registra que a frequência foi
escolha deliberada sua (o mapa ao vivo), e mudar isso altera algo que você vê.
Com T-1, T-2 e T-6 aplicados, é bem possível que não precise mudar — a
recomendação é medir antes de decidir.

### ✅ T-5 — N+1 e transação por posição
`erp-novo/app/Domain/Monitora/MonitoraSyncService.php`

- `jaGravada()` recebia o veículo e fazia `ultimaPosicao()->first()` **dentro do
  laço** — uma consulta por veículo, por ciclo. Agora recebe o instante já
  carregado, e as últimas posições da frota vêm em **uma** query (`whereIn` +
  `pluck`);
- `registrarPosicao()` abria **uma transação por posição**. O laço inteiro passou
  a rodar numa transação só — o ciclo grava tudo ou nada, em vez de deixar metade
  das posições de um instante;
- ciclo sai cedo quando o provedor não devolve nada.

`MonitoraTest`, `MonitoraApiTest` e os testes de sync continuam verdes (28/28),
incluindo `sync sgcasa ingere posicoes via gate`.

### ✅ T-6 — timeout do Traccar
`erp-novo/app/Domain/Monitora/Drivers/TraccarDriver.php`

`Http::timeout(20)` → `Http::connectTimeout(3)->timeout(5)` nas duas chamadas.

São 2 chamadas por ciclo: com 20s, um provedor lento fazia um único ciclo passar
de 40s, ultrapassar a própria janela e empilhar processos do scheduler — que é
exatamente o `schedule:run` de 99s que flagramos. Posição de GPS que demora 20s
já não serve ao mapa ao vivo; desistir rápido e esperar o próximo ciclo entrega
mais.

### ✅ T-7 — retenção do histórico de rastreamento
Comando novo: `erp-novo/app/Console/Commands/MonitoraExpurgarPosicoes.php`
Agendado para 03:30, **depois** do backup das 03:15 — o dia apagado já foi salvo
pelo menos uma vez.

```bash
php artisan monitora:expurgar-posicoes --dry-run      # conta, não apaga
php artisan monitora:expurgar-posicoes --empresa=1
```

A janela é **configuração da empresa** (`dados.monitora_retencao_dias` em
`empresa_configs`), nunca constante no código: quanto tempo se guarda
rastreamento é decisão de negócio. Padrão 90 dias; `0` significa guardar para
sempre, e é escolha explícita possível — apagar histórico é irreversível, o
default silencioso nunca deve ser a opção destrutiva.

Apaga em lotes de 1000 (DELETE único de centenas de milhares de linhas segura
lock e incha o WAL).

**Como configurar a retenção de uma empresa** (hoje só pelo banco — expor o
campo na tela de configurações fica para quando alguém precisar mudá-lo sem
ajuda; não inventei tela que ninguém pediu):

```sql
UPDATE empresa_configs
   SET dados = jsonb_set(COALESCE(dados,'{}')::jsonb, '{monitora_retencao_dias}', '180')
 WHERE empresa_id = 1;
```

**Antes de rodar pela primeira vez em produção, use `--dry-run`.** Ele conta sem
apagar, e é assim que se descobre o tamanho do passivo antes de mexer nele.

**5 testes, e provei que detectam** — plantei duas regressões e as duas foram
pegas:

| Regressão plantada | Resultado |
|---|---|
| ignorar a config e usar sempre a constante | ⨯ 2 testes falharam |
| esquecer o `where('empresa_id')` (apagaria histórico alheio) | ⨯ 1 teste falhou |

### ⏳ T-8, T-9 — operação, não código
Limpeza de Docker e a lotação da VPS seguem pendentes. O prompt para o time do
m2centerauto está em
[`PROMPT_PARA_IA_M2CENTERAUTO.md`](PROMPT_PARA_IA_M2CENTERAUTO.md).

---

## Como subir e verificar

O `php.ini` e o `entrypoint.sh` estão **dentro da imagem** — editar o arquivo no
repositório não muda nada em produção até a imagem ser reconstruída.

```bash
# 1. Baseline ANTES de subir (para haver com o que comparar)
docker exec erpnovo-app sh -c 'time php artisan pix:expirar'

# 2. Após o deploy — o opcache está de fato ligado no CLI?
docker exec erpnovo-app php -i | grep -E 'opcache.enable_cli|opcache.file_cache'

# 3. A config foi cacheada?
docker exec erpnovo-app ls -la bootstrap/cache/config.php bootstrap/cache/routes-v7.php

# 4. Mesma medição de (1) — é o número que prova o ganho
docker exec erpnovo-app sh -c 'time php artisan pix:expirar'

# 5. Nenhum tick empilhado
ps -eo etimes,cmd | grep '[a]rtisan' | sort -nr | head
```

O passo 1 é o que costuma ser pulado, e sem ele não há como afirmar ganho nenhum
depois.

## O que falta

O código está feito. O que resta não é programação.

| # | O que | Onde | Derruba load? | Depende de |
|---|---|---|---|---|
| 1 | Parar o crash-loop do `m2centerauto-backend` | VPS | **muito** | time do m2centerauto (prompt pronto) |
| 2 | **Subir o erp-novo** com as mudanças | deploy | **muito** | rebuild da imagem |
| 3 | Derrubar containers *unhealthy* de projetos parados | VPS | **muito** | sua decisão |
| 4 | Tirar builds de CI da VPS de produção | VPS | **muito** | sua decisão |
| 5 | Limpeza de Docker (T-8) | VPS | disco | — |
| 6 | Decidir a frequência do GPS (T-4) | código | médio | **sua decisão** — medir antes |
| 7 | Separar o Dubena numa VPS própria | decisão | **muito** | sua decisão |

**Medir entre cada passo.** A tentação é fazer tudo de uma vez e não saber o que
funcionou — e aqui isso importa mais que o normal, porque a maior parte do load
vem de fora da nossa aplicação: se a máquina melhorar só porque o vizinho parou,
concluir que foi o nosso opcache seria enganar a si mesmo.

---

## Critério de pronto

A Hostinger propõe critérios que não se aplicam (não há scheduler duplicado para
eliminar, e os `du` são dela). Os nossos:

- [ ] `time php artisan pix:expirar` no container cai de forma mensurável (medir
      o baseline **antes** de T-1);
- [ ] nenhum `schedule:run` com `etimes` acima de 10s
      (`ps -eo etimes,cmd | grep '[a]rtisan'`);
- [ ] load average abaixo de 8 de forma sustentada (4 vCPUs);
- [ ] `docker ps | grep -c Restarting` = 0;
- [ ] disco abaixo de 70%;
- [ ] limite de CPU removido pela Hostinger (automático, até 3h após normalizar).

---

## Verificações feitas (para quem revisar depois)

Tudo por SSH em `gasemcasa.com`, **somente leitura**, em 2026-09-14:

```
uptime                    → load average: 293.82, 293.62, 279.84
nproc                     → 4
docker ps -q | wc -l      → 87
crontab -l                → nenhum schedule:run
grep -rl schedule:run /etc/cron* /var/spool/cron → vazio
ps -eo etimes,cmd | grep artisan → 1 schedule:work (175391s), ticks de 39s e 99s
ps aux --sort=-%cpu       → topo: vite build do ultrazend (91.8%)
```

E no repositório:

```
routes/console.php        → 11 tarefas, todas com withoutOverlapping()
docker/php/php.ini:9      → opcache.enable_cli = 0
docker/php/entrypoint.sh  → config:clear, nunca config:cache
config/cache.php:18       → default 'database'
grep config:cache nos workflows de deploy → vazio
grep expurgo/retencao para posicoes → vazio
```
