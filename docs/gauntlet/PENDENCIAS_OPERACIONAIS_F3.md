# Pendências operacionais da F3 — exigem você

> As tarefas de código e infraestrutura da F3 estão feitas e verificadas em
> produção. O que sobrou aqui **não é código**: são contas em serviços externos,
> arquivos que não podem ser versionados e decisões de negócio.
>
> **Enquanto estes itens não forem feitos, o `golive:check --strict` não passa
> em produção — e é ele que o workflow de deploy usa como portão bloqueante.**

---

## Situação atual (medida na VPS em 17/08/2026)

```
golive:check → 0 FALHA(s), 3 aviso(s)   PORTÃO LIBERADO COM AVISOS

  PASS  APP_KEY, APP_DEBUG=false, banco pgsql, conexão OK
  PASS  middleware tenant, RLS ativo (147 policies), role erp_app restrita
  PASS  fila redis, broadcast reverb, cache redis, 12 empresas
  WARN  APP_ENV = homologation      ← o ambiente da VPS ainda é homolog
  WARN  FISCAL_DRIVER = fake        ← não emite NF-e real
  WARN  COBRANCA_DRIVER = fake      ← não gera CNAB real
```

**O ambiente hoje na VPS é HOMOLOGAÇÃO**, não produção. Os drivers em `fake` são
coerentes com isso. A virada para produção é a F6 (cutover) e depende dos itens
abaixo.

---

## P1 — Firebase: arquivo de service account (T3.10) — **BLOQUEANTE**

Sem ele, o login do app do consumidor não funciona com `FIREBASE_DRIVER=kreait`;
e com o driver em `fake`, **qualquer pessoa loga como qualquer cliente** enviando
`firebase_id_token = "fake:+5542..."` (o gate da F1 impede isso em produção
lançando exceção — o que significa que o app simplesmente não loga).

1. Console Firebase → Configurações do projeto → Contas de serviço → **Gerar
   nova chave privada** (baixa um JSON).
2. Colocar o arquivo no volume de produção, **fora do repositório**:
   ```bash
   scp service-account.json root@gasemcasa.com:/tmp/
   ssh root@gasemcasa.com
   docker cp /tmp/service-account.json erpnovo-app:/var/www/storage/app/firebase/
   docker exec erpnovo-app chown www-data:www-data /var/www/storage/app/firebase/service-account.json
   docker exec erpnovo-app chmod 400 /var/www/storage/app/firebase/service-account.json
   rm /tmp/service-account.json
   ```
3. No `.env` de produção:
   ```
   FIREBASE_DRIVER=kreait
   FIREBASE_PROJECT_ID=<id do projeto>
   FIREBASE_CREDENTIALS=/var/www/storage/app/firebase/service-account.json
   FCM_DRIVER=v1
   ```

**Pronto quando:** um login real de cliente no app (SMS de verdade, não `fake:`)
funciona ponta a ponta. O arquivo entra no backup automaticamente — está sob
`storage/app`.

---

## P2 — Certificado A1 por empresa (T3.10) — **BLOQUEANTE para faturar**

Verificado hoje: **nenhuma empresa tem certificado** (zero registros em
`empresa_configs`; a pasta `storage/app/certificados/` sequer existe). Com
`FISCAL_DRIVER=nfephp`, o `golive:check` **FALHA** enquanto alguma empresa que
vai faturar estiver sem certificado.

Para **cada empresa** que emitirá nota:

1. Ter o arquivo `.pfx` (A1) e a senha em mãos.
2. Na SPA: Empresas → *(a empresa)* → Certificado → upload + senha.
   O sistema valida com `openssl_pkcs12_read` — se a senha estiver errada, o
   upload é recusado na hora.
3. Conferir que gravou: `storage/app/certificados/empresa_<id>/<timestamp>.pfx`.

> ⚠️ **Certificado A1 é insubstituível sem custo e prazo.** Depois do primeiro
> upload, confirme que o backup passou a incluí-lo: a linha
> `storage: N caminho(s) de certificado` do `backup.sh` deve deixar de mostrar 0.

**Depois disso:** `FISCAL_DRIVER=nfephp` e emitir uma NFC-e em **homologação da
SEFAZ** antes de virar para produção.

---

## P3 — PIX: credenciais do PSP (T3.10) — **BLOQUEANTE para cobrar**

São **dois segredos distintos** (a auditoria destaca isso porque confundi-los é
comum):

