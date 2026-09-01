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

## F9-07 — medir antes de remover

A tarefa tem três partes em ordem de dependência: **medir uso**, **substituir
por contratos canônicos**, **remover gradualmente**. A primeira estava faltando
inteira, e sem ela as outras duas não são executáveis — só apostáveis.

### O que já estava certo

A terceira exigência da tarefa — *"falha HTTP não é estado de domínio"* — já
estava atendida desde a F0. O `DialetoLegado` traduz 422 em `OPS` e o resto em
`NOK`, sempre em HTTP 200: o app em campo mostra "sem limite no convênio" em vez
de "erro de conexão". Não mexi nisso; conferi e registro aqui para que ninguém
refaça.

### O que faltava

Ninguém contava nada. Os 29 endpoints de ponte não têm um número que diga quais
ainda são chamados, por qual revenda, por qual versão de APK.

Isso trava a remoção nos dois sentidos:

 - **remover por leitura de código** é apostar. O MovelApp está em `targetSdk 28`
   e **não publica na Play Store** — um endpoint removido cedo demais vira venda
   travada em campo, sem correção rápida do outro lado;
 - **não remover nada** faz a ponte "com data para morrer" virar permanente.

### O desenho

`ponte_usos`, agregado por **(ponte, endpoint, empresa, dia)** — o mesmo desenho
de `integracao_consumos` (F6-01) e pela mesma razão: o app faz *polling* de
`getPedidosPendentes`, e uma linha por chamada cresceria mais rápido que o pedido
que ela acompanha.

O ponto de captura é o **middleware**, não cada controller. Instrumentar
controller a controller depende de alguém lembrar — e instrumentação incompleta é
pior que nenhuma, porque autoriza remover justamente o que ela não viu.

Três decisões que mudam a leitura do número:

 - **recusa conta separado.** Endpoint muito chamado e sempre recusado não é uso,
   é app velho insistindo. Somá-los faria "está em uso" ser falso, e a ponte
   nunca sairia;
 - **PDF conta.** `visualizarDanfe` e `visualizarBoleto` saem do middleware antes
   da tradução de dialeto; deixá-los fora faria dois endpoints reais parecerem
   mortos;
 - **versão comparada como versão.** `'1.10' > '1.9'` é falso em string. Guardar
   `1.9` como a mais nova inverteria a decisão: pareceria que só APK velho chama
   o endpoint, e a ponte sairia embaixo de quem atualizou.

### O relatório mostra o ZERO

`ponte:uso` parte das **rotas registradas no router**, não da tabela de medição.
A diferença não é cosmética: partindo da tabela, o endpoint nunca chamado não
apareceria, e o silêncio se confundiria com ausência de dado — a mesma armadilha
que esta base já pagou duas vezes (registry vazio imprimindo "concluído", teste
que varria zero arquivos).

Se o comando não enxergar rota nenhuma, ele **reprova**. A alternativa seria
imprimir uma tabela vazia dizendo que ninguém usa nada — saída que autorizaria
remover as 29 rotas de uma vez.

### Um comentário meu que estava errado

Escrevi que `where('empresa_id', null)` geraria `= NULL` e duplicaria linhas.
Plantei a regressão para provar e o teste **não pegou** — porque o query builder
do Laravel converte para `IS NULL` sozinho. Conferi com `toSql()`.

O comentário virou o que é verdade: o `whereNull` é legibilidade, e quem guarda o
agregado é o teste, que reprova qualquer reescrita (SQL à mão, `updateOrInsert`
com chave em array) que perca a comparação com nulo. Com `whereRaw('empresa_id =
NULL')` plantado, **quatro** testes falharam.

### O que continua aberto

**Substituir** e **remover** dependem do número que a medição vai produzir em
produção. Hoje `ponte_usos` está vazia porque acabou de nascer — e remover com
base numa tabela recém-criada seria exatamente o erro que ela existe para evitar.

## F9-08 — os três cenários que não precisam de navegador

A tarefa lista sete: *sessão A→logout→B, tenant→SuperAdmin, requests em voo, duas
abas, cache persistido, troca de empresa e tentativa de IDs externos*.

Quatro exigem navegador de verdade. **Três não**, e conferi um a um:

| Cenário | Estado |
|---|---|
| tentativa de IDs externos | já coberto — `TrocaAdversarialDeIdsTest` varre as rotas E tem guardião de cobertura |
| troca de empresa | já coberto — `TrocaDeEmpresaRegraTest`, inclusive "trocar não concede acesso a outra rede" |
| **tenant → SuperAdmin** | **tinha furo** |

### O furo

`SuperAdminTest::test_usuario_de_tenant_nao_acessa_superadmin` existia e estava
certo — mas cobria **uma rota de 34**. Um teste assim protege até alguém
adicionar a 35ª esquecendo o guard, que é justamente quando a proteção
importaria.

O gate F9 exige *"todas as mutações críticas têm autorização negativa"*. A
palavra é **todas**, e honrá-la significa partir da lista de rotas do **router** —
nunca de uma lista escrita à mão, que envelhece em silêncio.

`EscaladaParaPlataformaTest` varre as 34 e testa duas coisas: token de tenant não
passa, e sem token nenhum também não. A segunda parece óbvia e não é — uma rota
registrada fora do grupo com `auth:platform` responde 200 para qualquer um, e o
sintoma é o dado de **todas as revendas** exposto sem autenticação.

Com uma rota de plataforma plantada fora do grupo, os dois testes reprovaram
apontando o nome dela.

O `assertGreaterThan(20, ...)` não é decoração: varredura que varre zero e passa
já aconteceu aqui mais de uma vez.

## Aberto

A parte de navegador do **F9-08** — duas abas, cache persistido e requests em voo
exigem Playwright, que este projeto não usa. Os cenários de cache que dão para
cobrir sem navegador já estão em `cache-isolamento.test.ts`.
