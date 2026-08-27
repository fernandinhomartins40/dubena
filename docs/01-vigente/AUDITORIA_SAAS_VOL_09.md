# Auditoria SaaS — Volume 9: Cliente, Identidade, Geográfico

**Recorte:** `app/Domain/Cliente/`, `app/Domain/Identidade/`,
`app/Domain/Geografico/` — 18 arquivos, 3.273 linhas.
**Leitura:** 18/18 lidos integralmente (conferido por `wc -l`: 3.273).
**Data:** 2026-08-25.
**Método:** ver [AUDITORIA_SAAS.md](AUDITORIA_SAAS.md). Achados formados só a
partir do código.

---

## Arquivos lidos

| Arquivo | Linhas |
|---|---:|
| Identidade/NormalizadorTexto.php | 496 |
| Identidade/IdentidadeCliente.php | 337 |
| Identidade/ConsolidarClientes.php | 302 |
| Geografico/NormalizarCidades.php | 290 |
| Identidade/IdentificarOuCriarCliente.php | 273 |
| Geografico/ImportarLogradouros.php | 255 |
| Cliente/ClienteService.php | 243 |
| Geografico/NormalizarLogradouros.php | 241 |
| Geografico/CatalogoIbge.php | 194 |
| Cliente/GeocodificarClienteJob.php | 130 |
| Geografico/ImportarLogradourosJob.php | 111 |
| Identidade/PesoTraco.php | 99 |
| Geografico/Drivers/ViaCepFonte.php | 80 |
| Identidade/ResultadoIdentidade.php | 61 |
| Geografico/Drivers/FonteLogradourosFake.php | 54 |
| Identidade/ResultadoCadastro.php | 41 |
| Geografico/ColisaoDeNome.php | 34 |
| Geografico/Contracts/FonteLogradouros.php | 32 |
| **Total** | **3.273** |

---

## Leitura geral do domínio

O domínio de Identidade é, junto com o Monitora, o ápice técnico do sistema — e
por um motivo diferente. O Monitora documenta *medições de campo*; a Identidade
documenta *calibragem contra a base real*, com números nomeados que só podem ter
vindo de execução: "73.893 pares cujo ÚNICO traço em comum era o nome", "1.667
pares têm nome idêntico E mesmo endereço", "em 2.760 fusões, 20 caíram aqui",
"90,5% da base não tem CPF", "44.338 clientes apontam para `ruas.id`".

Mais raro ainda: o código registra decisões que foram **revertidas** e por quê. A
trava de endereço duplicado do `ClienteService` foi removida e o comentário que
sobrou explica que ela *"bloqueava venda real, e o operador contornava alterando o
número, corrompendo o endereço de entrega"*. Essa é a única parte do sistema onde
uma regra foi tirada porque se mediu que ela causava mais dano do que evitava.

O `IdentificarOuCriarCliente` resolve o problema estrutural que a auditoria vem
encontrando em todo lugar: **cinco portas com cinco regras** viraram uma porta com
uma decisão. É exatamente o remédio que os Volumes 5 e 7 pedem para o tenant e
para a baixa de parcela.

Dito isso, os achados deste volume têm um caráter distinto dos anteriores. Não são
descuidos — são **premissas de instalação única embutidas em decisões deliberadas
e bem argumentadas**. Três eixos:

1. **A calibragem é da Dubena.** Todos os pesos, limiares e listas de exceção
   foram derivados de uma base. Nenhum é parâmetro.
2. **O Geográfico é deliberadamente global.** `withoutGrupo()` aparece 11 vezes,
   sempre de propósito. Isso é coerente com "geografia é fato público" — e
   colide com "cada revenda cadastra suas cidades do seu jeito".
3. **Um serviço externo compartilhado sem identificação por tenant.** ViaCEP e
   IBGE são consumidos sem chave, e sem nenhuma noção de quem consumiu.

---

## Achados

### A-9.1 (ALTA) — `GeocodificarClienteJob` exige `endereco`, coluna que o próprio sistema declara vazia em toda a base

**Critério:** C1 — conceito ausente / C4.

`GeocodificarClienteJob::temEnderecoSuficiente()` (`:113-116`):

```php
private function temEnderecoSuficiente(Cliente $c): bool
{
    return ! empty($c->endereco) && ! empty($c->cidade_id);
}
```

E `montarEndereco()` (`:118-129`) monta a string a partir de `$c->endereco`.

