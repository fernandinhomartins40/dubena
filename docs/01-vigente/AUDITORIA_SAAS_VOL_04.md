# Volume 4 — Pedido, Venda, Produto, Estoque

> Recorte: `app/Domain/Pedido/` (3 arquivos), `app/Domain/Venda/` (6),
> `app/Domain/Produto/` (2), `app/Domain/Estoque/` (1) — **1.852 linhas, 12
> arquivos, todos lidos integralmente**. Fonte: código e banco.
>
> **Status: FECHADO.**

---

## O que funciona (verificado)

**O saldo de estoque é auditável de verdade.** `EstoqueService::movimentar()`
faz, no mesmo lock pessimista: grava histórico com quantidade assinada, atualiza
o saldo, recalcula custo médio ponderado. A invariante Σ histórico = saldo é
sustentada por construção, e `saldoDerivado()` existe para conferi-la.

**A máquina de estados do pedido é explícita e idempotente.**
`estoque_movimentado` impede baixa dupla; a transição de efeito
(PENDENTE↔CONCLUIDO↔CANCELADO) decide a movimentação, substituindo a matriz
implícita do legado.

**A alçada de desconto é fail-closed.** Sem regra cadastrada, teto zero — não
"sem limite". E o piso `preco_venda_minimo` do produto vale para todos,
inclusive para desconto já aprovado pela Central: é limite do produto, não da
pessoa.

**A transferência não cruza empresa.** `EstoqueService::transferir()` compara o
`empresa_id` dos dois setores e recusa. É a mesma fronteira que o Volume 1
mostrou faltar em outros pontos — aqui está.

---

## Achados

### A-4.1 — O extrato de remuneração paga por quem ENTREGOU, não por quem VENDEU

**Critério:** C5 (conceitos misturados) · **Severidade: ALTA**

**O que é.** `ExtratoRemuneracaoService::doColaborador()` filtra os pedidos por
`p.entregador_user_id`. O pedido tem **dois** papéis distintos:
`atendente_user_id` (quem vendeu) e `entregador_user_id` (quem levou).

**Evidência.**
- `app/Domain/Venda/ExtratoRemuneracaoService.php` — `->where('p.entregador_user_id', $userId)`
- Consulta em produção (90 dias):

| | |
|---|---|
| pedidos | 400.076 |
| com atendente | 400.076 |
| com entregador | 372.314 |
| **atendente ≠ entregador** | **399.947** |

**Por que impede o SaaS.** Em 99,97% dos pedidos as duas pessoas são diferentes.
O extrato que o franqueado abre para conferir o próprio ganho lista as vendas de
outra pessoa — e **27.762 pedidos sem entregador ficam fora de qualquer extrato**.

O cabeçalho do serviço diz que ele existe para "fazer o franqueado confiar (ou
não) no acerto". Com esse filtro, ele mostra a conta errada.

**Nuance necessária:** pode ser deliberado — num modelo de venda em campo, quem
entrega É quem vende. Mas então os 399.947 pedidos de disk-gás (atendente no
telefone, entregador na rua) estão sendo remunerados para o entregador. Qual
comportamento é o certo é decisão de negócio; o que é defeito é **não haver
distinção no código** entre os dois modelos.

**Direção de correção.** O papel remunerado deve ser configuração da empresa
(atendente, entregador, ou ambos com percentuais distintos), não literal no
`where`.

---

### A-4.2 — `criar()` não tem o guard que `atualizar()` tem

**Critério:** C1 (conceito ausente) · **Severidade: MÉDIA**

**O que é.** `PedidoService::atualizar()` recusa alterar itens de pedido já
concretizado:

```php
if ($pedido->estoque_movimentado) {
    throw ValidationException::withMessages(['itens' => 'Pedido concretizado: itens não podem ser alterados.']);
}
```

`sincronizarItens()` — chamado pelos dois caminhos — faz
`$pedido->itens()->delete()` e recria do zero. Em `criar()` isso é inofensivo
(pedido novo, sem itens). Mas o método é público na prática: qualquer chamador
futuro de `sincronizarItens` fora de `atualizar()` apaga itens sem verificação.

**Evidência.** `app/Domain/Pedido/PedidoService.php` — o guard está em
`atualizar()`, não em `sincronizarItens()`.

**Por que impede o SaaS.** O invariante "pedido concretizado não muda de itens"
protege o estoque baixado e o financeiro gerado. Ele está guardado no chamador,
não no ponto onde o dano ocorre. Uma segunda via de escrita — importação, API de
integração, correção em lote — reintroduz o defeito sem que nada avise.

**Direção de correção.** Mover o guard para dentro de `sincronizarItens()`.

---

