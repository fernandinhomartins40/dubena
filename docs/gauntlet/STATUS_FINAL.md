# Status final do PLANO_PRODUCAO — o que está feito e o que sobrou

**Data:** 2026-08-18
**Suíte:** 835 testes verdes (2.568 asserções)
**Portão de dados:** `cutover:check` → **71 OK, 0 falhas**

---

## §1 — As 6 fases

| Fase | Estado | Observação |
|---|---|---|
| **F1** — Segurança | ✅ completa | 9 tarefas; `composer audit` e `npm audit` limpos |
| **F2** — Dados | ✅ completa | dedup aplicado em produção (66.557 → 55.453); soma financeira intacta em R$ 250.029.904,80 |
| **F3** — Infra | ✅ código completo | backup rodando na VPS, restore com RTO de 169 s, Reverb 575 restarts → 0. **7 pendências externas** (§3) |
| **F4** — Paridade | ✅ completa | 9 tarefas, incluindo T4.3 e T4.4 que estavam bloqueadas por decisão |
| **F5** — Débito | ✅ completa | reauditoria da seção 5; 1 das 4 refutações era falsa |
| **F6** — Cutover | ⚠️ parcial | o que é código está pronto; o que é operação não (§2) |

---

## §2 — F6: o que falta é operação, não código

| Tarefa | Estado | Por quê |
|---|---|---|
| T6.1 runbook | ⚠️ **escrito, não ensaiado** | `deploy/CUTOVER_RUNBOOK.md` tem os 15 passos, 3 portões, smoke test e 3 níveis de rollback. **A coluna "tempo medido" está vazia** e os campos de decisor/responsável em branco. O critério binário é "ensaiado em staging pelo menos uma vez" |
| T6.2 banco limpo | ❌ não iniciada | Exige criar o banco de produção na VPS |
| T6.3 virada Nginx | ⚠️ **escrito, falta ensaiar** | `virar.sh` + 2 vhosts existem; valida com `nginx -t` antes de recarregar e reverte sozinho. **Falta o passo 1**: comparar `nginx -T` da VPS com o repo |
| T6.4 smoke test | ❌ humano | 7 itens, executados por quem conhece o negócio, com o legado aberto ao lado |
| T6.5 GO/NO-GO | ✅ escrito | está no runbook; falta **nomear o decisor** e a hora-limite |
| T6.6 pós-virada | ✅ parcial | a trava do ETL (T6.6.5) está **pronta e verificada**; o resto é observação |
| T6.7 rollback | ✅ escrito | 3 níveis com gatilho observável; níveis 1 e 2 não ensaiados |
| T6.8 aposentadoria | ❌ pós-cutover | +30 dias após a virada |

**A única da F6 que está pronta de fato:** a trava do `etl:run`. Ela recusa a
recarga quando detecta pedidos criados no sistema novo — por evidência no banco,
não por flag que alguém precisa lembrar de ligar. Verificada em execução real:
bloqueia com exit 1, `--dry-run` passa, `--eu-sei-o-que-estou-fazendo` libera.

---

## §3 — O que sobrou para você

### 🔴 Urgente — segurança

**1. Senha `admin1234` em 2 contas com `support = true`**
`admin@gasemcasa.com` e `admin@dubena.com.br`. `support = true` é bypass total de
RBAC. A F1 impede que **novas** contas nasçam assim, mas não troca a senha de
contas existentes — os seeders só definem senha na criação, de propósito, para
não sobrescrever troca manual.

```bash
docker exec -it erpnovo-app php artisan tinker
>>> $u = App\Models\User::where('email','admin@gasemcasa.com')->first();
>>> $u->password = Hash::make('<senha forte>'); $u->save();
```

*Posso fazer isto por você* — gero a senha e troco nas duas contas.

### 🟠 Decisões que só você tem como tomar

**2. O acerto físico de malote ainda acontece?**
Implementado (T4.3). "Sim" → está pronto. "Não" → apagar 4 arquivos.

**3. O call-center usa bina?**
Implementado (T4.4). Mesma lógica.

