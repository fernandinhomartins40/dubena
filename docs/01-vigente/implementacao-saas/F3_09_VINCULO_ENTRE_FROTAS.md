# F3-09 (parcial) — As duas frotas passam a se conhecer

Data: 2026-08-31 (America/Sao_Paulo)

## O mesmo caminhão, duas vezes

| Tabela | O que guarda |
|---|---|
| `veiculos` | km, troca de óleo, documentos, abastecimento, pneus |
| `monitora_veiculos` | imei do rastreador, posições, cercas, viagens |

E **nada as ligava**. Cada uma tem o seu `veiculo_id` apontando para si mesma; a
placa é a única coisa em comum, e ninguém confere se batem.

Duas consequências:

- **operacional**: *"onde está o caminhão que precisa trocar o óleo?"* não tem
  resposta. Uma frota sabe o km, a outra sabe a posição, e cruzar as duas é
  trabalho manual sobre planilha;
- **de cadastro**: a placa pode divergir entre as duas por um erro de digitação,
  e nada acusa. O veículo simplesmente some de um dos lados — sem erro, sem
  alerta.

## Vínculo, e não fusão

A tarefa fala em "consolidar as duas frotas", e o alvo final é uma tabela só.
**Isto é o passo anterior.**

Fundi-las agora alcançaria `Veiculo` em 23 arquivos e as tabelas de posição, que
têm milhões de linhas. É migração de dado grande, e o custo de errá-la é perder
histórico de rastreamento — que é a prova de onde o veículo esteve, usada para
conferir entrega e jornada.

O vínculo entrega a resposta operacional hoje e deixa a fusão como um passo
separado, feito com os dois lados **já conciliados** — que é uma posição bem
melhor para fazê-la.

## Duas decisões de desenho

**`nullOnDelete`, não `cascade`.** Apagar o cadastro de frota não pode apagar o
histórico de posições. Um cascade aqui destruiria prova operacional por causa de
uma limpeza de cadastro. Há teste.

**A chave mora no lado do monitora.** A frota é o cadastro principal do veículo;
o rastreamento é algo que se acopla a ele. Daí `frota()` ser `belongsTo` e
`rastreamento()` ser `hasOne`.

E o corolário que a tarefa pede explicitamente: **o rastreador é um vínculo, não
a identidade.** Trocar de aparelho não cria um caminhão novo nem desfaz a ligação
— há teste para isso também.

## A conciliação, verificada em PostgreSQL

Pela placa **normalizada** (maiúsculas, só alfanumérico), e só quando o par é
inequívoco: exatamente um de cada lado, na mesma empresa.

| Caso | Resultado |
|---|---|
| frota `ABC1D23` × rastreado `abc-1d23` | **casou** — a normalização é o ponto |
| placa duplicada na frota | **NULO** |
| rastreado sem par | NULO |
| mesma placa em outra empresa | casou com **o veículo dela** |

Placa duplicada fica sem vínculo de propósito. Escolher uma delas gravaria um
palpite num banco que ninguém revisa — e aqui o palpite errado liga a manutenção
de um caminhão à posição de outro, que é pior que não ligar nada.

Mesma placa em empresas diferentes é possível e legítimo: são veículos distintos,
e a conversão não pode uni-los.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 5 (`VinculoEntreFrotasTest`) |
| Migrations em PostgreSQL real | 152, sem erro |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 |
| Conciliação com massa real | 4 casos, todos corretos |
| Rollback → reaplicação | OK |
| Suíte integral | ver ESTADO_ATUAL |
| Pint | aprovado |

## O que fica aberto

- a **fusão** das duas tabelas (o pedaço grande);
- uma tela de conciliação que mostre os não vinculados e os ambíguos — hoje eles
  ficam nulos corretamente, mas ninguém os vê;
- `monitora_veiculos` ainda pode ser criado com placa que não existe na frota,
  sem aviso.
