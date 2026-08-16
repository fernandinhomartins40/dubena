# Decisão sobre as tabelas Oracle sem destino no sistema novo

> **T2.7 do `PLANO_PRODUCAO.md`.** A auditoria (§6) identificou 15 tabelas com dados
> no Oracle e sem nenhum destino no sistema novo, somando ~56 mil linhas. A tarefa
> não é migrar tudo: é **registrar uma decisão explícita para cada uma**, porque foi
> justamente o implícito — a ausência que ninguém declarou — que criou o buraco.
>
> **Data da verificação:** 16 de agosto de 2026 (Oracle `dubena-ora2`, snapshot CTRL2QTI).
> **Método:** contagem real (`COUNT(*)`, não `num_rows`), estrutura via `user_tab_columns`,
> e cruzamento com o schema `legado` do Postgres e com as tabelas do sistema novo.

---

## Correção de premissa: 2 das 15 já estavam migradas

A lista da auditoria usou as grafias **Oracle** (`COLABORADORS`, `COLABORADORCOMISSAOS`),
mas o espelho as materializa com nome regularizado (`colaboradores`,
`colaboradorcomissoes`). São as mesmas tabelas, e já têm migrator e invariante:

```
cutover:check → [OK] contagem colaboradores→colaboradores — origem=destino=81
                [OK] contagem colaboradorcomissoes→colaborador_comissoes — origem=destino=872
```

Isso remove do escopo justamente os dois itens que o plano classificava como segundo
maior risco (RH/folha). **Restam 13 tabelas para decidir.**

---

## Quadro de decisões

| # | Tabela Oracle | Linhas | Decisão | Justificativa |
|---|---|---:|---|---|
| 1 | `CREDITOPISCOFINS` | 246 | **PORTAR** | Tributário. Ver §1. |
| 2 | `NFRECEBIDAPARCELAS` | 862 | **PORTAR** | Financeiro/fiscal. Ver §2. |
| 3 | `CONTAEXTRATOCONFIGS` | 16 | **PORTAR** | Fecha lacuna funcional da matriz (linha 11). Ver §3. |
| 4 | `CLIENTECONVENIOS` | 97 | **PORTAR (parcial)** | 18 convênios ausentes + campos de contrato. Ver §4. |
| 5 | `CLIENTEPRODUTOSCONVENIOS` | 135 | **APOSENTAR** | Já coberto por `clienteprecos`. Ver §4. |
| 6 | `ESTOQUEREQUISICAOS` | 109 | **ARQUIVAR** | Ver §5. |
| 7 | `ESTOQUEREQUISICAOITEMS` | 109 | **ARQUIVAR** | Idem (itens da mesma requisição). |
| 8 | `ESTOQUEFISICOSETORS` | 218 | **ARQUIVAR** | Ver §5. |
| 9 | `ESTOQUEPRODUTOS` | 12 | **APOSENTAR** | Ver §5. |
| 10 | `BENEFICIARIOS` | 480 | **APOSENTAR** | Ver §6. |
| 11 | `LOGCERCAS` | 39.929 | **ARQUIVAR** | Ver §7. |
| 12 | `LIGACOESTELEFONICAS` | 13.214 | **ARQUIVAR** | Ver §7. |
| 13 | `APPNOTIFICATIONS` | 67 | **APOSENTAR** | Ver §7. |

**Legenda.** *Portar* = criar tabela + migrator + invariante. *Arquivar* = manter
consultável no schema `legado` do Postgres, sem destino operacional. *Aposentar* =
o negócio abandona o dado; nada é preservado além do dump.

---

## §1 — `CREDITOPISCOFINS` (246) → **PORTAR**

Estrutura: `identificador`, `codigo`, `descricao` (CLOB), `parent_identificador`.
É a **tabela de códigos de crédito PIS/COFINS** da EFD-Contribuições — um catálogo
hierárquico (o `parent_identificador` monta a árvore), não movimento.

**Por que portar:** é insumo de geração do SPED Contribuições, que o novo já emite
(`SpedContribuicoesService`). Sem o catálogo, os registros do bloco M saem com código
em branco ou fixo no código-fonte. É dado tributário: erro aqui é multa, não estética.

**Risco de não portar:** ALTO — declaração acessória incorreta.

---

