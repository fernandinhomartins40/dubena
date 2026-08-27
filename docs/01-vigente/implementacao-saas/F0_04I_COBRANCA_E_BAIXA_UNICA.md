# F0-04I — Numeração de boleto e baixa financeira única

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO COMO CONTENÇÃO  
**Achados contidos:** A-7.3 e A-7.4.

## Releitura executada

Foram relidos integralmente os trechos A-7.3 e A-7.4 do Volume 7 e os fontes atuais dos drivers Caixa/Itaú/fake, contrato de driver, helper CNAB, configuração de conta de cobrança, `BoletoService`, `PixService`, `CaixaService`, `ChequeService`, modelos e migrations de cobrança/financeiro, gerador atômico de sequências e todos os testes diretamente relacionados. Também foi feita busca global pelos escritores de `financeiroparcelas.baixado=true`.

## Diagnóstico confirmado

- Caixa e Itaú ainda derivavam o nosso-número diretamente do `boletos.id` global;
- a tabela genérica `sequencias` e seu serviço atômico já existiam, mas cobrança não os usava;
- a criação inicial de uma chave de sequência fazia `select` seguido de `insert`, deixando corrida entre duas primeiras requisições;
- correções anteriores já impediam sobrescrita simples em CNAB e cheque, mas PIX, CNAB, caixa e cheque continuavam donos independentes da mesma transição;
- reentrega do mesmo meio deve ser idempotente, enquanto baixa manual ou encontro de contas sobre parcela já quitada deve falhar;
- dois pagamentos externos reais para a mesma parcela não podem ser desfeitos pelo software: o segundo precisa ser reconhecido como duplicidade e não pode apagar a baixa original.

## Alterações

### Nosso-número por namespace

- drivers reais agora reservam sequência em `sequencias` com chave `empresa+banco+carteira`;
- carteira é validada e normalizada conforme a largura do banco antes de compor a chave;
- o maior nosso-número legado válido do mesmo namespace é usado como piso na primeira reserva;
- Caixa usa a sequência nos 15 dígitos posteriores à carteira; Itaú usa a sequência de 8 dígitos;
- remessa real recusa boleto sem nosso-número válido, em vez de regenerá-lo a partir do ID;
- estouro do espaço numérico do layout falha fechado;
- `NumeroSequencialService` passou a criar chaves com `insertOrIgnore` antes do `FOR UPDATE`, removendo a corrida do primeiro número e mantendo a semântica fiscal existente.

### Dono único da baixa

- criado `BaixaService` como única porta runtime que escreve `baixado=true`, `valor_efetivado` e `datahora_baixa`;
- a porta exige `empresa_id`, valida owner da parcela e usa lock pessimista;
- caixa e cheque rejeitam tentativa de baixar parcela já quitada;
- PIX e CNAB tratam reentrega como idempotente, preservam horário e valor originais e emitem alerta estruturado de duplicidade;
- `BoletoService`, `PixService`, `CaixaService` e `ChequeService` deixaram de atualizar diretamente a parcela;
- busca global confirmou que, fora de ETL/fixtures, apenas `BaixaService` grava `baixado=true` no código de domínio.

## Evidência

Validação dirigida inicial de cobrança/caixa/cheque/malote:

```text
Tests: 64 passed (152 assertions)
Duration: 5.93s
```

Validação ampliada, incluindo o consumidor fiscal do mesmo gerador de sequências:

```text
Tests: 135 passed (329 assertions)
Duration: 8.63s
```

Provas novas:

- primeira empresa recebe sequências 1 e 2 no namespace Caixa/carteira 14;
- segunda empresa no mesmo banco/carteira possui sequência independente iniciando em 1;
- PIX confirmado seguido de retorno CNAB divergente mantém valor e instante da primeira baixa;
- a situação externa do boleto ainda é registrada como liquidada, sem fingir que o segundo pagamento não ocorreu;
- todos os contratos fiscais de numeração continuaram verdes após o endurecimento do gerador compartilhado.

`pint` foi aplicado apenas aos arquivos do microlote e `git diff --check` passou sem erro.

## Limites e substituição canônica

- a contenção registra duplicidade em log estruturado, mas F3 deve introduzir ledger/evento financeiro persistente, com origem, referência externa, idempotency key, valor, status aplicado/duplicado, reconciliação e estorno correlacionado;
- concorrência PostgreSQL real precisa ser exercitada no gate obrigatório T-02.05/F0-06; SQLite prova a lógica, não o bloqueio do banco;
- os vetores matemáticos CNAB atuais ainda não são uma homologação bancária independente; F5 exige vetores oficiais por carteira e aprovação do banco;
- sequências importadas que não respeitem o formato do driver são ignoradas como piso e devem aparecer no diagnóstico/backfill de F3/F7 antes do primeiro boleto real;
- a baixa reversa (`estorno`) permanece no `CaixaService`; F3 deverá tratá-la como evento compensatório, não atualização destrutiva.
