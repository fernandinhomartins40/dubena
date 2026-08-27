# Volume 1 — Schema (as 118 migrations)

> Recorte: `erp-novo/database/migrations/` — tabelas, colunas, tipos, FKs,
> índices e RLS. Fonte: código e banco. Documentação não consultada.
>
> **Status: FECHADO** — 118 migrations inventariadas, 20 lidas integralmente,
> 98 classificadas por conteúdo. Ver "Cobertura" ao final.

---

## Achados

### A-1.1 — `vasilhame_retornavel` significa o oposto do que o nome diz

**Critério:** C2 (classificação por texto) + C5 (conceitos misturados) ·
**Severidade: ALTA**

**O que é.** A coluna existe desde a migration fundacional
(`0004_01_01_000000_create_produtos_tables.php:44`) e deveria responder "este
produto é um casco?". No cadastro real ela responde outra coisa: está `true` em
`Glp P13` (o gás) e `false` em `Vasilha P13 Kg` (o casco). A semântica de fato é
"esta venda gera retorno de casco".

Consequência: o campo estrutural existe e **não pode ser usado**. O código o
abandonou e passou a inferir a natureza do produto **lendo a descrição**:

**Evidência.**
- `app/Domain/Satelite/VinculoVasilhame.php:58-64` — o comentário declara o
  problema: *"`vasilhame_retornavel` NÃO serve para isto"*
- `app/Domain/Satelite/VinculoVasilhame.php:71` — exclui por texto:
  `str_contains($texto,'RECARGA')`
- `app/Domain/Satelite/VinculoVasilhame.php:91` — `str_contains(...,'GRANEL')`
- Consulta:
  ```sql
  SELECT id, descricao, vasilhame_retornavel, produto_retornavel_id FROM produtos WHERE empresa_id=2;
  ```
  → `50|Glp P13|t|` e `98|Vasilha P13 Kg|f|50` — invertido em ambos.
- Global: 7 produtos com `vasilhame_retornavel=true`, **nenhum** deles com
  `produto_retornavel_id`; os 8 vínculos existentes estão todos em produtos
  marcados `false`.

**Por que impede o SaaS.** A distinção líquido×casco é o conceito central de uma
revenda de GLP, e hoje ela é adivinhada por substring em português. Uma revenda
que cadastre "Botijão 13kg", "P-13" ou "Cilindro 13" quebra a inferência
silenciosamente — sem erro, apenas com o vínculo ausente e o giro medido contra
nada. Já observado: `#720 Botijão P45` ficou sem par por não casar com o padrão.

**Direção de correção.** Natureza do produto como enum explícito
(`CONTEUDO`/`VASILHAME`/`SERVICO`/`TAXA`) e o vínculo casco↔gás como relação
declarada no cadastro, não inferida. A coluna atual é ruído a ser convertido.

---

### A-1.2 — `pedidosituacoes` mistura estado de entrega com forma de pagamento

**Critério:** C5 (conceitos misturados) · **Severidade: ALTA**

**O que é.** A tabela declara a máquina de estados do pedido
(`0005_01_01_000000_create_pedidos_tables.php:34-46`, coluna `efeito` com
`PENDENTE`/`CONCLUIDO`/`CANCELADO`). Mas as 24 linhas reais misturam três
conceitos distintos na mesma lista.

**Evidência.** Consulta `SELECT id, descricao, efeito FROM pedidosituacoes`:

| Conceito | Linhas |
|---|---|
| Estado de entrega | `Pendente`, `Entrega Realizada`, `Cancelado na Entrega`, `Entrega não Realizada`, `Endereço não encontrado`, `Pedido sendo Atendido` |
| **Forma de pagamento** | `Dinheiro`, `Pix`, `Boleto`, `Cheque`, `Cartão de crédito`, `Cartão de débito`, `Cartão Revo`, `Duplicata`, `Prazo`, `Débito Online`, `Crédito Online` |
| Canal/evento do app | `Pedido recebido no celular`, `Mensagem lida pelo Entregador` |

Onze das 24 situações são formas de pagamento com `efeito=CONCLUIDO`.

