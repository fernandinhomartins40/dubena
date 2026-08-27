# AUDITORIA SaaS — Apêndice de infraestrutura operacional

**Data da releitura:** 25/08/2026  
**Status:** FECHADO — 100% do recorte explícito foi lido integralmente  
**Recorte:** 22 arquivos, 1.675 linhas físicas  
**Exclusões respeitadas:** `.env` real, locks, caches e assets gerados não foram abertos  
**Severidades:** 5 ALTAS, 3 MÉDIAS, 1 BAIXA

## Método e critérios

Cada arquivo listado no inventário foi lido da primeira à última linha, sem amostragem. A linha 18 de `resources/views/welcome.blade.php`, que contém 28.398 caracteres de CSS minificado em uma única linha física, também foi percorrida integralmente. As contagens são de linhas físicas retornadas pelo arquivo.

Aplicam-se os critérios canônicos do método mestre: **C1 — conceito ausente**; **C2 — classificação por texto**; **C3 — flag como proxy**; **C4 — convenção não declarada**; **C5 — conceitos misturados**; **C6 — escopo de tenant errado**. Riscos puramente operacionais, de disponibilidade ou de cadeia de suprimentos que não cabem honestamente em C1–C6 são identificados como **transversais fora de C1–C6**.

## Inventário integral verificável

| # | Arquivo | Linhas | Leitura |
|---:|---|---:|---|
| 1 | `erp-novo/.dockerignore` | 16 | integral |
| 2 | `erp-novo/.editorconfig` | 18 | integral |
| 3 | `erp-novo/.env.example` | 151 | integral |
| 4 | `erp-novo/.env.homolog.example` | 60 | integral |
| 5 | `erp-novo/.env.production.example` | 313 | integral |
| 6 | `erp-novo/.gitattributes` | 11 | integral |
| 7 | `erp-novo/.gitignore` | 31 | integral |
| 8 | `erp-novo/artisan` | 18 | integral |
| 9 | `erp-novo/composer.json` | 93 | integral |
| 10 | `erp-novo/docker-compose.homolog.yml` | 155 | integral |
| 11 | `erp-novo/docker-compose.producao.yml` | 190 | integral |
| 12 | `erp-novo/package.json` | 17 | integral |
| 13 | `erp-novo/phpunit.xml` | 55 | integral |
| 14 | `erp-novo/vite.config.js` | 18 | integral |
| 15 | `erp-novo/docker/nginx/default.conf` | 101 | integral |
| 16 | `erp-novo/docker/php/Dockerfile` | 54 | integral |
| 17 | `erp-novo/docker/php/entrypoint.sh` | 44 | integral |
| 18 | `erp-novo/docker/php/php.ini` | 15 | integral |
| 19 | `erp-novo/resources/css/app.css` | 11 | integral |
| 20 | `erp-novo/resources/views/welcome.blade.php` | 277 | integral |
| 21 | `erp-novo/public/.htaccess` | 25 | integral |
| 22 | `erp-novo/public/robots.txt` | 2 | integral |
|  | **Total** | **1.675** | **100%** |

## Achados

### INF-01 — ALTA — C1 — a imagem de produção não contém a aplicação

**Evidência:** `erp-novo/docker/php/Dockerfile:43-54` copia apenas o binário do Composer, `php.ini` e o entrypoint; não há `COPY` do código, `composer install` de produção nem build/cópia da SPA. Ao mesmo tempo, `erp-novo/docker-compose.producao.yml:32-50` declara que os serviços executam `erpnovo-app:${GIT_SHA}` e não monta o checkout, e `:38-41` monta somente `storage` e `bootstrap/cache`.

**Impacto SaaS:** a imagem construída por esse Dockerfile não possui `artisan`, `vendor/autoload.php`, `public/index.php` nem o bundle da SPA. App, filas, scheduler, Reverb e rollback por SHA não formam um artefato executável e reproduzível.

**Gate de correção:** uma construção limpa deve conter código, dependências PHP sem pacotes de desenvolvimento e bundle Vite; `php artisan about`, o healthcheck HTTP e a leitura do manifesto Vite devem funcionar sem bind-mount do repositório.

### INF-02 — ALTA — C1 — o volume público de produção não tem produtor

**Evidência:** `erp-novo/docker-compose.producao.yml:38-41` não monta `app_public` no serviço `app`; `:72-76` monta esse volume nomeado exclusivamente no Nginx, em `/var/www/public`. O Dockerfile também não declara ou povoa esse volume (`erp-novo/docker/php/Dockerfile:47-54`).

**Impacto SaaS:** o Nginx enxerga um volume novo vazio, sem `index.php`, landing ou SPA. Mesmo que a imagem da aplicação fosse completada externamente, o frontend e o front controller não seriam compartilhados com o container web.

