# Pendências operacionais da F1 — exigem acesso a consoles externos

> As tarefas de código da F1 estão concluídas e verificadas (ver `PLANO_PRODUCAO.md`,
> T1.1–T1.9). O que sobrou aqui **não é código**: são ações em consoles de terceiros
> e no `.env` da VPS, que o agente não tem — e não deve ter — acesso para executar.
>
> **Enquanto estes itens não forem feitos, a F1 não está fechada de verdade.**

---

## P1 — Rotacionar a chave do Google Maps (T1.9, passos 1–2)

**Por quê.** A chave `AIzaSyDygo…Qd0d8` esteve versionada em
`app-entregador/eas.json` e `app-gas-em-casa/eas.json`. Ela continua no histórico
do git e em `docs/gauntlet/AUDITORIA.md` — **considere-a queimada**. Tirá-la dos
arquivos (feito) impede vazamentos futuros, não desfaz o passado.

**O que fazer.**
1. Console Google Cloud → Credentials → **criar chave nova** e desativar a antiga.
2. Na chave nova, aplicar restrições:
   - Android: package name + impressão SHA-1 de cada app;
   - iOS: bundle ID;
   - por API: apenas **Maps SDK** e **Geocoding** (o app do entregador também usa Routes).
3. Cadastrar como secret no EAS, nos **dois** apps:
   ```bash
   cd app-entregador   && eas secret:create --name GOOGLE_MAPS_API_KEY --value '<chave-nova>'
   cd app-gas-em-casa  && eas secret:create --name GOOGLE_MAPS_API_KEY --value '<chave-nova>'
   ```
   Os `eas.json` já referenciam `"$GOOGLE_MAPS_API_KEY"` — sem o secret, o build
   sai com a chave vazia e os mapas não carregam.

**Pronto quando.** `eas secret:list` mostra `GOOGLE_MAPS_API_KEY` nos dois apps, e
um build de preview renderiza o mapa.

---

## P2 — Preencher as credenciais de bootstrap no `.env` de produção (T1.1)

**Por quê.** Os seeders passaram a ser **fail-close**: em `APP_ENV=production`,
sem estas variáveis (ou com menos de 12 caracteres) o `DeploySeeder` **aborta o
deploy** com `RuntimeException`. Isso é intencional — era assim que a senha
`admin1234` chegava à produção. Mas significa que **o próximo deploy quebra** se
o `.env` da VPS não for atualizado antes.

**O que fazer.** No `.env` de produção (VPS `gasemcasa.com`), definir:

```
ADMIN_SEED_EMAIL=<e-mail do admin>
ADMIN_SEED_PASSWORD=<senha forte, >= 12 caracteres>
SUPERADMIN_SEED_EMAIL=<e-mail do superadmin>
SUPERADMIN_SEED_PASSWORD=<senha forte, >= 12 caracteres>
SANCTUM_EXPIRATION=1440
```

Os mesmos segredos precisam existir no ambiente do workflow de deploy (GitHub
Secrets), já que é ele quem roda `db:seed --class=DeploySeeder`.

**Pronto quando.** Um deploy completa sem `RuntimeException`, e
`Hash::check('admin1234', <senha do admin>)` na VPS retorna **false**.

> ⚠️ **A conta admin existente NÃO tem a senha trocada por este plano.** Os
> seeders só definem senha na CRIAÇÃO (`if (! $admin->exists)`) — de propósito,
> para não sobrescrever senha trocada à mão. A conta `admin@gasemcasa.com` em
> produção **continua com `admin1234` até alguém trocá-la manualmente**. Faça
> isso na primeira janela: é o bloqueador nº 1 da auditoria (§1.4).

---

## P3 — Definir os drivers reais no `.env` de produção (T1.3)

**Por quê.** Os gates agora falham fechado: em produção, resolver o contrato com
driver `fake` lança `RuntimeException`. Sem estas variáveis, a primeira
requisição que tocar cada área quebra — em vez de emitir NF-e simulada ou aceitar
login forjado, que era o comportamento anterior.

```
FIREBASE_DRIVER=kreait      # + FIREBASE_CREDENTIALS e FIREBASE_PROJECT_ID
FCM_DRIVER=v1               # usa as mesmas credenciais do Firebase
FISCAL_DRIVER=nfephp        # + certificado A1 do tenant
COBRANCA_DRIVER=caixa       # ou itau
MONITORA_DRIVER=sgcasa      # não derruba se faltar: só loga warning
```

**Pronto quando.** `php artisan tinker --execute='echo get_class(app(\App\Domain\Fiscal\Contracts\SefazDriver::class));'`
na VPS imprime `NFePHPSefazDriver` (e equivalentes para os demais).

> Isto se conecta ao bloqueador nº 3 da auditoria (`FISCAL_DRIVER=fake` e
> `COBRANCA_DRIVER=fake` **ativos em produção hoje**): o gate transforma um erro
> silencioso num erro visível, mas quem resolve o problema de fato é a configuração.