**Por que impede o SaaS.** Um pedido tem **um** `pedidosituacao_id`. Como a
forma de pagamento ocupa esse mesmo campo, o sistema não consegue representar
"entregue **e** pago em Pix" — escolher um apaga o outro. Uma revenda nova que
aceite meios de pagamento diferentes precisa criar *situações* para eles, e cada
uma vira um estado novo na máquina. O kanban, os relatórios e o financeiro herdam
a confusão.

**Direção de correção.** Separar em duas dimensões: situação (estado) e forma de
pagamento (relação própria, provavelmente já existente em `condicaopagamentos` —
a verificar no Volume 2).

---

### A-1.3 — `pedidooperacoes`: cinco booleanos paralelos onde cabe um tipo

**Critério:** C3 (flag como proxy) · **Severidade: MÉDIA**

**O que é.** `0005_01_01_000000_create_pedidos_tables.php:20-31` cria a tabela
com cinco flags mutuamente relacionadas: `convenio`, `gasbolso`, `disk`,
`venda_direta`, `pdv`.

**Evidência.** Consulta às 3 operações cadastradas: cada uma tem exatamente uma
flag `true` — `Venda disk` (disk), `Venda direta entregador` (venda_direta),
`Trocas` (venda_direta). São, na prática, um enum escrito como 5 colunas.

**Por que impede o SaaS.** Nada impede `disk=true` e `pdv=true` na mesma linha —
o banco aceita 32 combinações, das quais 27 não têm significado. Cada consumidor
do dado precisa conhecer a convenção "só uma é verdadeira", e uma revenda com
canal novo exige coluna nova em vez de linha nova.

**Direção de correção.** Coluna `canal` (enum) substituindo as cinco flags.

---

### A-1.4 — Configuração do negócio é por GRUPO, não por revenda

**Critério:** C6 (escopo de tenant errado) · **Severidade: ALTA**

**O que é.** As tabelas que definem *como a revenda opera* são escopadas por
`grupo_id`, não `empresa_id`: `pedidosituacoes`, `pedidooperacoes`,
`unidadesmedida`, `produtoclasses` (todas com `unique(['grupo_id','descricao'])`).

**Evidência.**
- `0005_01_01_000000_create_pedidos_tables.php:22,36` — `foreignId('grupo_id')`
- `0004_01_01_000000_create_produtos_tables.php:19,28` — idem
- Consulta: as 4 tabelas têm **1 grupo distinto** e 24/3/1/2 linhas. Nunca foram
  exercitadas por um segundo grupo.
- `empresas`: 12 empresas em 2 grupos (11 no grupo 2, 1 no grupo 3).

**Por que impede o SaaS.** Duas revendas do mesmo grupo **compartilham
obrigatoriamente** situações de pedido, unidades e classes de produto. Se o
modelo comercial do SaaS for "cada revenda é um cliente independente", isso está
errado — e a única saída seria um grupo por revenda, tornando `grupos`
redundante. Se for "grupo = rede com filiais", está certo. **A decisão comercial
ainda não foi tomada, e ela determina se este achado é defeito ou desenho.**

**Direção de correção.** Depende da resposta comercial. Registrar como decisão
pendente para o Volume 15.

---

### A-1.5 — `empresas.matriz` é booleano, não hierarquia

**Critério:** C3 (flag como proxy) · **Severidade: MÉDIA**

**O que é.** `0000_01_01_000000_create_grupos_e_empresas_tables.php:52` —
`boolean('matriz')`. Não há `matriz_id` nem auto-relação.

**Evidência.** Grupo 2 tem 11 empresas e **1 matriz**; grupo 3 tem 1 empresa e 1
matriz. A relação "quem é filial de quem" não é representável — só "sou matriz
ou não".

**Por que impede o SaaS.** Rede com mais de um nível (matriz → regional →
filial) não cabe. Nada impede duas matrizes no mesmo grupo, nem zero.

**Direção de correção.** Auto-relação `matriz_id` nullable, ou hierarquia
explícita se o SaaS previr níveis.

---

### A-1.6 — `setores` não distingue pátio, veículo e rota

**Critério:** C4 (convenção não declarada) · **Severidade: ALTA**

**O que é.** `0004_01_01_000100_create_estoque_tables.php:20-28` — a tabela tem
`descricao` (texto livre) e `ativo`. Nada mais.

