# Auditoria SaaS — Volume 8: Logística, Missão, Frota, Monitora

**Recorte:** `app/Domain/Logistica/`, `app/Domain/Missao/`, `app/Domain/Frota/`,
`app/Domain/Monitora/` — 39 arquivos, 4.593 linhas.
**Leitura:** 39/39 lidos integralmente (conferido por `wc -l`: 4.593).
**Data:** 2026-08-25.
**Método:** ver [AUDITORIA_SAAS.md](AUDITORIA_SAAS.md). Achados formados só a
partir do código.

---

## Arquivos lidos

| Arquivo | Linhas |
|---|---:|
| Monitora/ViagensService.php | 641 |
| Monitora/CercasInteligentesService.php | 322 |
| Monitora/GrafoViario.php | 253 |
| Missao/MissaoService.php | 254 |
| Logistica/CentralService.php | 226 |
| Monitora/Drivers/TraccarDriver.php | 213 |
| Monitora/MonitoraSyncService.php | 179 |
| Monitora/RelatorioMonitoraService.php | 168 |
| Logistica/RoteirizadorService.php | 167 |
| Frota/VeiculoService.php | 161 |
| Missao/VendaCampoService.php | 156 |
| Missao/GeradorMissaoService.php | 152 |
| Monitora/MonitoraService.php | 140 |
| Logistica/CalculadoraTaxaEntrega.php | 136 |
| Logistica/DistribuidorService.php | 115 |
| Logistica/JornadaService.php | 114 |
| Monitora/Drivers/OverpassMalha.php | 99 |
| Logistica/Jobs/AtribuirPedidoJob.php | 87 |
| Logistica/Drivers/GoogleRoutesDriver.php | 87 |
| Monitora/Drivers/RoadsApiAjustador.php | 80 |
| Logistica/DistanciaEntrega.php | 74 |
| Logistica/Events/PedidoAtribuido.php | 70 |
| Monitora/Drivers/MalhaCacheada.php | 70 |
| Logistica/ResultadoTaxaEntrega.php | 66 |
| Monitora/Drivers/SgcasaHttpDriver.php | 66 |
| Logistica/Drivers/TracadorRotaCacheado.php | 63 |
| Logistica/Events/PedidoEntrouNaFila.php | 58 |
| Monitora/Drivers/FakeMalhaViaria.php | 58 |
| Monitora/Drivers/AjustadorCacheado.php | 56 |
| Monitora/Contracts/MalhaViaria.php | 30 |
| Monitora/Contracts/SgcasaDriver.php | 30 |
| Monitora/Drivers/FakeAjustadorDeVia.php | 30 |
| Logistica/Drivers/GoogleMatrizDriver.php | 30 |
| Monitora/Contracts/AjustadorDeVia.php | 29 |
| Logistica/Drivers/HaversineDriver.php | 25 |
| Logistica/Contracts/MatrizDistancia.php | 19 |
| Logistica/Contracts/TracadorRota.php | 19 |
| Logistica/Drivers/SemTracado.php | 17 |
| Monitora/Drivers/FakeSgcasaDriver.php | 33 |
| **Total** | **4.593** |

---

## Leitura geral do domínio