Mas o `IdentidadeCliente`, no mesmo domínio, afirma o contrário — **duas vezes**,
com o mesmo comentário em `tracosDoCliente()` (`:62-65`) e `tracosParaConsulta()`
(`:91-94`):

> *"A coluna `endereco` esta NULL em toda a base: o logradouro real vem da FK
> `rua_id`. Sem isto o traco de endereco virava so 'cidade|numero' — e casava
> qualquer cliente de mesmo numero na cidade, independente da rua."*

E resolve com `$cliente->endereco ?: $cliente->rua?->descricao`.

O job de geocodificação **não faz esse fallback**. Consequência direta: para
todo cliente cuja rua vem de `rua_id` (isto é, toda a base, segundo o comentário),
o job retorna no primeiro `if` e **nunca geocodifica**.

O impacto encadeia por três domínios auditados:

- `DistribuidorService` (Vol. 8) rankeia por proximidade — sem `latitude`, o
  `scoreDist` cai no neutro 0.5 para todos.
- `RoteirizadorService` (Vol. 8) separa `comGeo`/`semGeo` — clientes sem
  coordenada vão para o fim da rota, sem métrica.
- `CalculadoraTaxaEntrega` (Vol. 8) — o critério `distancia` nunca casa, e a
  taxa cai para cidade/padrão.
- `MissaoService::proximaCasa()` (Vol. 8) filtra `whereNotNull('latitude')`.

O próprio job documenta a consequência em `failed()` (`:98-106`): *"ele não
aparece no mapa da central e a distribuição automática não consegue medir
distância até ele"*. O que o comentário não diz é que isso acontece **sem
falhar** — o job retorna com sucesso, `$tries` nunca é consumido, nada é logado.
É o pior modo de falha: silencioso e reportado como êxito.

**Nota de método:** este achado é derivado de dois comentários no código
afirmando que a coluna está vazia. Não foi verificado contra o banco (a auditoria
é de código). Se a afirmação estiver desatualizada, o achado cai; mas então os
dois comentários do `IdentidadeCliente` precisam ser corrigidos, porque ali eles
justificam um fallback.

---

### A-9.2 (ALTA) — Toda a calibragem de identidade é constante, derivada de uma base

**Critério:** C4 — convenção não declarada.

`PesoTraco` declara-se *"Ponto único de calibragem do sistema"* — e é, de fato,
único e bem organizado. Todos os valores são `public const`:

| Constante | Valor | Justificativa registrada |
|---|---:|---|
| `CPF` / `CNPJ` | 100 | determinístico |
| `TELEFONE` | 60 | *"'JEANN RICARDO DE GOES-' e 'Karem Francieli Calixto' no mesmo número"* |
| `TELEFONE_VERIFICADO` | 75 | SMS do Firebase |
| `EMAIL` | 45 | *"raro na base"* |
| `NOME_EXATO` | 75 | *"1.667 pares têm nome idêntico E mesmo endereço (…) com 40+25=65 ficariam represadas na fila para sempre"* |
| `NOME_FORTE` | 40 | — |
| `NOME_FRACO` | 15 | — |
| `ENDERECO` | 25 | *"prédio, vila e condomínio têm dezenas de clientes no mesmo logradouro"* |
| `LIMIAR_AUTOMATICO` | 100 | — |
| `LIMIAR_REVISAO` | 50 | — |
| `SIMILARIDADE_FORTE` | 0.8 | *"Gabriel Niczay × GABRIEL NICZAI DE ARAUJO"* |
| `SIMILARIDADE_MINIMA` | 0.6 | — |

Cada número é defensável **para a base que os produziu**. O problema é o que
acontece com outra revenda:

- **`NOME_EXATO = 75` foi escolhido para que `nome exato + endereço = 100`**
  (fusão automática). Isso presume que homônimos no mesmo endereço são raros —
  verdade numa base de Guarapuava, falso numa revenda que atenda um condomínio
  popular ou uma zona rural com famílias homônimas. Numa base assim, o sistema
  **funde pai e filho automaticamente**, e a fusão remapeia todas as FKs.
- **`EMAIL = 45`** foi rebaixado porque e-mail é raro *nesta* base. Numa revenda
  urbana com venda por app, e-mail pode ser o traço mais confiável — e ali ele
  não alcança nem a fila de revisão sozinho.
