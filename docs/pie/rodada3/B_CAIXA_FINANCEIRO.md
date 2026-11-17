# Rodada 3 — Agente B: caixa e financeiro

**Data:** 2026-10-31 · **Commit de partida:** `9fcfde5a` · **Perfis:** caixa (no banco-base é o papel **Operador**, que tem `caixa.view/edit/fechar` e `financeiro.baixar`; não existe papel "Caixa") e gerente financeiro (papel **Gerente**, com `caixa.estornar`).

## 1. Ambiente

| Item | Valor |
|---|---|
| Banco | `pie_r3_b` (`create database pie_r3_b template pie_r3_base`), Postgres 15 local, porta 55432 |
| API | `php artisan serve --port=8212` como `erp_app`, `SAAS_ENFORCE_LICENCA=true`, `SAAS_ENFORCE_TENANT_ENVELOPE=true` (script do README do e2e) |
| SPA | `npx vite --port 5212 --strictPort` |
| Jornadas | `PIE_DB=pie_r3_b PIE_API_URL=http://127.0.0.1:8212 PIE_SPA_URL=http://localhost:5212/novo/app/ npx playwright test -c caixa/playwright.caixa.config.ts` |
| Backend | `php artisan test tests/Feature/PieR3CaixaFinanceiroTest.php` (sqlite) |

**Condição do ambiente que afetou a execução:** com sete agentes na mesma máquina, a API levou 18–33 s por requisição (`/api/login` 25 s; um `Maximum execution time of 30 seconds exceeded` no log). A espera padrão do harness (10 s) reprovava o **login** antes da jornada começar. Por isso há `e2e/caixa/playwright.caixa.config.ts`, que herda a config do harness e só aumenta os prazos (teste 180 s, `expect` 45 s) e desliga o relatório HTML (pasta compartilhada). Também por isso o projeto **celular** não foi executado nesta rodada (ver §6). A sessão do agente caiu duas vezes por limite de uso; API e Vite foram resubidos na mesma porta.

## 2. Matriz perfil × jornada

| Jornada | Caixa (Operador) | Gerente | Elo que falta |
|---|---|---|---|
| Ver contas, saldo e movimentos | **FALHA REPRODUZIDA → CORRIGIDA e VERIFICADA no navegador** (R3-B-01) | idem | — |
| Abrir/fechar caixa | IMPLEMENTADA NÃO VERIFICADA no navegador (login bloqueado pelo ambiente); servidor coberto por `CaixaTest` | idem | elo 1/9 no navegador |
| Conta sem abertura (P2-D-09) | **CORRIGIDA**: servidor provado e API em runtime; tela não confirmada (timeout) (R3-B-02) | idem | elo 1 no navegador |
| Receber título integral | IMPLEMENTADA NÃO VERIFICADA no navegador; servidor coberto por `CaixaTest`/`PieDinheiroRegressaoTest` | — | elo 9 no navegador |
| Receber com desconto "5,50" / juros "1.000,00" | **FALHA REPRODUZIDA (R2-08/P2-D-08) → CORRIGIDA** (R3-B-03) | — | — |
| Recusar "-10", "5%", "abc", desconto > total sem chamar o servidor | **CORRIGIDA** (R3-B-03) | — | — |
| Recebimento parcial (P2-D-16) | **IMPLEMENTADA** (R3-B-04) | — | decisão de imputação registrada |
| Duplo clique / duas abas no Receber | servidor já recusava (lock + "Parcela já baixada"); jornada escrita | — | duas abas não executada (§6) |
| Estornar movimento | n/a (sem permissão: botão oculto, API 403) | **IMPLEMENTADA (tela nova)** (R3-B-05) | — |
| Recibo do movimento em PDF | **IMPLEMENTADA (tela nova)** (R3-B-05) | idem | — |
| Malote: conferir e fechar | **FALHA REPRODUZIDA → CORRIGIDA** (R3-B-06) | idem | — |
| Cheque recebido: cadastrar, compensar | PARCIAL (tela existe; campo valor ainda `type=number`) | — | R3-B-09 |
| Boletos / remessa / retorno CNAB | BLOQUEADA na tela (rotas sem tela; §5) | — | decisão de produto |
| PIX (driver fake) | servidor: webhook tardio CORRIGIDO (PIE-24) | — | sem tela de cobranças PIX |
| Autorização: sem token 401, entregador 403 | VERIFICADA por API (ver §4) | — | — |