Este é o volume de **maior qualidade técnica** da auditoria. O `ViagensService`,
o `GrafoViario` e o `CercasInteligentesService` são o único lugar do sistema onde
cada constante traz junto a **medição que a justifica** — "o rastreador que
reporta a cada 10 s deixa 82–104 m entre posições, o que reporta a cada 2 min
deixa ~937 m; 150 m separa um caso do outro com folga". Vários comentários narram
tentativas que falharam e por quê ("o primeiro critério que tentei foi 90% da área
dentro da outra, e ele falhou nos dados reais"). Isso é engenharia registrada, não
código entregue.

E é exatamente por isso que este volume é o mais revelador para a pergunta do SaaS.
Porque **o que está errado aqui não é a lógica — é a suposição de que existe uma
praça, uma frota e uma conta de rastreamento.** Cada constante calibrada é
calibrada *para Guarapuava*: a correção de cosseno cita "−25°", a malha sintética
diz "~110 m na latitude de Guarapuava", a velocidade média urbana é 25 km/h.
Nenhuma delas está errada; todas estão amarradas a um lugar.

Os três eixos de achado:

1. **Uma conta de provedor externo para toda a plataforma.** Traccar (usuário e
   senha em `config/services`), Google Routes (chave por env), Roads API (chave em
   `ConfigGlobal`), SGCasa (token global). Quatro integrações, quatro credenciais
   de instalação. O `IntegracaoTenant` — que o Volume 7 mostrou existir e
   funcionar — não é usado por nenhuma.

2. **Dois models de veículo.** `App\Models\Frota\Veiculo` e
   `App\Models\Monitora\Veiculo` coexistem, e serviços diferentes usam um ou outro
   sem que nada os relacione.

3. **Tenant por hábito, não por regra.** Metade das consultas filtra `empresa_id`
   explicitamente (e o autor comenta o porquê); a outra metade busca por
   `entregador_user_id` cru, confiando no global scope que em job não existe.

---

## Achados

### A-8.1 (ALTA) — Dois models de `Veiculo` coexistem, e a jornada valida um enquanto a frota gerencia o outro

**Critério:** C5 — conceitos misturados.

| Serviço | Model importado |
|---|---|
| `Frota/VeiculoService` | `App\Models\Frota\Veiculo` |
| `Logistica/JornadaService` | `App\Models\Monitora\Veiculo` |
| `Monitora/MonitoraService` | `App\Models\Monitora\Veiculo` |
| `Monitora/MonitoraSyncService` | `App\Models\Monitora\Veiculo` |
| `Monitora/ViagensService` | `App\Models\Monitora\Veiculo` |
| `Monitora/RelatorioMonitoraService` | `App\Models\Monitora\Veiculo` |

O Volume 4 já registrou a duplicidade de tabela (`veiculos` × `monitora_veiculos`)
como "criado ao lado sem substituir". Aqui vê-se o efeito operacional:

- `JornadaService::resolverVeiculo()` valida contra `Monitora\Veiculo` e depois
  `JornadaService::encerrar()` atualiza `km_atual` **nesse** model.
- `VeiculoService::abastecer()` e `registrarTrocaOleo()` atualizam `km_atual` no
  **outro** model, com a regra de hodômetro-não-regride.

Ou seja: **o hodômetro é mantido em duas tabelas diferentes, por dois caminhos que
não se conhecem**. A jornada avança o km do veículo de monitoramento; o
abastecimento avança o km do veículo de frota. O alerta de troca de óleo
(`alertaTrocaOleo`) lê o km da frota — que nunca recebe os km rodados nas jornadas.

Consequência direta: **o alerta de troca de óleo nunca dispara pelo uso real**. Só
avança quando alguém registra abastecimento. Um veículo que roda todo dia e
abastece em posto com nota manual fica com o hodômetro parado.

E para o SaaS: a revenda que só usa a Central (sem rastreador) cadastra veículo em
`veiculos`; a que usa rastreamento tem veículo em `monitora_veiculos`; a que usa
os dois tem **dois cadastros do mesmo caminhão**, com placas possivelmente
divergentes.

---

### A-8.2 (ALTA) — Traccar: uma conta, um usuário e uma senha para a plataforma inteira

**Critério:** C6 — escopo de tenant errado / C4.

`TraccarDriver::buscarPosicoes()` (`:50-53`) e `listarAparelhos()` (`:128-131`):

```php
$url = rtrim((string) config('services.traccar.url'), '/');
$usuario = (string) config('services.traccar.usuario');
$senha = (string) config('services.traccar.senha');
```

Credencial única de instalação. E a API do Traccar devolve **todos os aparelhos da
conta** — `/api/devices` e `/api/positions` não têm filtro por cliente.

O código já **descobriu esse problema em produção** e o documentou
(`MonitoraSyncService:22-27`):

> *"O auto-cadastro roda para UMA empresa só, a dona da conta no provedor. A lista
> de aparelhos do Traccar é global: rodando para todas, cada empresa ganhava uma
> cópia dos mesmos 25 rastreadores — **277 veículos fantasmas na primeira noite em
> produção**."*

A correção aplicada foi `TRACCAR_EMPRESA_ID` — uma variável de ambiente que diz
qual empresa é "a dona da conta". Isso resolve o sintoma para **uma** revenda e
codifica a premissa de instalação única: num SaaS, ou existe uma empresa
privilegiada que recebe todos os rastreadores de todas as revendas, ou o
auto-cadastro fica desligado para sempre.

O mesmo vale para `buscarPosicoes`: o filtro é por IMEI (`$procurados =
array_flip($imeis)`), o que impede vazamento de posição — mas só porque a lista de
IMEIs vem da empresa. Se duas revendas cadastrarem o mesmo IMEI (erro de digitação
ou aparelho transferido), as duas recebem a mesma posição, e
`cadastrarAparelhosNovos` (`:150-153`) confere IMEI **global** (`Veiculo::query()`
sem empresa) — comentado como decisão deliberada, correta para uma conta
compartilhada e errada para revendas independentes.

---

### A-8.3 (ALTA) — Chaves do Google: três fontes diferentes, nenhuma por tenant

**Critério:** C6 / C5.

| Uso | Onde a chave vem | Arquivo |
|---|---|---|
| Routes API (traçado de rota) | construtor, injetado do env | `GoogleRoutesDriver:24` |
| Roads API (encaixe de via) | `ConfigGlobal::query()->value('google_maps_key')` | `RoadsApiAjustador:33` |
| Distance Matrix | delega ao Routes | `GoogleMatrizDriver` |

Duas fontes diferentes para chaves do mesmo fornecedor, e **nenhuma** consulta o
`IntegracaoTenant` — que a memória do projeto registra como o resolvedor de
"PIX/cartão/**Maps**" por empresa. O padrão existe, está implementado, é usado pelo
`PixService`, e o domínio de logística inteiro o ignora.

Consequências no SaaS:

- **Custo não atribuível.** Toda chamada à Routes/Roads é faturada na conta da
  plataforma. Uma revenda com 40 entregadores e roteirização ativa consome a cota
  de todas as outras. Não há como cobrar, limitar ou desligar por revenda.
- **Circuit breaker global** (`GoogleRoutesDriver:29-35`): a chave da constante é
  `'groutes:circuito-aberto'`, sem empresa. Uma falha — quota estourada de
  **qualquer** origem — abre o circuito por 5 minutos **para toda a plataforma**.
  O comentário explica bem por que o breaker existe (403 em cascata levavam o
  endpoint a 20s+), mas a granularidade escolhida transforma o problema de uma
  revenda em indisponibilidade de todas.
- **Cache de traçado global sem empresa.** `TracadorRotaCacheado` e
  `AjustadorCacheado` gravam em tabelas sem `empresa_id`. Isto está **certo** e
  documentado (`AjustadorCacheado:15-18`: *"por onde passa a Rua Martin Afonso é
  fato geográfico público, não dado de tenant"*) — registro aqui só para separar
  do que é problema: o cache compartilhado é acerto, a chave compartilhada é falha.

---

### A-8.4 (ALTA) — Consultas por `entregador_user_id` sem empresa, em serviços que rodam em job

**Critério:** C6.

Cinco consultas de decisão sem `empresa_id`:

| Consulta | Arquivo | O que decide |
|---|---|---|
| `Jornada::where('entregador_user_id')->where('status','ativa')` | `JornadaService:74-79` | se o entregador pode operar |
| `EntregadorPosicao::where('entregador_user_id')->first()` | `RoteirizadorService:52` | ponto de partida da rota |
| `EntregadorPosicao::where('entregador_user_id')->first()` | `GeradorMissaoService:64` | qual missão atribuir |
| `MissaoAtribuicao::where('entregador_user_id')->whereIn('status',…)` | `MissaoService:32-37` | qual missão está em execução |
| `MissaoAtribuicao::where('entregador_user_id')->…->exists()` | `GeradorMissaoService:110-115` | se pode empilhar missão |

`GeradorMissaoService::gerarParaEmpresa()` é chamado pelo comando agendado
`logistica:gerar-missoes`, que varre **todas as empresas**. Dentro do laço, as
consultas de `EntregadorPosicao` e `MissaoAtribuicao` não filtram empresa. Se o
comando não resolve tenant entre iterações (e o método não o faz — recebe só
`$empresaId` e usa em algumas queries, não em outras), o global scope carrega a
empresa da iteração anterior ou nenhuma.

O caso mais concreto é `temMissaoEmExecucao($uid)`: um entregador que atenda duas
empresas do mesmo grupo (motorista compartilhado — cenário que o modelo de grupo
permite) teria a missão de uma empresa impedindo a atribuição na outra, ou
recebendo missões das duas simultaneamente, dependendo de qual scope está ativo.

`MissaoService::registrarTrilha()` (`:120-127`) usa `MissaoTrilha::insert($linhas)`
— **insert em massa não dispara model events**, então `BelongsToTenant` não
preenche nada. O código compensa passando `empresa_id` explícito no array, o que
está correto; registro aqui porque é o padrão certo que os outros cinco casos não
seguem.

---

### A-8.5 (MÉDIA) — `pontoEmPoligono` implementado duas vezes, com convenções de eixo trocadas

**Critério:** C5 — conceitos misturados.

`MonitoraService::pontoEmPoligono()` (`:117-136`):

```php
$cruza = ($lngI > $lng) !== ($lngJ > $lng)
    && $lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI;
```

`GrafoViario::contem()` (`:229-249`):

```php
if (($yi > $lat) !== ($yj > $lat)
    && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
```

O mesmo algoritmo (ray-casting par/ímpar) com os **eixos trocados**: o primeiro
lança o raio na direção da latitude varrendo por longitude; o segundo faz o
inverso. Matematicamente ambos funcionam para polígonos fechados simples — o
resultado só difere em casos degenerados (vértice exatamente na linha do raio,
aresta horizontal/vertical exata).

E casos degenerados são justamente o que as cercas produzem: o
`CercasInteligentesService` documenta que *"duas cercas vizinhas compartilham os
vértices da divisa de propósito (é o que o snap do editor produz)"*. Vértices
compartilhados são exatamente onde as duas implementações podem discordar.

Efeito prático: `setorPorPonto()` (que decide o setor de entrega de um pedido, via
`MonitoraService`) e `fracaoDentro()` (que decide se duas cercas conflitam, via
`GrafoViario`) podem dar respostas diferentes sobre o mesmo ponto e a mesma cerca.
A ferramenta que audita a cerca usa um algoritmo; a operação que a aplica usa
outro.

---

### A-8.6 (MÉDIA) — Código morto no `ViagensService`: `reduzir`, `rdp` e duas constantes

**Critério:** C4.

`ViagensService` mantém, com ~50 linhas de comentário explicativo:

- `reduzir()` (`:495-524`) e `rdp()` (`:533-560`) — o próprio comentário no
  `montar()` (`:249-258`) declara: *"`reduzir` saiu de vez do caminho encaixado. O
  RDP com 12 m de tolerância descarta pontos colineares (…) Ele desfazia o encaixe
  que a chamada acabou de pagar"*. Nenhum caminho chama `reduzir()`.
- `SALTO_QUE_CORTA_QUARTEIRAO` (`:279`) — o comentário em `encaixarNasVias()`
  (`:326-334`) explica que *"o gatilho de 150 m era o que sobrava dos desvios (…)
  TODA viagem vai para o encaixe, sem gatilho de salto mínimo"*. A constante não é
  referenciada em lugar nenhum.
- `SALTO_SEM_SOLUCAO` (`:305`) — idem, definida e nunca usada.

Isto não é descuido: é o **registro de um caminho abandonado**, deixado com a
explicação de por que foi abandonado. Tem valor histórico real. Mas o efeito de
manter código morto num arquivo de 641 linhas é que o próximo leitor não sabe qual
metade está viva — e `metrosAteReta()`, usada de verdade por `limparVaivem()`, é
chamada também por `rdp()`, o que faz o morto parecer vivo numa busca de
referências.

**Recomendação:** o comentário fica (é a explicação), o código sai.

---

### A-8.7 (MÉDIA) — Constantes calibradas para uma praça, sem parâmetro por empresa

**Critério:** C4 — convenção não declarada.

O volume tem 19 constantes de comportamento, todas embutidas em `private const`:

| Constante | Valor | Onde | Calibrada para |
|---|---|---|---|
| `VELOCIDADE_MEDIA_KMH` | 25.0 | `HaversineDriver` | trânsito urbano de Guarapuava |
| `PARADA_MINIMA_SEGUNDOS` | 300 | `ViagensService`, `RelatorioMonitora` | "entrega típica passa de 5 min" |
| `DISTANCIA_MINIMA_KM` | 0.3 | `ViagensService` | ruído de GPS parado |
| `VAIVEM_MINIMO` | 30.0 | `ViagensService` | erro do GPS |
| `ESPACAMENTO_PARA_API` | 250.0 | `ViagensService` | medido na frota |
| `MARGEM_QUADRA` | 0.005 | `CercasInteligentes` | quadra de ~100 m |
| `FATOR_AREA_MAE` | 4.0 | `CercasInteligentes` | "a mãe tem 36 km de perímetro contra 9 a 17 km dos setores" |
| `PASSO` (malha fake) | 0.001 | `FakeMalhaViaria` | "~110 m **na latitude de Guarapuava**" |
| `PESO_DISTANCIA` / `PESO_CARGA` | 0.7 / 0.3 | `DistribuidorService` | — |
| `TOLERANCIA_FUTURO_MINUTOS` | 60 | `TraccarDriver` | "8 posições em 2080" no dump |

Duas observações que separam o que é problema do que não é:

- `DistribuidorService` **acerta**: lê `LogisticaConfig` por empresa e só usa a
  constante como default (`:44-48`). É o padrão certo, e existe em exatamente um
  dos dez casos.
- `GeradorMissaoService` também lê `ociosidade_min` da config (`:41`).

Os demais são fixos. Uma revenda em cidade grande tem velocidade média menor e
quadras maiores; uma em cidade pequena tem paradas mais curtas. `FATOR_AREA_MAE =
4.0` foi calibrado contra **as 19 cercas da Dubena** — outra revenda com hierarquia
de setores diferente (três níveis, por exemplo) teria a área-mãe classificada como
conflito ou o conflito real classificado como área-mãe.

A `LogisticaConfig` já existe e já é o lugar certo. Ela cobre 3 parâmetros dos ~19.

---

### A-8.8 (MÉDIA) — `RelatorioMonitoraService` apura paradas e excessos duas vezes cada

**Critério:** C4.

`eventosVeiculo()` (`:51-58`):

```php
'paradas' => $this->paradas($posicoes),
'excessos' => $this->excessos($posicoes, $velMax),
'resumo' => [
    'total_paradas' => count($this->paradas($posicoes)),      // ← recalcula
    'total_excessos' => count($this->excessos($posicoes, $velMax)),  // ← recalcula
```

`paradas()` é O(n) sobre a coleção de posições — um dia de veículo tem 300+
posições, um relatório mensal tem ~9.000. Rodar duas vezes dobra o custo sem
motivo, e `linhasEventos()` chama `eventosVeiculo()` inteiro (quatro apurações no
total) só para achatar em CSV.

É achado de eficiência, não de correção — mas num relatório mensal de frota com
vários veículos o efeito é sentido, e o `ViagensService` ao lado tem cache
persistente justamente porque *"apurar viagens varre todas as posições do dia, e a
mesma consulta repetida não pode pagar isso de novo"*. O relatório não tem cache
nenhum e ainda paga duas vezes.

---

### A-8.9 (MÉDIA) — Nenhum registro de autoria em atribuição automática e missões

**Critério:** C1 — conceito ausente.

Quarta ocorrência do mesmo buraco (A-5.6, A-6.9, A-7.11):

- `CentralService::atribuir()` grava `operador_user_id` — **correto**, com trilha
  em `pedido_atribuicoes`, incluindo `de_entregador`, `para_entregador`, `acao`,
  `automatico` e `motivo`. É o melhor registro de autoria de todo o sistema.
- `CentralService::priorizar()` e `reagendar()` (`:167-181`) — `forceFill()->save()`
  sem qualquer registro. Quem marcou o pedido como urgente? Quem reagendou a
  entrega para amanhã? Não há resposta.
- `bloquearEntregador()` grava `operador_user_id`; `desbloquearEntregador()`
  (`:159-165`) faz `update(['ativo' => false])` sem registrar quem desbloqueou.
- `MissaoService::adiar()` grava motivo e detalhe, mas não quem adiou.
- `VendaCampoService::venderValeGas()` não passa operador ao `ValeGasService` —
  que já não registrava (A-5.6).

O padrão: onde há tabela de trilha dedicada, a autoria é registrada exemplarmente;
onde a mudança é um `update` numa coluna, não é. A decisão de registrar seguiu a
forma da escrita, não a importância do ato.

---

### A-8.10 (BAIXA) — `MonitoraSyncService`: auto-cadastro cria veículo com placa `?IMEI` truncada

**Critério:** C4.

`placaProvisoria()` (`:174-177`):

```php
return mb_substr('?'.$imei, 0, 10);
```

Um IMEI tem 15 dígitos; a coluna `placa` tem 10 caracteres. A placa provisória é
`?` + os 9 primeiros dígitos do IMEI. **Dois aparelhos de lotes próximos —
comuns, porque IMEIs sequenciais são vendidos juntos — compartilham os 9 primeiros
dígitos**, e a coluna é declarada única por empresa (o próprio comentário diz
isso).

O segundo cadastro falharia com violação de unique, dentro de um laço sem
try/catch (`:158-171`), abortando o auto-cadastro dos aparelhos seguintes.

Impacto real hoje: baixo (25 rastreadores, IMEIs de origens diferentes). Mas o
mecanismo é o que roda sozinho de madrugada, e a falha se apresentaria como
"aparelhos novos pararam de aparecer" sem erro visível.

---

### A-8.11 (BAIXA) — `RoteirizadorService`: partida indefinida quando não há posição

**Critério:** C4.

`:52-54`:

```php
$partidaLat = $pos ? (float) $pos->latitude : ($comGeo->first()?->cliente->latitude !== null ? (float) $comGeo->first()->cliente->latitude : null);
```

Sem posição do celular, a partida vira **a primeira parada da coleção** — e a
coleção vem de um `->get()` sem `orderBy`, portanto em ordem indeterminada do
banco. O nearest-neighbor a partir de um ponto arbitrário produz sequências
diferentes a cada chamada para as mesmas entregas.

O entregador que abre a tela duas vezes vê duas rotas diferentes. Não é erro de
cálculo — o TSP aproximado é sensível à partida por natureza — mas a instabilidade
é gratuita: ordenar por `id` ou por `datahora` daria resultado reproduzível.

Também: `Pedido::query()->…->get()` em `filaDistribuicao` tem `orderBy` explícito
(bom); o roteirizador não tem.

---

### A-8.12 (BAIXA) — `OverpassMalha` identifica-se como "ERP-Dubena" e compartilha a cota entre todas as revendas

**Critério:** C4.

`OverpassMalha:64`:

```php
->withHeaders(['User-Agent' => 'ERP-Dubena/1.0 (geofencing)'])
```

O nome de **um cliente** está hard-coded no cabeçalho que identifica a aplicação
perante um serviço externo. Numa plataforma vendida a N revendas, toda consulta de
malha viária — de qualquer revenda — se apresenta ao OpenStreetMap como sendo da
Dubena. É o caso mais literal de "convenção de uma única revenda" encontrado na
auditoria: não é um default que dá certo por acaso, é o nome do primeiro cliente
gravado no produto.

Dois efeitos somados:

- **Identidade errada.** O User-Agent é o que o Overpass usa para contatar quem
  abusa da infraestrutura comunitária (a política de uso exige identificação real).
  Deveria nomear a plataforma, não um cliente dela.
- **Cota compartilhada por IP.** A API pública bloqueia por IP quem excede o uso
  justo. Todas as chamadas saem do mesmo servidor, então o excesso de uma revenda
  desenhando cercas bloqueia a ferramenta para todas — e se apresenta como "a
  vareta mágica parou de funcionar", sem erro visível ao operador (`vias()` devolve
  `[]` e a tela só não sugere nada).

O `MalhaCacheada` na frente mitiga bastante (célula de 0,01°, ~1 km, com a decisão
de cachear a célula inteira documentada), e `LADO_MAXIMO` recusa retângulos
grandes antes de gastar a chamada. Fica como BAIXA por isso — mas o User-Agent é
correção de uma linha e deveria sair antes de a segunda revenda entrar.

---

## Padrões que este volume confirma

**1. O contrato/driver como padrão maduro, sem tenant.** Este domínio tem seis
interfaces (`MatrizDistancia`, `TracadorRota`, `AjustadorDeVia`, `MalhaViaria`,
`SgcasaDriver`, e os fakes correspondentes) com decisões de degradação
explicitamente documentadas — *"`null` e não exceção porque traçado é degradação
aceitável (…) não é dado financeiro para justificar fail-closed"*. A arquitetura de
gates está madura. **Nenhuma delas recebe a empresa.** Todo driver externo é
instanciado com credencial de instalação.

**2. Descobriu-se o problema de multi-tenant em produção e corrigiu-se para uma
empresa.** Os "277 veículos fantasmas" foram um encontro real com a
multi-tenancy — a resposta foi `TRACCAR_EMPRESA_ID`, que é a formalização da
premissa de instalação única. A lição foi aprendida e aplicada na direção oposta à
do SaaS.

**3. Calibração empírica sem parâmetro.** É o padrão mais próprio deste volume:
constantes derivadas de medição real na frota da Dubena, com a medição documentada.
Excelente engenharia, zero configurabilidade. `LogisticaConfig` mostra que o autor
sabia como fazer — cobre 3 de ~19 parâmetros.

**4. Autoria registrada por forma, não por importância.** `pedido_atribuicoes` é
exemplar; `priorizar()` e `reagendar()` não registram nada. O critério foi "existe
tabela de trilha?", não "este ato precisa de responsável?".

---

## Para o plano (Volume 15)

Decisões que dependem do dono:

- **D-8.1** — Rastreamento no SaaS: cada revenda com sua conta Traccar
  (credencial em `IntegracaoTenant`), ou a plataforma opera uma conta e provisiona
  aparelhos? Muda quem paga o provedor e quem administra os IMEIs.
- **D-8.2** — Chaves do Google por revenda ou da plataforma com repasse de custo?
  Se for da plataforma, é preciso medição por empresa para faturar — que hoje não
  existe.
- **D-8.3** — Veículo é um cadastro só (unificar `veiculos` e `monitora_veiculos`)
  ou dois conceitos distintos? A auditoria não vê justificativa para dois, mas a
  conversão de dados depende da resposta.

Itens de código para o plano consolidado:

- **Unificar o model de veículo** e o hodômetro. Resolve A-8.1.
- **`IntegracaoTenant` para Traccar, Google Routes, Roads e SGCasa**, com circuit
  breaker por empresa. Resolve A-8.2 e A-8.3.
- **`empresa_id` nas cinco consultas por `entregador_user_id`.** Resolve A-8.4.
- **Uma única implementação de ponto-em-polígono** (a do `GrafoViario`, que tem
  teste geométrico via `FakeMalhaViaria`). Resolve A-8.5.
- **Mover as constantes calibradas para `LogisticaConfig`/`MonitoraConfig` por
  empresa**, mantendo os valores atuais como default e os comentários como
  documentação do default. Resolve A-8.7.
- **Registrar autoria em `priorizar`, `reagendar`, `desbloquear` e `adiar`.**
  Resolve A-8.9.
- **Remover `reduzir`/`rdp` e as duas constantes órfãs**, preservando os
  comentários que explicam o abandono. Resolve A-8.6.

---

**Volume 8 fechado.** 39/39 arquivos, 4.593/4.593 linhas. 12 achados
(4 alta, 5 média, 3 baixa).