**Gate de correção:** após `docker compose up` a partir de volumes vazios, o container web deve possuir e servir `/var/www/public/index.php`, `/var/www/public/app/index.html` e os assets hashados exatamente da mesma release SHA.

### INF-03 — ALTA — C4 — o arquivo externo de produção não alimenta a interpolação do banco

**Evidência:** `erp-novo/docker-compose.producao.yml:36-37` aplica o arquivo `${ENV_PRODUCAO:-/opt/dubena-env/erp-novo-producao.env}` somente como `env_file` dos serviços baseados em `x-app-comum`. O serviço `db`, em `:148-154`, usa `${DB_DATABASE}`, `${DB_OWNER_USERNAME}`, `${DB_USERNAME}` e `${DB_OWNER_PASSWORD:?...}` na interpolação do próprio Compose, mas não recebe esse `env_file`. A invocação documentada no próprio arquivo, `:28-30`, fornece apenas `GIT_SHA`. Em validação local somente-leitura, `GIT_SHA=audit docker compose -f docker-compose.producao.yml config` terminou com erro por ausência de `DB_OWNER_PASSWORD`.

**Impacto SaaS:** guardar os segredos no caminho prescrito não basta para renderizar o Compose; o deploy para antes de criar o banco, ou depende de uma exportação paralela e não declarada dos mesmos segredos no shell.

**Gate de correção:** `docker compose --env-file <arquivo-externo> -f docker-compose.producao.yml config` deve renderizar sem segredo duplicado no checkout ou no shell, e o serviço `db` deve receber somente as variáveis necessárias.

### INF-04 — ALTA — C4 — o entrypoint gera APP_KEY por container em vez de falhar fechado

**Evidência:** `erp-novo/docker/php/entrypoint.sh:21-38` trata produção e homologação como casos sem instalação de dependências, mas em `:30-37` verifica exclusivamente a existência de `APP_KEY=base64:` no arquivo `.env`. Se o arquivo não existir — `env_file` injeta ambiente, não cria `/var/www/.env` — o script acrescenta `APP_KEY=` e executa `key:generate`, inclusive quando a variável do processo já veio correta. Todos os serviços compartilham esse entrypoint (`erp-novo/docker-compose.producao.yml:32-43`).

**Impacto SaaS:** configuração ausente pode virar chave aleatória e diferente entre app, worker, scheduler e Reverb, ou rotacionar em recriação. Cookies, payloads criptografados e dados protegidos pela chave deixam de ser interoperáveis; o deploy inseguro não é bloqueado.

**Gate de correção:** em produção/homologação, validar a variável de processo e abortar quando ausente ou inválida; nunca criar ou alterar `.env`. Dois containers da mesma release devem reportar a mesma impressão digital não reversível da chave configurada.

### INF-05 — ALTA — C5 — senha Redis do cliente não configura senha no servidor

**Evidência:** `erp-novo/.env.production.example:128-132` apresenta `REDIS_PASSWORD` como proteção importante. Porém `erp-novo/docker-compose.producao.yml:166-174` inicia `redis-server --appendonly yes`, sem `--requirepass`, ACL ou arquivo de configuração, e nem sequer injeta o arquivo de ambiente no serviço Redis. Homologação também inicia Redis sem autenticação (`erp-novo/docker-compose.homolog.yml:140-145`).

**Impacto SaaS:** se o operador preencher a senha conforme o template, Laravel tenta autenticar contra servidor sem senha e sessão, cache e filas podem parar; se a deixar vazia, qualquer processo comprometido na rede Docker pode ler sessões, cache e jobs de todos os tenants.

**Gate de correção:** configurar ACL/segredo no servidor e no cliente pela mesma fonte, testar `PING` autenticado e negar `PING` anônimo; healthchecks e workers devem falhar explicitamente quando as credenciais divergirem.

### INF-06 — MÉDIA — C4 — homologação sobe Reverb sem contrato mínimo de configuração

**Evidência:** `erp-novo/docker-compose.homolog.yml:97-121` sempre cria o serviço `reverb`, mas `erp-novo/.env.homolog.example:51-60` termina sem `BROADCAST_CONNECTION` ou qualquer `REVERB_*`. O template genérico usa `BROADCAST_CONNECTION=log` (`erp-novo/.env.example:54-57`) e deixa as credenciais vazias em `:124-130`.

**Impacto SaaS:** a homologação pode manter um container reiniciando ou validar apenas polling, sem provar o canal WebSocket que transmite posição, pedido e PIX antes da produção.

**Gate de correção:** perfil explicitamente desabilitado sem container ou perfil Reverb completo; teste de homologação deve publicar e receber um evento autenticado no tenant correto.

### INF-07 — MÉDIA — C6 — o runner padrão não exercita o banco/RLS de produção

