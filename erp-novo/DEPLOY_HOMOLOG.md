# Deploy de HOMOLOGAÇÃO — erp-novo (backend reescrito)

Ambiente **isolado** do backend reescrito na VPS, rodando **em paralelo** ao
ctrl-web (legado) **sem tocá-lo**. É o "Mundo B" do plano de reescrita
(`PLANO_REESCRITA_BACKEND.md`, princípio #1: *a app nova não toca o banco de
produção até o cutover*).

## O que sobe

| Item            | Legado (ctrl-web)        | erp-novo (homolog)            |
|-----------------|--------------------------|-------------------------------|
| Containers      | `ctrl-web-*`             | `erpnovo-*`                   |
| Rede docker     | `ctrl`                   | `erpnovo`                     |
| Banco           | Postgres de produção     | Postgres **próprio** isolado  |
| Porta interna   | `127.0.0.1:3110`         | `127.0.0.1:3120`              |
| Domínio         | `erp.gasemcasa.com.br`   | `homolog-erp.gasemcasa.com.br`|
| Workflow        | `deploy-ctrl-web.yml`    | `deploy-erp-novo-homolog.yml` |

Sem colisão de containers, rede, volumes, porta nem banco — os dois coexistem.

## Arquitetura de rede (igual ao legado)

```
Internet → Nginx do HOST (TLS/Certbot) → 127.0.0.1:3120 → erpnovo-web (nginx) → erpnovo-app (php-fpm)
                                                                                       └→ erpnovo-db (Postgres próprio)
```

## Preparação (uma vez na VPS)

1. **`.env` de homologação** (segredos, fora do checkout):
   ```sh
   sudo mkdir -p /opt/dubena-env
   sudo cp erp-novo/.env.homolog.example /opt/dubena-env/erp-novo-homolog.env
   # editar e preencher APP_KEY (php artisan key:generate --show) e DB_PASSWORD
   sudo nano /opt/dubena-env/erp-novo-homolog.env
   ```

2. **Nginx do host** (TLS + proxy para 3120):
   ```sh
   sudo cp deploy/nginx/homolog-erp.conf /etc/nginx/sites-available/homolog-erp
   sudo ln -s /etc/nginx/sites-available/homolog-erp /etc/nginx/sites-enabled/
   sudo nginx -t && sudo systemctl reload nginx
   sudo certbot --nginx -d homolog-erp.gasemcasa.com.br
   ```
   (Crie o registro DNS `homolog-erp` apontando para a VPS antes do certbot.)

## Deploy

Automático: qualquer push em `main` que altere `erp-novo/**` dispara
`deploy-erp-novo-homolog.yml` no self-hosted runner da VPS. Também dá para
disparar manualmente em **Actions → Deploy erp-novo (HOMOLOGAÇÃO) → Run workflow**.

O workflow: restaura o `.env` → `build` → `up -d` → `composer install --no-dev`
→ `migrate --force` (no banco próprio) → `config:cache` → health check em
`http://127.0.0.1:3120/up`.

## Verificação

```sh
docker compose -f erp-novo/docker-compose.homolog.yml ps
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3120/up   # 200
```

## Importante

- **Não toca o legado**: banco, rede e containers são próprios. Pode subir/derrubar
  à vontade sem risco à produção.
- O `migrate` roda no Postgres **do erp-novo**, nunca no do ctrl-web.
- Artisan via `docker exec` roda como **root**; o workflow já faz
  `chown -R www-data:www-data storage bootstrap/cache` ao final.
