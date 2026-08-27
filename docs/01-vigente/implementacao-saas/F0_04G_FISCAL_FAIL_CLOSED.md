# F0-04G — Fiscal fail-closed

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO COMO CONTENÇÃO  
**Achados contidos:** A-6.1, A-6.2, A-6.3 e A-6.4.

## Releitura executada

Foram relidos os achados A-6.1–A-6.4 do Volume 6 e, integralmente, os fontes atuais de orquestração fiscal, resolução e cálculo tributário, DTO calculado, driver NFePHP, builder XML, modelos de nota/item/empresa/configuração/cidade/município, migrations fiscais e geográficas, testes fiscais e os consumidores atingidos. A API exata do NFePHP 5.2.6 foi conferida no `vendor` instalado.

A validação de `CRT=4` foi confrontada com a NT 2024.001 v1.20 do Portal Nacional da NF-e: <https://www.nfe.fazenda.gov.br/Portal/exibirArquivo.aspx?conteudo=kIiniiSkpKc%3D>.

## Diagnóstico confirmado

- `tpAmb=2` estava fixo na inutilização e no perfil do emitente;
- o XML inventava São Paulo, município zero, endereço `N/I`, CRT 3 e natureza `VENDA`;
- o cálculo já produzia ST, FCP, DIFAL e reduções, mas `nota_itens` não possui todas as colunas necessárias e o model descartava silenciosamente parte do resultado;
- ausência de operação, regra, CFOP, série ou configuração fiscal ainda caía em valores históricos inventados;
- a relação `NotaFiscal::empresa()` consumida pelo driver nem existia.

## Alterações

### Matriz, operação, CFOP e série

- removido `TRIBUTACAO_PADRAO` (CST 00 / 18% / PIS 1,65 / COFINS 7,6);
- produto sem operação habilitada ou sem regra na matriz agora gera `ValidationException`;
- a transação não deixa nota rascunho órfã nessas falhas;
- CFOP não cai mais em `5102`: precisa existir, estar ativo, pertencer ao grupo e ter quatro dígitos válidos;
- série não cai mais em `1`: `ConfigFiscal` da empresa é obrigatória e a série precisa ser positiva.

### Perfil fiscal por empresa

- `tpAmb` vem de `config_fiscais.ambiente` também para inutilização;
- CRT aceita os valores oficiais 1–4 e vem da configuração da empresa;
- emitente usa CNPJ, IE, endereço, CEP, UF e município ligado ao catálogo IBGE;
- `cUF` e `cMun` vêm do município oficial e precisam ser coerentes com a UF da empresa;
- natureza da operação, UF do destino e classificação de consumidor final não recebem defaults;
- certificado precisa existir, não estar vencido e, quando o CNPJ extraído está disponível, pertencer à empresa;
- removidos os fallbacks de SP, ambiente 2, CRT 3 e endereço fictício no driver/builder.

### Snapshot e transmissão real

O schema atual não consegue congelar integralmente a decisão tributária. Por isso o `XmlNfeBuilder` real verifica a presença do snapshot necessário e recusa a montagem enquanto faltarem origem/modalidade, CST e bases de PIS/COFINS, ST, FCP, DIFAL e reduções. Essa é uma contenção deliberada: o driver fake continua exercitando fluxo e numeração em testes, mas ativar `FISCAL_DRIVER=nfephp` não transmite um XML fiscalmente inventado.

O builder já deixou de usar defaults nos campos que passará a consumir quando F5-08 introduzir o snapshot completo.

## Evidência

Validação dirigida após formatação:

```text
Tests: 30 passed (110 assertions)
Duration: 5.42s
```

Provas negativas novas:

- ausência de regra falha e deixa zero notas;
- snapshot incompleto bloqueia o XML real;
- cadastro de emitente incompleto é recusado;
- ambiente 1, CRT 4, cUF 41 e cMun 4106902 são lidos da empresa/configuração, sem defaults.

Suíte integral do repositório durante o microlote:

```text
Tests: 1,240 passed, 5 skipped, 8 failed (3,671 assertions)
Duration: 536.61s
```

Quatro falhas eram regressões de chamadores/fixtures e foram corrigidas depois: duas no `HomologSeeder` (owner obrigatório do caixa) e duas em `PedidoNfceTest` (perfil/matriz obrigatórios). A reexecução desses contratos passou com 6 testes/39 asserções. As quatro falhas restantes são o baseline de comodato já conhecido e preservado para decisão contratual de F4: três inserções sem `sentido` em `ComodatoAcrescimoProdutoTest` e a expectativa antiga baseada em `cliente.fornecedor` em `ComodatoVigilanciaTest`.

`pint` foi executado somente nos arquivos do microlote. Sintaxe PHP e `git diff --check` não apontaram erro; permanecem apenas avisos CRLF preexistentes em outros arquivos do workspace.

## Limites deliberados desta contenção

- emissão real permanece bloqueada até F5-06/F5-08 criarem perfil fiscal vigente e snapshot completo; isto é segurança intencional, não homologação concluída;
- natureza da operação ainda não é coluna versionada da nota;
- `nota_itens` ainda não persiste todos os campos produzidos por `ImpostoItem`;
- o builder ainda não implementa todos os grupos XML por CST, ST, FCP, DIFAL, monofásico e reforma tributária; F5 exige confirmação legal vigente, cenários por regime/UF e homologação oficial;
- DANFE e SPED ainda possuem fallbacks próprios a eliminar quando consumirem o snapshot congelado;
- certificado real, schemas atuais, webservice SEFAZ e aprovação fiscal externa continuam gates de F5-09.