- **`LIMIAR_REVISAO = 50`** define quanto trabalho humano a fila gera. Uma
  revenda com base de 200 mil clientes e outra com 3 mil precisam de limiares
  diferentes para que a fila seja operável.

Nenhum desses é configurável. Uma nova revenda entra no SaaS herdando a
calibragem da Dubena, e o modo de falha é o mais caro que este sistema pode ter —
o próprio código diz isso em `similaridadeNome()`: *"consolidaria pessoas
diferentes — o erro mais caro que este sistema pode cometer"*.

Agravante estrutural: `ConsolidarClientes` **não é reversível**. Remapeia FKs em
todas as tabelas, desativa o absorvido e não tem método `desfazer()`. Uma fusão
automática errada por calibragem inadequada é permanente.

---

### A-9.3 (ALTA) — Geográfico opera sobre todos os grupos: `withoutGrupo()` em 11 pontos

**Critério:** C6 — escopo de tenant errado.

| Chamada | Arquivo | Efeito |
|---|---|---|
| `Cidade::withoutGrupo()->orderBy(…)->get()` | `CatalogoIbge:88` | conciliação varre todas as cidades de todos os grupos |
| `Cidade::withoutGrupo()->orderBy(…)->get()` | `NormalizarCidades:47` | idem, na análise |
| `Cidade::withoutGrupo()->where('grupo_id', …)` | `NormalizarCidades:216` | colisão — este **filtra** grupo depois |
| `Rua::withoutGrupo()->where('cidade_id', …)` | `NormalizarLogradouros:67` | análise de ruas |
| `Bairro::withoutGrupo()->where('cidade_id', …)` | `NormalizarLogradouros:229` | busca de bairro |
| `Bairro::withoutGrupo()->where('cidade_id', …)` | `ImportarLogradouros:230` | índice de bairros |
| `Rua::withoutGrupo()->where('cidade_id', …)` | `ImportarLogradouros:242` | índice de ruas |
| `ImportacaoLogradouro::withoutGrupo()->find(…)` | `ImportarLogradourosJob:54` | registro da importação |
| `Cidade::withoutGrupo()->find(…)` | `ImportarLogradourosJob:60` | cidade da importação |

Os que filtram por `cidade_id` estão **protegidos na prática** — a cidade
pertence a um grupo, então as ruas dela também. Os perigosos são os dois
`Cidade::withoutGrupo()->orderBy('descricao')->get()` sem filtro nenhum:

`CatalogoIbge::conciliar()` e `NormalizarCidades::analisar()` percorrem **as
cidades de todos os grupos da instalação**. E `CatalogoIbge::aplicar()` (`:157-190`)
escreve:

```php
$cidade->forceFill([
    'municipio_ibge' => $municipio->cod_ibge,
    'cod_ibge' => $municipio->cod_ibge,
])->save();
```

Um operador de uma revenda que rode a conciliação **altera o cadastro de cidades
de todas as outras**. `NormalizarCidades::corrigirNome()` vai além: reescreve
`descricao`, `uf` e os dois códigos.

A intenção é defensável — o catálogo IBGE é nacional e único, e corrigir "Jaraguá
do Siul" é correção objetiva. Mas:

- `cidades` tem `grupo_id` e unique `(grupo_id, descricao, uf)` — o modelo de
  dados diz que cidade **é** por grupo.
- `ColisaoDeNome` existe exatamente porque duas cidades do mesmo grupo colidem —
  a classe filtra grupo, provando que o autor sabia da dimensão.
- Rodar isso pela tela de uma revenda escreve na de outra, sem que nada avise.

**O conflito de fundo é a decisão D-1** (que o Volume 3 já isolou): se `grupo` é
uma rede de um dono, escrever nas cidades do grupo é aceitável e o
`withoutGrupo()` está errado só por não filtrar o grupo ativo. Se `grupo` pode
conter revendas independentes, isto é escrita cruzada entre clientes do SaaS.

---

### A-9.4 (MÉDIA) — `ConsolidarClientes` remapeia FKs por id global, sem `empresa_id`

**Critério:** C6.

`remapearReferencias()` (`:245-268`):

```php
$n = DB::table($ref['tabela'])
    ->where($ref['coluna'], $absorvido->id)
    ->update([$ref['coluna'] => $principal->id]);
```

