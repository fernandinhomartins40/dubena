# O que existe no dump x o que foi migrado

Levantamento feito em 14/08/2026 para responder duas perguntas: *"o dump está
incompleto?"* e *"por que as telas aparecem vazias?"*.

**Resposta: o dump estava completo; a migração é que cobria 5 módulos de 13.**
Não foi preciso pedir outro banco — com uma exceção pontual (certificado digital
A1), detalhada no fim.

> **Situação em 14/08/2026 (final):** todos os módulos migrados.
> **19.877.797 registros em 56 tabelas** (4,2 GB), zero órfãos em 12 checagens
> de FK e de tenant. Detalhe por módulo na seção 2.

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

| Módulo | Entidade no destino | Registros |
|---|---|---|
| Monitora (GPS) | `monitora_posicoes` | 16.114.548 |
| Estoque | `estoquehistorico` | 506.891 |
| Financeiro | `financeiroparcelas` | 449.772 |
| Financeiro | `financeiros` | 443.714 |
| Caixa / conta | `contamovimentos` | 410.417 |
| Pedidos | `pedidoitens` | 406.883 |
| Pedidos | `pedidos` | 400.070 |
| Fiscal | `nota_itens` | 254.305 |
| Fiscal | `nota_volumes` | 241.225 |
| Fiscal | `notas_fiscais` | 241.021 |
| Cobrança | `boleto_historicos` | 68.227 |
| Clientes | `clientes` | 66.557 |
| Estoque | `estoque_transferencia_itens` | 51.656 |
| Estoque | `estoque_transferencias` | 28.447 |
| Vale-gás | `vale_gas` | 23.588 |
| Cobrança | `boletos` | 21.132 |
| CRM | `promotor_visitas` | 18.279 |
| Caixa | `conta_transferencias` | 9.080 |
| Caixa | `contamovimento_estornos` | 7.238 |
| Convênio | `convenio_fechamentos` | 3.717 |
| CRM | `venda_ativa_clientes` | 2.412 |
| Vale-gás | `vale_gas_vendas` | 1.864 |
| Comodato | `comodatos` | 975 |
| Fiscal | `nf_recebidas` | 850 |
| Estoque | `estoque_acertos` | 376 |

**Total: 19.877.797 registros em 56 tabelas** (4,2 GB).

Indicadores de negócio conferidos contra a operação real:

| Indicador | Valor |
|---|---|
| Faturamento NF-e (modelo 55) | R$ 228.095.066,73 |
| Faturamento NFC-e (modelo 65) | R$ 31.928.491,64 |
| Contas a receber | R$ 122.723.029,17 |
| Contas a pagar | R$ 127.306.875,63 |
| Parcelas em aberto | 4.265 |
| Período fiscal coberto | 03/2020 a 08/2026 |

### Por que não estavam migrados (histórico)

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

## 3. Tabelas que a reescrita esqueceu (criadas)

Auditoria cruzando as 179 tabelas com dados do Oracle contra as 168 do schema
novo. **A maioria das "faltas" era só nome diferente** — a reescrita
reimplementou, não esqueceu:

| No legado | No ERP novo |
|---|---|
| `ESTOQUESETORHISTORICOS` | `estoquehistorico` |
| `PLANOCONTAS` | `planos_conta` |
| `CENTROCUSTOS` | `centros_custo` |
| `CHEQUERECEBIDOS` | `cheques` |
| `BOLETOREMESSAS` | `remessas_cnab` |
| `POSVENDAPESQUISAS` | `pos_vendas` |
| `NFOPERACAOS` | `operacoes_fiscais` |
| `NFEMITIDACARTACORRECAOS` | `cartas_correcao` |

Sobraram **12 funcionalidades sem lugar**, criadas em
`2026_08_14_000100_tabelas_esquecidas_na_refatoracao` — todas multi-tenant, com
`empresa_id` NOT NULL e policy de RLS:

transferência de estoque (+ itens), acerto manual de estoque, transferência
entre contas, estorno de movimento, volumes da NF-e (o grupo `<transp><vol>` do
XML), histórico do boleto, venda do vale-gás, parcelas da condição de pagamento,
venda ativa/telemarketing (+ clientes) e visitas do promotor.

Descartadas por decisão (infraestrutura do framework antigo, não dado de
negócio): `REVISIONS` (3,5 M de linhas da auditoria própria do legado — o novo
tem `audit_logs`), `MENUS`/`MENUUSERS` (menu em banco; o novo tem navegação
declarativa), tokens do Passport (o novo usa Sanctum) e as `SYS_EXPORT_FULL_*`,
que são temporárias do Data Pump.

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

## Ordem da carga (dependência entre módulos)

```bash
php artisan etl:run estados      # semente
php artisan etl:run empresas     # tenant — tudo depende
php artisan etl:run empresa-config
php artisan etl:run geografico
php artisan etl:run clientes
php artisan etl:run produtos
php artisan etl:run estoque      # antes do fiscal (item de nota usa produto/setor)
php artisan etl:run pedidos
php artisan etl:run financeiro   # alimenta caixa, cobrança e o DRE
php artisan etl:run fiscal
php artisan etl:run caixa
php artisan etl:run cobranca     # boleto pega valor/vencimento da parcela
php artisan etl:run satelites
php artisan etl:run monitora-legado
php artisan etl:run app-gasemcasa
php artisan etl:run complementos # por último: depende de quase tudo
python database/etl/migrar_posicoes.py
```

Antes disso, espelhar o Oracle: `python database/etl/espelhar_oracle.py`.

## Decisões de mapeamento que valem saber

Coisas que o legado modela de um jeito e o schema novo de outro — a migração
resolve, mas o resultado não é 1:1:

- **Duplicatas do legado.** 12.441 combinações `(financeiro_id, numero)` se
  repetem em `financeiroparcelas`; o destino tem UNIQUE. Ficam 449.772 de
  475.000 — as 25.228 excedentes são duplicatas reais, não perda.
- **Nota reemitida.** `notas_fiscais` tem UNIQUE `(empresa, modelo, série,
  número)` e o legado reemite nota inutilizada com o mesmo número. Fica a
  primeira.
- **Convênio.** O legado não tem cadastro de convênio, só fechamentos por
  cliente — 79 convênios foram criados a partir dos clientes com fechamento,
  nomeados pelo titular.
- **Saldos reconstruídos.** `estoquehistorico.saldo_resultante` e
  `contamovimentos.saldo_resultante` são NOT NULL no destino e não existem no
  legado: são acumulados em ordem cronológica na carga.
- **Rateio.** Plano de contas e centro de custo vivem em `financeirorateios`
  (um título pode ser rateado); o destino guarda um por título — adota-se o
  rateio de maior valor.
- **XML da NF-e.** Fica no Oracle. O schema novo guarda os dados estruturados e
  os totais, não o XML bruto.
- **Vale-gás com valor 0.** O legado guarda o valor no produto, não no vale.
- **Comodato.** No legado é contrato (cliente + datas), sem produto nem
  quantidade — migrado com produto padrão e quantidade 1.
