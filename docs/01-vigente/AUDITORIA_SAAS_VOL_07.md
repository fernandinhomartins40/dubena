# Auditoria SaaS — Volume 7: Financeiro, Cobrança, Pagamento, Caixa

**Recorte:** `app/Domain/Financeiro/`, `app/Domain/Cobranca/`,
`app/Domain/Pagamento/`, `app/Domain/Caixa/` — 29 arquivos, 3.038 linhas.
**Leitura:** 29/29 lidos integralmente (conferido por `wc -l`: 3.038).
**Data:** 2026-08-25.
**Método:** ver [AUDITORIA_SAAS.md](AUDITORIA_SAAS.md). Achados formados só a
partir do código.

---

## Arquivos lidos

| Arquivo | Linhas |
|---|---:|
| Caixa/CaixaService.php | 303 |
| Caixa/MaloteService.php | 266 |
| Cobranca/PixService.php | 215 |
| Financeiro/FinanceiroService.php | 206 |
| Pagamento/GasDoPovoService.php | 171 |
| Financeiro/RegraExtratoService.php | 161 |
| Cobranca/BoletoService.php | 138 |
| Cobranca/Cnab/CnabHelper.php | 137 |
| Cobranca/BoletoPdfService.php | 137 |
| Caixa/ChequeService.php | 112 |
| Financeiro/ConciliacaoService.php | 110 |
| Pagamento/PagamentoService.php | 103 |
| Financeiro/ConciliacaoContabilService.php | 102 |
| Cobranca/Drivers/ItauBoletoDriver.php | 97 |
| Cobranca/Drivers/CaixaBoletoDriver.php | 96 |
| Cobranca/CodigoBarrasI25.php | 92 |
| Cobranca/Drivers/CnabDriverBase.php | 89 |
| Financeiro/OfxParser.php | 67 |
| Cobranca/Drivers/FakeBoletoDriver.php | 67 |
| Cobranca/Events/PixConfirmado.php | 65 |
| Cobranca/Cnab/ContaCobranca.php | 62 |
| Financeiro/ContaExtratoAcao.php | 56 |
| Cobranca/Contracts/BoletoDriver.php | 37 |
| Caixa/SituacaoCheque.php | 33 |
| Cobranca/Drivers/FakePixDriver.php | 31 |
| Cobranca/Contracts/PixDriver.php | 28 |
| Financeiro/AgrupamentoStatus.php | 26 |
| Cobranca/SituacaoBoleto.php | 17 |
| Cobranca/SituacaoPix.php | 14 |
| **Total** | **3.038** |

---

## Leitura geral do domínio

Este volume tem a maior **variância de qualidade** de toda a auditoria até agora,
e a variância é o achado principal.

No topo: o `PixService` é o melhor código do sistema. Fail-closed por empresa com
credencial resolvida por id explícito (nunca contexto ambiente), binding de valor
no webhook, idempotência real na reentrega, lock pessimista, evento despachado só
na transição verdadeira. O comentário de cabeçalho declara cada decisão de
segurança e o porquê. Ao lado dele, `SituacaoCheque` com máquina de estados
explícita, `CodigoBarrasI25` e `CnabHelper` com matemática FEBRABAN correta e
documentada, e o `CaixaService` com a invariante Σ movimentos = saldo mantida sob
lock — todos bons.

No fundo, no **mesmo domínio**: `BoletoService::localizarBoleto()` carrega todos
os boletos de todas as empresas em memória e casa por `str_contains`.

A explicação não é descuido de um autor. É que **o tenant não é uma propriedade
do sistema, é uma decisão tomada arquivo a arquivo**. Onde alguém pensou nele
(PIX, Malote), está certo e comentado. Onde ninguém pensou (Boleto, Caixa,
Financeiro), o `withoutTenant()` foi usado como ferramenta de conveniência para
fazer a query funcionar — e cada uso desses é uma porta.

