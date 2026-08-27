# Auditoria para SaaS — método e progresso

> **Objetivo.** Determinar, lendo o código e os dados, o que na aplicação é
> **estrutura de negócio** e o que é **convenção da Dubena** — e produzir, ao
> final, o plano de transformação em SaaS multi-revenda e a ferramenta que
> converte os dados reais para a estrutura nova.

---

## Premissas (não negociáveis durante a auditoria)

**1. A fonte é o CÓDIGO e os DADOS. Documentação não entra.**
Nenhum achado pode citar `docs/` como evidência. Documento envelhece e descreve
intenção; o código descreve o que acontece. Onde o código e um documento
divergirem, o achado é sobre o código — e a divergência em si é um achado.

**2. O banco de dados é uma CÓPIA da operação real, para trabalhar.**
Não é produção viva. A estrutura deve ser redesenhada para o SaaS; os **dados é
que se adaptam a ela**, por conversão. Nunca o contrário. Isto proíbe o reflexo
de propor correção aditiva ("coluna nova com default para não quebrar nada")
quando o modelo está errado.

**3. O alvo é N revendas, não a Dubena.**
Hoje há 12 empresas cadastradas e **1 opera** (empresa 2: 28 setores, 400 mil
pedidos; as outras 11 têm 1 setor e zero pedidos). Tudo que "funciona" é
convenção de uma única empresa, nunca exercitada por uma segunda. O critério de
avaliação é sempre: *isto sobrevive à segunda revenda?*

**4. Auditar não é corrigir.**
Nenhuma linha de produção muda durante os volumes. Achado é registro, não
conserto — corrigir no meio contamina o diagnóstico e impede ver o padrão
inteiro.

**4-BIS. Ler 100% do recorte. Amostragem não conta como auditoria.**

Esta é a premissa que já foi violada e custou caro — duas vezes documentadas:

- **2026-08-14.** `espelhar_oracle.py` espelhava 43 de ~200 tabelas do Oracle. A
  amostra parecia representativa. O resultado foi módulos inteiros vazios em
  produção, migradores lendo nomes de tabela inventados, e `catch (\Throwable) {
  return []; }` reportando zero linhas como sucesso. Ao ler tudo (espelho
  47→112): 25.228 parcelas descartadas, 400 mil pedidos sem atendente,
  `grupo_fiscal_id` nunca migrado — sem ele, 25 de 26 produtos na regra fiscal
  errada.
- **2026-08-24, neste documento.** O Volume 1 foi entregue com 26 de 118
  migrations lidas e o restante "varrido por critério", declarando cobertura. As
  92 restantes, lidas depois, renderam **4 achados que nenhuma varredura
  encontraria**: A-1.16, A-1.17, A-1.18 e A-1.19.

**A razão técnica:** varredura dirigida encontra o que já se sabe procurar.
Leitura integral encontra **contradição entre partes distantes** — duas tabelas
para a mesma coisa, estrutura criada e nunca alimentada, estrutura nova que não
substituiu a antiga. Esses achados não têm assinatura de busca; só aparecem
quando se leu os dois lados.

Se um volume for grande demais, **divide-se em mais volumes** — nunca se amostra
dentro de um. E "varri por critério" jamais é registrado como "li". As correções vêm depois, no plano.

**5. Todo achado precisa de evidência verificável.**
Arquivo e linha (`app/Domain/X/Y.php:42`) ou consulta ao banco com o resultado.
Impressão sem evidência não entra; se não deu para verificar, o achado é
"não verificado" e diz por quê.

---

## Os seis critérios

Aplicados igualmente a todos os domínios. Cada achado é classificado por um
deles.

