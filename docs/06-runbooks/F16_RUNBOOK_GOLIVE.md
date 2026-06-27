# F16 — Runbook de Cutover & Go-Live

> Procedimento operacional da virada CTRL-WEB → ERP-NOVO/SPA. Pré-requisito:
> fases F00, F02, F08, F09, F15 concluídas (bloqueadores de produção fechados).
> Este runbook é executável por um operador; cada passo tem comando, critério de
> sucesso e ponto de rollback. Data: 2026-06-25.

---

## 0. Pré-condições (D-7 a D-1)

| # | Item | Como validar | Bloqueia? |
|---|---|---|---|
| 0.1 | Banco novo PostgreSQL provisionado | `php artisan migrate:status` | Sim |
| 0.2 | Migrations aplicadas (inclui RLS F02, eventos fiscais F09, IBPT) | `php artisan migrate --force` | Sim |
| 0.3 | Certificado A1 por empresa carregado | `golive:check` (item Certificado) | Sim (se fiscal real) |
| 0.4 | Conta de cobrança por empresa (agência/conta/carteira/convênio) | `golive:check` (item cobrança) | Não (WARN) |
| 0.5 | Gates de produção definidos no `.env` | ver §1 | Sim |
| 0.6 | Homologação SEFAZ OK (NF-e autorizada em ambiente de teste) | manual (PVA/portal) | Sim |
| 0.7 | Homologação bancária CNAB (remessa aceita pelo banco) | manual (banco) | Não (pode ir só PIX) |
| 0.8 | Backup do legado + snapshot do banco novo | dump verificado | Sim |

---

## 1. Configuração de produção (`.env`)

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql      # + host/port/database/username/password
# Gates externos (ativar conforme homologação):
FISCAL_DRIVER=nfephp     # SEFAZ real (exige A1 por empresa)
COBRANCA_DRIVER=caixa    # ou itau; senão fica 'fake'
CONSISA_API_URL=...      # conciliação contábil (opcional)
IBPT_CSV_URL=...         # atualização IBPT (opcional)
PIX_ENABLED=true         # se usar PIX
```

Depois: `php artisan config:cache` (os gates são lidos via `config()`, compatível com cache).

---

## 2. Portões automáticos (rodar e exigir verde)

```bash
# Portão 1 — prontidão de produção (config, RLS, tenant, gates, certificado).
php artisan golive:check            # deve sair 0 (sem FAIL)
php artisan golive:check --strict   # opcional: exige gates reais (sem WARN)

# Portão 2 — integridade dos dados migrados (Count/Integrity/Sum/Balance).
php artisan cutover:check           # deve sair 0
```

**Critério:** ambos exit 0. `golive:check` em FAIL bloqueia; WARN exige decisão consciente
(ex.: subir só com PIX e boleto/SEFAZ ainda Fake → ciente de que não fatura NF-e real).

---

## 3. Janela de cutover (D-Day)

1. **Congelar o legado** (somente leitura): impedir novos lançamentos no CTRL-WEB.
   *Rollback:* reabrir o legado para escrita.
2. **Migração final (delta)**:
   ```bash
   php artisan etl:run --check       # carga + invariantes; deve sair 0
   ```
   Cobre os 20 migrators (15 base + RH/Frota/CRM/Gestão/Pagamentos da F15).
   *Critério:* `etl:run --check` verde (contagens origem=destino, zero órfão).
   *Rollback:* restaurar snapshot do banco novo (passo 0.8) e voltar ao passo 1.
3. **Smoke test em produção** (1 transação real de cada, em UMA empresa piloto):
   - **Venda → estoque/financeiro**: criar pedido, concluir, conferir baixa de estoque + título.
   - **Fiscal**: emitir 1 NF-e/NFC-e → autorizada (cStat 100). Cancelar 1 → homologado.
   - **Cobrança**: gerar 1 boleto → remessa `.rem` válida; OU 1 cobrança PIX → QR.
   - **Multi-tenant**: logar com usuário de outra empresa → não vê dados da piloto.
   *Critério:* todos passam. *Rollback:* §5.
4. **Liberar acesso** à SPA para os usuários; manter legado em leitura por 48h.

---

## 4. Pós-go-live (D+1 a D+7)

- Conferir os **agendamentos** (`schedule:run` via cron): `pix:expirar`, `vendas:diaria`,
  `notify:alertas`, `financeiro:notificar-vencidos`, `notify:inconsistencias`, `ibpt:atualizar`.
- Monitorar `notify:inconsistencias` (saldo estoque/caixa) — deve reportar 0.
- Acompanhar emissão fiscal e retorno bancário (CNAB) reais.
- Após 48–72h estáveis: **desativar o legado** (passo final).

---

## 5. Plano de rollback

Gatilho: smoke test falha, divergência de saldo, ou emissão fiscal/cobrança quebrada
em produção que não se resolve em < 2h.

1. Bloquear escrita no ERP-NOVO (modo manutenção: `php artisan down`).
2. Restaurar o **snapshot do banco novo** (0.8) se houver dados inconsistentes.
3. Reabrir o **legado** para escrita (reverter passo 3.1).
4. Redirecionar usuários de volta ao CTRL-WEB.
5. Post-mortem: identificar a causa (invariante? gate? config?), corrigir, reagendar.

**RPO/RTO alvo:** RPO = último snapshot (passo 0.8 / delta); RTO ≤ 2h (reabertura do legado).

---

## 6. Critérios de aceite do go-live (da spec F16)

- [ ] `golive:check` exit 0 (sem FAIL).
- [ ] `cutover:check` exit 0 (invariantes 100% verdes).
- [ ] Operação real comprovada: **fatura** (NF-e autorizada), **cobra** (boleto/PIX),
      **rastreia** (posição GPS) — em produção multi-tenant.
- [ ] Isolamento entre empresas validado (RLS ativo + smoke cross-tenant).
- [ ] Legado desativado após período de observação.

> Observação honesta: os passos 0.6/0.7 (homologação SEFAZ/banco) e o 3.3 (smoke real)
> dependem de credenciais e ambientes externos — são execução operacional, fora do código.
> Todo o ferramental para suportá-los (`golive:check`, `cutover:check`, `etl:run --check`,
> drivers reais atrás de gate) está implementado e testado.
