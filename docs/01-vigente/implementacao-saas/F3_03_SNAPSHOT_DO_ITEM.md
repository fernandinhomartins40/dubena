# F3-03 — O item congela o que o produto era na venda

Data: 2026-08-31 (America/Sao_Paulo)

## O achado mais sério desta fase

`pedidoitens` e `nota_itens` guardavam `produto_id`, quantidade e preço. **O
preço estava congelado, e isso sempre esteve certo.** A descrição não — era lida
do produto na hora de exibir.

No pedido, isso reescreve o histórico: renomear um produto faz o pedido de três
meses atrás dizer que o cliente comprou algo que não existia com aquele nome.
Chato, mas contornável.

**Na nota fiscal é outra coisa.** `XmlNfeBuilder` montava o `xProd` do XML assim:

```php
$prod->xProd = $item->produto?->descricao ?? ('Item '.$n);
$prod->NCM   = $item->produto?->ncm ?? '00000000';
```

Depois de autorizada, a NF-e é imutável na SEFAZ. Mas uma reimpressão de DANFE —
ou o XML remontado — passava a mostrar a descrição **nova**. O papel deixava de
bater com o documento autorizado, e isso é divergência fiscal, não detalhe de
tela.

`nota_itens` já congelava CFOP, CST e alíquotas. Faltava justamente o que aparece
impresso.

## O que foi congelado

| Tabela | Colunas |
|---|---|
| `pedidoitens` | `descricao_snapshot` |
| `nota_itens` | `descricao_snapshot`, `ncm_snapshot`, `unidade_snapshot` |

NCM e unidade entram no XML e definem tributação: um produto reclassificado
depois faria a reimpressão divergir do autorizado pelo mesmo mecanismo.

Os três pontos de leitura passaram a preferir o congelado: `XmlNfeBuilder`,
`DanfePdfService` e `CupomTextoService`.

## `null` significa "não capturado", não "sem nome"

As colunas nascem nullable e há fallback para o cadastro atual em toda leitura.
Isso atende as linhas anteriores a esta migration sem fingir que o valor gravado
é histórico quando não é.

A conversão preenche as linhas existentes com o cadastro de **hoje** — que é
exatamente o que elas já usavam ao exibir. Não piora nada: troca "lê o atual toda
vez" por "leu o atual uma vez, e a partir daqui congela".

A conversão usa **subselect correlacionado** e não `UPDATE ... FROM`: Postgres e
sqlite escrevem o segundo de formas diferentes, e o resultado precisa ser o mesmo
nos dois. E é feita em SQL por tabela, não linha a linha — `nota_itens` numa base
real tem centenas de milhares de linhas, e uma migration que demora minutos numa
janela de deploy é um problema por si só.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 5 (`SnapshotDoItemTest`) + 1 fiscal (`FiscalTest`) |
| Testes fiscais existentes | 75 passando |
| Migrations em PostgreSQL real | 154, sem erro |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 |
| Conversão com massa real | preencheu corretamente |
| Rollback → reaplicação | OK |
| Suíte integral | ver ESTADO_ATUAL |
| Pint | aprovado |

O teste que mais importa é `test_nota_congela_a_descricao_do_produto_na_emissao`:
emite a nota, renomeia o produto **depois**, e confirma que a nota continua
dizendo o que dizia.

## O que fica aberto

- `unidade_snapshot` é gravado na emissão, mas o `XmlNfeBuilder` ainda manda
  `uCom = 'UN'` fixo — o campo está pronto e a leitura não foi trocada, porque
  mexer na unidade do XML sem conferir a tabela de unidades da SEFAZ é risco
  fiscal que não cabe neste lote;
- `NfEntradaService` (nota de entrada) cria itens por outro caminho e não recebeu
  o snapshot — é nota de terceiro, onde a descrição vem do XML do fornecedor e
  não do cadastro, então o problema não é o mesmo. Fica registrado para
  conferência.