O segundo eixo: **dinheiro que entra por três caminhos que não se conhecem.**
PIX baixa parcela pelo webhook; boleto baixa parcela pelo retorno CNAB; caixa
baixa parcela pela tela. Nenhum dos três verifica se outro já baixou (o PIX
verifica `where('baixado', false)`, os outros dois não). Um título com boleto
emitido e PIX gerado pode ser pago pelos dois — situação real, porque o cliente
recebe o boleto por e-mail e o QR no app.

---

## Achados

### A-7.1 (ALTA) — `CaixaService::movimentar()` aceita conta de qualquer empresa

**Critério:** C6 — escopo de tenant errado.

`CaixaService.php:47`:

```php
$conta = Conta::withoutTenant()->whereKey($contaId)->lockForUpdate()->firstOrFail();
...
return ContaMovimento::create(array_merge([
    'empresa_id' => $conta->empresa_id,   // ← empresa vem da CONTA, não do contexto
```

`movimentar()` é a porta única de mutação de saldo — todo o domínio passa por
ela: `baixarParcela`, `baixarTitulos`, `transferir`, `estornar`, `criarConta`,
`lancarEmCaixaFechado`, `PagamentoService::registrarCartao`,
`PagamentoService::sacarBeneficio`, `ChequeService::mudarSituacao`. Nenhuma
dessas valida que `$contaId` pertence à empresa ativa.

O `withoutTenant()` está ali por um motivo legítimo (o método é chamado de jobs e
webhooks sem tenant resolvido), mas o efeito é que **o `conta_id` vindo do
request é confiado sem verificação**.

O caso mais claro é `baixarParcela` (`:132-150`): ele valida cuidadosamente que a
**parcela** pertence à empresa ativa — há inclusive um comentário `F00.5` narrando
que isso corrigiu um IDOR encontrado em auditoria anterior — e em seguida entrega
a baixa a `movimentar($contaId, ...)` **sem validar a conta**. Metade do par foi
protegida.

Consequência: baixar uma parcela própria creditando o caixa de outra empresa. O
movimento nasce com `empresa_id` da conta destino, então o dinheiro aparece
corretamente no caixa alheio e o título é quitado no caixa de quem baixou —
divergência que a conciliação não pega, porque os dois lados estão internamente
consistentes.

O contraexemplo está no mesmo volume: `MaloteService::exigirContaDaEmpresa()`
(`:245-256`) faz exatamente a checagem que falta, com o comentário certo — *"a
RLS já isola por tenant, mas quem chama informa o id"*. A proteção foi pensada e
implementada **no chamador**, não na porta. Só um dos oito chamadores a tem.

**Nota sobre RLS:** a RLS pode barrar o `SELECT` da conta alheia em runtime, o
que reduziria o impacto — mas `withoutTenant()` remove o global scope do Eloquent,
não a policy do Postgres. Se a policy de `contas` usa `app.empresas_visiveis` e o
usuário enxerga mais de uma empresa (caso de grupo — ver D-1), a RLS **permite** a
leitura. O código não deve depender disso: a checagem custa uma cláusula.

---

### A-7.2 (ALTA) — Retorno CNAB casa boleto carregando toda a base e comparando substring

**Critério:** C6 / C2 — classificação por texto.

`BoletoService.php:134-139`:

```php
private function localizarBoleto(string $linha): ?Boleto
{
    return Boleto::withoutTenant()->get()->first(
        fn (Boleto $b) => $b->nosso_numero && str_contains($linha, $b->nosso_numero),
    );
}
```

Três problemas somados:

**(a) Sem tenant.** Um arquivo de retorno de uma empresa pode casar com boleto de
outra e — via `processarRetorno` (`:105-124`) — **baixar a parcela alheia**,
marcando `baixado = true`, `valor_efetivado` e `datahora_baixa`. É pagamento
fantasma criado por importação de arquivo.

**(b) `->get()` sem filtro.** Carrega **todos os boletos já emitidos** em memória,
para cada linha do retorno. Um retorno de 500 linhas contra uma base de 50 mil
boletos são 25 milhões de comparações e 500 cargas completas da tabela. Não é
apenas lento: em base real, estoura memória.

