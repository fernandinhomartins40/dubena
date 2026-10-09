# Otimização de CPU da VPS — o que foi feito e o que falta

**Data:** 2026-09-14
**Commit:** `a3e9c55b` — `perf(vps): reduz consumo de CPU do scheduler e do sync de GPS`
**Auditoria completa:** [`PLANO_OTIMIZACAO_CPU_VPS.md`](PLANO_OTIMIZACAO_CPU_VPS.md)

Este documento é o resumo executivo: o que já está no código, o que ainda não
está, e o que não é código. Para o raciocínio por trás de cada item, o plano.

---

## Em uma frase

**O código está pronto e commitado; nada disso vale até a imagem ser
reconstruída** — e mesmo depois, a maior parte do load da VPS não é nossa.

---

## ✅ Implementado (no código, suíte verde)

| # | O que | Arquivo |
|---|---|---|
| T-1 | opcache ligado no CLI, com cache em disco | `erp-novo/docker/php/php.ini` |
| T-2 | `config:cache` + `route:cache` + `event:cache` no boot | `erp-novo/docker/php/entrypoint.sh` |
| T-2a | duas closures de rota viraram controller | `AuthController@me`, `WelcomeController` |
| T-2b | `IBPT_CSV_URL` movida para `config/` | `config/services.php` |
| T-4 | TTL de 5 min no lock do sync de GPS | `erp-novo/routes/console.php` |
| T-5 | N+1 e transação-por-posição eliminados | `MonitoraSyncService.php` |
| T-6 | timeout do Traccar: 20s → 5s | `TraccarDriver.php` |
| T-7 | expurgo do histórico de rastreamento | `MonitoraExpurgarPosicoes.php` |
| — | 2 guardiões novos, com regressão plantada | `RotasCacheaveisTest`, `MonitoraExpurgoPosicoesTest` |

### O ganho esperado, e de onde vem

A correção de maior efeito é a mais simples: **todo processo `artisan`
recompilava o Laravel inteiro do zero** — framework, `sped-nfe`,
`phpspreadsheet`, `firebase`, `dompdf` — porque `opcache.enable_cli` estava em
`0`. Como `everyThirtySeconds()` obriga o scheduler a acordar a cada segundo,
isso era constante.

Somado ao segundo: **não havia `config:cache` nem `route:cache` em lugar
nenhum**, então cada tick também reinterpretava 16 arquivos de config e
reparseava ~1055 linhas de rotas.

### Três defeitos silenciosos achados no caminho

Nenhum deles aparecia como erro. Ficam registrados porque a classe do problema
tende a se repetir:

1. **`route:cache` não funcionava.** Duas rotas com closure de ação (`/api/me` e
   `/`) faziam o comando falhar. Só descobri porque rodei o comando em vez de
   supor que funcionaria.
2. **`APP_ENV` na VPS é `homologation`, não `homolog`.** Eu havia escrito
   `homolog`. Com essa grafia o cache nunca teria ligado em homologação, e nada
   acusaria — tudo funciona, só que lento.
3. **`.env.example` não é o `.env`.** Eu diagnostiquei que o lock do
   `withoutOverlapping` ia para o Postgres. Era falso: o `.env` real da VPS já
   usa Redis para cache, fila e sessão. A tarefa T-3 não existia.

### Os guardiões foram provados, não só escritos

Regra da casa: guardião só vale se você plantar a regressão que ele deveria
pegar. Plantei três, todas detectadas:

| Regressão plantada | Detectada? |
|---|---|
| closure de ação nova numa rota | ✅ |
| expurgo ignorando a config da empresa | ✅ |
| expurgo sem filtro de empresa (apagaria histórico alheio) | ✅ |

### Testes

| | Antes | Depois |
|---|---|---|
| Suíte backend | 1826 | **1832 verdes, 0 falhas** |
| Typecheck da SPA | — | limpo |
| Pint (arquivos alterados) | — | passa |

---

## ⏸️ Não implementado de propósito

### T-4 — baixar a frequência do GPS de 30s para 60s

Apliquei **só o TTL do lock**, que é correção real e independente. Os 30s ficaram
como estão.

**Por quê:** o comentário no código registra que a frequência foi escolha
deliberada sua — o mapa ao vivo é a tela que fica aberta durante a operação.
Mudar isso altera algo que você vê, e com T-1, T-2 e T-6 aplicados é bem
possível que nem seja necessário.

**Decisão sua, e só depois de medir.**

### Expor a retenção na tela de configurações

O `monitora:expurgar-posicoes` lê `dados.monitora_retencao_dias` de
`empresa_configs`. Hoje isso se configura por SQL:

