# F2-06 — As quatro trilhas passam a falar a mesma língua

Data: 2026-08-31 (America/Sao_Paulo)

## O achado

O sistema tem quatro trilhas de auditoria. Medido antes de mexer:

| Tabela | tenant_account_id | correlation_id | motivo |
|---|---|---|---|
| `audit_logs` | coluna existia (000300) | ✗ | dentro do JSON `depois` |
| `login_logs` | coluna existia (000300) | ✗ | ✓ |
| `security_events` | coluna existia (000300) | ✗ | ✗ |
| `platform_audit_logs` | ✗ | ✗ | ✗ |

O detalhe que muda a leitura da tarefa: **três das quatro já tinham a coluna
`tenant_account_id`** — adicionada pela migration `000300` — e **nada a
preenchia**. Não era um problema de schema esperando migration; era uma coluna
vazia esperando código. Coluna vazia não responde pergunta nenhuma, e é pior que
coluna ausente, porque parece resolvida.

Descobri isso rodando as migrations contra um PostgreSQL real e lendo o `\d` das
tabelas — a suíte em sqlite não teria mostrado a FK que denunciou a origem.

## Por que isso importa num SaaS

**`tenant_account_id`:** com N revendas, "empresa 2" não identifica ninguém
sozinho. Duas revendas podem ter unidades homônimas, e a pergunta de uma
investigação é "o que aconteceu no tenant X".

**`correlation_id`:** uma ação humana vira várias linhas em tabelas diferentes —
o update do model em `audit_logs`, o 403 em `security_events`, a intervenção em
`platform_audit_logs`. Sem um fio comum, reconstruir "o que aconteceu naquele
clique" é adivinhação por timestamp.

**`motivo` como coluna:** já existia em `audit_logs`, escondido dentro do JSON
`depois`. Dentro do JSON não dá para filtrar nem para exigir.

## A decisão de desenho

`ContextoAuditoria` é o ponto único que responde "de qual tenant e de qual
requisição". As quatro trilhas passam por ele.

**A ordem da correlação é envelope > header `X-Request-Id` > gerado.** O envelope
vem primeiro porque é o mesmo valor que os jobs despachados carregam: usar outro
aqui romperia justamente o fio entre a ação HTTP e o trabalho assíncrono dela.
O header vem antes do gerado porque liga a trilha ao log de requisição do
servidor, em vez de criar um identificador paralelo.

**A trilha de plataforma deriva o tenant da empresa ALVO.** O SuperAdmin opera
sem tenant resolvido — é assim por desenho, senão ele não cruzaria empresas.
Então o tenant não pode vir do envelope. Vem de quem é o dado tocado. Ação global
(criar um plano) fica sem tenant, sem inventar um.

## O bug que o teste pegou

A primeira versão memorizava o `correlation_id` num campo da classe, registrada
como `scoped`. Parecia certo: "scoped = uma por requisição".

Não é. O container resolve serviços no **boot**, antes da requisição, e sob
Octane a mesma instância atende requisições seguidas. Confirmei comparando
`spl_object_id` dentro e fora da requisição: **o mesmo objeto**.

A consequência seria a trilha afirmando que ações de clientes diferentes vieram
do mesmo clique — exatamente o oposto do que esta tarefa existe para garantir.

A correção é memorizar por requisição, num `WeakMap` indexado pelo objeto
`Request`, com um campo separado para execuções sem requisição (console, fila).
O teste `test_requisicoes_distintas_nao_compartilham_o_fio` trava a regressão.

## O fio precisa ser navegável

Gravar `correlation_id` e não deixar filtrar por ele deixaria a resposta
enterrada no banco. Então:

- `ConsultaTrilha` aceita o filtro `correlacao`, **dentro** do `where empresa_id`
  já existente — o fio é um recorte dentro do tenant, nunca uma porta para fora
  dele (há teste adversarial para isso: linha de outra empresa com o mesmo fio
  não é alcançada);
- a linha da trilha expõe `correlacao`, e a tela ganha "Ver tudo que veio deste
  clique" com um indicador removível do recorte ativo;
- `motivo` lê a coluna primeiro e cai no JSON como fallback — a trilha é
  append-only, não se reescreve o passado para uniformizar formato.

## O rollback que teria destruído trabalho alheio

O `down()` inicial dropava `tenant_account_id` das quatro tabelas. Em três delas
a coluna é da migration `000300`: o rollback desta migration teria destruído o
trabalho de outra. Agora só `platform_audit_logs` perde a coluna — as demais
perdem apenas o que esta migration criou. Verificado com up → down → up contra
PostgreSQL real.

## Verificação

| Portão | Resultado |
|---|---|
| Suíte PHP (modo padrão) | **1393 passaram**, 8 skipped |
| Suíte PHP (enforcement ligado) | 103 falhas — **idênticas às de antes** desta tarefa (backlog de fixtures sem plano, 402) |
| `RlsCoberturaTest` (PostgreSQL real) | **6/6** |
| Migrations up → down → up (PostgreSQL real) | OK, sem perda de coluna alheia |
| `tsc --noEmit` | limpo |
| Vitest | 39 passaram |
| Pint | limpo |

## O que fica em aberto

`audit_logs`, `login_logs` e `security_events` têm FK
`tenant_account_id → tenant_accounts ON DELETE RESTRICT`, herdada da `000300`.
Numa exclusão de tenant, isso bloquearia a operação — toda conta real tem
trilha. **Hoje é inócuo: não existe exclusão de tenant no sistema.** Quando F2
ou F3 introduzir esse fluxo, a decisão terá de ser explícita (anonimizar o
tenant na trilha, ou mover para arquivo frio), porque apagar a trilha junto com
a conta apaga a prova do que a conta fez.

Não alterei a FK aqui: mudá-la é decisão de retenção, não de correlação, e
migration destrutiva não deve viajar junto com feature.
