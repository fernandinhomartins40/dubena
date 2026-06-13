# Ambiente Docker — monitoramento-veiculos (Piloto · Fase 0)

Ambiente de **dev/staging** containerizado, parte do `PLANO_MODERNIZACAO_ECOSSISTEMA.md` (Fase 0).
Objetivo: validar o pipeline **Docker → VPS → staging** com o menor sistema, sem tocar na produção.

## Stack do container
- **PHP 7.1-fpm** (faixa suportada pelo Laravel 5.4)
- **Nginx** (reverse proxy → php-fpm)
- **MySQL 5.7** (banco `monitora`, dados de teste)
- **Redis 7** (disponível para cache/sessão/fila)

## Como subir (local ou VPS)

```bash
# na raiz do projeto monitoramento-veiculos/
docker compose up -d --build

# acompanhar a primeira inicialização (composer install + key:generate)
docker compose logs -f app
```

Acessar: **http://localhost:8080** (ou o domínio configurado na VPS).

Rodar migrations (cria as tabelas no MySQL de teste):
```bash
docker compose exec app php artisan migrate
```

Comandos úteis:
```bash
docker compose exec app php artisan route:list   # conferir rotas
docker compose exec app bash                      # shell no container
docker compose down                               # derruba (mantém o volume do banco)
docker compose down -v                            # derruba e APAGA o banco de teste
```

## O que foi feito nesta fase (Fase 0)
- ✅ Containerização do app Laravel as-is (PHP 7.1 / MySQL / Redis).
- ✅ **Extração de segredos** do `config/database.php`: removidas as credenciais de
  produção hardcoded (`reset1`, `dubena@4321`, `toor`) e os IPs reais. Agora tudo vem
  de variáveis de ambiente.
- ✅ `.env.docker` com valores **fake** de dev; `.env` real continua fora do Git.

## Limitações conhecidas (esperadas no piloto)
- **Oracle não incluído.** O driver `oci8` (Oracle Instant Client) é pesado/licenciado
  e os dados próprios deste sistema estão em MySQL. Rotas que leem o ERP via Oracle
  (`SearchController@getPedidosPendentes`, conexão `oracle3`) **ficam inoperantes** até
  a **Fase 3** (migração do ERP para PostgreSQL). Isso é intencional.
- **Módulo `integration/`** (scripts `mysql_*` em PHP 4/5) **não roda** em PHP 7 e
  **não** é executado por este container. Ainda contém credenciais hardcoded —
  será **reescrito/removido na Fase 4**.

## ⚠️ Antes de EXPOR na VPS (blindagem — seção 3.2 do plano)
- [ ] `APP_DEBUG=false` no `.env` (o template vem `true` só para a 1ª subida).
- [ ] Acesso restrito: `ufw` liberando só seu IP/VPN **ou** HTTP Basic Auth no Nginx.
- [ ] **HTTPS** via Let's Encrypt/Certbot (adicionar terminação TLS no Nginx/proxy).
- [ ] Trocar as senhas **fake** do `docker-compose.yml`/`.env.docker` por segredos reais
      de staging (via variáveis de ambiente do Docker, não no arquivo versionado).
- [ ] **Dados fake/anonimizados** no banco — nunca dump cru de produção (LGPD).
- [ ] SSH por chave, `root` desabilitado, `fail2ban`.

## Próximos passos
1. Validar que o app sobe e responde em staging com HTTPS e acesso restrito.
2. Replicar este padrão para `api-app-gc` e `ctrl-web` (este com PostgreSQL).
3. Seguir para a **Fase 1 (segurança)**: autenticar `/savePosition`, remover `password`
   de `getUsuarios`, etc.
