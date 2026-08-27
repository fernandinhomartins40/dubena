# F0-04E — Owner financeiro e casamento CNAB posicional

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO COMO CONTENÇÃO  
**Achados contidos:** A-7.2, parcela de A-7.4 e parcela de A-7.5.

## Releitura executada

Foram relidos integralmente o Volume 7, `FinanceiroService`, `BoletoService`, o contrato e os três drivers de boleto, o controller, os models e todos os testes de financeiro/cobrança/CNAB atingidos. Também foram conferidos os layouts primários vigentes publicados pelos bancos:

- CAIXA SIGCB CNAB240: Segmento T, “Seu Número” nas posições 59–69;
- Itaú CNAB400: “Uso da Empresa” nas posições 38–62, devolvido com o conteúdo da remessa.

Fontes: [manual CAIXA](https://www.caixa.gov.br/Downloads/cobranca-caixa/Manual_de_Leiaute_de_Arquivo_Eletronico_CNAB_240.pdf) e [manual Itaú](https://download.itau.com.br/bankline/layout_cobranca_400bytes_cnab_itau.pdf).

## Alterações

- geração e estorno de financeiro de pedido incluem `empresa_id` explicitamente;
- agrupamento relê os títulos por `id + empresa_id` e os bloqueia dentro da transação;
- agrupamento recusa título ausente/alheio, duplicado, cancelado, já agrupado, com baixa, cliente diferente ou natureza diferente;
- desagrupamento só reativa filhos do mesmo owner;
- remessa relê e bloqueia exclusivamente boletos pendentes da empresa e do banco ativos;
- retorno exige `empresaId`, extrai o identificador de posição fixa pelo driver e consulta exatamente `id + empresa_id + banco_codigo`;
- foi removida a varredura global com `get()` e o casamento por `str_contains`;
- baixa CNAB exige que a parcela também pertença à empresa e atualiza somente quando `baixado = false`, preservando a primeira baixa;
- as linhas resumidas de remessa passaram a colocar o identificador nos campos posicionais usados no retorno.

## Evidência

```text
Tests: 31 passed (84 assertions)
Duration: 4.48s
git diff --check: sem erro de whitespace; somente avisos CRLF preexistentes
```

Os testes negativos provam remessa e retorno intertenant recusados, ausência de casamento por substring, preservação da primeira baixa em reprocessamento, agrupamento intertenant recusado e estorno de pedido isolado pelo owner.

## Limites deliberados desta contenção

- A porta única `BaixaService`, com origem, chave idempotente e histórico de pagamentos concorrentes, continua reservada para F5-02; a contenção atual impede sobrescrita pelo CNAB, mas não redesenha todos os escritores.
- A sequência de nosso-número por empresa+banco+carteira continua em F5-03.
- Os builders de remessa atuais continuam sendo linhas resumidas, não um arquivo CNAB homologável completo; a homologação por vetores oficiais e por banco permanece gate de F5-03/F5-09.
- A ocorrência CNAB recebida continua registrada mesmo quando a parcela já estava baixada, preservando evidência da possível duplicidade; ela não substitui silenciosamente a primeira liquidação.