## §2 — `NFRECEBIDAPARCELAS` (862) → **PORTAR**

Estrutura: `nfrecebida_id`, `numeroparcela`, `referencia`, `datavencimento`,
`valororiginal`. São as **parcelas do contas a pagar geradas pela nota de entrada**.

O novo já migra `nfrecebidas` e `nfrecebidaitems` (ambas no espelho), e o
`NfEntradaService` tem o fluxo importar→processar→contas a pagar. Mas o histórico das
parcelas já emitidas fica de fora: as notas antigas chegam sem o parcelamento que as
acompanhava.

**Por que portar:** documento com efeito financeiro. Sem as parcelas, o contas a pagar
histórico fica incompleto e a conciliação com o fornecedor não fecha.

**Risco de não portar:** ALTO — passivo financeiro faltando.

---

## §3 — `CONTAEXTRATOCONFIGS` (16) → **PORTAR**

Estrutura: `descricao`, `conta_id`, `condicaopagamento_id`, `planoconta_id`,
`centrocusto_id`, `cliente_id`, `contamovimentotipo_id`, `acao`, `contaorigem_id`.

São as **regras de classificação automática do extrato bancário** — exatamente a lacuna
da linha 11 da matriz de paridade (Auditoria §2). O novo importa OFX pela conciliação,
mas sem estas regras cada linha do extrato precisa ser classificada à mão.

**Por que portar:** são só 16 linhas, mas cada uma economiza trabalho manual diário.
Portar o dado é barato; o caro é a funcionalidade que o consome — e essa é uma tarefa
de F4, não de F2. **Esta decisão cobre o DADO; a tela e o motor de classificação
seguem como pendência de paridade funcional.**

**Risco de não portar:** MÉDIO — retrabalho diário do financeiro.

---

## §4 — Convênios: `CLIENTECONVENIOS` (97) e `CLIENTEPRODUTOSCONVENIOS` (135)

### `CLIENTEPRODUTOSCONVENIOS` (135) → **APOSENTAR**

Estrutura: `cliente_id`, `produto_id`, `preco`. É **exatamente** o que
`public.clienteprecos` já guarda (cliente+produto+preço+desconto), e essa tabela já
está populada com **1.385 linhas** vindas de `CLIENTEPRODUTOS` (1.386 no Oracle).

O preço de convênio do legado é preço por cliente — o mesmo mecanismo. Portar a tabela
criaria uma segunda fonte de verdade para a mesma regra, que é pior que não portar.

**Risco:** BAIXO — o dado equivalente já está migrado e em uso.

### `CLIENTECONVENIOS` (97) → **PORTAR (parcial)**

Aqui há lacuna real, e ela é de dois tipos. Os convênios do novo são **derivados de
`conveniofechamentos`** (`SatelitesMigrator::migrarConvenios` cria um convênio por
cliente **que teve fechamento**), não do cadastro:

```sql
-- Oracle
SELECT COUNT(DISTINCT cliente_id) FROM CLIENTECONVENIOS;                  -- 97
   ...WHERE cliente_id     IN (SELECT cliente_id FROM CONVENIOFECHAMENTOS) -- 79
   ...WHERE cliente_id NOT IN (SELECT cliente_id FROM CONVENIOFECHAMENTOS) -- 18
```

1. **18 clientes conveniados nunca fecharam** → não existem como convênio no novo.
   São contratos ativos que somem do sistema.
2. **Os 97 perdem os campos de contrato**: `datacontrato`, `comissao`,
   `comissaodestino`, `limitecompra` e os dados do representante
   (`nomerepresentante`, `cpfrepresentante`, `rgrepresentante`). O destino
   (`public.convenios`) só tem `dia_fechamento`, `dia_vencimento` e `ativo`.

**Por que portar:** `limitecompra` é controle de crédito e `comissao` entra no
faturamento — sem eles o convênio opera sem trava e sem a regra de comissão. Os dados do
representante são o que o contrato impresso exige (lacuna de F4, linha 3 da matriz).

**Risco de não portar:** ALTO — 18 convênios invisíveis + limite de crédito ausente.

---

## §5 — Estoque: requisições (109+109), físico por setor (218), produtos (12)