| # | Critério | A pergunta | Exemplo já confirmado |
|---|---|---|---|
| **C1** | **Conceito ausente** | O negócio tem um conceito que o modelo não nomeia? | O casco e o líquido andam juntos ("GLP não sai sem vasilhame") e o pedido não sabe disso |
| **C2** | **Classificação por texto** | O sistema adivinha tipo lendo descrição? | `VinculoVasilhame.php:91` decide "é granel" com `str_contains(descricao,'GRANEL')` |
| **C3** | **Flag como proxy** | Booleano ou coluna fazendo papel de enum/relação? | `clientes.fornecedor` usado como direção de comodato — errado em 38 de 39 casos |
| **C4** | **Convenção não declarada** | Significado que só existe no costume do operador? | `setores` = texto livre; `Plataforma` é o pátio e `Caminhão Volvo` é veículo, mas nada no sistema sabe |
| **C5** | **Conceitos misturados** | Uma tabela/entidade carregando dois conceitos? | `pedidosituacoes` mistura estado de entrega ("Entrega Realizada") com forma de pagamento ("Pix", "Boleto", "Cheque") |
| **C6** | **Escopo de tenant errado** | Dado global que deveria ser por revenda, ou o inverso? | 141 tabelas com `empresa_id`, 69 sem — verificar caso a caso quais estão do lado errado |

**Severidade:** cada achado recebe **ALTA** (impede a segunda revenda de operar,
ou produz número errado), **MÉDIA** (obriga convenção manual no onboarding) ou
**BAIXA** (incômodo, contornável).

---

## Método por volume

A auditoria é entregue em **volumes**, cada um fechando um recorte auditável.
Cada volume produz um arquivo `AUDITORIA_SAAS_VOL_N.md` e uma atualização da
tabela de progresso abaixo.

### O que fazer em cada volume

1. **Inventariar** o recorte — arquivos, tabelas, linhas. Sem isto não há como
   afirmar cobertura.
2. **Ler o código** do recorte inteiro. Não amostrar: o objetivo é cobertura, e
   amostra já foi feita (ver "Sondagem inicial" abaixo).
3. **Confrontar com os dados** — para cada suspeita, uma consulta que confirma
   ou desmente. É o passo que separa achado de impressão.
4. **Registrar** cada achado com critério, severidade, evidência (arquivo:linha
   ou consulta+resultado) e impacto para o SaaS.
5. **Fechar o volume** com: quantos arquivos/tabelas foram lidos, quantos
   achados por critério e severidade, e o que ficou **não verificado** e por quê.

### Regra de cobertura

Um volume só fecha quando **100% do recorte foi lido**. Se algo não pôde ser
verificado, isso é declarado explicitamente no fechamento — silêncio não conta
como cobertura.

---

## Ordem dos volumes

A ordem é por dependência: o modelo de dados determina o desenho do SaaS e a
ferramenta de conversão, então vem primeiro.

### Camada 1 — Modelo de dados (a estrutura)

| Vol | Recorte | Tamanho |
|---|---|---|
| **1** | Schema: as 118 migrations — tabelas, colunas, tipos, FKs, índices, RLS | 118 arquivos |
| **2** | Models: os 179 models — relações, casts, fillable, escopos, tenancy | 179 arquivos |
| **3** | Tenancy e escopo: o que é por empresa, por grupo, global — e o que está errado | 210 tabelas |

### Camada 2 — Regras de negócio (o comportamento)

Os 32 domínios agrupados por afinidade e tamanho (linhas de código):

| Vol | Domínios | Linhas |
|---|---|---|
| **4** | Pedido, Venda, Produto, Estoque | ~1.850 |
| **5** | Satelite (comodato, convênio, vale-gás), Alerta | ~2.200 |
| **6** | Fiscal | ~2.920 |
| **7** | Financeiro, Cobranca, Pagamento, Caixa | ~3.030 |
| **8** | Logistica, Missao, Frota, Monitora | ~4.590 |
| **9** | Cliente, Identidade, Geografico | ~3.270 |
| **10** | Acesso, Seguranca, Tenant, Saas, Empresa | ~1.960 |
| **11** | Mobile, Integracao, Telefonia, Apoio, Rh, Gestao, Auditoria, Relatorio, Shared | ~4.400 |

### Camada 3 — Superfície (o que o usuário vê e integra)

| Vol | Recorte | Tamanho |
|---|---|---|
| **12** | API: 58 controllers admin + rotas + manifesto | ~490 endpoints |
| **13** | SPA: 30 features do frontend | ~22.800 linhas |
| **14** | ETL: os 28 migradores e as invariantes — a base da ferramenta de conversão | 28 arquivos |