**(c) `str_contains` como casamento.** O nosso número do `FakeBoletoDriver` é o
id com zeros à esquerda (`00000000123`). Uma linha CNAB de 400 caracteres
numéricos contém dezenas de substrings assim por coincidência. `str_contains`
casa o **primeiro** boleto cujo nosso-número apareça em qualquer posição da
linha — inclusive dentro do campo de valor, da data ou do nosso-número de outro
boleto (`...0123...` contém `123`).

O dado para casar corretamente existe: os dois drivers reais gravam o `boleto->id`
no campo "uso da empresa" em posição fixa (`CaixaBoletoDriver:66`,
`ItauBoletoDriver:64`), justamente para isso — os comentários dizem `(id p/
casamento)`. O `BoletoService` não usa esse campo. A infraestrutura de casamento
correto foi construída e ignorada.

---

### A-7.3 (ALTA) — Nosso-número derivado do `id` global: colide entre empresas do mesmo banco

**Critério:** C4 — convenção não declarada.

`CaixaBoletoDriver:29` e `ItauBoletoDriver:26`:

```php
$seq = CnabHelper::numero((int) $boleto->id, 15);   // Caixa
return CnabHelper::numero((int) $boleto->id, 8);    // Itaú
```

O nosso número é a chave que o **banco** usa para identificar o título dentro de
um convênio/carteira. Ele precisa ser único **por convênio**, e o convênio é por
empresa (`ContaCobranca::daEmpresa`).

Usar o `id` global da tabela `boletos` funciona por acidente enquanto há uma
empresa: os ids nunca colidem porque são globalmente únicos. Mas produz duas
consequências no SaaS:

**(a) Numeração esburacada e não-sequencial por empresa.** A empresa A recebe os
nossos-números 1, 5, 9, 12 e a B recebe 2, 3, 4, 6 — porque a sequência é
compartilhada. Vários bancos exigem sequencial contínuo por convênio na remessa e
rejeitam faixas com saltos; e a conciliação bancária do cliente fica ilegível.

**(b) Itaú estoura em 8 dígitos.** `CnabHelper::numero` faz
`substr(..., 0, $tamanho)` **antes** do padding — ou seja, trunca pela
**esquerda**? Não: `substr($v, 0, 8)` pega os 8 primeiros caracteres. Com `id =
123456789` (9 dígitos), o nosso número vira `12345678` — e o `id = 123456780`
vira o **mesmo** `12345678`. A partir de 100 milhões de boletos há colisão
silenciosa; muito antes disso, em qualquer base com ids grandes vindos do ETL
(que preserva ids do legado — ver [[migracao-dados-legados]]), a truncagem já
acontece.

A `RemessaCnab` faz o certo no arquivo vizinho (`BoletoService:53`):
`->where('empresa_id', $empresaId)->max('numero_remessa') + 1` — sequência **por
empresa**. O mesmo padrão não foi aplicado ao nosso número.

---

### A-7.4 (ALTA) — Três caminhos de baixa que não se conhecem: risco de baixa dupla

**Critério:** C1 — conceito ausente (não há dono único da baixa).

O mesmo `financeiroparcelas.baixado` é escrito por quatro lugares diferentes, com
guardas diferentes:

| Origem | Local | Guarda de idempotência |
|---|---|---|
| Webhook PIX | `PixService:184` | `->where('baixado', false)` ✅ |
| Retorno CNAB | `BoletoService:116` | **nenhuma** ❌ |
| Tela de caixa | `CaixaService:139` | `if ($parcela->baixado) throw` ✅ |
| Encontro de contas (cheque) | `ChequeService:104` | **nenhuma** (`DB::table` direto) ❌ |

`BoletoService::processarRetorno` faz `update(['baixado' => true, ...])` sem
checar o estado anterior. Reprocessar o mesmo arquivo de retorno — coisa que
acontece (o operador não sabe se já importou) — sobrescreve `valor_efetivado` e
`datahora_baixa`, apagando o registro da baixa original.

