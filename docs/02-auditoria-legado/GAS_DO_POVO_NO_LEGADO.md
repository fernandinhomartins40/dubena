# Gás do Povo no legado — como era e como funcionava

Análise feita a pedido do dono, para decidir o que a tela
`/novo/app/gas-do-povo` deve mostrar. Fontes: código do `ctrl-web` e o dump
Oracle espelhado em `legado`.

---

## A conclusão que muda o desenho

**No legado NÃO existe tela de Gás do Povo.** Não há controller, não há rota, não
há view própria. Confirmado por busca em `app/`, `resources/views/` e `routes/`:

- `grep -rli "gasdopovo" routes/` → **nada**
- controllers com "gas" no nome → só `Valegas*` (vale-gás é outro produto)
- views que citam o programa → **três**, e todas são de outros cadastros

O programa não é um **módulo**; é um **modo de venda** configurado em três
lugares e aplicado no pedido.

---

## Onde o programa vive, de fato

### 1. Configuração da empresa — a regra do programa

`resources/views/empresaconfig/partials/section_gasdopovo.blade.php` define
**seis** parâmetros:

| Campo | Papel |
|---|---|
| `produtogp_id` | o ÚNICO produto vendável pelo programa |
| `condicaopagamentogp_id` | a condição que MARCA o pedido como do programa |
| `valorfretegp` | valor fixo da entrega |
| `ccfretegp_id` / `pcfretegp_id` | centro de custo e plano de conta da entrega |
| `condicaopagamentofretegp_id` | condição de pagamento da entrega |

### 2. Cadastro do cliente — quem é beneficiário

`form_clientes.blade.php`: um único checkbox "Gás do Povo:". Sem NIS, sem
validade, sem cota. **É uma flag booleana e nada mais.**

### 3. Cadastro do produto — o preço do programa

`produtos_form.blade.php`: campo "Preço Gás do Povo" (`precogasdopovo`), ao lado
do preço de venda e do custo médio.

---

## A regra de negócio (o que o código faz)

**A marcação do pedido** — `PedidoUtil.php:337`:

```php
$data['gasdopovo'] = $cliente->gasdopovo
    && $data['condicaopagamento_id'] == $condicaopagamentogp_id;
```

O pedido é do programa quando **as duas coisas** valem: o cliente é beneficiário
**e** a condição de pagamento escolhida é a do programa. Nenhuma sozinha basta.

**A trava** — `PedidoUtil.php:367-370`:

```php
if ($pedido->gasdopovo && $data['modalcondicaopagamento_id'] != $pedido->condicaopagamento_id)
    throw new Exception('Não é permitido mudar a condição de pagamento para pedido do Gás do Povo');
if ($data['modalcondicaopagamento_id'] != $pedido->condicaopagamento_id
    && $data['modalcondicaopagamento_id'] == $config->condicaopagamentogp_id)
    throw new Exception('Não é permitido mudar a condição de pagamento para Gás do Povo');
```

Trava nos **dois sentidos**: não se tira um pedido do programa, nem se coloca um
pedido nele depois de criado. Faz sentido — o programa é subsidiado, e trocar a
condição depois mudaria o preço e a prestação de contas.

**No app do consumidor** — `ProdutoController.php:96-101`: quando a sessão é de
gás do povo, a lista de produtos é **filtrada para o `produtogp_id`**. O
beneficiário só enxerga o botijão do programa.

**O frete** — `PedidoController.php:111`: `$data["valorfrete"] = $user->valorfretegp;`
— valor fixo da config, não o cálculo normal de entrega.

---

## O que o dump contém

| Dado | Quantidade |
|---|---|
| `clientes.gasdopovo = 1` | **821** beneficiários |
| `pedidos.gasdopovo = 1` | **1.003** vendas pelo programa |
| `produtos.precogasdopovo` | preenchido |
| `empresaconfigs` (6 campos GP) | preenchidos na matriz |
| **tabela de benefício concedido** | **não existe** |

`BENEFICIARIOS` (480 linhas) é o **cadastro do programa** — `codbenef`,
`descricao`, `datainicio`, `datafim`, `uf` (todas PR). Sem cliente, sem NIS, sem
valor, sem competência. Não é o benefício de ninguém.

---

## O descompasso com o sistema novo

A tela nova (`GasDoPovoPage`) foi desenhada para **benefícios concedidos**: uma
lista com cliente, valor e saque (`POST /gasdopovo/{id}/sacar`). Isso é um modelo
de **crédito/voucher** — o beneficiário tem um saldo que consome.