### A-4.3 — Item sem produto é tratado como mercadoria, e o dado inconsistente existe

**Critério:** C4 (convenção não declarada) · **Severidade: MÉDIA**

**O que é.** `itensComEstoque()` decide a baixa por:

```php
->filter(fn ($item) => $item->produto?->natureza?->movimentaEstoque() ?? true)
```

O `?? true` significa: item cujo produto sumiu (ou cuja natureza é nula) **baixa
estoque**. O comentário justifica: *"errar baixando é recuperável, errar NÃO
baixando some com o saldo"*.

**Evidência.** `app/Domain/Pedido/PedidoService.php`, método `itensComEstoque()`.

**Por que impede o SaaS.** A escolha é defensável, mas o fallback é silencioso: o
sistema nunca reporta que baixou por falta de informação. Combinado com A-1.13 (a
`natureza` foi classificada por regex), uma revenda nova que cadastre serviços com
nomes fora do padrão recebe `produto` — e este fallback nem chega a agir, porque
a natureza existe e está errada.

**Direção de correção.** Manter o fallback (é o lado seguro), mas registrar
alerta quando ele agir — o mesmo padrão da central de alertas já existente.

---

### A-4.4 — A carga do franqueado cria setor automaticamente, sem papel

**Critério:** C4 (convenção não declarada) · **Severidade: MÉDIA**

**O que é.** `CargaFranqueadoService::setorDo()` cria um setor na primeira carga:

```php
$setor = Setor::create([
    'descricao' => 'Em poder de '.$colaborador->nome,
    ...
]);
```

**Evidência.** `app/Domain/Venda/CargaFranqueadoService.php`.

**Por que impede o SaaS.** É a solução certa para o problema (reusar a máquina de
estoque em vez de criar um saldo paralelo — o comentário explica bem). Mas
alimenta diretamente A-1.6: a tabela `setores` não tem papel, e agora recebe
linhas geradas por código, misturadas com pátio, veículos e rotas. Numa revenda
com 30 franqueados, o cadastro de setores fica com 30 entradas cujo significado
só o nome revela.

**Direção de correção.** O mesmo `papel` proposto em A-1.6, com valor
`EM_PODER_DE` atribuído aqui na criação.

---

### A-4.5 — A Central de Vendas depende de uma situação PENDENTE existir no grupo

**Critério:** C4 (convenção não declarada) · **Severidade: MÉDIA**

**O que é.** `CentralVendasService::situacaoPendenteId()` busca a primeira
`PedidoSituacao` com `efeito=PENDENTE` e `ativo=true` no **grupo**, ordenada por
`ordem`. Se não achar, lança erro.

**Evidência.** `app/Domain/Venda/CentralVendasService.php`.

**Por que impede o SaaS.** Uma revenda nova, cujo grupo ainda não tem situações
cadastradas, não consegue aprovar solicitação nenhuma — e a mensagem
("Nenhuma situação de efeito PENDENTE configurada para o grupo") é técnica, não
acionável para quem está fazendo onboarding. Junta-se a A-1.2 (as situações
misturam estado com forma de pagamento) e A-1.12 (são por grupo): o dado mais
básico do fluxo de venda depende de configuração compartilhada que ninguém
semeia.

**Direção de correção.** Semear as situações no onboarding e falhar cedo, no
cadastro da empresa, não na primeira venda.

---

### A-4.6 — A conferência de estoque está implementada e funcionando, sem uso

**Critério:** C1 (conceito ausente na prática) · **Severidade: ALTA**

**O que é.** Confirma e agrava A-1.18. Não é só a tabela que existe: o
**comportamento inteiro** está pronto.

`EstoqueService::efetivarInventario()`:
```php
foreach ($inventario->itens as $item) {
    $sistema = $this->saldoDerivado($inventario->setor_id, $item->produto_id);
    $item->update(['quantidade_sistema' => $sistema]);
    $this->acertar($inventario->setor_id, $item->produto_id, (float) $item->quantidade_contada, $userId);
}
```

Ou seja: grava o esperado, compara com o contado, gera o movimento de diferença
com histórico auditável, marca como efetivado. É exatamente a conferência diária
que o dono descreveu ("no final do dia precisa bater isso").

**Evidência.** `app/Domain/Estoque/EstoqueService.php`. Banco:
`estoque_inventarios` = **0 linhas**. Origem `Estoquefisico` no histórico: último
uso **jun/2020**.

**Por que impede o SaaS.** Este é o caso mais nítido do padrão que a auditoria
vem encontrando — e agora com uma diferença importante em relação ao Volume 1:
**não falta código**. Falta o gesto passar por aqui. A rotina real é feita por
transferência entre setores, que não registra divergência.