## 3. Achados

### R3-B-01 — Painel de movimentos do caixa mostrava "—", data vazia, "R$ NaN" e sinal trocado
- **Onde:** `frontend/src/features/financeiro/tabs/CaixaTab.tsx` (versão do HEAD, linhas 46–55) × `CaixaController::movimentos`.
- **Reprodução:** operador → Financeiro → Caixa → clicar em "Caixa Geral". A tela lia `mov.saldo`, `m.datahorabaixa`, `m.valorefetivado` e `m.pagarreceber`; a API (`GET /api/admin/caixa/1/movimentos`) devolve `datahora`, `valor` assinado e nenhum `saldo` (resposta real capturada: `{"data":[{"id":1,…,"tipo":"ABERTURA","pagarreceber":null,"valor":"2000.00",…}],"meta":…}`).
- **Esperado × observado:** saldo R$ 2.000,00 e "+ R$ 2.000,00 Saldo inicial" × "Saldo atual: —" (Playwright: `getByText(/Saldo atual:/)` com "2.000,00" não encontrado), valor `brl(Math.abs(undefined))`, e abertura/transferência/estorno (pagarreceber nulo) pintados como **saída**.
- **Severidade:** ALTA (o caixa não consegue conferir o dia pela tela). **Estado:** CORRIGIDO.

### R3-B-02 — P2-D-09: conta "Aberto" que não fecha e sem como abrir
- **Onde:** `CaixaService::criarConta` não cria fechamento; `CaixaTab` decidia o botão só por `fechado`.
- **Correção:** `GET /caixa/contas` devolve `estado` (`ABERTO` / `FECHADO` / `SEM_ABERTURA`); a tela mostra **"Sem abertura"** com o botão **"Abrir caixa"** (o `abrir()` do serviço já aceitava esse caso); depois o ciclo normal. Os botões passaram a se chamar "Abrir caixa"/"Fechar caixa" para não confundir com o "Abrir registro" da linha (apontado pela Frente D).
- **Não mudou:** a conta sem abertura continua aceitando movimento (conta de banco não "abre"). Ver decisão D-B-03.

### R3-B-03 — R2-08 / P2-D-08 no recebimento: "Receber -R$ 879,00", "-10" aumentava o valor, "1.000,00" virava R$ 1
- **Onde:** `BaixaDialog.tsx` (HEAD, l. 26 e 66–68): `Number(desconto)` em campo `type=number`.
- **Correção:** campos de texto lidos por `decimalBR` (o helper do P2-D-08); validação antes de enviar com as **mesmas regras do `CaixaService`** (negativo, inválido, desconto > total, parcial > total, parcial que não cobre encargos) e mensagem em pt-BR; o rótulo do botão nunca mostra valor negativo (com entrada inválida mostra só "Receber"). Sem rede: "Não foi possível registrar: sem conexão com o servidor. Nada foi gravado." O servidor continua recusando tudo isso por conta própria (R2-08 da Rodada 1).
- **Severidade:** MÉDIA (o servidor já barrava o negativo; o "1.000,00 → 1" gravava valor errado). **Estado:** CORRIGIDO.

### R3-B-04 — P2-D-16: recebimento parcial inexistente
- **Correção (servidor):** `POST /caixa/{conta}/baixar` aceita `valor_recebido`. `CaixaService::separarRecebimentoParcial` divide a parcela como o legado fazia (`ctrl-web/.../CaixaController.php:466-590`): a parte paga é baixada e o restante vira **parcela nova do mesmo título, mesmo vencimento**; Σ parcelas não muda; estornar a parte paga devolve o total em aberto. Juros e multa são quitados primeiro (CC art. 354). **Fail-closed:** recusa parcial com boleto PENDENTE/REGISTRADO ou PIX ATIVO na parcela (o banco ainda pode liquidar o valor cheio), recusa zero, maior que o total e valor que não cobre os encargos.
- **Tela:** campo "Valor recebido agora" (em branco = total) e "Fica em aberto: R$ …".
- **Estado:** IMPLEMENTADO e provado no servidor; regra de imputação em D-B-01.