**Evidência:** `erp-novo/phpunit.xml:27-34` força SQLite em memória; `:35-47` força as três origens legadas a conexões inválidas. O source inclui apenas `app` (`:21-25`), sem limiar de cobertura configurado. Isso converge com **T-01.1**: os testes dependentes de catálogo PostgreSQL/RLS são pulados fora do PostgreSQL.

**Impacto SaaS:** o comando padrão pode ficar verde sem provar `FORCE ROW LEVEL SECURITY`, a role `NOBYPASSRLS`, políticas por tenant, SQL específico de PostgreSQL ou transformação de origem real.

**Gate de correção:** manter a suíte rápida SQLite, mas adicionar gate obrigatório PostgreSQL com role runtime real, contexto ausente negado, dois tenants adversariais e zero skip dos guardiões RLS.

### INF-08 — MÉDIA — transversal fora de C1–C6 — bases mutáveis impedem rebuild e rollback determinísticos

**Evidência:** `erp-novo/docker/php/Dockerfile:7` usa `php:8.3-fpm` e `:43` usa `composer:2`, ambos sem patch ou digest. Os Composes usam `nginx:1.25-alpine`, `postgres:15-alpine` e `redis:7-alpine` (`erp-novo/docker-compose.producao.yml:65-67`, `:144-145`, `:166-167`; equivalentes em homologação `:34-35`, `:123-124`, `:140-141`). O apt instala o estado corrente dos repositórios (`erp-novo/docker/php/Dockerfile:10-41`).

**Impacto SaaS:** reconstruir a mesma SHA em outra data pode produzir sistema operacional, PHP auxiliar, Composer, Nginx, Postgres ou Redis diferentes. Rollback nominal por SHA não recompõe necessariamente o artefato previamente validado.

**Gate de correção:** fixar versões/digests, gerar SBOM e promover a mesma imagem imutável entre ambientes; rollback deve selecionar digest existente, nunca reconstruir a SHA.

### INF-09 — BAIXA — transversal fora de C1–C6 — OPcache de produção revalida arquivos a cada requisição

**Evidência:** `erp-novo/docker/php/php.ini:8-11` ativa OPcache, mas mantém `opcache.validate_timestamps=1` e `opcache.revalidate_freq=0`. O Compose de produção declara código imutável dentro da imagem (`erp-novo/docker-compose.producao.yml:13-15`).

**Impacto SaaS:** cada requisição paga verificação de timestamp sem benefício numa imagem imutável, reduzindo capacidade por instância e tornando o comportamento incoerente com o modelo de release.

**Gate de correção:** perfil de produção com `validate_timestamps=0`, acompanhado de restart explícito dos processos na promoção da imagem; desenvolvimento/homologação podem manter revalidação.

## Consolidação

| Severidade | Quantidade | Achados |
|---|---:|---|
| ALTA | 5 | INF-01 a INF-05 |
| MÉDIA | 3 | INF-06 a INF-08 |
| BAIXA | 1 | INF-09 |
| **Total** | **9** |  |

Por critério canônico, há dois achados C1, dois C4, um C5, um C6 e três transversais fora de C1–C6. Não foram encontrados achados honestamente classificáveis como C2 ou C3 neste recorte.

## Itens não verificáveis neste recorte

- O workflow de build/deploy não integra os 22 arquivos pedidos. Portanto não foi possível verificar se alguma automação externa copia código para uma imagem incompleta, exporta `DB_OWNER_PASSWORD`, popula `app_public`, executa migrations ou exige os healthchecks. Isso não elimina as contradições autossuficientes do Dockerfile/Compose; apenas impede afirmar o comportamento da automação externa.
- O Nginx do host, que termina TLS e faz proxy, ficou fora do recorte. HSTS, CSP, demais cabeçalhos, limites de requisição e a preservação/remoção do prefixo `/novo` não puderam ser confirmados ponta a ponta.
- Não foram lidos `.env` reais; portanto não se afirmou quais segredos ou drivers estão efetivamente ativos. A auditoria registra o comportamento dos templates e dos entrypoints quando seguidos literalmente.
- Locks foram excluídos por solicitação. Assim, não se avaliou a resolução efetiva das dependências PHP/JS nem vulnerabilidades das versões travadas.

## Fechamento

O anel operacional não está pronto para promoção autossuficiente. A ordem mínima de correção por dependência é: **(1)** produzir uma imagem completa e imutável; **(2)** definir como o mesmo `public/` chega ao Nginx; **(3)** unificar a fonte de variáveis usada pela interpolação e pelos containers; **(4)** tornar APP_KEY e Redis fail-closed; **(5)** provar Reverb e PostgreSQL/RLS em gates reais; **(6)** endurecer reprodutibilidade e desempenho. O recorte documental está fechado em 22/22 arquivos e 1.675/1.675 linhas; a prontidão operacional, não.
