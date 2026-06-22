# Landing page (gasemcasa.com)

Página estática servida na **raiz** de `gasemcasa.com` / `www.gasemcasa.com` para
escolher entre os dois ERPs durante a transição:

- **ERP novo (SPA)** → `/novo/app/`
- **ERP atual (legado)** → `/login`

## Instalação na VPS

1. Copiar `index.html` para a VPS:
   ```sh
   sudo mkdir -p /var/www/html/landing
   sudo cp index.html /var/www/html/landing/index.html
   ```

2. No nginx do host (`/etc/nginx/sites-available/gasemcasa.com`), dentro do
   `server { server_name gasemcasa.com www.gasemcasa.com; ... }`, **antes** do
   `location /` do legado, adicionar:
   ```nginx
   # Landing: escolha entre ERP novo e legado (só na raiz exata).
   location = / {
       root /var/www/html/landing;
       try_files /index.html =404;
   }
   ```
   `location = /` casa **só** a raiz exata — o resto do legado (`/login`, `/home`)
   e o erp-novo (`/novo/...`) seguem inalterados.

3. `sudo nginx -t && sudo systemctl reload nginx`.

## Usuário de teste (ambos os ERPs)

`teste@gasemcasa.com` / `teste1234` — exibido nos cards da landing.
- erp-novo: usuário support (bypass RBAC).
- legado: clonado do admin (com todos os menus).