### R3-B-05 — Estorno e recibo de movimento: rotas prontas sem tela
- `POST caixa/movimentos/{id}/estornar` e `GET caixa/movimentos/{id}/recibo` não tinham consumidor (costura). A regra é clara e já protegida no servidor (lock, índice único em `estorno_de_id`, recusa de estorno em dobro, reabertura da parcela pela porta única, ABAC `caixa.estornar`). **Ligadas à tela:** ícones "Recibo" e "Estornar" em cada movimento; estornar só aparece com `caixa.estornar`, nunca num ESTORNO nem num movimento já estornado (a API passou a devolver `estornado`), e pede confirmação explicando o efeito.

### R3-B-06 — Malote não fechava em revenda sem configuração e o botão dizia "Excluir"
- **Onde:** `MaloteTab.tsx`: não mandava `conta_id`; `MaloteService::fechar` exige `dados.maloteconta_id` (o banco-base não tem `empresa_configs` nenhum) → "Nenhuma conta de malote configurada" sem saída na tela. O `ConfirmDialog` sem `confirmLabel` usa o padrão **"Excluir"** em vermelho para *fechar malote*.
- **Correção:** campo "Conta do malote" (opcional; em branco usa a configurada), confirmação "Fechar malote" (variante padrão) citando a conta. `useFecharMalote` invalidava `['caixa']`, chave que não existe: trocado pelas chaves reais (o saldo ficava velho).

### R3-B-07 — Lista de títulos e recebimento sem nome do cliente
- `FinanceiroController::lancamentos` não devolvia `cliente`; a coluna "Cliente" e o cabeçalho do recebimento mostravam "—"/"Sem cliente/fornecedor" em todo título. **Corrigido** (eager load `financeiro.cliente:id,nome`).

### R3-B-08 — PIE-25 e PIE-24 (Rodada 1)
- **PIE-25** (`BoletoService::gerarRemessa`, `max+1` sem trava): agora usa `NumeroSequencialService` (`SELECT … FOR UPDATE`, chave `boleto:empresa:{id}:remessa`, partindo do maior número existente). Duas remessas simultâneas teriam o mesmo número e o **segundo arquivo sobrescreveria o primeiro** (mesmo caminho em disco). **CORRIGIDO.** A CC-e (também citada no PIE-25) é do Fiscal: fica para o Agente F.
- **PIE-24** (webhook PIX depois da expiração local → 422 sem rastro): cobrança `EXPIRADA` com valor que bate vira **`PAGA_APOS_EXPIRAR`** (e2eid e `pago_em` gravados, `Log::error` para conciliar, resposta 200, reentrega idempotente), **sem baixa automática** (D-B-02). Valor divergente continua 422, agora com `Log::warning` no controller. **CORRIGIDO (parte independente da decisão).**

### R3-B-09 — Cheque recebido: valor em `type=number` (MÉDIA, aberto)
`FinanceiroExtraTabs.tsx` (ChequesRecebidosTab, campo "Valor"): mesmo padrão do R3-B-03 ("1.000,50" não é digitável). Não corrigido nesta rodada por tempo; correção é a mesma do `BaixaDialog`.

### R3-B-10 — Dado de demonstração viola a invariante do caixa (BAIXA, para o coordenador)
`pie_r3_base`: "Banco - Conta Movimento" com `saldo_atual = 15000.00` e **zero** movimentos (Σ movimentos = 0). O seeder grava saldo direto. `financeiro:conferir` acusaria; jornadas que conferem a invariante usam só o "Caixa Geral".

## 4. Correções e prova

**Teste de aceite:** `tests/Feature/PieR3CaixaFinanceiroTest.php` (11 testes).

| Execução | Resultado |
|---|---|
| **Sem a correção** (arquivos de produto ainda no estado do `HEAD` — conferido por `git status`, nenhum deles modificado — e o teste novo já escrito) | **10 falham, 1 passa** (`valor divergente continua recusado`, guarda de não-regressão). Ex.: `p2 d 16 … Failed asserting that two strings are identical`; `movimentos … null is identical to 80`; `lancamentos … null is identical to 'Fulana de Tal'`; PIE-24 e PIE-25 falham |
| **Com a correção** | **11 passed (46 assertions)** |

