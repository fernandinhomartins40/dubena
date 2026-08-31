# F3-04A — "Saiu para entrega" deixa de ser uma palavra procurada

Data: 2026-08-31 (America/Sao_Paulo)

## O código que existia

`EntregaService::iniciarRota()` — a ação que o entregador dispara no app ao sair
com a carga — encontrava a situação de deslocamento assim:

```php
->where(function ($q) {
    foreach (['%saiu%', '%rota%', '%caminho%'] as $termo) {
        $q->orWhereRaw('LOWER(descricao) LIKE ?', [$termo]);
    }
})
->orderBy('id')->first();

return $alvo ?? PedidoSituacao::create(['descricao' => 'Saiu para entrega', ...]);
```

Três problemas empilhados, e o terceiro é o que dói:

1. **procura por texto em português.** Funciona para a revenda que escreveu
   essas palavras;
2. **desempata por `orderBy('id')`.** Duas situações casando, a mais antiga
   ganha — sem que ninguém tenha decidido isso;
3. **cadastra uma situação quando não acha.** Para a segunda revenda — "Em
   trânsito", "Despachado", ou qualquer coisa em espanhol — a busca falha e o
   sistema **cria** "Saiu para entrega" para conseguir continuar.

O item 3 é o pior tipo de defeito: silencioso e cumulativo. O cliente configurou
o Kanban dele, e o sistema acrescenta uma coluna que ele não pediu, com o nome
que o desenvolvedor achou natural. A partir daí existem dois nomes para o mesmo
momento, o relatório soma errado, e nada liga o sintoma à causa.

Também havia o falso positivo do outro lado: uma coluna chamada
**"Saiu do estoque para conferência"** casa com `%saiu%` e viraria destino de
entrega.

## A correção

`papel` na situação — uma marca declarada, não inferida.

`EfeitoPedido` continua com três valores e governa a máquina de estados (o que a
transição faz com estoque e financeiro). Isso está certo e não mudou. O que
faltava era distinguir momentos **dentro** de um mesmo efeito: "Aguardando
separação" e "Saiu para entrega" são ambos `PENDENTE`, mas só o segundo
significa mercadoria na rua.

Quatro decisões:

- **default `NENHUM`.** Papel é afirmação deliberada de quem configura. Um
  default que inferisse algo reintroduziria o problema por outro caminho;
- **exclusivo por grupo.** Duas situações `EM_ROTA` deixariam a ação com dois
  alvos, e a escolha voltaria a ser o desempate arbitrário por id;
- **sem papel, a ação FALHA** com uma mensagem que diz o que configurar. Um erro
  assim custa um minuto; uma situação duplicada em silêncio contamina o
  relatório do cliente para sempre;
- **exposto na UI** (diálogo da coluna do Kanban), senão a configuração
  existiria só na API e ninguém a preencheria.

## A conversão dos dados existentes

A heurística antiga é usada **uma vez**, na migration, e só onde é inequívoca:
um único candidato no grupo. Grupo com dois candidatos fica **sem papel**, de
propósito.

Escolher "a de menor id" resolveria a migration e deixaria uma decisão errada
gravada num banco que ninguém mais revisaria. Sem papel, a ação avisa que
precisa de configuração — que é uma pergunta respondível por quem sabe a
resposta.

A migration também aceita `%trânsito%` e `%transito%`, que a busca em runtime
não tinha — na conversão, alcançar mais casos legítimos é ganho; em runtime,
seria só mais uma palavra na lista errada.

## Os testes usam espanhol de propósito

O gate da F3 exige "teste com nomenclatura não portuguesa". `En reparto`,
`Camino al cliente`, `Pendiente de despacho` — a heurística antiga não sobrevive
a nenhum deles, e o papel declarado passa em todos.

Também há o teste do falso positivo: "Saiu do estoque para conferência" **não**
é escolhida, porque o nome deixou de decidir.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 8 (`PapelSituacaoEmRotaTest`) + 6 (`IniciarRotaTest` atualizado) |
| Suíte integral, modo padrão | **1460 passes / 4627 assertions** |
| Suíte integral, enforcement ligado | **1460 passes**, zero falhas |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 |
| Migration up → down → up (PostgreSQL real) | OK |
| `tsc --noEmit` / Vitest | limpo / 39 |
| Pint | aprovado |

O contrato de schema (F2-01) capturou a mudança sozinho: `papel` apareceu no
diff de `api-schema.json` sem ninguém editar o arquivo. Foi para isso que ele
foi construído.

## O que fica

F3-04A está fechada. As demais tarefas da F3 (Party, Item, snapshot, estados
ortogonais, StockLocation, geografia, frota, configuração) continuam abertas —
esta era a mais concreta e verificável, e a que tinha um defeito ativo.