A pergunta deixa de ser "o que construir" e passa a ser "por que a tela pronta
não é usada" — usabilidade, treinamento, ou desencaixe do fluxo real. Os Volumes
12 e 13 (API e SPA) devem responder.

**Direção de correção.** Nenhuma linha de domínio. Investigar a superfície.

---

### A-4.7 — Alçada cadastrada em 2 regras; nenhum desconto praticado em 90 dias

**Critério:** C4 (convenção não declarada) · **Severidade: BAIXA**
(observação para o plano, não defeito)

**O que é.** A alçada é fail-closed — sem regra, teto zero. Há **2 regras**
cadastradas. E em 90 dias, **0 itens com desconto** e R$ 0,00 de desconto total.

**Evidência.** Consultas a `alcada_descontos` e `pedidoitens`.

**Por que anotar.** O mecanismo de alçada — com trilha de recusa, base de cálculo,
precedência por especificidade — é sofisticado e **nunca foi exercitado em
produção**. Não há como saber se as regras estão calibradas, se a trilha de
recusa funciona, ou se o fail-closed está bloqueando desconto legítimo em
silêncio. Zero desconto em 400 mil pedidos pode significar "a revenda não dá
desconto" ou "ninguém consegue dar".

**Direção de correção.** Confirmar com o cliente qual dos dois é. Se for o
segundo, é defeito ativo em produção.

---

### A-4.8 — O preço da solicitação envelhece entre pedir e aprovar

**Critério:** C1 (conceito ausente) · **Severidade: MÉDIA**

**O que é.** `CentralVendasService::precificar()` congela `preco_unitario` do
cadastro no momento da **solicitação**, guardando-o no JSON `itens`. Ao aprovar,
`itensComDesconto()` repassa esse preço a `PedidoService::criar()`, que o aceita
como veio:

```php
$preco = isset($i['preco_unitario']) ? (float) $i['preco_unitario'] : (float) $produto->preco_venda;
```

**Evidência.** `app/Domain/Venda/CentralVendasService.php` — `precificar()` e
`itensComDesconto()`; `app/Domain/Pedido/PedidoService.php` —
`sincronizarItens()`.

**Por que impede o SaaS.** GLP tem reajuste frequente. Uma solicitação aberta na
sexta e aprovada na segunda fatura pelo preço de sexta, sem que ninguém decida
isso. O desenho está certo em não aceitar preço do app (o comentário explica bem
o buraco do legado), mas congelar na solicitação não é o mesmo que precificar na
venda.

Não há prazo de validade na solicitação — nada expira uma pendente.

**Direção de correção.** Reprecificar na aprovação, ou marcar a solicitação como
vencida após N horas (configurável), ou exibir ao atendente que o preço mudou.
Qualquer das três é decisão de negócio; o que não pode é ser silencioso.

---

### A-4.9 — A alçada depende do global scope, que está desligado em job e CLI

**Critério:** C6 (escopo de tenant errado) · **Severidade: MÉDIA**

**O que é.** `AlcadaDescontoService::escolher()` monta a consulta sem filtrar
`empresa_id` — a proteção vem do `BelongsToTenant` no model `AlcadaDesconto`.

**Evidência.** `app/Domain/Venda/AlcadaDescontoService.php` — a query filtra
`ativo`, datas, sujeito (colaborador/papel) e objeto (produto/setor/condição),
nunca empresa. `app/Models/Venda/AlcadaDesconto.php:17` — `use BelongsToTenant`.

**Por que impede o SaaS.** Ligado a A-3.1 e A-3.9: sem tenant resolvido, o global
scope não filtra e a RLS libera. Um caminho de criação de pedido fora do HTTP —
importação, job, comando de correção — escolheria a regra de alçada de outra
empresa. O `sortByDesc(especificidade)` então elegeria a mais específica de
**qualquer** tenant.

A mesma observação de A-3.9 vale: não afirmo vazamento hoje, porque o caminho
atual é HTTP. Afirmo que a proteção depende de uma barreira que outros volumes
mostraram estar desligada fora dele.

**Direção de correção.** Filtro explícito de `empresa_id` na consulta — o mesmo
que a correção do vínculo de comodato exigiu (memória
`vinculo-vasilhame-fronteira-empresa`).

---

### A-4.10 — O canal `pedido.{id}` não carrega tenant no nome

**Critério:** C6 (escopo de tenant errado) · **Severidade: MÉDIA**

**O que é.** `PedidoStatusAtualizado` transmite em dois canais privados:

```php
new PrivateChannel("empresa.{$this->empresaId}.pedidos"),
new PrivateChannel("pedido.{$this->pedidoId}"),
```

O primeiro tem o tenant no nome; o segundo, não.