`DB::table` — sem model, sem global scope, sem tenant. A varredura descobre as
FKs em `pg_constraint` (decisão correta e bem documentada, com a lição de
produção sobre `information_schema`) e atualiza **toda linha do banco** que aponte
para o id absorvido, em qualquer empresa.

Na prática há uma proteção: `validar()` (`:113-118`) recusa quando
`$principal->empresa_id !== $absorvido->empresa_id`. Como `cliente_id` só é válido
dentro de uma empresa, as linhas filhas são da mesma empresa por construção.

O que sobra de risco é o caso em que uma tabela filha tem `cliente_id` **sem**
`empresa_id` próprio ou com `empresa_id` divergente por dado sujo do ETL — e o
ETL preserva ids do legado ([[migracao-dados-legados]]). Nesse caso o update
atravessa a fronteira sem nada perceber.

É MÉDIA e não ALTA porque a validação prévia cobre o caminho normal; o registro
existe porque a operação é irreversível e a defesa depende de um invariante que
não é verificado no ponto do update.

---

### A-9.5 (MÉDIA) — `NormalizadorTexto::logradouro()` precisa ser idêntico a um script Python separado

**Critério:** C4.

`NormalizadorTexto::logradouro()` (`:459-489`) traz o aviso:

> *"Precisa produzir EXATAMENTE o mesmo resultado que `normalizar()` do
> `scripts/cnefe_importar.py` — as duas pontas alimentam a mesma coluna
> `nome_busca`, e qualquer divergência faria a busca não encontrar nada."*

Duas implementações do mesmo algoritmo, em linguagens diferentes, em repositórios
de conceito diferente (PHP de aplicação × script de importação), com acoplamento
por igualdade **exata** de saída. As duas dependem de:

- a lista `TIPOS_LOGRADOURO` (37 entradas)
- a tabela `ROMANOS` (22 entradas)
- a tabela `EXTENSO` (23 entradas)
- a tabela `ACENTOS` (26 entradas)
- a ordem exata das substituições

Qualquer alteração — acrescentar "estrada" à lista de tipos, tratar mais um
numeral — precisa ser feita **duas vezes, identicamente**, ou a busca de
logradouro para de encontrar as vias importadas por aquele lado.

Nada no código detecta a divergência. Não há teste que compare as duas saídas,
nem versão gravada da tabela junto com os dados importados. O modo de falha é
"a busca de rua parou de achar coisas em algumas cidades" — sem erro.

Para o SaaS o risco cresce: cada município novo importado passa pelo script
Python, e a divergência só se manifesta nas cidades importadas depois da
alteração.

---

### A-9.6 (MÉDIA) — ViaCEP e IBGE: serviços públicos consumidos sem identificação nem cota por revenda

**Critério:** C4.

`ImportarLogradouros` faz até **3.000 consultas ao ViaCEP por cidade**
(`CONSULTAS_MAX`), com `retry(2, 1500)` em cada uma no `ViaCepFonte` — potencial
de 9.000 requisições HTTP por importação. `CatalogoIbge` baixa os 5.570
municípios com `retry(3, 2000)`.

Nenhum dos dois envia identificação (nem `User-Agent` próprio, ao contrário do
`OverpassMalha` do Volume 8, que ao menos se identifica — como "ERP-Dubena", o que
é outro problema). Ambos são serviços públicos gratuitos que limitam por IP.

No SaaS, todas as revendas importam do mesmo servidor. Uma revenda entrando com 30
municípios dispara até 90 mil requisições ao ViaCEP em sequência, e o bloqueio por
IP atinge todas as outras. O sintoma é `buscar()` devolvendo `[]` — o que a
varredura interpreta como "não há ruas com esse termo", **não** como falha —
produzindo uma importação silenciosamente vazia marcada como `concluida`.

Note que o algoritmo é cuidadoso com truncamento (conta `truncados` e reporta
importação possivelmente incompleta), mas **não distingue "zero resultados" de
"fonte indisponível"** — o `catch` do `ViaCepFonte` loga e devolve `[]`, e a
varredura segue.

---

### A-9.7 (MÉDIA) — Sincronização de identidade apaga e recria em cada gravação

**Critério:** C4.

`IdentidadeCliente::sincronizar()` (`:26-49`):

```php
ClienteIdentidade::query()->where('cliente_id', $cliente->id)->delete();
foreach ($tracos as $tipo => $valores) {
    foreach ((array) $valores as $valor) {
        ClienteIdentidade::query()->create([…]);
```