Arquivos alterados (todos da área B):
- `erp-novo/app/Domain/Caixa/CaixaService.php` — parcial (`valorRecebido`, `separarRecebimentoParcial`)
- `erp-novo/app/Http/Controllers/Api/Admin/CaixaController.php` — `valor_recebido`, `estado`, `saldo`, `estornado`
- `erp-novo/app/Http/Controllers/Api/Admin/FinanceiroController.php` — `cliente` nos lançamentos
- `erp-novo/app/Domain/Cobranca/BoletoService.php` — PIE-25
- `erp-novo/app/Domain/Cobranca/PixService.php`, `SituacaoPix.php`, `app/Http/Controllers/Api/PixWebhookController.php` — PIE-24
- `erp-novo/frontend/src/features/financeiro/api.ts`, `tabs/BaixaDialog.tsx`, `tabs/CaixaTab.tsx`, `tabs/MaloteTab.tsx`
- Novos: `erp-novo/tests/Feature/PieR3CaixaFinanceiroTest.php`, `erp-novo/e2e/caixa/{caixa.spec.ts,apoio.ts,playwright.caixa.config.ts}`

Nenhuma rota nova (só campo novo em rota existente): `api:manifest` não precisa rodar. Nenhuma migration: `situacao` de `pix_cobrancas` é `varchar(20)` e `PAGA_APOS_EXPIRAR` tem 17 caracteres.

### Resultados de runtime (navegador e API reais, `pie_r3_b`, `erp_app`)

| Prova | Antes da correção | Depois |
|---|---|---|
| E2E `o caixa vê saldo e movimentos legíveis da conta` (operador, desktop) | **FALHA**: `getByText(/Saldo atual:/)` com "2.000,00" não encontrado | **PASSOU** (saldo, "Saldo inicial", nenhum "NaN") |
| `GET /api/admin/caixa/contas` como gerente | sem campo de estado (`fechado:0` nas duas contas) | `Banco…: "estado":"SEM_ABERTURA"`, `Caixa Geral: "estado":"ABERTO"` |
| `GET /api/admin/caixa/1/movimentos` | sem `saldo`; campos `datahora`/`valor` que a tela não lia | `saldo` e `estornado` na resposta (coberto pelo teste de feature) |
| Demais jornadas (`integral`, `nome do cliente`, `vírgula/milhar`, negativos, parcial, duplo clique, estorno, malote) | não chegaram a rodar | **BLOQUEADAS pelo ambiente**: o login da SPA falhou por tempo; a API respondeu `500 Maximum execution time of 30 seconds exceeded` dentro do `AbstractHasher` (bcrypt) com a máquina saturada. O E2E do P2-D-09 também estourou os 45 s esperando a linha do gerente, embora a API devolva o estado certo |

Nenhuma dessas jornadas bloqueadas está declarada como VERIFICADA no navegador. O comportamento delas está provado no servidor pelo teste de feature (parcial, estado da conta, saldo, cliente) e na SPA por `tsc` e `vitest`; as jornadas estão escritas para o coordenador ou o verificador rodarem com a máquina livre:
`PIE_DB=pie_r3_b … npx playwright test -c caixa/playwright.caixa.config.ts`.

**Regressão:**
- `php artisan test --filter="Caixa|Financeiro|Baixa|Malote|Cheque|Cobranca|Pix|PieDinheiro|Boleto|Cnab|Conciliacao|PortaUnica|AbacVerbos|PagamentoTest|PiePagamentoOnline|ApiContratoDrift"`: **250 passed, 1 skipped, 0 falhas** (1454 asserções).
- `npx tsc --noEmit`: **0 erros nos arquivos do financeiro**. Os 5 erros restantes estão em `src/components/ui/button.tsx:44` e `src/lib/pie-r3-plataforma.test.tsx`, que são de outro agente e estavam sendo editados.
- `npx vitest run`: 174/181 na suíte inteira. As 7 falhas foram timeouts de 5 s com a máquina saturada (duas no financeiro, sendo que `ChequesTab` nem foi alterado). `npx vitest run src/features/financeiro --testTimeout=60000`: **3 arquivos, 4/4 passaram**.

## 5. Triagem das rotas sem consumidor (costura)

