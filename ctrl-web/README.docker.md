# Ambiente Docker — ctrl-web (ERP · Fase 0)

Ambiente de **dev/staging** containerizado, parte do `PLANO_MODERNIZACAO_ECOSSISTEMA.md`.
**Primeiro passo concreto da saída do Oracle**: o ERP sobe sobre **PostgreSQL**.

## Stack do container
- **PHP 7.4-fpm** (constraint do composer.json: `>=7.4`)
- **Nginx** (reverse proxy → php-fpm)
- **PostgreSQL 15** (destino da migração; substitui o Oracle)
- **Redis 7**

> **Oracle (oci8) NÃO é instalado.** O pacote `yajra/laravel-oci8` permanece no vendor
> (o provider registrado em `config/app.php` não quebra o boot), mas a extensão é
> ignorada no composer (`--ignore-platform-req=ext-oci8`) e a conexão default é `pgsql`.

## Portas (coexistem com os outros sistemas)
| Serviço | monitoramento | api-app-gc | **ctrl-web** |
| --- | --- | --- | --- |
| Web (HTTP) | 8080 | 8081 | **8082** |
| Banco | 3307 (MySQL) | 3308 (MySQL) | **5433 (Postgres)** |

## Como subir
```bash
cd ctrl-web
docker compose up -d --build
docker compose logs -f app          # composer install do ERP é o maior (demora)
```
Acessar: **http://localhost:8082** (home e /login respondem HTTP 200).

## O que foi feito (Fase 0)
- ✅ Container PHP 7.4 + **PostgreSQL** + Redis; ERP **sobe e responde** (HTTP 200).
- ✅ **PostgreSQL conectado** (validado via PDO).
- ✅ Conexão `pgsql` adicionada ao `config/database.php`; default = `pgsql`.
- ✅ **Extração de segredos**: removida a senha `toor` hardcoded da conexão `oracle`;
  vars do Oracle separadas (`*_ORACLE`) para não colidir com Postgres.
- ✅ `config/session.php`: `'driver'` voltou a respeitar `env('SESSION_DRIVER')`
  (estava fixo em `'database'`, o que exigia a tabela `sessions` inexistente).
- ✅ `composer-setup.php`, `vendor_fork`, `Pentaho` no `.dockerignore`.

## ⚠️ Limitação conhecida — `migrate` NÃO funciona ainda (é o trabalho da Fase 3)
`php artisan migrate` falha em PostgreSQL por **dois motivos**, ambos esperados:
1. **~19 migrations com SQL Oracle-específico** (`DB::statement`, sequences, `TO_DATE`,
   `VARCHAR2`, `NUMBER(...)`) — precisam ser traduzidas para PostgreSQL.
2. **`doctrine/dbal` 2.4** (antigo) usa `pg_attrdef.adsrc`, coluna **removida no PG 12+**
   → erro `column pg_attrdef.adsrc does not exist`. Exige atualizar o dbal na Fase 3/4.

Isso **não bloqueia a Fase 0**: o objetivo aqui era app no ar + Postgres conectado, o que
está validado. A criação/tradução do schema é a **Fase 3 (migração Oracle→PostgreSQL)**,
protegida pela rede de testes da Fase 2.

## ⚠️ Antes de EXPOR na VPS (blindagem — seção 3.2 do plano)
- [ ] `APP_DEBUG=false`.
- [ ] Acesso restrito + **HTTPS** (Certbot).
- [ ] Trocar senha **fake** do Postgres por segredo real de staging.
- [ ] Dados fake/anonimizados — nunca dump cru de produção (LGPD).
- [ ] Rotacionar segredos do ERP que já vazaram no Git.

## Próximos passos
- **Fase 1 (segurança)**: webhook PIX, cripto base64 do certificado NF-e, SQLi.
- **Fase 2 (testes)** dos fluxos fiscais/financeiros.
- **Fase 3**: traduzir migrations/SQL Oracle → PostgreSQL (com a rede de testes).