**4. O saldo das contas: qual número é o verdadeiro?**
`T5.1_ACHADOS.md` §4. O legado nunca manteve `saldo = Σ movimentos` — a conta 692
tem `saldoatual = 0` na **origem** com R$ 26,5 milhões em movimentos. Não é
defeito da migração. **Conferir 2–3 contas ativas contra o extrato bancário real**
decide se é preciso recalcular. Se for, 28 contas mudam de saldo.

### 🟡 Verificação física — ninguém faz remotamente

**5. Imprimir um boleto e passar o código de barras num leitor de caixa** (I2of5)
**6. Imprimir um DANFE e passar a chave num leitor** (Code 128C)

São símbolos diferentes; dois testes separados. Os testes automatizados provam
conformidade com a especificação, **não** legibilidade física.

**7. Revisão jurídica das cláusulas do contrato de comodato**
O texto está isolado em `ComodatoPdfService::clausulas()` justamente para ser
trocado sem tocar em nada. Quem responde pelo contrato assinado é a revenda.

### 🔵 Chaves e contas externas — não existem no servidor

Nenhuma destas se resolve com acesso à VPS: o insumo está fora dela.

| # | Item | O que é preciso |
|---|---|---|
| 8 | **Firebase** (P1) | Baixar o JSON de service account no Console Firebase (sua conta Google). Sem ele, o app do consumidor não loga em produção |
| 9 | **Certificado A1** (P2) | O arquivo `.pfx` + senha, por empresa. Documento jurídico. **Nenhuma empresa tem hoje** |
| 10 | **PIX** (P3) | Credenciais do PSP contratado. São **dois** segredos distintos: `PIX_WEBHOOK_SECRET` e `PIX_WEBHOOK_HMAC_SECRET` |
| 11 | **Sentry** (P6) | DSN de uma conta a criar. Hoje um erro em produção só existe no arquivo de log — ninguém é avisado |
| 12 | **Backup externo** (P5) | Escolher o destino (outro servidor? S3?). O backup vive no mesmo host que ele protege |
| 13 | **Rebuild dos apps** (P4) | Depende do #8 e #10; sem as chaves no bundle, os apps ficam em polling |
| 14 | **PABX** (T4.4) | Se a bina for usada: apontar o PABX para `POST /api/pabx/chamada` com `X-Pabx-Token` |
| 15 | **Restringir a chave Google no console** (T1.9, passo 2) | Ver §5 |

---

## §4 — O que eu posso fazer na VPS, se você autorizar

1. **Trocar as 2 senhas** (#1) — gero e te entrego
2. **Comparar `nginx -T` com o repo** (passo 1 da T6.3)
3. **Montar o comparativo de saldos** (#4) — extraio os números; a conferência
   com o extrato continua sendo sua
4. **Configurar o `rsync` do backup externo** (#12) — assim que você disser o destino

O resto da lista não é questão de acesso: é chave que não existe no servidor, ou
papel que precisa passar num leitor.

---

## §5 — Sobre a chave do Google Maps/Firebase nos apps (T1.9)

**Verificado:** a chave `AIzaSy…` existe em `app-gas-em-casa/google-services.json`
e na cópia sob `android/app/`, **mas**:

- nenhum dos dois arquivos está versionado (`git ls-files` → vazio);
- ambos estão no `.gitignore`;
- a string **nunca apareceu em nenhum commit** (`git log --all -S` → vazio).

Ou seja: não houve vazamento por repositório, e a chave não precisa ser
rotacionada por esse motivo.

⚠️ **O que continua valendo:** `google-services.json` vai embutido no APK por
natureza — a chave é extraível de qualquer app instalado, e isso é esperado. A
proteção não é escondê-la, é **restringi-la no console Google**: package name +
SHA-1 (Android), bundle ID (iOS), e limitar às APIs realmente usadas (Maps SDK,
Geocoding). Sem restrição, qualquer um que extraia a chave do APK consome a
quota da conta.

Isso é console Google — conta do dono.