### Cobertura complementar descoberta no fechamento

O inventário global revelou código próprio que não estava explicitamente nomeado
na ordem original. Para não transformar a fronteira dos volumes em nova forma de
amostragem, o fechamento também inclui:

- os **186 testes PHP** (`AUDITORIA_SAAS_TESTES_01/02/03.md`), 28.615 linhas;
- **22 arquivos de infraestrutura operacional** (`AUDITORIA_SAAS_INFRA.md`),
  1.675 linhas;
- Providers, Observer, configuração, entrypoints, seeders, factories e scripts,
  incorporados aos Volumes 12–14.

### Consolidação

| Vol | Entrega |
|---|---|
| **15** | Análise transversal: os padrões que atravessam volumes, a estrutura-alvo do SaaS, e o plano (fases, ordem, riscos, e o desenho da ferramenta de conversão) |

---

## Sondagem inicial (2026-08-24)

Feita **antes** deste método, para verificar se o padrão existia. Não é
auditoria — é a amostra que a motivou. Todos os itens abaixo devem ser
**reconfirmados** no volume correspondente.

| Achado | Critério | Evidência |
|---|---|---|
| `produtos.especie` vazia em 100% dos 16 produtos da empresa 2; `tipo_glp` nulo em 12 deles | C1/C2 | consulta ao banco |
| Distinção casco×líquido inferida por texto e capacidade | C2 | `app/Domain/Satelite/VinculoVasilhame.php` |
| `clientes.fornecedor` (3.469 registros) usado como direção de comodato | C3 | 38 de 39 clientes assim marcados compraram no último ano |
| `setores` sem papel: pátio, veículo e rota são texto livre | C4 | `Plataforma`, `Caminhão Volvo`, `Setor 04` |
| `pedidosituacoes` mistura estado de entrega e forma de pagamento | C5 | 24 linhas: "Entrega Realizada" ao lado de "Pix", "Boleto", "Cheque" |
| Pedido move o líquido e não move o casco | C1 | 398.019 movimentos por Pedido em 90 dias, **27** de vasilhame |
| Conferência diária existe na operação, não no sistema | C1 | `estoquefechamentos` vazia; `Estoquefisico` sem uso desde jun/2020 |

---

## Progresso

| Vol | Recorte | Status | Achados | Fechado em |
|---|---|---|---|---|
| 1 | Schema (118 migrations) | ✅ [fechado](AUDITORIA_SAAS_VOL_01.md) — 118/118 lidas | 19 (12 alta) | 2026-08-24 |
| 2 | Models (179) | ✅ [fechado](AUDITORIA_SAAS_VOL_02.md) — 179/179 lidos | 11 (5 alta) | 2026-08-25 |
| 3 | Tenancy e escopo | ✅ [fechado](AUDITORIA_SAAS_VOL_03.md) | 10 (6 alta) | 2026-08-25 |
| 4 | Pedido, Venda, Produto, Estoque | ✅ [fechado](AUDITORIA_SAAS_VOL_04.md) — 12/12 lidos | 10 (2 alta) | 2026-08-25 |
| 5 | Satelite, Alerta | ✅ [fechado](AUDITORIA_SAAS_VOL_05.md) — 10/10 lidos | 7 (3 alta) | 2026-08-25 |
| 6 | Fiscal | ✅ [fechado](AUDITORIA_SAAS_VOL_06.md) — 18/18 lidos | 12 (5 alta) | 2026-08-25 |
| 7 | Financeiro, Cobranca, Pagamento, Caixa | ✅ [fechado](AUDITORIA_SAAS_VOL_07.md) — 29/29 lidos | 13 (5 alta) | 2026-08-25 |
| 8 | Logistica, Missao, Frota, Monitora | ✅ [fechado](AUDITORIA_SAAS_VOL_08.md) — 39/39 lidos | 12 (4 alta) | 2026-08-25 |
| 9 | Cliente, Identidade, Geografico | ✅ [fechado](AUDITORIA_SAAS_VOL_09.md) — 18/18 lidos | 10 (3 alta) | 2026-08-25 |
| 10 | Acesso, Seguranca, Tenant, Saas, Empresa | ✅ [fechado](AUDITORIA_SAAS_VOL_10.md) — 18/18 lidos | 10 (4 alta) | 2026-08-25 |
| 11 | Mobile, Shared, Relatorio, Apoio, Auditoria, Rh, Integracao, Telefonia, Gestao | ✅ [fechado](AUDITORIA_SAAS_VOL_11.md) — 49/49 lidos | 11 (3 alta) | 2026-08-25 |
| 12 | API e infraestrutura backend | ✅ [fechado](AUDITORIA_SAAS_VOL_12.md) — 156/156 arquivos, 22.675 linhas | 19 (10 alta) | 2026-08-25 |
| 13 | SPA e configuração frontend | ✅ [fechado](AUDITORIA_SAAS_VOL_13.md) — 221/221 arquivos, 23.192 linhas | 10 (6 alta) | 2026-08-25 |
| 14 | ETL, conversão, seeders e invariantes | ✅ [fechado](AUDITORIA_SAAS_VOL_14.md) — 107/107 artefatos, 19.484 linhas | 23 (19 alta) | 2026-08-25 |
| 15 | Consolidação, arquitetura-alvo e plano | ✅ [fechado](AUDITORIA_SAAS_VOL_15.md) | 177 achados consolidados (87 alta) | 2026-08-25 |