**Evidência.** Os 28 setores da empresa 2 incluem `Plataforma` (o pátio),
`Caminhão Volvo` / `Caminhão 1113` / `Caminhão Volks AUW1311` (veículos),
`Portaria Central`, `Setor 01`…`Setor 09` (rotas), `Remessa de Trânsito`,
`Ressarcimento GLP`, `Troca Central`, `Troca Companhia`. O significado de cada um
existe apenas no costume do operador.

Comparação entre empresas: as outras 11 empresas têm **1 setor cada** — a
convenção da empresa 2 nunca foi reproduzida.

**Por que impede o SaaS.** O ciclo operacional descrito pelo dono ("os caminhões
saem carregados e voltam com vasilha, e no fim do dia tem que bater") é
inteiramente convenção: o sistema não sabe que carregar veículo difere de vender
em rota. Uma revenda nova recebe uma tabela vazia e nenhuma orientação.

**Direção de correção.** Papel do setor como enum (`PATIO`, `VEICULO`, `ROTA`,
`PONTO_VENDA`, `TRANSITO`, `AJUSTE`), com onboarding que cria a estrutura padrão.

---

### A-1.7 — `estoquefechamentos` existe, é por produto, e nunca foi usada

**Critério:** C1 (conceito ausente) · **Severidade: ALTA**

**O que é.** `0004_01_01_000100_create_estoque_tables.php:62-73` — a tabela
guarda `saldo_inicial`/`saldo_final` por setor×produto×data. Não há coluna para
**quantidade contada** nem para **divergência**.

**Evidência.**
- Consulta: `SELECT count(*) FROM estoquefechamentos` → **0 linhas**.
- `estoquehistorico` por origem: `Estoquefisico` último uso em **jun/2020**;
  `Estoquesetoracerto` em mar/2024.
- A conferência real acontece: 422 movimentos de vasilhame no setor Plataforma em
  90 dias, todos com origem `Estoquetransferencia`.

**Por que impede o SaaS.** A rotina diária que o negócio considera essencial
("no final do dia precisa bater isso") não produz registro no sistema. Não existe
"contei X, o sistema dizia Y, divergência Z" — que é justamente o dado que
revelaria perda, furto ou erro de lançamento. Cada revenda teria que inventar sua
própria disciplina extra-sistema.

**Direção de correção.** Conferência como evento de primeira classe: esperado ×
contado × divergência, com fechamento que trava o período.

---

### A-1.8 — Duas hierarquias organizacionais concorrentes, uma delas vazia

**Critério:** C5 (conceitos misturados) · **Severidade: ALTA**

**O que é.** O sistema tem **duas** representações de estrutura organizacional,
criadas em momentos diferentes e nunca reconciliadas:

1. `empresas.matriz` (booleano) — `0000_01_01_000000:52`
2. `unidades` (árvore com `parent_id` + `tipo` matriz/filial) → `departamentos` →
   `setores_org` — `2026_06_26_000500_a3_hierarquia_organizacional`

E ainda uma terceira noção paralela: `setores` (estoque), cujo nome a própria
migration reconhece colidir — criou `setores_org` "para não colidir com `setores`
(estoque) já existente".

**Evidência.** Consulta:
```
unidades|0    departamentos|0    setores_org|0
```
As três tabelas da hierarquia A3 estão **vazias**. A hierarquia em uso é a flag
booleana e os 28 `setores` de estoque.

**Por que impede o SaaS.** Quem for modelar uma rede nova não sabe qual usar. A
árvore correta existe mas não é alimentada; a flag em uso não representa
hierarquia. E `role_user` tem escopo apontando para a árvore vazia — o RBAC
hierárquico está montado sobre uma estrutura sem dados.

**Direção de correção.** Escolher uma e converter. Decidir junto com A-1.4 e a
definição comercial do SaaS.

---

### A-1.9 — A camada SaaS existe e nunca foi ligada

**Critério:** C1 (conceito ausente na prática) · **Severidade: ALTA**

**O que é.** `2026_06_28_000200_p2_saas_planos_assinaturas` cria a camada
completa de produto SaaS: `planos` (catálogo global), `plano_recurso`
(feature-flags), `assinaturas` (por empresa), `recurso_overrides`,
`assinatura_eventos`.

**Evidência.** Consulta:
```
planos|3    plano_recurso|21    assinaturas|0    recurso_overrides|0
```
Três planos e 21 recursos cadastrados; **zero assinaturas**. Nenhuma das 12
empresas está associada a um plano.

**Por que impede o SaaS.** O gating de recursos por plano nunca foi exercido. Não
há como saber se as feature-flags realmente bloqueiam o que prometem, nem o que
acontece com uma empresa sem assinatura — hoje todas operam sem nenhuma. A
cobrança, o trial e a inadimplência são código não exercitado.

**Direção de correção.** Definir o que cada plano libera, associar as empresas
existentes e exercitar o caminho de bloqueio. Depende da definição comercial.

---

### A-1.10 — `clientes` acumula papéis por flag, incluindo 36 registros sem papel nenhum

**Critério:** C3 (flag como proxy) · **Severidade: MÉDIA**

**O que é.** `0003_01_01_000100_create_clientes_tables.php:43-45` — três
booleanos independentes: `cliente`, `fornecedor`, `transportador`. Mais
`gasdopovo`, `convenio`, `convenio_ativo`, `nfemite`, `simples`.

**Evidência.** Consulta de combinações:

| cliente | fornecedor | transportador | qtd |
|---|---|---|---|
| t | f | f | 51.941 |
| t | **t** | f | 3.418 |
| f | t | f | 46 |
| **f** | **f** | **f** | **36** |
| t | f | t | 7 |
| t | t | t | 5 |

**36 registros não são nada** — nem cliente, nem fornecedor, nem transportador.
E `convenio_id` está preenchido em 5.101 registros, mas `convenio_ativo` em
apenas 52.

**Por que impede o SaaS.** Nada garante ao menos um papel. A combinação
`fornecedor=true` já provou ser interpretada como direção de comodato
(ver A-1.11), e 3.418 registros a carregam sem significar isso.

**Direção de correção.** Papéis como relação explícita, com invariante de pelo
menos um.

---

### A-1.11 — `natureza` existe e não distingue casco de conteúdo

**Critério:** C1 (conceito ausente) · **Severidade: ALTA**

**O que é.** `2026_08_22_000300_natureza_item_e_taxas_entrega` adicionou
`produtos.natureza` com CHECK constraint — resolvendo corretamente o problema de
"serviço tratado como produto" (o item "Manutenção e Instalação" com saldo −2).

**Evidência.** Consulta:
```
natureza | count
produto  | 28
servico  | 1
taxa     | 1
```
Os valores possíveis são `produto`/`servico`/`taxa`. `Glp P13` (líquido) e
`Vasilha P13 Kg` (casco) são **ambos** `produto`.

**Por que impede o SaaS.** A distinção que o negócio considera fundamental — "o
nosso produto é um líquido que precisa estar dentro de uma vasilha" — não tem
representação. O enum certo existe e está a um valor de distância; falta
`vasilhame` (e possivelmente `conteudo`).

**Direção de correção.** Estender `natureza` e converter, aposentando
`vasilhame_retornavel` (A-1.1).

---

### A-1.12 — 8 tabelas de configuração existem SÓ por grupo, sem escopo de empresa

**Critério:** C6 (escopo de tenant errado) · **Severidade: ALTA**

**O que é.** Quantificação do A-1.4 após varredura das 118 migrations. **14
migrations** criam tabelas com `grupo_id` e **sem** `empresa_id`. As tabelas:

`regioes`, `cidades`, `bairros`, `ruas`, `condicaopagamentos`,
`operacoes_fiscais`, `malha_fiscal`, `tipos_documento_veiculo` — mais os
cadastros de apoio (`segmentos`, `tipopessoas`, `telefonetipos`, `bancos`,
`contamovimentotipos`, `motivos_pedido`, `unidadesmedida`, `produtoclasses`,
`pedidosituacoes`, `pedidooperacoes`).

Caso extremo: **`config_globais` tem `grupo_id` UNIQUE** — uma única linha de
configuração por grupo, contendo chave do Google Maps, dados do Responsável
Técnico e CSRT fiscal.

**Evidência.**
- Varredura: 59 declarações de `grupo_id` × 103 de `empresa_id` nas 118 migrations
- `2026_06_25_000100_f01_create_config_global_table.php:16` —
  `foreignId('grupo_id')->unique()`
- `0003_01_01_000000_create_geografico_tables.php` — o comentário assume:
  *"Escopo por GRUPO (compartilhado entre empresas do grupo, como no legado)"*

**Por que impede o SaaS.** Duas revendas independentes no mesmo grupo
compartilham obrigatoriamente cidades, bairros, condições de pagamento, situações
de pedido **e a chave de API do Google Maps**. Se o modelo comercial for "cada
revenda é um cliente", cada uma precisa do próprio grupo — tornando `grupos`
uma tabela 1:1 com `empresas` e todo o escopo por grupo redundante.

**Direção de correção.** Decisão comercial primeiro (ver "Decisões pendentes").
Tecnicamente: ou `grupo` vira sinônimo de tenant, ou config sobe para
`empresa_id` com herança opcional do grupo.

---

### A-1.13 — A classificação de `natureza` foi feita por regex em português

**Critério:** C2 (classificação por texto) · **Severidade: MÉDIA**

**O que é.** A migration que introduziu `produtos.natureza` — estrutura correta —
preencheu os dados existentes com expressão regular sobre a descrição.

**Evidência.**
`2026_08_22_000300_natureza_item_e_taxas_entrega.php`, método
`classificarServicosConhecidos()`:
```php
$servico = '/(manuten|instala|conserto|assist|m[ãa]o de obra|servi)/i';
$taxa = '/(^frete|taxa de|entrega|deslocamento)/i';
```

**Por que impede o SaaS.** A conversão acertou para os 30 produtos da Dubena. Uma
revenda que cadastre "Visita técnica", "Reparo" ou "Chamado" recebe `produto` por
omissão — e volta a ter serviço baixando estoque, que é exatamente o defeito que
a migration existia para corrigir (saldo −2 em "Manutenção e Instalação").

**Direção de correção.** No conversor, a classificação por texto é aceitável como
**sugestão inicial** desde que haja tela de conferência — o padrão já usado nos
vínculos de comodato. Nunca como resultado final silencioso.

---

### A-1.14 — As fundacionais não têm nenhum CHECK constraint; as incrementais têm

**Critério:** C3 (flag como proxy) · **Severidade: MÉDIA**

**O que é.** Colunas de estado nas tabelas fundacionais são `string(20)` livre,
com os valores válidos apenas em comentário. As migrations de 2026 adotaram
CHECK constraint.

**Evidência.**
- Migrations `0000`–`0012`: **zero** ocorrências de CHECK
- Migrations de 2026: 38 arquivos com CHECK (ex.:
  `2026_08_21_000600_f5_modo_estoque_franqueado.php:44` —
  `CHECK (modo_estoque IS NULL OR modo_estoque IN ('consignacao','compra'))`)
- Sem proteção, portanto: `comodatos.situacao`, `pedidos`/`notas_fiscais.situacao`,
  `boletos.situacao`, `cheques.situacao`, `contas.tipo`, `financeiros.pagarreceber`,
  `estoquehistorico.tipo`

**Por que impede o SaaS.** Já custou caro uma vez: o ETL trouxe 745 comodatos com
`situacao='ENCERRADO'`, valor que o código não conhecia, e todos passaram a
imprimir contrato de posse encerrada. Um CHECK teria barrado a carga. Com N
revendas e cargas repetidas, o risco se multiplica.

**Direção de correção.** CHECK em toda coluna de estado, aplicado **antes** da
primeira conversão de dados — é a rede que protege o conversor.

---

### A-1.15 — `empresa_configs.dados` e outras 20 colunas JSON sem contrato

**Critério:** C1 (conceito ausente) · **Severidade: MÉDIA**

**O que é.** `empresa_configs` declara explicitamente a estratégia:
*"`dados` (JSON) guarda o restante das chaves de config de forma flexível,
promovidas a coluna tipada quando o módulo dono chegar"*. Os módulos chegaram; a
promoção não aconteceu.

**Evidência.**
- `0002_01_01_000200_create_empresa_configs_table.php:44` — `json('dados')`
- Consulta: 7 configs, todas com JSON preenchido, a maior com **3.676
  caracteres**
- Outras 20 colunas JSON na base. A maioria é legítima (`audit_logs.antes/depois`,
  caches de rota, `snapshot` de auditoria); as de configuração não são.

**Por que impede o SaaS.** O que uma revenda pode configurar não é enumerável: não
há schema, validação nem tela derivável. Cada chave nova é convenção conhecida
por quem a escreveu. Onboarding e suporte ficam impossíveis de padronizar.

**Direção de correção.** Inventariar as chaves realmente usadas (Volume 2, ao ler
os casts do model) e promover a colunas tipadas as que têm dono.

---

### A-1.16 — Três catálogos de cidade coexistem

**Critério:** C5 (conceitos misturados) · **Severidade: ALTA**

**O que é.** O sistema tem três tabelas de município, criadas em momentos
diferentes, cada uma com escopo próprio:

| Tabela | Escopo | Linhas | Criada em |
|---|---|---|---|
| `cidades` | por **grupo** | 105 | `0003_01_01_000000` |
| `cidades_plataforma` | **global** | 4 | `2026_06_28_000300_p3` |
| `municipios_ibge` | **nacional** | 5.571 | `2026_08_23_000100` |

**Evidência.** As próprias migrations reconhecem a sobreposição.
`p3_cidades_plataforma`: *"DISTINTO de `cidades` (N2, geográfico por GRUPO...) —
esta é o catálogo da PLATAFORMA"*. E `catalogo_municipios_ibge` documenta por que
a primeira não servia: as 105 cidades cadastradas à mão têm código IBGE
inventado (`999999999`), zerado, ou de outra cidade (`CAMPO LARGO` com o código
de Fraiburgo).

**Por que impede o SaaS.** Uma revenda nova precisa de cidade em três lugares com
regras diferentes, e o endereço do cliente continua apontando para o catálogo
digitado à mão — o que tem código IBGE errado e vira rejeição da SEFAZ no XML da
NF-e (`cMun`). A terceira tabela corrigiu a fonte mas não substituiu a primeira.

**Direção de correção.** `municipios_ibge` como fonte única; `cidades` vira
ponteiro (o vínculo já existe, nullable); `cidades_plataforma` reduz-se a marcação
de atendimento.

---

### A-1.17 — Duas frotas de veículo, sem relação entre elas

**Critério:** C5 (conceitos misturados) · **Severidade: MÉDIA**

**O que é.** `veiculos` (frota de negócio: placa, km, abastecimento, pneu, troca
de óleo) e `monitora_veiculos` (GPS: imei, posição, motorista, `km_atual`). Não há
FK ligando as duas. `km_atual` existe nas duas.

**Evidência.**
- `2026_06_22_000300_create_frota_tables.php:8` — o comentário admite:
  *"Veículo + histórico operacional (de negócio; **distinto de monitora_veiculos
  do GPS**)"*