Três consequências:

**(a) O flag `verificado` é perdido em toda regravação.** Ele é setado como
`$tipo === 'telefone' && $origem === 'app'`. Um telefone verificado por SMS no app
que passe por qualquer edição posterior com outra origem (`admin`, `campo`,
`consolidacao`) volta a ser não-verificado — e o peso cai de 75 para 60,
justamente o que separa a fusão automática da fila de revisão.

`enriquecer()` (`IdentificarOuCriarCliente:214`) chama `sincronizar($cliente,
$origem)` com a origem **da chamada atual**, não a original. `ConsolidarClientes`
(`:97`) chama com `'consolidacao'`. Ou seja: consolidar um cliente **desverifica
todos os telefones dele**.

**(b) A query de delete não filtra empresa.** `where('cliente_id', $cliente->id)`
— mesma classe de A-9.4, aqui num delete.

**(c) Custo em escala.** Um cliente com 3 telefones gera 1 delete + ~7 inserts a
cada edição. `ConsolidarClientes` chama isso por fusão; a varredura de passivo
(mencionada em `tracosParaConsulta`) chamaria por cliente.

---

### A-9.8 (BAIXA) — `ClienteService::sincronizarTelefones` apaga e recria, perdendo o histórico

**Critério:** C4.

`:196-211`:

```php
$cliente->telefones()->delete();
foreach ($telefones as $tel) { … $cliente->telefones()->create([…]); }
```

Toda edição de cliente que envie `telefones` **recria** as linhas com ids novos.
Consequências:

- Se `clientetelefones.id` for referenciado por alguma outra tabela (interações,
  campanhas de WhatsApp), a referência quebra.
- O `created_at` de um telefone antigo vira a data da última edição — perde-se
  "desde quando temos este número".
- Combinado com A-9.7, uma edição de cliente destrói e recria telefone **e**
  identidade.

Contrasta com o cuidado explícito de `IdentificarOuCriarCliente::enriquecer()`,
que **acrescenta** telefone sem substituir, com o comentário *"a pessoa pode ter
dois números, e trocar o antigo pelo novo perderia contato válido"*. As duas
lógicas convivem: a porta de identidade preserva, a porta de edição destrói.

---

### A-9.9 (BAIXA) — `NormalizarCidades::contemONome` com lista fechada de palavras "vazias"

**Critério:** C2 — classificação por texto.

`:262-278`:

```php
$vazias = ['rua', 'av', 'avenida', 'cidade', 'municipio', 'distrito', 'de', 'do', 'da'];
return array_diff($sobra, $vazias) === [];
```

A lista foi derivada dos casos da base da Dubena ("Rua Palhoça"). O comentário
declara o desenho: *"A lista é fechada de propósito: qualquer palavra que sobre e
não esteja aqui é tratada como nome próprio, e o registro fica como distrito.
Errar para o lado do distrito é seguro — não altera nada."*

O fail-safe está correto e a escolha é boa. O registro aqui é que a lista é
**dado de calibragem tratado como código**: outra revenda com "Vila", "Núcleo",
"Colônia" ou "Zona" no prefixo dos cadastros teria esses casos classificados como
distrito e nunca corrigidos — silenciosamente, porque errar para distrito não
produz aviso.

Mesma observação para as `SEMENTES` do `ImportarLogradouros` (~120 trigramas
escolhidos para nomes brasileiros) e para `PARTICULAS`/`TIPOS_LOGRADOURO` do
`NormalizadorTexto`.

---

### A-9.10 (BAIXA) — `similar_text` como desempate: O(n³) e sensível a acento

**Critério:** C4.

