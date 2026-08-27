# Volume 5 — Satelite e Alerta

> Recorte: `app/Domain/Satelite/` (9 arquivos) e `app/Domain/Alerta/` (1) —
> **2.219 linhas, 10 arquivos, todos lidos por inteiro**. Fonte: código e banco.
>
> **Status: FECHADO.**

---

## O que funciona (verificado)

**O extrato de comodato é auditável e reversível.** Todo movimento gera linha;
`recalcular()` reconstrói `quantidade_devolvida` a partir da série e prova que o
acumulado é derivado — se ele mudar algo, houve escrita fora do serviço. Estorno
é linha nova, não edição.

**O contrato é versionado com números congelados.** A versão que o cliente
assinou continua dizendo o que dizia; a nova descreve a posse atual.

**A deduplicação de alertas é bem desenhada.** `chave` + `ocorrencias` impedem 52
alertas idênticos por ano, e `registrar()` preserva `situacao` e
`responsavel_user_id` ao atualizar — não devolve à fila o que alguém já assumiu.

**A vigilância mede sem acusar.** Régua adaptativa contra o próprio histórico,
baseline normalizado por duração, e `classificar()` devolve o motivo em texto
que a equipe lê antes de visitar o cliente.

---

## Achados

### A-5.1 — `encerrarAusentes` com lista vazia resolve a fila inteira

**Critério:** C4 (convenção não declarada) · **Severidade: ALTA**

**O que é.** `AlertaService::encerrarAusentes()` fecha todos os alertas de uma
origem que não estejam na lista de chaves reafirmadas:

```php
if ($chavesVivas !== []) {
    $query->whereNotIn('chave', $chavesVivas);
}
return $query->update([... 'situacao' => Alerta::RESOLVIDO ...]);
```

Quando `$chavesVivas` é `[]`, o `whereNotIn` **não é aplicado** — e o `update`
resolve **todos** os alertas abertos daquela origem.

**Evidência.** `app/Domain/Alerta/AlertaService.php`, método
`encerrarAusentes()`. E `GerarAlertasComodato::executar()` passa exatamente essa
lista:
```php
$chavesGiro = [];
foreach ($avaliacoes as $avaliacao) { ... $chavesGiro[] = ...; }
$encerrados = $this->alertas->encerrarAusentes($empresaId, self::ORIGEM_GIRO, $chavesGiro);
```

**Por que impede o SaaS.** Qualquer motivo que zere `$avaliacoes` limpa a fila
sem deixar sinal: vínculo casco↔gás ausente (`idsCompraveis` devolve vazio →
consumo zero → mas ainda geraria alertas), `posse_minima_vigiada` mal
configurada, `sentido` errado após a conversão de dados, ou uma exceção parcial.
Os 170 alertas em produção seriam marcados "Encerrado automaticamente: a condição
que originou o alerta deixou de existir" — mensagem que afirma algo falso.

O comportamento correto para lista vazia é ambíguo: pode significar "nada mais
está em alerta" (fechar tudo) ou "a rodada não produziu nada" (não fechar nada).
O código escolhe o primeiro sem distinguir os casos.

**Direção de correção.** Distinguir "rodada bem-sucedida sem achados" de "rodada
sem resultado": passar um flag explícito, ou exigir que o chamador confirme que a
avaliação rodou. E registrar quantos foram encerrados de uma vez — encerramento
em massa é sinal, não rotina.

---

### A-5.2 — `acrescentarProduto` procura comodato existente sem filtrar empresa

**Critério:** C6 (escopo de tenant errado) · **Severidade: ALTA**

**O que é.** O método valida corretamente que o **produto** é da mesma empresa —
e logo abaixo busca o comodato existente **sem** essa validação:

```php
$existente = Comodato::query()
    ->where('cliente_id', $referencia->cliente_id)
    ->where('produto_id', $produtoId)
    ->where('sentido', $referencia->sentido)
    ->whereIn('situacao', ['ATIVO', 'PARCIAL'])
    ->first();
```

**Evidência.** `app/Domain/Satelite/ComodatoService.php`,
`acrescentarProduto()`. Contraste com a validação imediatamente anterior, que
tem o filtro:
```php
$produto = Produto::query()->where('empresa_id', $referencia->empresa_id)->find($produtoId);
```

**Por que impede o SaaS.** A proteção depende do global scope
`BelongsToTenant` — que, pelos Volumes 3 e 4, está **desligado fora do HTTP**. Um
acréscimo feito por job, comando ou importação encontraria o comodato de outra
empresa do grupo para o mesmo cliente e produto, e cresceria a linha errada:
estoque baixado numa empresa, contrato emitido em outra.

