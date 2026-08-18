# Runbook do cutover — legado (`ctrl-web`) → `erp-novo`

**Status deste documento:** escrito, **não ensaiado**. A T6.1 só está pronta
quando a coluna "tempo medido" estiver preenchida por um ensaio real em staging
com dump de produção. **Enquanto ela estiver vazia, não abra a janela.**

**Decisor nomeado:** ______________________ *(uma pessoa, não um comitê — T6.5.2)*
**Hora-limite de decisão:** ______:______ *(se os portões não estiverem verdes até
aqui, aborta e remarca; não se estende a janela improvisando)*

---

## Por que a janela tem esta forma

Três fatos verificados no código determinam o procedimento — não são preferência:

1. **`etl:run` é carga cheia, sempre.** Não existe modo incremental por
   timestamp. As únicas opções são `{migrator?}`, `--dry-run` e `--check`.
2. **A recarga é upsert preservando id.** Consequência: re-rodar o ETL
   **sobrescreve** qualquer linha de id legado editada no sistema novo. É por
   isso que **o congelamento é obrigatório** — todo trabalho feito no novo sobre
   uma linha herdada é perdido na recarga final.
3. **A carga leva horas** (~16 milhões de linhas, `memory_limit=3G`). O deploy
   **não pode reciclar o container no meio** — um `git push` na `main` durante a
   janela dispara deploy automático e mata o ETL. **Congele a `main` também.**

---

## D-7 — uma semana antes

| # | Passo | Comando / responsável | Tempo medido |
|---|---|---|---|
| 1 | Ensaio completo em staging com dump **real** de produção | runbook inteiro | ______ |
| 2 | `cutover:check` verde no ensaio | `php artisan cutover:check` | ______ |
| 3 | Restore testado e cronometrado | `bash deploy/backup/restore.sh <dump>` | ______ |
| 4 | Comunicação aos usuários (data, hora, duração) | — | — |

> O ensaio existe para **dimensionar a janela**. A regra da T6.5 é janela com
> folga ≥ 2× o tempo medido do ETL. Sem o número do ensaio, não há como aplicar
> a regra.

---

## D-1 — véspera

| # | Passo | Comando | OK |
|---|---|---|---|
| 5 | Backup completo do legado (Oracle + MySQLs) e do que já existe do novo | `bash deploy/backup/backup.sh` | ☐ |
| 6 | `golive:check --strict` verde em produção | `php artisan golive:check --strict` | ☐ |
| 7 | Gates externos provisionados: Firebase, certificados A1 por empresa, PIX por empresa | conferir `.env` de produção | ☐ |
| 8 | **Congelar a branch `main`** — push dispara deploy e recicla container | avisar a equipe | ☐ |
| 9 | Senhas de suporte trocadas (ver §Pendências) | — | ☐ |

---

## D-0 — a janela

### Passo 8 — congelar escrita no legado

Cinto e suspensório, nesta ordem:

```bash
# 1) modo manutenção no ctrl-web
docker exec ctrl-web php artisan down

# 2) revogar escrita da role de aplicação (Oracle e MySQLs)
#    — o modo manutenção sozinho não impede um job/cron do legado de escrever
REVOKE INSERT, UPDATE, DELETE ON <schema>.* FROM <role_app_legado>;
```

**Anote o timestamp exato do congelamento:** ______________________

> Este timestamp é a fronteira. Qualquer coisa que apareça no legado depois dele
> é escrita que escapou — e precisa ser reconciliada à mão.

### Passo 9 — recarga final

```bash
php artisan etl:run --check
```

**Esperar horas.** Não reciclar o container. Não fazer deploy.

Tempo medido no ensaio: ______  |  Tempo real: ______

### Passo 10 — PORTÃO 1: dados

```bash
php artisan cutover:check
```

**Exigido: 0 falhas, exit 0.** Falhou → §Rollback, não "vamos ver se é grave".

> A auditoria é categórica sobre o defeito de processo que este portão existe
> para corrigir: *"o autoteste da migração existe, funciona e está reprovando —
> e mesmo assim a migração consta como concluída. O portão não está sendo
> respeitado como portão."*

