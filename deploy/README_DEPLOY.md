# Deploy na VPS — Guia (GitHub Actions self-hosted runner)

> Deploy automático via **push na branch `main`**, usando um **self-hosted runner**
> instalado na própria VPS. A VPS conecta ao GitHub (saída), evitando o bloqueio de
> IPs do Azure (runners hospedados) na porta 22 da VPS.

## Investigação da VPS (já realizada)

- **Host:** `ultrazend.com.br` · Ubuntu 22.04.5 · Docker 28.4 + Compose v2.
- **~30 apps em Docker.** Padrão de roteamento:
  ```
  Internet → Nginx do HOST (TLS/Certbot, /etc/nginx/sites-enabled/, roteia por domínio)
           → proxy_pass http://127.0.0.1:PORTA → container nginx da app → web/api
  ```
- **Já existe um self-hosted runner** em `/opt/actions-runner` (usuário `runner`, grupo
  `docker`, systemd ativo) — mas registrado para o repo `Digiurbanlite`. Runners de
  repositório atendem **só aquele repo**, então o `dubena` precisa de um **runner próprio**.

## Portas reservadas (faixa 3100–3299 estava livre)

| App | Porta interna (127.0.0.1) | Domínio sugerido |
| --- | --- | --- |
| ctrl-web (ERP) | **3110** | erp.gasemcasa.com.br |
| api-app-gc | **3111** | api.gasemcasa.com.br |
| monitoramento-veiculos | **3112** | monitora.gasemcasa.com.br |

> Portas já em uso (não reutilizar): 3001, 3002, 3040, 3050, 3060, 3070, 3090, 3091,
> 3092, 3095, 3096, 3190.

---

## Passo 1 — Instalar o self-hosted runner para o repo `dubena`

Pegue a versão/token em `github.com/fernandinhomartins40/dubena/settings/actions/runners/new`.
Use uma **pasta separada** do runner já existente (que é de outro repo).

```bash
# como root na VPS
mkdir -p /opt/actions-runner-dubena
chown runner:runner /opt/actions-runner-dubena
cd /opt/actions-runner-dubena

# baixar o runner (ajuste a versão à indicada na página do GitHub)
curl -sL https://github.com/actions/runner/releases/download/v2.334.0/actions-runner-linux-x64-2.334.0.tar.gz -o runner.tar.gz
tar xzf runner.tar.gz && rm runner.tar.gz
chown -R runner:runner /opt/actions-runner-dubena

# configurar com o TOKEN gerado no GitHub
sudo -u runner ./config.sh \
  --url https://github.com/fernandinhomartins40/dubena \
  --token TOKEN_DO_GITHUB \
  --name dubena-vps \
  --labels self-hosted,Linux,X64 \
  --work _work \
  --unattended

# instalar como serviço systemd (inicia no boot, Restart=always)
./svc.sh install runner
./svc.sh start
```

O usuário `runner` já está no grupo `docker` (verificado) — então pode rodar `docker`/
`docker compose` sem sudo. Se precisar recarregar o Nginx do host pelo workflow, conceda:

```bash
echo "runner ALL=(ALL) NOPASSWD: /usr/sbin/nginx, /bin/systemctl reload nginx, /bin/systemctl restart nginx" > /etc/sudoers.d/runner-deploy
chmod 440 /etc/sudoers.d/runner-deploy
```

## Passo 2 — Criar os `.env` de produção (uma vez, na VPS)

Os workflows **não criam** o `.env` (ele tem segredos). Crie-os uma vez em cada pasta,
a partir do `.env.docker`, com os **segredos REAIS de produção** (rotacionados — ver
`SEGREDOS_LOCAIS.md`). O runner faz checkout em `/opt/actions-runner-dubena/_work/dubena/dubena/`.

Exemplo para o ctrl-web (PostgreSQL):
```bash
cd /opt/actions-runner-dubena/_work/dubena/dubena/ctrl-web
cp .env.docker .env
# editar .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://erp...,
#   DB_PASSWORD forte, PIX_WEBHOOK_TOKEN, etc.
```
> ⚠️ Defina `APP_DEBUG=false` e senhas fortes. Para a api-app-gc, definir também
> `DB_ROOT_PASSWORD`. Para o monitoramento, `DB_ROOT_PASSWORD`.

## Passo 3 — Ativar o Nginx do host (TLS por domínio)

Para cada app, copie o template de `deploy/nginx/` e gere o certificado:
```bash
cp deploy/nginx/ctrl-web.conf /etc/nginx/sites-available/ctrl-web.conf
ln -s /etc/nginx/sites-available/ctrl-web.conf /etc/nginx/sites-enabled/
# ajustar o server_name no arquivo, então:
nginx -t && systemctl reload nginx
certbot --nginx -d erp.gasemcasa.com.br
```
(Repetir para api-app-gc.conf → 3111 e monitoramento.conf → 3112, com seus domínios.)

> Pré-requisito: o DNS dos domínios deve apontar para o IP da VPS antes do certbot.

## Passo 4 — Deploy

A partir daí, **todo push na `main`** que altere uma pasta dispara o workflow
correspondente (`.github/workflows/deploy-*.yml`), que roda **dentro da VPS**:
build → `up -d` → composer/migrate → health check na porta interna.

Disparo manual: aba **Actions** do GitHub → workflow → *Run workflow*.

---

## Notas de arquitetura
- Cada app expõe **só** `127.0.0.1:PORTA` (não fica público) — o Nginx do host é o
  único ponto de entrada TLS, igual às outras ~30 apps da VPS.
- Os `docker-compose.prod.yml` montam volumes para `storage` e `vendor` persistirem
  entre deploys; o banco persiste em volume nomeado.
- Segredos **nunca** entram no GitHub (workflow exige `.env` pré-existente na VPS).
- **ctrl-web já roda em PostgreSQL** (Fase 3); api/monitoramento em MySQL.