**Nota de responsabilidade:** este código foi escrito nesta mesma sessão
(commit `4d8a3f3`), horas depois de a correção `vinculo-vasilhame-fronteira-empresa`
ter estabelecido exatamente esta regra. A validação de produto foi feita; a de
comodato, esquecida. É evidência de que a regra precisa virar teste automatizado,
não memória.

**Direção de correção.** `->where('empresa_id', $referencia->empresa_id)` na
busca, e um teste que cubra o caso.

---

### A-5.3 — `ComodatoAvaliacao` tem chave única sem `empresa_id`

**Critério:** C6 (escopo de tenant errado) · **Severidade: MÉDIA**

**O que é.** A avaliação é gravada por `updateOrCreate` com chave
`[cliente_id, referencia]`:

```php
return ComodatoAvaliacao::updateOrCreate(
    ['cliente_id' => $cliente->id, 'referencia' => $referencia->toDateString()],
    [ 'empresa_id' => $cliente->empresa_id, ... ],
);
```

**Evidência.** `app/Domain/Satelite/VigilanciaComodatoService.php`,
`avaliarCliente()`.

**Por que impede o SaaS.** `empresa_id` está no payload, não na chave. Hoje não
colide porque `clientes.id` é global e único — cada cliente pertence a uma
empresa. Mas isso amarra a vigilância a essa propriedade: se a conversão de dados
para o SaaS reusar ids de cliente por tenant (o que é comum ao importar bases
independentes), duas empresas passariam a sobrescrever a avaliação uma da outra
silenciosamente.

**Direção de correção.** Incluir `empresa_id` na chave do `updateOrCreate`.

---

### A-5.4 — O consumo do cliente é medido sem filtrar empresa

**Critério:** C6 (escopo de tenant errado) · **Severidade: MÉDIA**

**O que é.** `VigilanciaComodatoService::consumo()` soma pedidos por
`p.cliente_id`, sem `p.empresa_id`:

```php
DB::table('pedidoitens as i')
    ->join('pedidos as p', 'p.id', '=', 'i.pedido_id')
    ->where('p.cliente_id', $clienteId)
    ...
```

**Evidência.** O mesmo arquivo, método `consumo()`. Usa `DB::table`, portanto
**sem global scope** — só a RLS protegeria, e o comando roda em CLI, onde a
policy libera (A-3.1).

**Por que impede o SaaS.** O giro do cliente somaria compras feitas em outra
empresa do grupo. O efeito é o **oposto** de um alerta falso: infla o consumo e
**esconde** um desvio real. Numa rede com filiais — o cenário que o sistema
inteiro assume — é o caso normal, não a exceção.

`ConvenioFechamentoService::fechar()` tem o mesmo padrão: busca pedidos por
`cliente_id` sem `empresa_id`, e ali o efeito é financeiro — o fechamento
consolidaria pedidos de outra empresa numa fatura só.

**Direção de correção.** `->where('p.empresa_id', $empresaId)` nas duas consultas.
O `empresaId` já está disponível em ambos os métodos.

---

### A-5.5 — A classificação de vasilhame depende de palavras em português

**Critério:** C2 (classificação por texto) · **Severidade: ALTA**

**O que é.** Confirma e detalha A-1.1. `VinculoVasilhame` decide o que é casco e
o que é conteúdo por substring:

```php
return str_contains($texto, 'VASILHA') || str_contains($texto, 'CASCO')
    || str_contains($texto, 'BOTIJAO') || str_contains($texto, 'BOTIJÃO');
```
```php
return str_contains($texto, 'GLP') || str_contains($texto, 'RECARGA');
```

E a capacidade por regex: `/\bP\s?(13|20|45|90)\b/` ou `/\b(13|20|45|90)\s?KG\b/`.

**Evidência.** `app/Domain/Satelite/VinculoVasilhame.php` — `ehVasilhame()`,
`ehConteudo()`, `capacidade()`.

**Por que impede o SaaS.** As capacidades são fixas em quatro valores (13, 20, 45,
90). Uma revenda que trabalhe com P2, P8 ou cilindro industrial de 190 kg fica
sem vínculo e, portanto, **sem vigilância nenhuma** — silenciosamente, porque
`conteudosDe()` devolve `[]` e o cliente simplesmente não aparece na fila.

O mesmo vale para nomenclatura: "Cilindro", "Garrafa", "Vasilhame" (com M) não
casam com nenhuma das quatro palavras.

**Nuance:** o desenho é honesto sobre isso — casos ambíguos ficam sem vínculo e
vão para a tela de conferência, em vez de serem adivinhados. O problema não é a
heurística existir; é ela ser a **única** fonte, sem que a estrutura
(`natureza`, A-1.11) carregue a informação.

**Direção de correção.** `natureza` com valor `vasilhame`/`conteudo` e
`capacidade` como campo, preenchidos na conversão com a heurística **como
sugestão**, conferidos na tela que já existe.

---

### A-5.6 — Vale-gás e convênio não registram quem operou

