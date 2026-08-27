# F0-05.01/02 — imagem completa e `public/` da mesma release

**Estado:** CONCLUÍDO  
**Data:** 2026-08-26 09:42 (America/Sao_Paulo)

## Implementação

- Dockerfile multi-stage compila a SPA pelo `frontend/package-lock.json`.
- Dependências PHP são instaladas pelo `composer.lock`, sem pacotes de dev.
- O target `runtime` recebe código, `vendor/` e bundle antes da promoção.
- O target `web` copia `public/` diretamente do mesmo stage `application`.
- Produção não usa mais `app_public`, volume vazio sem produtor.
- `.dockerignore` exclui storage operacional, dumps SQL, dependências locais e
  bundle anterior. O contexto limpo medido caiu para 8,12 MB.

## Gate executado

- frontend construído via `npm ci` + `tsc -b` + Vite;
- runtime: `artisan`, `vendor/autoload.php`, `public/index.php`, SPA e assets
  presentes; `vendor/phpunit` ausente; `php artisan about` aprovado;
- web: `public/index.php`, SPA e assets presentes; `nginx -t` aprovado com o
  upstream de teste resolvido;
- manifesto SHA-256 de todos os arquivos de `public/` nas duas imagens:
  `16ef0fbb637d091f14eac01c55b0064cda7a9ec469fb156ad3fb9f6ac806577a`.

Imagens locais de prova: `erpnovo-app:f0-05-test` e
`erpnovo-web:f0-05-test`. Não houve push nem deploy.

Rollback: voltar Dockerfile/Compose reabre INF-01/02 e não deve ser promovido.

Próximo: INF-03/04/05 — fonte única de ambiente, APP_KEY e Redis fail-closed.
