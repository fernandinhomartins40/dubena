# F4 — Fechamento da fase

Data: 2026-08-31 (America/Sao_Paulo)

## O gate, item a item

> **Gate F4:** soma do ledger fecha por tenant/empresa/local/item; rerun não
> duplica; inventário gera divergência auditável; custódia não depende de
> texto/flag; quatro testes falhos são substituídos por contrato aprovado.

| Item do gate | Onde está |
|---|---|
| soma do ledger fecha | `ConferenciaDeSaldo` + `ConferenciaDeCustodia`, via `estoque:conferir` (F4-02, F4-04) |
| rerun não duplica | `chave_idempotencia` + índice único parcial, garantido pelo banco (F4-01) |
| inventário gera divergência auditável | `quantidade_sistema` gravada + movimento de acerto + autoria/aprovação (F4-03) |
| custódia não depende de texto/flag | `sentido` CONCEDIDO/RECEBIDO — **já existia** |
| quatro testes falhos | 71 testes de comodato verdes, com contrato multi-item e consolidado |

## A descoberta que se repetiu

**As estruturas já existiam, e eram boas.** `estoquehistorico` já era um ledger
completo; `comodato_movimentos` também; o `sentido` já separava concedido de
recebido; a efetivação do inventário já usava o saldo derivado do ledger.

Medir antes de escrever evitou reescrever o que estava certo — e teria sido fácil
não medir: a tarefa F4-01 diz "criar movimento imutável", e a leitura apressada
levaria a criar uma tabela nova ao lado de uma que já fazia o trabalho.

O que faltava, em quase tudo, era a mesma coisa: **prova**. Projeção mantida na
transação, sem ninguém conferir contra o ledger. Coluna de tenant criada e nunca
preenchida. Idempotência por caso de uso, não do mecanismo.

## Dois princípios que valeram para a fase inteira

**Conferência nunca ajusta.** Se a projeção diz 10 e o ledger soma 8, ou a
projeção está errada, ou falta movimento no ledger — mercadoria que entrou sem
registro. Sobrescrever resolve a tela e apaga a pergunta; se a resposta era a
segunda, a mercadoria some da contabilidade sem rastro.

**A comparação percorre a união das chaves.** Um par que existe no ledger sem
linha de projeção é o caso mais grave, e olhar só a projeção o deixaria
invisível.

## Defeitos encontrados no caminho

- **`tenant_account_id` vazio em `estoquehistorico`** — criado pela migration
  `000300` (F1) e nunca preenchido. Mesmo achado do F2-06 nas trilhas: coluna
  vazia é pior que ausente, porque parece resolvida;
- **preço não revalidado na conclusão** (F4-06) — entre criar e concluir o piso
  do produto pode subir, e a venda fechava abaixo dele sem registro;
- **`motivo` vazio na trilha** — defeito que eu mesmo deixei no F2-06:
  `RegistroAcao` tem dois ramos e só um foi ajustado quando `motivo` virou
  coluna. O efeito era silencioso e atingia o caso **comum**;
- **`id` faltando no `get()`** da conferência de custódia, que impedia
  reconhecer o par estornado/estorno.

## F4-07 é operação, não código

> **F4-07 — Reconciliação Dubena:** comparar ledger proposto, saldos, pedidos,
> inventários e acumulados; diferenças históricas vão para evidência/quarentena.

A ferramenta existe e está testada: `php artisan estoque:conferir --csv=...`
contra a cópia real produz exatamente essa comparação, sem ajustar nada.

O que falta é **rodá-la contra o banco com os dados migrados** e decidir, caso a
caso, o que é diferença histórica legítima e o que é defeito. Isso não é trabalho
de código: é leitura de relatório com quem conhece a operação.

## Verificação

| Portão | Resultado |
|---|---|
| Suíte integral | **1554 passes / 4799 assertions** |
| Migrations em PostgreSQL real | 157, sem erro |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 |
| Índice de idempotência | verificado no banco (recusa duplicata) |
| Rollbacks | validados, preservando colunas de outras migrations |
| CI | verde |

## Estado do plano

Fechadas: **F0, F1, F2, F3** (com quatro parciais registradas) e **F4**.
Restam: F5 a F10.