| Rota | Decisão |
|---|---|
| `POST caixa/movimentos/{id}/estornar`, `GET caixa/movimentos/{id}/recibo` | **(b) metade → tela ligada** (R3-B-05) |
| `POST caixa/contas` (nova conta), `POST caixa/transferencias` | (b) metade; regra clara e protegida (owner validado, saldo inicial como movimento). **Não ligada** por tempo — próxima tarefa de baixo risco |
| `POST caixa/{conta}/baixar-titulos` | (b) baixa em lote (tudo-ou-nada). O malote já usa o serviço; a tela de lote em "A pagar e receber" fica para depois (sem decisão de negócio) |
| `POST caixa/{conta}/lancar-fechado` | (b) mas **decisão**: lançamento retroativo em caixa fechado só com `caixa.edit` — o legado exigia período de fechamento existente. D-B-04 |
| `POST financeiro/lancamentos/agrupar`, `…/{id}/desagrupar`, `…/{id}/reparcelar` (hook `useReparcelarLancamento` existe e nenhuma tela o usa), `DELETE financeiro/lancamentos/{id}` | (b) metade. Servidor protegido (PIE-06..08). Ligar exige tela por **título** (a lista atual é por parcela). Não ligado: registrar como tarefa |
| `POST financeiro/conciliacao`, `…/casar`, `…/desfazer`, `GET conciliacao-contabil` | (b) a aba Conciliação só faz o GET; upload OFX e casamento manual sem tela. Além disso a aba lê `m.datahorabaixa/valorefetivado` — mesmo padrão do R3-B-01, **não verificado** |
| `boletos` (gerar, `remessa`, `retorno`, `{id}/pdf`, `remessas`, arquivo) e aliases `cobranca/*` | (b) a aba Boletos só lista. Geração/remessa/retorno exigem conta de cobrança bancária configurada e homologação com o banco (passar boleto em leitor — `GUIA_DO_DONO`). **Bloqueada por ambiente**, não por código |
| `POST pix/cobrancas` | (c) consumida pelo fluxo do app/pedido; para parcela avulsa não há tela. Decisão de produto (cobrar título por PIX no balcão) |
| `POST cheques/{id}/encontro-de-contas` | (b) metade; regra de encontro de contas é de negócio (quais títulos compensam). D-B-05 |

## 6. Para outros donos

- **Coordenador / harness (`e2e/support/fixtures.ts`):** a espera de 10 s do login é curta para a máquina com sete agentes (login 25 s). Sugestão: `expect` configurável por `PIE_EXPECT_MS`. Seed `pie_r3_base`: R3-B-10.
- **Agente F (Fiscal):** a numeração de **CC-e** citada no PIE-25 continua por `max+1` (não verificada por mim).
- **Agente C (layout):** nada.

## 7. Decisões pendentes (com recomendação)

- **D-B-01 — Imputação do recebimento parcial.** Implementado: juros e multa primeiro (CC art. 354), desconto aplicado no recebimento parcial, restante com o mesmo vencimento. Alternativa do legado: rateio proporcional de juros/multa/desconto entre a parte paga e a residual. **Recomendação:** manter a regra legal; confirmar com o contador da revenda.
- **D-B-02 — PIX pago depois da expiração local (`PAGA_APOS_EXPIRAR`).** Baixar automaticamente a parcela/pedido se ainda estiver em aberto e sem outro PIX concluído, ou exigir conciliação manual? **Recomendação:** baixa automática quando a parcela está aberta e não houve outro recebimento; caso contrário, pendência de estorno ao cliente. Falta também uma tela de "PIX a conciliar".
- **D-B-03 — Conta "Sem abertura" aceita movimento?** Hoje sim (necessário para conta de banco). **Recomendação:** marcar no cadastro se a conta tem controle de abertura (caixa físico) e, nas que têm, recusar movimento sem caixa aberto.
- **D-B-04 — Lançamento em caixa fechado (`lancar-fechado`).** Basta `caixa.edit`? **Recomendação:** permissão própria (`caixa.lancar_fechado`) e data obrigatoriamente dentro de um período de fechamento existente, como no legado.
- **D-B-05 — Encontro de contas de cheque:** quais títulos podem compensar e com que alçada.

## 8. Não testado (e por quê)

- **Projeto celular (390×844 / Pixel 7)** e **axe** completo: as jornadas e as chamadas `acessibilidade()` estão no spec, mas não houve janela de execução com a máquina saturada.
- **Duas abas, rede caindo, sessão vencida, recibo, estorno, parcial e malote no navegador:** escritos em `e2e/caixa/caixa.spec.ts`; o que efetivamente rodou está em §4 (resultados de runtime).
- **Concorrência real no Postgres** para PIE-25 (o teste prova que a numeração passa pela sequência travada, não dispara duas transações simultâneas). Teste Pg novo para o portão: **nenhum** nesta rodada.
- Cheques, conciliação, DRE, regras de extrato: não percorridos.