### Passo 11 — PORTÃO 2: infra

```bash
php artisan golive:check --strict
```

**Exigido: exit 0.**

### Passo 12 — PORTÃO 3: smoke test funcional (humano)

**Por que não é opcional.** O `cutover:check` prova volume e integridade
referencial. Ele **não** prova que o dado certo está na coluna certa — um
migrator pode gravar a contagem correta com colunas trocadas e passar em tudo.
O smoke test é a única defesa contra isso.

Executado por um operador que **conhece o negócio**, com o legado aberto ao lado:

| # | Verificação | Como | OK |
|---|---|---|---|
| 1 | 5 clientes de perfis diferentes (PF, PJ, convênio, inadimplente, do app): nome, telefone, endereço, histórico, saldo | comparar campo a campo com o legado | ☐ |
| 2 | Pedido ponta a ponta: criar na SPA → CONCLUÍDO → conferir baixa de estoque e geração de financeiro | `estoquesaldos`, `financeiros` | ☐ |
| 3 | Emitir **NFC-e real** e conferir na SEFAZ | só funciona com certificado A1 real | ☐ |
| 4 | App do cliente: login Firebase **real** (não `fake:`), pedido, PIX, webhook | — | ☐ |
| 5 | App do entregador: jornada, aceitar, posição, concluir; tempo real chegando | Reverb | ☐ |
| 6 | Soma financeira bate **ao centavo** | `SELECT round(sum(valor)::numeric,2) FROM financeiros` | ☐ |
| 7 | Multi-tenant: usuário de uma empresa **não vê** dados de outra | RLS barra mesmo se a aplicação falhar | ☐ |

**Assinado por:** ______________________  **Hora:** ______

**Qualquer item reprovado = NO-GO.**

### Passo 13 — decisão GO / NO-GO

**Critérios de GO — todos obrigatórios:**

| # | Critério | Prova |
|---|---|---|
| 1 | Portão de dados verde | `cutover:check` → 0 falhas |
| 2 | Portão de infra verde | `golive:check --strict` → exit 0 |
| 3 | Smoke test 100% | checklist assinado |
| 4 | Backup pré-virada concluído e **verificado** | checksum conferido |
| 5 | Restore ensaiado, RTO conhecido | tempo registrado |
| 6 | Reversão de Nginx ensaiada | `virar.sh` testado nos dois sentidos |
| 7 | Equipe de plantão disponível | escala nomeada |
| 8 | Janela com folga ≥ 2× o ETL ensaiado | do D-7 |

**NO-GO automático, sem discussão:** `cutover:check` com ≥ 1 falha;
`golive:check --strict` ≠ 0; qualquer item do smoke reprovado; backup falho ou
não verificado; ETL estourando a janela.

**Decisão registrada por escrito, com a saída dos comandos anexada.**

### Passo 14 — virada do Nginx

```bash
bash deploy/nginx/virar.sh novo
curl -sf -o /dev/null -w '%{http_code}\n' https://gasemcasa.com/
curl -sf -o /dev/null -w '%{http_code}\n' https://gasemcasa.com/novo/up
```

O script valida com `nginx -t` **antes** de recarregar e reverte sozinho se a
config reprovar. O ctrl-web continua de pé em `/legado/`.

### Passo 15 — verificação pós-virada

**Primeiros 15 minutos:**
```bash
curl -sf https://gasemcasa.com/novo/up
docker compose -f erp-novo/docker-compose.producao.yml logs --tail=100 app
docker compose -f erp-novo/docker-compose.producao.yml logs --tail=100 queue
```

**Primeira hora** — os 8 agendamentos rodaram?
```bash
docker compose -f erp-novo/docker-compose.producao.yml logs scheduler | grep -c "pix:expirar"   # > 0
psql -Atc "SELECT count(*) FROM failed_jobs WHERE failed_at > now() - interval '1 hour';"        # 0
```