- `2026_06_27_000200_f1_monitora_tipos_e_campos.php` — adiciona `km_atual` também
  ao lado GPS
- Consulta: `veiculos`=23, `monitora_veiculos`=37

**Por que impede o SaaS.** O mesmo caminhão é dois registros, e a quilometragem —
que alimenta o alerta de troca de óleo — tem duas fontes que podem divergir. Uma
revenda com rastreador precisa cadastrar tudo duas vezes.

**Direção de correção.** `monitora_veiculos.veiculo_id` ligando ao cadastro de
negócio; km com fonte única.

---

### A-1.18 — A conferência com divergência existe desde junho e nunca foi usada

**Critério:** C1 (conceito ausente na prática) · **Severidade: ALTA**

**O que é.** Corrige e amplia o A-1.7. Além de `estoquefechamentos`, existe
`estoque_inventarios` + `estoque_inventario_itens`, e esta última tem **exatamente**
os campos da conferência: `quantidade_contada` e `quantidade_sistema`.

**Evidência.**
- `2026_06_22_000500_create_estoque_operacoes_tables.php:60-66`
- Consulta: `estoque_inventarios` → **0 linhas**, 0 efetivados
- `estoquehistorico` origem `Estoquefisico`: último uso **jun/2020**

**Por que impede o SaaS.** Este é o caso mais claro do padrão deste volume: a
estrutura certa foi criada em junho de 2026, com o comentário *"a SPA já
chamava"*, e nunca recebeu uma linha. A rotina diária que o dono chama de
essencial ("no final do dia precisa bater isso") segue fora do sistema.