Pior: um título com boleto **e** PIX (cenário normal — o boleto vai por e-mail, o
QR aparece no app) pode ser pago pelos dois. O PIX baixa e credita; o retorno
CNAB chega depois, não vê que já está baixado, sobrescreve. O dinheiro entrou
duas vezes no caixa (dois movimentos), o título aparece quitado uma vez, e não há
nenhum registro de que houve pagamento em duplicidade.

`ChequeService::encontroDeContas` usa `DB::table('financeiroparcelas')` — sem
model, sem global scope, sem tenant, sem verificação de `baixado` — e grava
`valor_efetivado` com `min(valor do cheque, compromisso)`. É baixa integral de uma
parcela que pode ter sido quitada parcialmente.

**O conceito ausente:** não existe um `BaixaService` que seja o único caminho. O
`CaixaService` é a porta única para *movimento de conta*, mas não para *baixa de
parcela* — e é a baixa que representa o dinheiro.

---

### A-7.5 (ALTA) — `FinanceiroService`: idempotência, estorno e agrupamento sem `empresa_id`

**Critério:** C6.

Três consultas de negócio sem filtro de empresa:

**(a) `gerarDoPedido`** (`:82`):
```php
$existente = Financeiro::query()->where('origem', 'pedido')->where('origem_id', $pedido->id)->where('cancelado', false)->first();
```

**(b) `estornarDoPedido`** (`:117-121`): mesma query, seguida de `->each(fn ($f) =>
$this->cancelar($f))`.

**(c) `desagrupar`** (`:174`): `Financeiro::query()->where('agrupador_id', $agrupador->id)->update(...)`.

Se `Financeiro` tem global scope de tenant, o filtro depende da empresa **ativa
na sessão** — e `gerarDoPedido` recebe um `Pedido` que pode ser de outra empresa
(o método usa `$pedido->empresa_id` para criar, mas a empresa do contexto para
buscar). Os dois lados usam fontes de verdade diferentes para "qual empresa".

O caso (b) é o mais perigoso: `estornarDoPedido` **cancela** títulos. Um id de
pedido que colida entre empresas — e ids de pedido colidem, porque a tabela é
global — cancelaria o financeiro da outra empresa. Cancelar título é operação
destrutiva de receita.

**(d) `agrupar()`** (`:145-166`) não valida nada: recebe uma coleção de títulos,
soma e agrupa. Não verifica que são da mesma empresa, do mesmo cliente, do mesmo
`pagarreceber`, nem que já não estão agrupados. Agrupar um título a pagar com um a
receber produz um agrupador com valor somado e sinal indefinido.

Esta é a **quarta** ocorrência da mesma regra violada na auditoria (A-5.2, A-5.3,
A-5.4, A-6.5 e agora A-7.5), reforçando a conclusão do Volume 5: *"lembrar de
filtrar empresa" não é uma correção implementável.*

---

### A-7.6 (MÉDIA) — Conciliação OFX ignora o `FITID` e casa por valor+data

**Critério:** C1 — conceito ausente.

`OfxParser` extrai corretamente o `FITID` (`:26`) — o identificador único que o
banco atribui a cada transação, existente exatamente para conciliação. O
`ConciliacaoService` nunca o usa: `casar()` (`:88-108`) compara valor
arredondado e data com tolerância de 2 dias.

Consequências:

- Duas transações do mesmo valor no mesmo dia (dois PIX de R$ 100 de clientes
  diferentes — rotina em disk-gás) casam com o movimento errado. O algoritmo pega
  o **primeiro** não-usado que bate; qual é o primeiro depende da ordem de
  `->get()`, que é indeterminada sem `orderBy`.
- **Reimportar o mesmo OFX reconcilia tudo de novo**, porque não há registro do
  que já foi conciliado — o resultado é devolvido como array, não persistido.
- `if ($dataOfx === null || $m['data'] === null) return $i;` (`:101`) casa **só
  pelo valor** quando falta data, o que com valores redondos (R$ 100,00) casa
  praticamente qualquer coisa.

Além disso, `ContaMovimento::query()->where('conta_id', $contaId)` (`:37`) não
valida a empresa da conta — mesma classe de A-7.1, aqui com efeito de leitura:
importar OFX contra uma conta alheia expõe os movimentos dela na tela.

