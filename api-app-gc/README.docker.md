# Ambiente Docker — api-app-gc (API "Gás em Casa" · Fase 0)

Ambiente de **dev/staging** containerizado, parte do `PLANO_MODERNIZACAO_ECOSSISTEMA.md` (Fase 0).
Backend da API que o app mobile consome e que integra o ERP.

## Stack do container
- **PHP 7.4-fpm** — o `composer.lock` foi atualizado com deps que exigem PHP 7.2–7.4
  (psr/container, flysystem, type-resolver), então 7.1 não instala. 7.4 é o teto do
  Laravel 5.6 e satisfaz todas. (Difere do piloto monitoramento, que roda em 7.1.)
- **Nginx** (reverse proxy → php-fpm)
- **MySQL 5.7** com **dois bancos**: `sgcm_api` (principal) e `sgcm_logs` (logs),
  criados pelo `docker/mysql/init.sql`.
- **Redis 7**

## Portas (coexistem com o piloto monitoramento)
| Serviço | monitoramento | api-app-gc |
| --- | --- | --- |
| Web (HTTP) | 8080 | **8081** |
| MySQL | 3307 | **3308** |

## Como subir

```bash
cd api-app-gc
docker compose up -d --build
docker compose logs -f app          # acompanha composer install + key:generate
docker compose exec app php artisan migrate
```

Acessar: **http://localhost:8081** · API: `http://localhost:8081/api/...`

## O que foi feito nesta fase (Fase 0)
- ✅ Containerização do app Laravel 5.6 as-is (PHP 7.4 / 2× MySQL / Redis).
- ✅ `config/database.php` já usava `env()` sem credenciais hardcoded (nada a extrair).
- ✅ `.env.docker` com valores **fake** de dev; `.env` real fora do Git.
- ✅ `instruções api-siav.txt` (que contém segredos reais — APP_KEY/FCM/Maps) adicionado
  ao `.dockerignore` (não entra na imagem). **Pendente:** removê-lo do versionamento e
  **rotacionar** as chaves (Fase 1).

## ⚠️ Antes de EXPOR na VPS (blindagem — seção 3.2 do plano)
- [ ] `APP_DEBUG=false`.
- [ ] Acesso restrito (firewall/Basic Auth) + **HTTPS** (Certbot).
- [ ] Trocar senhas **fake** por segredos reais de staging (via env do Docker).
- [ ] Preencher `GMAPS_KEY`/`FCM_SERVER_KEY` com chaves de **teste**, nunca as de produção.
- [ ] Dados fake/anonimizados — nunca dump cru de produção (LGPD).

## Achados de segurança a corrigir (Fase 1) — confirmáveis neste ambiente
- `getToken` baseado em `sha1(APP_KEY)` + usuário fixo (`DEFAULT_USER_ID`).
- Middleware `access` que só loga (não autoriza) → risco de IDOR.
- Endpoints sensíveis dependentes apenas de `auth:api`.

## Próximos passos
1. Validar a API em staging com HTTPS e acesso restrito.
2. Replicar o padrão para `ctrl-web` (este com **PostgreSQL** — saída do Oracle).
