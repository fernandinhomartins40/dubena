# F9 — Progresso

Data: 2026-08-31 (America/Sao_Paulo)

## As nove tarefas

| Tarefa | Estado | Onde |
|---|---|---|
| F9-01 — API | **já estava** | manifesto de 600 endpoints; `api:manifest` é contrato |
| F9-01A — Lote | **já estava** | catálogos grandes usam chunk/cursor |
| F9-02 — Contexto visível | **já estava** | `/me` devolve `tenant` efetivo separado de `user` |
| F9-03 — Cache | fechada | logout e troca de empresa limpam de verdade |
| F9-04 — Persistência | **já estava** | filtro em `sessionStorage`, por decisão registrada |
| F9-05 — Mutação | **já estava** | backend é autoridade; UI reflete |
| F9-06 — White-label/localidade | fechada | fuso, título, `APP_NAME`, locale |
| F9-07 — Pontes | aberta | medir uso e substituir gradualmente |
| F9-08 — E2E adversarial | parcial | os cenários de cache cobertos por teste |

## Os três defeitos encontrados

**O fuso da plataforma era UTC** (F9-06) — e este é o mais caro da rodada
inteira, porque tem efeito contábil.

Medi ao vivo: eram **22:34 em São Paulo** e `now()` devolvia `2026-09-01 01:34`.
O **dia divergia**. Toda venda feita depois das 21h caía no dia errado, levando
junto o fechamento diário, a comissão do entregador e o DRE.

A suíte não pegava porque os testes criam pedidos com `now()` e conferem com
`now()`: o deslocamento é consistente dos dois lados e some. E ninguém confere se
o pedido das 22h está no relatório de ontem ou de hoje — a descoberta viria pelo
total do dia que não bate com o caixa, e a investigação começaria pelo lugar
errado.

**O cache não morria no logout** (F9-03). Só `['me']` era zerado; clientes,
pedidos e financeiro da sessão anterior seguiam em memória. Numa revenda só isso
passava despercebido — o próximo login era a mesma pessoa. Num SaaS, A sai, B
entra na mesma máquina e vê a carteira de A até o refetch chegar.

O mesmo valia para o SuperAdmin, e ali é pior: ele enxerga **todas as revendas**.

**O branding era da primeira revenda** (F9-06). `index.html` servia
`Dubena · ERP` a todas, e `.env.example` trazia `APP_NAME="ERP Gas em Casa"` —
que é o fallback do título, o remetente de e-mail e o `VITE_APP_NAME`.

## A mudança de fuso é segura, e verifiquei antes

O schema usa `timestamp`/`datetime` **sem fuso**, e não há conversão em lugar
nenhum do código — nem no ETL. O valor gravado **não se move**; o que muda é a
interpretação daqui em diante.

Há teste que falha se alguém migrar para `timestamptz` — e falha **antes** de o
histórico se mover.

## Um teste corrigido, não contornado

`EscritaRestauradaTest` fixava `2026-09-15T00:00:00.000000Z` — o formato ISO em
UTC. A data continua 15/09; o que mudou é a serialização.

Prender teste ao formato é o que faz correção legítima parecer regressão. Passou
a asserir a **data**.

## Verificação

| Portão | Resultado |
|---|---|
| Suíte backend | **1713 passes / 5944 assertions** |
| Suíte frontend | **47 passes**, `tsc` limpo |
| Guardiões | verificados com regressão plantada |

## Aberto

**F9-07** (medir uso das pontes legadas e substituí-las por contratos canônicos)
e a parte de navegador do **F9-08** — duas abas, cache persistido e requests em
voo exigem Playwright, que este projeto não usa. Os cenários de cache que dão
para cobrir sem navegador já estão em `cache-isolamento.test.ts`.