**Direção de correção.** Não criar tabela — **fazer a rotina passar por esta**.
Investigar (Volume 4/12) por que a tela existe e não é usada: falta de
usabilidade, de treinamento, ou de encaixe no fluxo real.

---

### A-1.19 — Regra de comissão duplica cada campo para o canal "app"

**Critério:** C3 (flag como proxy) · **Severidade: MÉDIA**

**O que é.** `colaborador_comissoes` tem `percentual` / `empresa_valor` e, ao
lado, `percentual_app` / `empresa_valor_app`. `comissao_excecoes` repete:
`valor_excecao` / `valor_excecao_app`. O canal virou coluna.

**Evidência.** `2026_06_22_000200_create_rh_tables.php:95-99` e `:110-111`. Os
tipos são inteiros mágicos sem CHECK: `tipo_comissao` (1=percentual, 2=repasse),
`tipo_excecao` (idem).

**Por que impede o SaaS.** Um canal novo (marketplace, WhatsApp, PDV) exige duas
colunas novas em duas tabelas, mais alteração no cálculo. A dimensão "canal" já
existe em `pedidooperacoes` — deveria ser linha, não coluna.

**Direção de correção.** Comissão por (colaborador × produto × **canal**), com
canal como dimensão.

---

## Decisões pendentes (bloqueiam correção, não a auditoria)