---

### A-7.7 (MÉDIA) — `iconv('ASCII//TRANSLIT')` no CNAB, a armadilha já documentada e já corrigida ao lado

**Critério:** C4.

`CnabHelper::semAcento()` (`:130-135`):

```php
$t = @iconv('UTF-8', 'ASCII//TRANSLIT', $v);
return $t !== false ? $t : $v;
```

Usado por `CnabHelper::texto()`, que monta os campos alfanuméricos da remessa —
nome do cedente, nome do sacado, uso da empresa.

Esta é a armadilha **explicitamente documentada no `CLAUDE.md`** do projeto: *"o
resultado do TRANSLIT depende do locale — no Windows devolve `?` para acentos"*.
E o `RegraExtratoService`, no mesmo volume, **aplicou a correção certa** com
tabela explícita de transliteração e um comentário citando exatamente esse motivo
(`:145-157`).

Duas classes, dois autores, o mesmo problema, e só uma soube. A remessa gerada em
ambiente com locale diferente sai com `?` no lugar de acentos — no nome do
pagador que o banco imprime no boleto e no extrato do cliente.

O `preg_replace('/[^A-Z0-9 .,\-\/]/', ' ', $v)` logo depois converte o `?` em
espaço, então o efeito visível é nome truncado/lacunoso, não caractere estranho —
mais difícil de notar, igualmente errado.

---

### A-7.8 (MÉDIA) — Dois "Gás do Povo" incompatíveis no mesmo sistema

**Critério:** C5 — conceitos misturados.

`PagamentoService` (`:66-101`) modela o programa como **saldo e saque**:
`registrarBeneficio()` cria `GasDoPovoBeneficio` com `situacao = 'disponivel'`;
`sacarBeneficio()` marca `'utilizado'` e credita o caixa.

`GasDoPovoService` (`:13-34`) declara, em comentário de cabeçalho, que essa
modelagem **não corresponde à realidade**:

> *"**Não é um módulo com saldo e saque.** A auditoria do `ctrl-web` mostrou que
> lá não existe tela, rota nem controller do programa: ele é um MODO DE VENDA"*

E documenta a descoberta que desfaz a premissa: `precogasdopovo` é igual ao preço
normal, as vendas variam de R$ 96 a R$ 127, e *"o programa não é um desconto no
preço — é o canal de pagamento"*.

Ou seja: alguém auditou o legado, descobriu o modelo correto, escreveu um serviço
novo com o modelo correto, documentou o porquê — e **não removeu o modelo
errado**, que continua vivo, com tabela (`gasdopovo_beneficios`), rotas e
capacidade de creditar caixa.

Os dois modelos são mutuamente inconsistentes: se o benefício é saldo sacável, o
caixa recebe do programa; se é canal de pagamento, o caixa recebe do cliente via
cartão do benefício. Contabilizar dos dois jeitos duplica a receita.

Nenhum dos dois valida `empresa_id`: `registrarBeneficio` não grava empresa
alguma no benefício, e `GasDoPovoService::resumo(int $empresaId, ...)` **recebe o
parâmetro e nunca o usa** — `Pedido::query()->where('gasdopovo', true)` e
`Cliente::query()->where('gasdopovo', true)` dependem inteiramente do global
scope.

---

### A-7.9 (MÉDIA) — CONSISA: uma URL de plataforma para todas as empresas

**Critério:** C6 / C4.

`ConciliacaoContabilService::saldosContabeis()` (`:77-95`):

```php
$url = rtrim((string) config('services.consisa.url'), '/');
...
Http::timeout(15)->acceptJson()->get("{$url}/get_contabil", ['empresa_id' => $empresaId, ...]);
```

A URL do sistema contábil vem de `config/services.php` — **global da instalação**.
Todas as empresas consultam o mesmo endpoint, passando o próprio `empresa_id` como
parâmetro.