**O legado nunca funcionou assim.** Nele o programa é: cliente marcado + condição
de pagamento específica + produto único + preço próprio + frete fixo. Não há
saldo, não há saque, não há concessão.

Por isso a tela está vazia — e vai continuar, porque **não existe origem para o
que ela pede**. Não é falha de migração.

---

## O que foi implementado (opção B)

A tela foi refeita no modelo real do legado, mantendo a de voucher como uma aba
à parte. Quatro abas em `/novo/app/gas-do-povo`:

| Aba | O que mostra | Origem |
|---|---|---|
| **Programa** | Parâmetros da empresa + resumo do período + evolução mensal | `empresa_configs.dados` (migrado) |
| **Beneficiários** | Os clientes marcados no cadastro | `clientes.gasdopovo` (821 migrados) |
| **Vendas** | Os pedidos marcados como do programa | `pedidos.gasdopovo` (1.003) |
| **Benefícios** | O modelo de voucher (saldo + saque) | operação do sistema novo |

### Backend

- `app/Domain/Pagamento/GasDoPovoService.php` — resolve os parâmetros a partir
  da config, apura o resumo do período e a série mensal;
- `GET /gasdopovo/programa`, `/gasdopovo/beneficiarios`, `/gasdopovo/vendas`;
- `2026_08_19_000100_gasdopovo_em_pedidos.php` — a coluna que faltava, com
  índice **parcial** (`WHERE gasdopovo`): são 0,25% dos pedidos e a consulta é
  sempre "os do programa";
- `PedidosMigrator` passa a trazer a marca das 1.003 vendas.

### Correção após conferir com o dado real

A primeira versão trazia um card de **"Subsídio concedido"** = (preço normal −
preço do programa) × botijões. **A conferência com o dump desmentiu a premissa:**

```
produto Glp P13:      preco_venda = 120,00   precogasdopovo = 120,00
vendas do programa:   R$ 96,00 a R$ 127,18   (média 111,90)
vendas normais:       média 105,95
```

Três fatos derrubam a ideia de desconto de tabela: o preço "do programa" é
**idêntico** ao normal; as vendas variam num intervalo que não corresponde a
nenhum dos dois; e a média do programa é **maior** que a das vendas normais.

**O Gás do Povo é o canal de pagamento — o cartão do benefício — não um desconto
no preço.** O card mostraria R$ 0,00 e induziria a erro justamente na prestação
de contas, que era seu propósito.

Trocado por **preço médio praticado**, rotulado como "das vendas, não o do
cadastro". O que se confere é volume e faturamento por período.

Registro do método: o teste unitário passava, porque fora escrito com a mesma
premissa errada. Foi a conferência contra o banco de produção que pegou.

### Detalhes de implementação que valem registro

- **Gráfico em CSS puro** (barras horizontais): a série tem 12 pontos e não
  justifica uma dependência de biblioteca de gráficos.
- **`to_char` só no Postgres**: a expressão do mês é trocada por `strftime` em
  sqlite, senão o serviço quebra nos testes.
- **Aviso de não configurado**: sem produto e condição definidos, a tela diz
  onde configurar em vez de mostrar zeros como se estivesse tudo certo.

`tests/Feature/GasDoPovoProgramaTest.php` — 7 testes, incluindo a contra-prova
de que a venda normal do mesmo produto **não** entra na conta do programa.

---

## Opções consideradas


**A. Deixar como está.** A tela existe para a operação futura do sistema novo, se
o modelo de voucher for adotado. O histórico do legado não a alimenta.

**B. Refazer a tela no modelo do legado** — o que reflete a operação real:

1. os 6 parâmetros do programa na config da empresa (**já migrados**: estão em
   `empresa_configs.dados` como `gp_produto_id`, `valorfretegp`, etc.);
2. lista dos **821 clientes beneficiários** (a flag já migrou);
3. relatório das **1.003 vendas** pelo programa — volume, período, valor.

O item 3 exige a coluna `gasdopovo` em `public.pedidos`, que **não existe**: é a
pendência já registrada. Com ela, a conferência com a distribuidora passa a ser
possível.

**Recomendação:** B, se o cliente continuar operando o programa após o cutover —
é a única forma de prestar contas do que foi vendido subsidiado. A é aceitável se
o programa tiver sido encerrado (as 1.003 vendas são histórico).

A pergunta que decide: **a revenda ainda vende pelo Gás do Povo hoje?**