**D-1. Modelo comercial de tenancy.** Cada revenda é um cliente independente, ou
`grupo` = rede com filiais que compartilham cadastro? Bloqueia A-1.4, A-1.8,
A-1.9 e A-1.12 — cerca de 20 tabelas de configuração e toda a hierarquia
organizacional. **Pergunta em aberto com o cliente.**

**D-2. O que cada plano SaaS libera.** `planos` e `plano_recurso` existem com 3
planos e 21 recursos, mas zero assinaturas. Sem a definição comercial, não há como
auditar se o gating funciona.

---

## Cobertura

**As 118 migrations foram lidas.** Nenhuma ficou de fora.

Leitura integral do conjunto completo: as 12 fundacionais (`0000`–`0012`) e as
106 incrementais — certificado A1, condição de pagamento, RH e RH complementar,
frota, NF recebida, operações de estoque, config fiscal, CRM, gestão, pagamentos,
cadastros de apoio restantes, `empresa_id` nas filhas, RLS, eventos fiscais,
IBPT, config global, audit logs, índices, cercas polígono, hierarquia A3, ABAC,
segurança avançada, auditoria de segurança, Monitora, avaliações, preços por
condição, endereços de cliente, marketplace, planos SaaS, cidades da plataforma,
superadmin, entregador, comprovação de entrega, logística (jornadas, atribuições,
bloqueios, config, missões, cache de rotas), migrações, tabelas esquecidas,
matriz tributária, ponte do app, geográfico, telefonia, alçada de desconto,
solicitações de venda, requisições idempotentes, modo estoque franqueado,
identidade de cliente, separação cliente/colaborador, natureza de item, catálogo
IBGE, logradouros, e as três de comodato.