Isso pressupõe que todas as revendas usam o mesmo escritório de contabilidade, com
a mesma instância de CONSISA, e que o `empresa_id` do nosso banco é o mesmo
identificador que o contábil usa. Nenhuma das três premissas sobrevive a uma
segunda revenda.

O sistema já tem o padrão certo para isso — `IntegracaoTenant`, usado pelo
`PixService` para resolver credencial por empresa/grupo/plataforma. O
`ConciliacaoContabilService` não o usa. É a mesma decisão (integração externa por
tenant) resolvida de dois jeitos no mesmo volume.

Agravante: não há autenticação nenhuma na chamada (`Http::get` sem token/header),
e a resposta é cacheada por `"consisa:{$empresaId}:{$inicio}:{$fim}"` — chave que
inclui a empresa, felizmente, então não há vazamento por cache.

---

### A-7.10 (MÉDIA) — Regras de extrato e boleto PDF: leitura sem verificação de empresa

**Critério:** C6.

**(a)** `RegraExtratoService::regrasDaConta()` (`:87-100`):
`ContaExtratoRegra::query()->where('conta_id', $contaId)->where('ativo', true)` —
sem empresa. Uma regra de outra empresa aplicada ao extrato desta sugeriria plano
de contas e centro de custo alheios. Como a sugestão é confirmada pelo operador, o
dano é contido, mas os ids sugeridos apontam para registros que o operador não
consegue ver na tela.

**(b)** `BoletoPdfService::gerar()` (`:50-52`):
```php
$cliente = \App\Models\Cliente\Cliente::withoutTenant()->find($boleto->cliente_id);
```
O `withoutTenant()` aqui é para o caso do boleto ser impresso fora de contexto
(job de e-mail), mas não há verificação de que `$cliente->empresa_id ==
$boleto->empresa_id`. Se o `cliente_id` do boleto estiver errado (por A-7.5, por
ETL, por edição), o PDF sai com o nome de um cliente de outra revenda impresso
como pagador.

---

### A-7.11 (BAIXA) — Cartão e benefício não gravam empresa nem usuário

**Critério:** C6 / C1.

`PagamentoService::registrarCartao()` (`:36-49`) cria `CartaoTransacao` sem
`empresa_id` — depende de `BelongsToTenant` preencher no evento `creating`. Se o
método for chamado de um job (e é: conciliação de cartão), o tenant pode não estar
resolvido e a transação nasce órfã. Mesma coisa em `registrarBeneficio()`.

Nenhum dos dois registra `user_id`. Quem registrou a transação de cartão de R$
5.000 com NSU inexistente? O sistema não sabe. Mesmo buraco de autoria de A-5.6
(vale-gás/convênio) e A-6.9 (fiscal) — terceira ocorrência, agora em cima do
caixa.

`sacarBeneficio` marca `'utilizado'` e credita caixa **sem transação de
verificação** de que o pedido existe ou é da mesma empresa — `$pedidoId` é
gravado cru.

---

### A-7.12 (BAIXA) — `fatorVencimento` com regra de reinício aproximada

**Critério:** C4.

`CnabHelper::fatorVencimento()` (`:81-96`):

```php
$fator = $dias + 1000;
if ($fator > 9999) {
    $fator = ($fator - 1000) % 9000 + 1000;
}
```

O comentário diz *"Após 21/02/2025 o fator reinicia (regra FEBRABAN 9999→1000)"*.
A regra oficial é que ao atingir 9999 o fator volta a 1000 — o que a fórmula
reproduz. Mas a base de cálculo permanece 07/10/1997, então após o primeiro
ciclo o fator calculado **não coincide** com o que os bancos usam, que redefinem a
data-base no reinício (22/02/2025 = fator 1000).

Para vencimentos correntes (2026) o `$dias` desde 1997 já passa de 10.000, então
o ramo de reinício está ativo hoje. O fator gerado precisa ser conferido contra um
boleto real antes do go-live — o que o `BoletoPdfService` já sinaliza com o aviso
de verificação humana obrigatória (`:20-24`).

Classificado BAIXA porque o próprio código pede a verificação física, que é a
única forma de fechar isso.

---

