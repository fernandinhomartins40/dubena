# Landing page (gasemcasa.com)

Página estática que aparece na **raiz** de `gasemcasa.com` durante a transição,
para escolher entre os dois ERPs:

- **ERP novo (SPA)** → `/novo/app/`
- **ERP atual (legado)** → `/login`

## Onde a landing mora agora (monorepo)

A landing é parte do **monorepo erp-novo** e é servida pelo **nginx dentro do
container** do erp-novo — não é mais um arquivo solto em `/var/www/html`.

- Fonte servida: `erp-novo/public/landing/index.html` (vai no deploy do erp-novo).
  Este diretório `deploy/landing/` guarda a referência/histórico.
- O nginx do container (`erp-novo/docker/nginx/default.conf`) serve:
  - `/` (raiz exata) → a landing;
  - `/novo/app` → a SPA React;
  - `/novo/api`, `/novo/sanctum` → a API Laravel.
- **Deploy automático**: vai junto no deploy do erp-novo (GitHub Actions
  `deploy-erp-novo-homolog.yml`, em push para `main` que toque `erp-novo/**`).
  Sem `sudo`, sem cópia manual.

## Nginx do HOST (uma vez, na VPS)

O host só faz **TLS + proxy** para o container (sem strip de prefixo). Use
[`../nginx/gasemcasa-com.conf`](../nginx/gasemcasa-com.conf):

- `gasemcasa.com/` e `gasemcasa.com/novo/...` → `127.0.0.1:3120` (container erp-novo)
- `gasemcasa.com/login`, `/home`, ... → `127.0.0.1:3110` (ctrl-web legado)

Depois: `sudo nginx -t && sudo systemctl reload nginx`.

> Migração do esquema antigo: remover o `location = / { root /var/www/html/landing; }`
> do host — a raiz agora vai por proxy ao container. O arquivo
> `/var/www/html/landing/index.html` pode ser apagado após o ajuste do roteamento.

## Usuário de teste (ambos os ERPs)

`teste@gasemcasa.com` / `teste1234` — exibido nos cards da landing.
- erp-novo: usuário support (bypass RBAC).
- legado: clonado do admin (com todos os menus).