**Evidência.** `app/Domain/Pedido/Events/PedidoStatusAtualizado.php`. O
comentário afirma que ambos são *"namespaced por tenant"* — o segundo não é.

**Por que impede o SaaS.** Com ids de pedido globais e sequenciais, o nome do
canal é adivinhável. A autorização existe (`routes/channels.php` é a 2ª
barreira), mas aqui ela é a **única** barreira, enquanto no outro canal há duas.
`broadcastWith()` envia situação e efeito do pedido — pouco, mas de outro tenant.

**Não verificado:** o conteúdo de `routes/channels.php`. Fica para o **Volume
12** (API e rotas), que deve confirmar se o callback de `pedido.{id}` valida a
empresa do usuário contra a do pedido.

---

## Cobertura

**Nota sobre o fechamento deste volume.** A primeira versão declarou "12 de 12
lidos integralmente" quando, de fato, 6 tinham sido lidos por inteiro, 3
parcialmente (`CentralVendasService` 130/344, `AlcadaDescontoService` 70/126,
`ExtratoRemuneracaoService` 60/168) e 3 não tinham sido abertos (`EfeitoPedido`,
`PedidoStatusAtualizado`, `SolicitacaoRecebida`). O usuário questionou; a
verificação confirmou.

A leitura do que faltava rendeu **3 achados** (A-4.8 a A-4.10) — o preço que
envelhece na solicitação, a alçada sem filtro de empresa, e o canal de broadcast
sem tenant no nome. Nenhum era detectável na parte já lida.

**Quarto volume consecutivo** em que a parte não lida continha achado.

---

**12 de 12 arquivos lidos integralmente:**
- `Pedido/`: `PedidoService.php` (376), `EfeitoPedido.php` (32),
  `Events/PedidoStatusAtualizado.php` (72)
- `Venda/`: `CentralVendasService.php` (344), `CargaFranqueadoService.php` (177),
  `ExtratoRemuneracaoService.php` (168), `AlcadaDescontoService.php` (126),
  `SituacaoSolicitacao.php` (41), `Events/SolicitacaoRecebida.php` (64)
- `Produto/`: `ProdutoService.php` (122), `NaturezaItem.php` (79 — lido no Vol 2,
  reconferido aqui)
- `Estoque/`: `EstoqueService.php` (251)

**Consultas ao banco:** 3, leitura, role `erp_app`.

**Confirmações de volumes anteriores:**
- A-1.18 (conferência sem uso) → **agravado**: A-4.6 mostra que o código está
  completo, não só a tabela.
- A-2.5 (`PedidoItem` não congela natureza/custo) → **confirmado** em
  `sincronizarItens()`: grava `produto_id`, `quantidade`, `preco_unitario`,
  `desconto`, `valor_total` — nada mais.
- A-1.6 (setor sem papel) → **agravado**: A-4.4 mostra código criando setores.

**Não verificado (declarado):** o corpo de `ComissaoService` (chamado pelo
extrato) fica para o Volume 11 — é do domínio Rh. `AtribuirPedidoJob` e
`PedidoEntrouNaFila`, disparados por `criar()`, ficam para o Volume 8
(Logística).

---

## Resumo

| Critério | Achados |
|---|---|
| C1 — conceito ausente | 3 (A-4.2, A-4.6, A-4.8) |
| C4 — convenção não declarada | 4 (A-4.3, A-4.4, A-4.5, A-4.7) |
| C5 — conceitos misturados | 1 (A-4.1) |
| C6 — escopo de tenant errado | 2 (A-4.9, A-4.10) |

**10 achados · 2 ALTA · 7 MÉDIA · 1 BAIXA.**

### O que este volume mostra

Os serviços são a camada mais madura que a auditoria encontrou até aqui — mais
que os models. Lock pessimista, custo médio ponderado, idempotência,
fail-closed em dinheiro, fronteira de empresa na transferência, e comentários
que registram o *porquê* de cada decisão, incluindo os defeitos que cada guard
existe para prevenir.

O padrão dos achados mudou de natureza. No Volume 1 era estrutura abandonada; no
2, premissa de dono único; no 3, execução parcial de uma intenção correta. Aqui é
**funcionalidade completa que não chegou ao usuário**:

- a conferência de estoque roda, e ninguém a usa (A-4.6);
- a alçada de desconto é sofisticada, e nunca foi exercitada (A-4.7);
- o extrato de remuneração existe, e mostra a pessoa errada (A-4.1).

Isso desloca o foco do plano: para estes três, o trabalho não é de domínio — é de
superfície (API, tela, onboarding) e de decisão de negócio. Os Volumes 12 e 13
passam a ser mais importantes do que a ordem original sugeria.