### A-7.13 (BAIXA) — `desagrupar` cancela o agrupador mesmo com parcela baixada

**Critério:** C4.

`FinanceiroService::desagrupar()` (`:172-181`) faz
`$agrupador->update(['cancelado' => true])` **direto**, sem passar por
`cancelar()` — que é o método que valida *"Título com parcela baixada não pode ser
cancelado"* (`:130-137`).

Desagrupar um título já parcialmente pago cancela o agrupador (perdendo o registro
do que foi recebido) e reativa os originais como `NORMAL`, que voltam a aparecer
como devidos. O cliente pagou e volta a dever.

---

## Padrões que este volume confirma

**1. `withoutTenant()` como ferramenta de conveniência.** Aparece 9 vezes no
volume. Em 3 delas é correto e comentado (webhook PIX, `empresaIdDoTxid`,
`expirarVencidas` — todos contextos genuinamente sem tenant). Nas outras 6 é
usado para fazer a query funcionar, sem nenhuma verificação substituta. Não há
convenção que diga quando é legítimo, então cada autor decidiu — e a metade que
decidiu errado abriu porta em cima de dinheiro.

**2. A proteção que existe no chamador em vez da porta.** `MaloteService` valida
a conta; `CaixaService` não. `PixService` valida `baixado = false`;
`BoletoService` não. `RegraExtratoService` corrige o `iconv`; `CnabHelper` não.
Em cada par, um autor pensou no problema e o outro não — e o sistema não tem onde
registrar o que foi aprendido, então o aprendizado não se propaga.

**3. Infraestrutura correta construída e não usada.** O `FITID` é extraído e
ignorado. O `boleto->id` é gravado em posição fixa na remessa "p/ casamento" e o
casamento usa `str_contains`. O `IntegracaoTenant` existe e a CONSISA não o usa.
Quarta variante do padrão dominante da auditoria.

**4. Modelo antigo mantido ao lado do novo.** Gás do Povo tem os dois. É o mesmo
que o Volume 4 encontrou em `municipios_ibge` × `cidades` e `veiculos` ×
`monitora_veiculos`: o certo foi construído, o errado não foi removido, e nada
diz qual vale.

---

## Para o plano (Volume 15)

Decisões que dependem do dono:

- **D-7.1** — Malote: o acerto físico ainda acontece? O próprio
  `MaloteService` (`:36-42`) marca a pergunta como aberta e diz que remover é
  apagar service + controller + 4 rotas. Segue aberta.
- **D-7.2** — Gás do Povo: qual dos dois modelos vale. Se for "modo de venda"
  (o que a auditoria do legado sustenta), `gasdopovo_beneficios` e os dois
  métodos do `PagamentoService` saem, e os dados existentes precisam de
  conversão.
- **D-7.3** — Contabilidade externa: CONSISA por revenda (cada uma com sua URL
  em `IntegracaoTenant`) ou a plataforma centraliza? Muda quem contrata o
  escritório contábil.

Itens de código para o plano consolidado:

- **`empresa_id` obrigatório em `movimentar()`** — parâmetro explícito, com
  verificação de que a conta pertence a ele. Resolve A-7.1 na porta, não em oito
  chamadores.
- **`BaixaService` como dono único de `financeiroparcelas.baixado`**, com
  idempotência e registro da origem do pagamento. Resolve A-7.4.
- **Casamento de retorno CNAB por posição fixa** (o campo "uso da empresa" que os
  drivers já gravam) + escopo por empresa da remessa. Resolve A-7.2.
- **Sequência de nosso-número por empresa+banco+carteira**, no padrão que
  `RemessaCnab` já usa. Resolve A-7.3.
- **`FITID` como chave de conciliação** + persistência do que já foi conciliado.
  Resolve A-7.6.
- **`CnabHelper::semAcento` com tabela explícita** (copiar de
  `RegraExtratoService::normalizar`). Resolve A-7.7.

---

**Volume 7 fechado.** 29/29 arquivos, 3.038/3.038 linhas. 13 achados
(5 alta, 5 média, 3 baixa).