**`ESTOQUEREQUISICAOS` + `ESTOQUEREQUISICAOITEMS` → ARQUIVAR.**
O novo tem requisição de estoque funcionando (`routes/api.php`, aba Requisição em
`features/estoque/tabs/`). O que falta é só o **histórico** de 109 requisições antigas —
documento interno de movimentação, já refletido nos saldos atuais (que batem: a
`BalanceInvariant` passa). Reconstituir o histórico não muda saldo nenhum.

**`ESTOQUEFISICOSETORS` (218) → ARQUIVAR.** Contagens de inventário físico já
encerradas. O resultado delas está nos saldos; a contagem em si é registro de auditoria
do passado.

**`ESTOQUEPRODUTOS` (12) → APOSENTAR.** 12 linhas. Inspecionada: é vínculo
produto↔estoque que o schema novo resolve por `estoquesaldos` (setor+produto). Não há
informação a preservar.

**Risco:** BAIXO — nenhum saldo depende disso, e as invariantes de saldo passam.

---

## §6 — `BENEFICIARIOS` (480) → **APOSENTAR**

Cadastro do programa Gás do Povo: `codbenef`, `descricao`, `datainicio`, `datafim`,
`uf` (todas PR). **Não é** o benefício concedido ao cliente — não tem cliente, NIS,
valor nem competência.

O novo tem `gasdopovo_beneficios`, que é o benefício CONCEDIDO (`cliente_id`, `nis`,
`competencia`, `valor`). Mapear um no outro inventaria dado, e foi tentar isso que
produziu o defeito da T2.3.

**Por que aposentar:** é catálogo de um programa governamental com vigência definida
(`datainicio`/`datafim`), gerido externamente. O sistema novo consome a regra do
programa vigente pela integração, não por cadastro local histórico.

**Risco:** BAIXO. *Se* o cliente quiser o histórico de programas, portar depois é
trivial (480 linhas, sem FK).

---

## §7 — Logs e histórico: cercas (39.929), telefonia (13.214), push (67)

**`LOGCERCAS` (39.929) → ARQUIVAR.** Log de entrada/saída de cerca virtual do GPS. O
novo tem o módulo monitora com cercas e eventos próprios (`app/Domain/Monitora/`). São
39.929 eventos históricos de rastreamento — volume alto, valor operacional nulo depois
do fato. Fica consultável no schema `legado`.

**`LIGACOESTELEFONICAS` (13.214) → ARQUIVAR.** Histórico do bina/identificador de
chamadas. O módulo de telefonia **não foi migrado** (linha 32 da matriz, ❌) e é uma das
decisões pendentes com o cliente: portar ou aposentar formalmente. Enquanto essa decisão
não é tomada, o dado fica arquivado — **não** aposentado, porque se a telefonia for
portada este histórico passa a ter dono.

**`APPNOTIFICATIONS` (67) → APOSENTAR.** Fila de push já entregue. O novo tem infra
própria (`PushService`, `EnviarPushJob`, FCM v1). Notificação entregue no passado não
tem valor de consulta.

**Risco:** BAIXO para as três.

---

## Consequência prática

- **4 tabelas a portar** (§1–§4): `CREDITOPISCOFINS` (246), `NFRECEBIDAPARCELAS` (862),
  `CONTAEXTRATOCONFIGS` (16) e `CLIENTECONVENIOS` (97) — **1.221 linhas**, todas de
  risco fiscal ou financeiro. Entram no `MAPA` do `espelhar_oracle.py` e precisam de
  tabela no schema novo, migrator e invariante.
- **6 arquivar**: ficam no schema `legado`, consultáveis, sem destino operacional.
- **5 aposentar**: decisão registrada; nada além do dump.

Duas premissas da auditoria foram corrigidas por verificação: `COLABORADORS`/
`COLABORADORCOMISSAOS` **já estavam migradas** (a lista usava a grafia Oracle, o espelho
usa o nome regularizado), e `CLIENTEPRODUTOSCONVENIOS` **já está coberta** por
`clienteprecos`. O escopo real da T2.7 é menor — e mais preciso — que o estimado.

O que **não** está coberto por esta decisão, e segue como pendência de **paridade
funcional (F4)**, não de dados: o motor de classificação automática do extrato que
consome `CONTAEXTRATOCONFIGS`, e a decisão do cliente sobre o módulo de telefonia.