**Critério:** C1 (conceito ausente) · **Severidade: MÉDIA**

**O que é.** `ValeGasService::mudarSituacao()` e
`ConvenioFechamentoService::fechar()` não recebem nem gravam `user_id`.

**Evidência.**
- `ValeGasService::mudarSituacao(ValeGas $vale, SituacaoValeGas $destino, ?int $pedidoId = null)`
- `ConvenioFechamentoService::fechar(Convenio $convenio, string $inicio, string $fim, int $numParcelas = 1)`

Contraste: `ComodatoService` propaga `?int $userId` por todos os métodos e o
grava em cada movimento.

**Por que impede o SaaS.** Vale-gás é dinheiro pré-pago: quem cancelou um vale
pago, ou marcou como utilizado, não fica registrado. O fechamento de convênio
gera um financeiro consolidado — quem o fechou, e com qual período, também não.
Nenhum dos dois models usa `Auditavel` (verificado no Volume 2).

**Direção de correção.** Propagar `userId` como o comodato já faz, ou adicionar
`Auditavel` aos models.

---

### A-5.7 — `emPosse` lê o acumulado, e `recalcular` existe porque ele pode divergir

**Critério:** C5 (conceitos misturados) · **Severidade: BAIXA**
(observação de desenho)

**O que é.** Há duas fontes para o saldo: `comodatos.quantidade_devolvida`
(acumulado, lido por `emPosse()`) e a série de `ComodatoMovimento` (a verdade,
lida por `recalcular()`).

**Evidência.** `ComodatoService::emPosse()` usa o acumulado; `recalcular()`
existe, nas palavras do próprio comentário, *"para provar que o acumulado é
derivado, não uma segunda verdade: se este método mudar algum comodato, houve
escrita fora do serviço"*.

**Por que anotar.** A duplicação é consciente e justificada (a listagem precisa
de um número pronto). Mas `recalcular()` **não é chamado por nada em produção** —
é uma prova que ninguém executa. E `comodatos` do legado não têm extrato, então
o método os ignora por design: exatamente as 975 linhas migradas, onde a
divergência seria mais provável, ficam fora da verificação.

**Direção de correção.** Rodar `recalcular()` como invariante no
`cutover:check`/`golive:check`, ao menos para os comodatos com extrato.

---

## Cobertura

**10 de 10 arquivos, lidos por inteiro:**

| Arquivo | Linhas |
|---|---|
| `Satelite/ComodatoService.php` | 520 |
| `Satelite/ComodatoPdfService.php` | 424 |
| `Satelite/VigilanciaComodatoService.php` | 312 |
| `Satelite/VinculoVasilhame.php` | 235 |
| `Satelite/GerarAlertasComodato.php` | 203 |
| `Satelite/ValeGasPdfService.php` | 180 |
| `Alerta/AlertaService.php` | 155 |
| `Satelite/ConvenioFechamentoService.php` | 83 |
| `Satelite/ValeGasService.php` | 77 |
| `Satelite/SituacaoValeGas.php` | 30 |

`ComodatoPdfService` e `ValeGasPdfService` foram lidos integralmente na sessão de
trabalho anterior (as alterações de contrato consolidado e sentido passaram por
eles); reconferidos aqui nos pontos que os achados tocam.

**Consultas ao banco:** 0 novas — este volume se apoia nas medições já feitas
nos Volumes 1 e 4 e no trabalho de comodato desta sessão.

---

## Resumo

| Critério | Achados |
|---|---|
| C1 — conceito ausente | 1 (A-5.6) |
| C2 — classificação por texto | 1 (A-5.5) |
| C4 — convenção não declarada | 1 (A-5.1) |
| C5 — conceitos misturados | 1 (A-5.7) |
| C6 — escopo de tenant errado | 3 (A-5.2, A-5.3, A-5.4) |

**7 achados · 3 ALTA · 3 MÉDIA · 1 BAIXA.**

### O que este volume mostra

Três dos sete achados são a **mesma regra violada**: consulta de negócio sem
filtro de `empresa_id`, confiando num global scope que os Volumes 3 e 4 mostraram
estar desligado fora do HTTP. `acrescentarProduto`, `consumo`, e
`ConvenioFechamentoService::fechar`.

O mais revelador é A-5.2: foi escrito **nesta sessão**, horas depois de a mesma
regra ter sido estabelecida e documentada em memória, no mesmo arquivo, com a
validação de produto logo acima fazendo exatamente o filtro que a de comodato
esqueceu.

Isso diz algo sobre o plano: **"lembrar de filtrar empresa" não é uma correção
implementável.** Enquanto a proteção depender de disciplina em cada consulta, o
defeito volta — inclusive para quem acabou de corrigi-lo. A correção real é
estrutural (A-3.1: fail-closed sem tenant), e cada filtro manual é paliativo até
lá.