```
PIX_ENABLED=true
PIX_DRIVER=<psp>
PIX_WEBHOOK_SECRET=<segredo compartilhado>
PIX_WEBHOOK_HMAC_SECRET=<DIFERENTE do anterior — assina o corpo>
```

E a credencial de cobrança é **por empresa**, na tela de integrações
(`empresa_configs.dados['integracoes']['pix']`). A resolução é
EMPRESA → GRUPO → PLATAFORMA(env), *fail-closed*: sem credencial da empresa, não
cobra — de propósito.

Registrar `https://gasemcasa.com/novo/api/pix/webhook` no painel do PSP.

---

## P4 — Rebuild dos apps mobile com REVERB_* (T3.9)

O proxy `wss://` está **funcionando** (handshake verificado: HTTP 101 +
`pusher:connection_established`), mas os apps só usam tempo real se as
credenciais estiverem no bundle deles.

Nos `.env` / EAS secrets de `app-entregador` e `app-gas-em-casa`:

```
REVERB_APP_KEY=<a mesma key do .env do servidor>
REVERB_HOST=gasemcasa.com
REVERB_PORT=443
REVERB_SCHEME=https
```

Sem o rebuild, `realtimeDisponivel()` devolve false e os apps continuam em
polling — o proxy fica correto e inútil.

---

## P5 — Backup fora do host — **IMPORTANTE**

O backup roda diariamente (cron 03:15 UTC) e o restore foi testado (RTO de
**169 s**), mas **a cópia vive no mesmo host que ela protege**. Um incidente de
host (disco, provedor, engano de comando) leva junto o original e o backup.

Escolha um destino externo e configure:

```bash
# no crontab, antes do comando:
BACKUP_REMOTO=usuario@servidor:/backups/erpnovo
```

O `backup.sh` replica via `rsync` e **falha** se a réplica não completar. Sem a
variável, ele avisa em toda execução — de propósito.

---

## P6 — Sentry (observabilidade) — **IMPORTANTE**

Não instalei o pacote porque ele exige um DSN de uma conta que não tenho. Quando
tiver:

```bash
cd erp-novo && composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=<DSN>
```

E no `.env`: `SENTRY_LARAVEL_DSN=<DSN>` + `SENTRY_TRACES_SAMPLE_RATE=0.05`.

Hoje um erro em produção só existe no arquivo de log (agora com rotação diária,
`LOG_STACK=daily`). Ninguém é avisado.

Junto: um monitor de uptime externo (UptimeRobot, Better Stack) apontando para
`https://gasemcasa.com/novo/up`.

---

## P7 — A senha default do admin — **AINDA ABERTA, de F1**

Repetido aqui porque continua sendo o item mais urgente da lista inteira:

```
Hash::check('admin1234', <senha de admin@gasemcasa.com>) → SENHA_DEFAULT_ATIVA
```

Essa conta tem `support = true` (bypass total de RBAC). A F1 impede que **novas**
contas nasçam assim, mas não troca a senha de uma conta **existente** — os
seeders só definem senha na criação, de propósito, para não sobrescrever troca
manual.

```bash
docker exec -it erpnovo-app php artisan tinker
>>> $u = App\Models\User::where('email','admin@gasemcasa.com')->first();
>>> $u->password = Hash::make('<senha forte nova>'); $u->save();
```

Há **duas** contas com `support = true`: `admin@gasemcasa.com` e
`admin@dubena.com.br`. Troque as duas.

---

## O que a F3 entregou (para referência)

| Tarefa | Estado |
|---|---|
| T3.1 `.env.production.example` | ✅ 170 de 196 chaves documentadas; 26 ausentes por decisão registrada |
| T3.2 `docker-compose.producao.yml` | ✅ imagem por SHA, código na imagem, 8 serviços, healthchecks |
| T3.3 `retry_after` > timeout | ✅ fila `redis-longo` (25200 s > 21600 s) + worker próprio |
| T3.4 backup automatizado | ✅ **rodando na VPS**, cron 03:15 UTC, checksums, retenção |
| T3.5 restore testado | ✅ **executado**, RTO **169 s**, todos os números batem |
| T3.6 workflow de produção | ✅ manual, backup obrigatório, `golive:check --strict` bloqueante |
| T3.7 seeds demo fora de produção | ✅ gate de ambiente + ausência no workflow |
| T3.8 rollback | ✅ `deploy/rollback.sh` + regra sobre migrations destrutivas |
| T3.9 Reverb + logs | ✅ **575 restarts → 0**, handshake `wss://` verificado |
| T3.10 gates externos | ⏳ **este documento** |
