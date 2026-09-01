# F6 — Progresso

Data: 2026-08-31 (America/Sao_Paulo)

## As dez tarefas

| Tarefa | Estado | Onde |
|---|---|---|
| F6-01 — IntegrationAccount | **parcial** | circuito por credencial; quota/custo em aberto |
| F6-02 — Frota/rastreador | **medida, sem ação** | não há ingestão por device (abaixo) |
| F6-03 — Geocodificação | **já estava** | chave por grupo, falha observável, retry real |
| F6-03A — Casos geográficos | fechada | `GeometriaPontoUnicoTest` |
| F6-04 — Logística | fechada | `Geo` único + defeito da `boundingBox` |
| F6-05 — Marketplace | fechada | cobertura independente do canal |
| F6-06 — Autoria | aberta | |
| F6-06A — Dados legados | fechada | normalizador canônico + `identidade:reparar` |
| F6-07 — Tempo real | fechada | `TempoRealCruzadoTest` |
| F6-08 — Falhas | fechada | `INDETERMINADO` ≠ recusa |

## Os defeitos encontrados

**A flag de canal governava a cobertura geográfica** (F6-05). `validarCobertura`
só rodava quando `app_marketplace_ativo` era verdadeiro — então uma revenda fora
do marketplace aceitava pedido do app de **qualquer endereço**, e a cerca que ela
mesma desenhou era ignorada. O teste que provava isso afirmava, com todas as
letras, que um pedido de Curitiba (250 km) era aceito por quem declarou raio de
5 km.

**O circuit breaker era global** (F6-01). A chave estava numa constante:
`'groutes:circuito-aberto'`. Com N revendas, cada uma com o seu credenciamento
Google, a quota estourada de **uma** abria o circuito de **todas**.

**Falha de rede virava recusa de cartão** (F6-08). Um timeout depois da
autorização gravava `NEGADO`, a venda era refeita, e **o cliente pagava duas
vezes**.

**A `boundingBox` recortava mais que o raio** (F6-04). Usava 111 320 m/grau (o
elipsoide) com a esfera de 6 371 km do Haversine ao lado: 0,112% menor, ou 5,6 m
num raio de 5 km. Como é pré-filtro de query indexada, o candidato descartado
nunca chegava ao cálculo fino — cliente na borda da área sumia da lista.

**Quatro Haversines com três raios** (F6-04). O `Geo` foi criado justamente para
unificar (Q-4 da auditoria) e as cópias voltaram depois.

**`iconv` dependente de locale e telefone fora do observer** (F6-06A). O boleto
saía com `?` no lugar do acento em Windows; e o telefone gravado por
`DB::table()->insert()` não virava traço de identidade, deixando o cliente
invisível ao motor de deduplicação.

## Uma decisão de escopo registrada

F6-05 **revoga** a exceção que a F7 (`PLANO_SEGURANCA_MULTITENANT_APPS`) abria
para builds white-label. Aquele plano dizia que empresa fora do marketplace
"mantém o comportamento atual"; o plano SaaS diz que a flag de canal decide
descoberta pública, não alcance de entrega.

O teste que protegia a regra antiga foi **reescrito**, não apagado — e passou a
cobrir os dois lados: quem declarou área respeita a área, quem não declarou não é
restringido. Essa segunda metade é o que impede a correção de derrubar a operação
de quem ainda não configurou cerca.

## F6-02 é futuro, não dívida

> **F6-02 — Frota/rastreador:** Vehicle único, device mapping explícito, posição
> por empresa/jornada e **quarentena para device desconhecido**.

Medi: **não existe ingestão por device**. A única porta de posição é
`POST /monitora/veiculos/{id}/posicoes`, que resolve o veículo por id sob o
escopo de tenant e exige `monitora.edit` — não há como um device desconhecido
entrar, porque não há caminho que resolva veículo por `imei`.

As colunas `imei`/`deviceid` existem como vínculo documental, e nenhuma consulta
as usa (verifiquei). A quarentena só faz sentido quando um provedor de
rastreamento for integrado por webhook; construí-la agora seria proteger uma
porta que não existe.

## Verificação

| Portão | Resultado |
|---|---|
| Suíte integral | **1691 passes / 5884 assertions** |
| CI (PostgreSQL, role restrita) | verde em todos os commits |
| Guardiões novos | cada um verificado com regressão plantada |

## Aberto

**F6-01** (quota, custo, finalidade e health por conta de integração) e **F6-06**
(autoria de atribuição, missão, vale, convênio e telefonia).
