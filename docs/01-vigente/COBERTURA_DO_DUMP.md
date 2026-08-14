# O que existe no dump x o que foi migrado

Levantamento feito em 14/08/2026 para responder duas perguntas: *"o dump está
incompleto?"* e *"por que as telas aparecem vazias?"*.

**Resposta curta: o dump está completo. As telas estão vazias porque a migração
cobriu 5 módulos de 13.** Não é preciso pedir outro banco ao cliente — com uma
exceção pontual (certificado digital A1), detalhada no fim.

## O que o dump tem

Schema Oracle `CTRL2QTI`: **222 tabelas, 179 com dados**, 43 vazias (e vazias
na origem — o cliente não usa aqueles recursos).

Tabelas mais volumosas:

| Tabela do legado | Linhas |
|---|---|
| `REVISIONS` (trilha de auditoria) | 3.499.497 |
| `PEDIDOSITUACAOHISTORICOS` | 2.077.510 |
| `ESTOQUESETORHISTORICOS` | 506.891 |
| `FINANCEIROPARCELAS` | 475.000 |
| `FINANCEIROS` | 443.714 |
| `CONTAMOVIMENTOS` | 410.417 |
| `PEDIDOITEMS` | 406.883 |
| `PEDIDOS` | 400.070 |
| `NFEMITIDAITEMS` | 254.308 |
| `NFEMITIDAS` | 241.024 |

O **módulo fiscal está inteiro**: 241.024 notas emitidas (03/2020 a 08/2026),
254.308 itens, 850 notas recebidas, além das tabelas de tributação (`NFICMS`,
`NFPIS`, `NFCOFINS`, `NFCSTS`, `NFOPERACAOS`, `NFGRUPOFISCALS`).

## O que foi migrado — e o que não foi

| Módulo | No legado | Migrado | Situação |
|---|---|---|---|
| Empresas / geográfico / clientes | 44.349 | 66.557 | ok |
| Produtos | 26 | 26 | ok |
| Pedidos + itens | 806.953 | 808.156 | ok |
| App (endereços/avaliações) | 45.393 | 53.783 | ok |
| Monitora (veículos/GPS) | 16.113.791 | 16.113.791 | ok |
| **Financeiro** | 475.000 | 0 | **não migrado** |
| **Fiscal — NF-e emitida** | 254.308 | 0 | **não migrado** |
| **Fiscal — NF recebida** | 2.319 | 0 | **não migrado** |
| **Caixa / conta** | 410.417 | 0 | **não migrado** |
| **Cobrança (boleto)** | 69.158 | 0 | **não migrado** |
| **Convênio** | 28.260 | 0 | **não migrado** |
| **Vale-gás** | 23.588 | 0 | **não migrado** |
| **Comodato** | 975 | 0 | **não migrado** |
| **Estoque (saldo/movimento)** | 506.891 | 0 | **sem tabela equivalente** |
| **CRM / pós-venda** | 16.345 | 0 | **sem tabela equivalente** |
| RH / colaboradores | 0 | 0 | vazio na origem |
| Promoções | 0 | 0 | vazio na origem |

No ERP novo: **168 tabelas, 140 vazias**. É exatamente o que se vê navegando.

### Por que não foram migrados

Os migradores de financeiro, estoque, caixa, fiscal, cobrança, convênio e
vale-gás foram escritos **antes de existir um dump real** e assumem nomes de
tabela/coluna que não conferem com o Oracle. Quando o ETL roda, eles encontram
a tabela inexistente, caem no `catch` e reportam "0 lidos, 0 gravados" — sem
erro. É a pendência já registrada em `MIGRACAO_DADOS_LEGADOS.md` §5.4.

Exemplos concretos da diferença de nomenclatura:

| Migrador espera | Existe no Oracle |
|---|---|
| `financeiro` | `FINANCEIROS` + `FINANCEIROPARCELAS` |
| `nfe` / `notafiscal` | `NFEMITIDAS` + `NFEMITIDAITEMS` |
| `estoque` | `ESTOQUES` + `ESTOQUESETORHISTORICOS` |
| `caixa` | `CAIXAS` + `CONTAMOVIMENTOS` |

## Chaves de API e credenciais

Existem no dump, parcialmente preenchidas — 2 das 7 empresas têm configuração:

| Credencial | Onde | Preenchido |
|---|---|---|
| Chave PIX | `EMPRESACONFIGS.CHAVEPIX` | 2 de 7 |
| `CLIENT_ID` / `CLIENT_SECRET` (PIX) | `EMPRESACONFIGS` | 2 |
| Google Maps | `EMPRESACONFIGS.KEYGOOGLEMAPS` | 2 |
| Senha do PFX | `EMPRESAS.NFESENHAPFX` | 7 |
| **Certificado digital A1** | `EMPRESAS.CERTIFICADODIGITAL` | **0 de 7** |

Nenhuma dessas foi migrada — o `EmpresaConfigMigrator` não trata esses campos.

**O certificado é a única lacuna real do dump.** A coluna existe, a senha do PFX
está lá, mas o conteúdo do `.pfx` está nulo nas 7 empresas. Pode ter sido
removido no export (é dado sensível) ou o certificado é guardado em arquivo, fora
do banco.

## O que isso significa na prática

Para testar **fiscal, financeiro, estoque e caixa com dados reais** o caminho é
escrever os migradores desses módulos — não pedir outro banco. O dado já está
aqui, em volume de produção de 6 anos.

Do cliente, vale pedir só:

1. **O certificado digital A1** (`.pfx` + senha) — sem ele dá para migrar e
   conferir o histórico fiscal, mas não para **emitir** NFC-e/NF-e em teste.
   Peça um certificado de homologação, nunca o de produção.
2. **Confirmação das credenciais de PIX/Maps** que quiser usar em teste — as do
   dump são de produção e não deveriam ser reaproveitadas.

## Ordem sugerida para os migradores que faltam

Pela dependência entre módulos e pelo valor de teste:

1. **Estoque** — precisa vir antes do fiscal (item de nota referencia produto/saldo).
2. **Financeiro** (443 mil lançamentos + 475 mil parcelas) — alimenta caixa,
   cobrança e os relatórios de DRE.
3. **Fiscal** (241 mil notas) — o módulo que você quer testar; depende de
   produto, cliente e financeiro.
4. **Caixa/conta**, **cobrança**, **convênio**, **vale-gás**, **comodato**.

Cada um precisa do mesmo tratamento que clientes e pedidos receberam: conferir
os nomes reais no Oracle, preservar ids, sanitizar FKs e herdar `empresa_id`.