> Sem worker, `AtribuirPedidoJob` e `EnviarPushJob` ficam **inertes**:
> auto-atribuição e push param sem erro visível.

**Primeiro dia:** acompanhar o operador. Cada "não consigo fazer X" é uma lacuna
da triagem T4.9 se materializando — registre e cruze com `TRIAGEM_LACUNAS_F4.md`.

**Primeiros 30 dias:** legado **de pé, sem escrita**, em `/legado/`. É a rede de
consulta para o que ficou PÓS-GO-LIVE e a fonte de qualquer reconciliação.

---

## Rollback

**Escolha sempre o nível mais barato que resolve.**

### Nível 1 — reversão de Nginx (segundos)

*Gatilho observável:*
- 5xx acima de 5% por 5 minutos consecutivos; **ou**
- operador não consegue completar o fluxo de pedido; **ou**
- divergência financeira detectada em conferência.

```bash
bash deploy/nginx/virar.sh legado
docker exec ctrl-web php artisan up      # reabrir escrita
# + restaurar o privilégio de escrita da role no Oracle/MySQL
```

*Perda:* o trabalho feito no novo depois da virada.
**Este é o caminho padrão durante a janela e nas primeiras horas.**

**Janela de decisão:** até **T+24h** após a virada, reverter é a decisão
*default* diante da dúvida e **não exige aprovação**. Depois disso o legado está
defasado demais e cada nível fica mais caro.

*Quem pode acionar:* ______________________

### Nível 2 — rollback de aplicação (minutos)

*Gatilho:* o deploy do novo quebrou, mas os dados estão bons.

```bash
bash deploy/rollback.sh <sha-anterior>
```

*Restrição:* só funciona se as migrations do deploy revertido forem aditivas.

*Quem pode acionar:* ______________________

### Nível 3 — restore de banco (horas — RTO da T3.5)

*Gatilho:* corrupção de dados detectada **depois** de escrita real no novo.

```bash
bash deploy/backup/restore.sh <backup> --em-producao
bash deploy/nginx/virar.sh legado
```

*Custo:* alto, com perda de dados no intervalo entre o backup e o restore, que
precisa ser reconciliada à mão.

*Quem pode acionar:* ______________________

---

## Proteção contra recarga acidental (T6.6.5)

Depois da virada, `etl:run` **se recusa a rodar** quando detecta pedidos criados
no sistema novo (id acima da faixa do legado):

```
RECARGA BLOQUEADA: existem N pedido(s) criados NESTE sistema (id > M, a faixa do legado).
```

A detecção é por evidência no banco, não por flag que alguém precisa lembrar de
ligar. Para forçar (só com backup recente): `--eu-sei-o-que-estou-fazendo`.

---

## ⚠️ Pendências que precisam estar resolvidas ANTES de abrir a janela

Estas não são passos do cutover — são bloqueios que o antecedem:

1. **Senha `admin1234` ainda válida em 2 contas com `support=true`**
   (`PENDENCIAS_OPERACIONAIS_F3.md`). Acesso administrativo com senha conhecida.
   Exige troca manual — a senha nova é escolha do dono.
2. **Conferência humana dos dois códigos de barras**: imprimir um boleto (I2of5)
   e um DANFE (Code 128C) e passar num leitor de caixa real. São símbolos
   diferentes; dois testes separados. Os testes automatizados provam
   conformidade com a especificação, não legibilidade física.
3. **Divergência de saldo herdada do legado** (`T5.1_ACHADOS.md` §4): o saldo
   materializado não deriva dos movimentos — na origem, não na migração.
   Conferir 2–3 contas ativas contra extrato bancário real para decidir qual dos
   dois números é o verdadeiro. Se for o derivado, 28 contas mudam de saldo.
4. **Duas decisões de escopo** (`TRIAGEM_LACUNAS_F4.md` §2 e §3): a conciliação
   física de malote ainda acontece? o call-center usa bina? Podem adicionar dois
   itens bloqueantes ou remover ambos do escopo.
5. **7 pendências operacionais da F3** (`PENDENCIAS_OPERACIONAIS_F3.md`).