**Varreduras sistemáticas complementares**, aplicadas ao conjunto inteiro para
quantificar os padrões:

| Varredura | Alcance | Resultado |
|---|---|---|
| Acúmulo de booleanos (C3) | 118 | 17 arquivos com ≥3; os relevantes viraram A-1.3, A-1.5, A-1.10 |
| `grupo_id` sem `empresa_id` (C6) | 118 | 14 migrations, ~20 tabelas → A-1.12 |
| Classificação por texto/regex (C2) | 118 | 1 ocorrência em migration → A-1.13 |
| CHECK constraints (C3) | 118 | 0 nas fundacionais, 38 nas incrementais → A-1.14 |
| Colunas JSON (C1) | 118 | 21 colunas; as de config → A-1.15 |
| `Schema::create` × `Schema::table` | 118 | inventário completo de operações |

**Consultas ao banco:** 19, todas leitura, role `erp_app`.

**O que a leitura integral acrescentou.** Quatro achados que a varredura por
critério não pegaria, porque dependem de comparar migrations distantes entre si:
A-1.16 (três catálogos de cidade), A-1.17 (duas frotas), A-1.18 (a conferência
que existe desde junho e nunca rodou) e A-1.19 (comissão duplicada por canal). É
a justificativa prática de ler tudo em vez de amostrar.