```sql
UPDATE empresa_configs
   SET dados = jsonb_set(COALESCE(dados,'{}')::jsonb, '{monitora_retencao_dias}', '180')
 WHERE empresa_id = 1;
```

Não criei tela porque ninguém pediu e o valor raramente muda. Quando um operador
precisar mexer sem ajuda, vale o campo na SPA.

### opcache estrito em homologação

O entrypoint só aplica `validate_timestamps=0` quando `APP_ENV=production`.
Homologação faz um `stat` por arquivo a cada acesso — trabalho inútil num
container de imagem imutável. Não mexi: muda o comportamento de homologação e
não é necessário para resolver a CPU.

---

## ⏳ O que falta — e não é código

Em ordem de impacto no load.

### 1. Subir o que foi commitado ⚠️

**`php.ini` e `entrypoint.sh` vivem dentro da imagem.** Editar o arquivo no
repositório não muda nada em produção até a imagem ser reconstruída e o container
recriado. Enquanto isso não acontecer, **o ganho é zero**.

### 2. Parar o crash-loop do `m2centerauto-backend`

Container reiniciando continuamente, queimando CPU sem entregar nada — está 100%
fora do ar de qualquer forma. O exit code mudou de 128 para **137** entre duas
medições: `128+9`, SIGKILL, ou seja **OOM kill** — falta de memória, não erro de
lógica.

Prompt pronto para o time deles em
[`PROMPT_PARA_IA_M2CENTERAUTO.md`](PROMPT_PARA_IA_M2CENTERAUTO.md). O alívio
imediato é um `docker stop`, que não causa indisponibilidade nova.

### 3. A lotação da VPS — o fator que mais pesa

**87 containers de 9 projetos em 4 vCPUs.** No instante da captura da Hostinger,
o maior consumidor **não era nosso**: um `vite build` do `ultrazend` a **91.8%**.

| Projeto | Situação |
|---|---|
| dubena (nosso) | 8 containers |
| ultrazend | build de CI rodando na VPS de produção |
| rapidinho | 6 containers, todos *unhealthy* |
| m2centerauto | 5 containers, backend em crash-loop |
| aprenderia, marcela, rebequi, fusehotel, digiurban | ~22 containers, quase todos *unhealthy* |

Opções, em ordem de eficácia: derrubar containers de projetos parados; tirar
builds de CI da máquina de produção; separar o Dubena numa VPS própria (o
cutover se aproxima e o alvo é SaaS multi-revenda).

**Nenhuma otimização nossa devolve a máquina sozinha enquanto isso continuar.**

### 4. Disco em 81% (161 GB de 200 GB)

É o que mantém o agente da Hostinger rodando `du` sem parar. O expurgo de
posições ajuda; falta `docker image prune -a` e conferir se os crons
`docker-image-prune` e `docker-builder-prune` estão de fato rodando.

---

## Como verificar o ganho

**O passo 1 é o que costuma ser pulado — e sem ele não dá para afirmar ganho
nenhum depois.**

```bash
# 1. ANTES de subir
docker exec erpnovo-app sh -c 'time php artisan pix:expirar'

# 2. Depois do deploy — o opcache ligou no CLI?
docker exec erpnovo-app php -i | grep -E 'opcache.enable_cli|opcache.file_cache'

# 3. A config foi cacheada?
docker exec erpnovo-app ls -la bootstrap/cache/config.php bootstrap/cache/routes-v7.php

# 4. Mesma medição de (1) — este é o número que prova o ganho
docker exec erpnovo-app sh -c 'time php artisan pix:expirar'

# 5. Nenhum tick empilhado (antes havia um de 99s)
ps -eo etimes,cmd | grep '[a]rtisan' | sort -nr | head
```

### Primeira execução do expurgo

```bash
docker exec erpnovo-app php artisan monitora:expurgar-posicoes --dry-run
```

Conta sem apagar. É assim que se descobre o tamanho do passivo antes de mexer
nele — apagar histórico é irreversível.

---

## Critério de pronto

Os critérios da Hostinger não se aplicam (não há scheduler duplicado para
eliminar, e os `du` são dela). Os nossos:

- [ ] `time php artisan pix:expirar` cai de forma mensurável — **medir o
      baseline antes**
- [ ] nenhum `schedule:run` com mais de 10s de vida
- [ ] load average abaixo de 8 de forma sustentada (4 vCPUs)
- [ ] `docker ps | grep -c Restarting` = 0
- [ ] disco abaixo de 70%
- [ ] limite de CPU removido pela Hostinger (automático, até 3h após normalizar)

⚠️ **Cuidado ao atribuir o ganho.** Se a VPS melhorar logo após o time do
m2centerauto parar o crash-loop, será fácil creditar ao nosso opcache. Por isso
a medição do passo 1 importa: ela isola o que é nosso do que é do vizinho.