`NormalizarLogradouros::maisParecido()` (`:186-214`) usa `similar_text($chave,
$o->nome_busca)` para desempatar oficiais com o mesmo escore — decisão
documentada com o caso real ("Das araucária" empatando entre a via certa e "VILA
ARAUCARIA").

`similar_text` do PHP é O(n³) no pior caso e roda **para cada oficial da cidade,
para cada rua da cidade**. Uma cidade com 3.000 logradouros oficiais e 2.000 ruas
cadastradas são 6 milhões de chamadas.

`analisar()` é chamado por `duplicatas()`, que percorre o resultado inteiro — e a
tela de revisão presumivelmente chama os dois. Não há cache.

É BAIXA porque a operação é administrativa e pontual (normalizar uma cidade), mas
num SaaS com dezenas de cidades por revenda o custo aparece.

---

## Padrões que este volume confirma

**1. A porta única funciona — e prova o remédio para o resto do sistema.**
`IdentificarOuCriarCliente` substituiu cinco caminhos com cinco regras por uma
decisão. É exatamente o que os Volumes 5 e 7 pedem: o `BaixaService` que falta
para `financeiroparcelas.baixado`, o `movimentar()` com tenant obrigatório. O
padrão existe no sistema, funciona, está documentado — e não foi replicado.

**2. Calibragem empírica sem parâmetro — agora com consequência irreversível.**
O Volume 8 registrou o mesmo padrão (19 constantes medidas em campo). Aqui a
diferença é o que a constante decide: lá, o traçado de uma rota; aqui, se dois
cadastros são a mesma pessoa — decisão que remapeia FKs e não tem desfazer.

**3. Fail-safe declarado e argumentado.** Três casos neste volume: "na dúvida a
venda ACONTECE" (`LIMIAR_REVISAO`), "errar para o lado do distrito é seguro"
(`contemONome`), "sem regra a entrega é gratuita" (Vol. 8, `ResultadoTaxaEntrega`).
É a única parte do sistema onde a direção do erro é escolhida explicitamente.
Contrasta com o Volume 6, onde a ausência de regra fiscal vira 18% sem que
ninguém tenha decidido isso.

**4. `withoutGrupo()` / `withoutTenant()` como decisão, não descuido.** No Volume
7 o `withoutTenant()` era conveniência (6 de 9 usos sem justificativa). Aqui os 11
`withoutGrupo()` são deliberados e coerentes com "geografia é fato público". O
problema não é o uso — é que a premissa por trás dele (uma instalação, um
catálogo geográfico) não foi revisitada para o SaaS.

**5. O comentário como registro de reversão.** Único lugar do sistema onde uma
regra removida deixou explicação do porquê (`ClienteService`, trava de endereço).
Vale como padrão a preservar.

---

## Para o plano (Volume 15)

Decisões que dependem do dono:

- **D-9.1** — Calibragem de identidade por revenda ou única da plataforma? Se por
  revenda, `PesoTraco` vira tabela de configuração com os valores atuais como
  default, e cada revenda precisa de um processo de aferição inicial.
- **D-9.2** — Catálogo geográfico compartilhado entre revendas ou por grupo?
  Depende diretamente de **D-1**. Se compartilhado, `cidades`/`ruas`/`bairros`
  deixam de ter `grupo_id` e viram catálogo de plataforma — o que muda a
  conversão de dados de todas as revendas.
- **D-9.3** — Fusão automática de cadastro (`LIMIAR_AUTOMATICO`) é aceitável sem
  confirmação humana, dado que é irreversível? Uma opção é tornar toda fusão
  revisável, mantendo a automática só para documento idêntico.

Itens de código para o plano consolidado:

- **`GeocodificarClienteJob` com o mesmo fallback `endereco ?: rua?->descricao`**
  que o `IdentidadeCliente` usa, e log quando o endereço for insuficiente (hoje
  retorna em silêncio). Resolve A-9.1.
- **`PesoTraco` como configuração por empresa**, com os valores atuais de default
  e a justificativa de cada um preservada como documentação. Resolve A-9.2.
- **Filtrar grupo ativo nas duas varreduras globais** de `CatalogoIbge::conciliar`
  e `NormalizarCidades::analisar`, ou promover o catálogo a plataforma
  explicitamente. Resolve A-9.3.
- **Preservar `verificado` na ressincronização de identidade** (ler o estado
  anterior antes do delete) e reaproveitar linhas em vez de recriar. Resolve
  A-9.7 (a) e (c).
- **`sincronizarTelefones` por diferença**, não por delete+create. Resolve A-9.8.
- **Teste de contrato entre `NormalizadorTexto::logradouro()` e o script
  Python** — um arquivo de casos com entrada/saída esperada, exercitado pelos
  dois lados. Resolve A-9.5.
- **Distinguir "sem resultado" de "fonte indisponível"** no `ViaCepFonte`, para a
  importação não concluir vazia em silêncio. Resolve parte de A-9.6.

---

**Volume 9 fechado.** 18/18 arquivos, 3.273/3.273 linhas. 10 achados
(3 alta, 4 média, 3 baixa).