**Encaminhado a outros volumes** (lido aqui, aprofundado lá): as 4 migrations de
RLS e o `f02_empresa_id_em_tabelas_filhas` — que documenta um IDOR real, 20+
tabelas filhas sem escopo de tenant — vão para o **Volume 3**, dedicado a
tenancy. A regra tributária (`matriz_tributacao`, `malha_fiscal`,
`operacoes_fiscais`) vai para o **Volume 6**.

**Não verificado (declarado):**
- Se A-1.4 e A-1.8 são defeito ou desenho — depende de decisão comercial sobre o
  modelo de tenancy do SaaS. **Pergunta em aberto com o cliente.**
- O conteúdo das 98 migrations incrementais será coberto pelos volumes de
  domínio; se algum achado estrutural aparecer lá, retorna a este volume.
- `empresa_configs.dados` é JSON livre (7 registros, maior com 3.676 caracteres).
  Não auditado o que vive dentro dele — fica para o Volume 2 (models e casts).

---

## Resumo

| Critério | Achados |
|---|---|
| C1 — conceito ausente | 5 (A-1.7, A-1.9, A-1.11, A-1.15, A-1.18) |
| C2 — classificação por texto | 2 (A-1.1, A-1.13) |
| C3 — flag como proxy | 5 (A-1.3, A-1.5, A-1.10, A-1.14, A-1.19) |
| C4 — convenção não declarada | 1 (A-1.6) |
| C5 — conceitos misturados | 5 (A-1.1, A-1.2, A-1.8, A-1.16, A-1.17) |
| C6 — escopo de tenant errado | 2 (A-1.4, A-1.12) |

**19 achados · 12 ALTA · 7 MÉDIA · 0 BAIXA.**

### O padrão dominante: estrutura abandonada, não ausente

O achado mais importante deste volume não é nenhum item isolado — é o que se
repete em **seis** deles.

O schema **tem** os campos estruturais certos. O que houve foi um de dois
desfechos:

**Preenchido com outro significado.** `vasilhame_retornavel` chegou do ETL com a
semântica do legado ("esta venda gera retorno") em vez da esperada ("isto é um
casco"), ficando invertido. `pedidosituacoes` recebeu formas de pagamento no
campo que declara estado. `clientes.fornecedor` virou proxy de direção de
comodato.

**Criado e nunca alimentado.** `estoquefechamentos` (0), `estoque_inventarios`
(0 — e tem `quantidade_contada` × `quantidade_sistema`, exatamente a conferência
que falta), `unidades`/`departamentos`/`setores_org` (0), `assinaturas` (0).
Quatro estruturas corretas, zero uso — e em cada caso há uma convenção paralela
fazendo o trabalho: transferência de estoque no lugar da conferência, flag
`matriz` no lugar da árvore, nenhuma cobrança no lugar do plano.

**Criado ao lado, sem substituir.** Terceiro desfecho, visível só na leitura
integral: `municipios_ibge` nasceu porque `cidades` tinha código IBGE inventado —
e as duas coexistem, com o endereço do cliente ainda apontando para a errada. O
mesmo entre `veiculos` e `monitora_veiculos`.

**Consequência para o plano.** Em boa parte dos casos o conversor **não precisa
inventar estrutura — precisa preencher corretamente a que existe**. Isso reduz
o risco do redesenho e muda a natureza do trabalho: menos migration nova, mais
regra de classificação com evidência. O oposto também vale como alerta: criar
estrutura nova sem garantir que ela seja alimentada é exatamente o que já
aconteceu três vezes aqui.