### Apêndices de cobertura

| Apêndice | Cobertura | Achados | Estado |
|---|---:|---:|---|
| [Testes 01](AUDITORIA_SAAS_TESTES_01.md) — Domain, Unit, Migration | 44 arquivos / 4.746 linhas | 10 (3 alta) | ✅ fechado |
| [Testes 02](AUDITORIA_SAAS_TESTES_02.md) — Feature A–M | 101 arquivos / 15.874 linhas | 11 (6 alta) | ✅ fechado |
| [Testes 03](AUDITORIA_SAAS_TESTES_03.md) — Feature N–Z | 43 arquivos / 7.995 linhas | 10 (3 alta) | ✅ fechado |
| [Infraestrutura](AUDITORIA_SAAS_INFRA.md) | 22 arquivos / 1.675 linhas | 9 (5 alta) | ✅ fechado |

**Cobertura única final:** 1.151 arquivos próprios não vazios e 132.431 linhas,
mais seis placeholders vazios inventariados. Dependências, artefatos gerados,
locks, caches e `.env` real ficaram fora do recorte por definição.

---

## Formato do achado

Cada achado, nos arquivos de volume, segue esta forma:

```markdown
### A-<vol>.<n> — <título curto e concreto>

**Critério:** C3 (flag como proxy) · **Severidade:** ALTA

**O que é.** Uma frase dizendo o que o código faz hoje.

**Evidência.**
- `app/Domain/X/Y.php:42-51` — trecho ou descrição precisa
- Consulta: `SELECT ...` → resultado obtido

**Por que impede o SaaS.** O que quebra, ou que número sai errado, quando a
segunda revenda entra. Se o impacto for hipotético, dizer que é hipotético.

**Direção de correção.** Uma linha. Não é o plano — é o registro do que o
volume 15 vai desenvolver.
```

---

## Registro de decisões e correções de rumo

Erros de interpretação que já custaram trabalho, para não se repetirem.

**2026-08-24 — o banco é cópia, não produção.** Interpretei os dados reais como
operação intocável e passei a propor apenas correções aditivas (coluna com
default, filtro que preserva comportamento errado). O pedido real era
redesenhar. Toda proposta de correção aditiva num modelo errado deve ser
reexaminada sob a premissa 2.

**2026-08-24 — a flag `fornecedor` não é o que parece.** Afirmei que a
vigilância acusava a distribuidora sem verificar; havia um filtro que já a
excluía. Ao verificar, o problema real era maior e diferente: a flag cegava a
vigilância para 6.255 vasilhames de clientes legítimos. **Verificar antes de
afirmar, inclusive quando a hipótese é plausível.**
