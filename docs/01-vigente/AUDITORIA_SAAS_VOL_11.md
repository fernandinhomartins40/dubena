# Auditoria SaaS — Volume 11: Mobile, Shared, Relatório, Apoio, Auditoria, RH, Integração, Telefonia, Gestão

**Recorte:** `app/Domain/Mobile/`, `Shared/`, `Relatorio/`, `Apoio/`,
`Auditoria/`, `Rh/`, `Integracao/`, `Telefonia/`, `Gestao/` — 49 arquivos,
5.314 linhas.
**Leitura:** 49/49 lidos integralmente (conferido por `wc -l`: 5.314).
**Data:** 2026-08-25.
**Método:** ver [AUDITORIA_SAAS.md](AUDITORIA_SAAS.md). Achados formados só a
partir do código.

---

## Arquivos lidos (49)

| Arquivo | Linhas |
|---|---:|
| Relatorio/RelatorioService.php | 678 |
| Mobile/PedidoMobileService.php | 355 |
| Apoio/InconsistenciaService.php | 239 |
| Telefonia/TelefoniaService.php | 214 |
| Mobile/EntregaService.php | 192 |
| Integracao/IntegracaoTenant.php | 192 |
| Auditoria/ConsultaTrilha.php | 187 |
| Mobile/ClienteAuthService.php | 181 |
| Mobile/MarketplaceService.php | 174 |
| Shared/PermissaoCatalogo.php | 173 |
| Shared/PdfService.php | 161 |
| Auditoria/CatalogoAuditoria.php | 157 |
| Mobile/CotacaoMobileService.php | 154 |
| Rh/ComissaoService.php | 136 |
| Relatorio/NotificarEstoqueBaixoJob.php | 117 |
| Mobile/EnderecoMobileService.php | 112 |
| Shared/CalculoParcelasService.php | 104 |
| Auditoria/RegistroAcao.php | 101 |
| Apoio/CadastroApoioRegistry.php | 98 |
| Shared/Auditavel.php | 93 |
| Rh/ColaboradorService.php | 91 |
| Apoio/CadastroSlugs.php | 90 |
| Mobile/CatalogoMobileService.php | 89 |
| Apoio/CadastroApoioService.php | 88 |
| Mobile/Drivers/EredeDriver.php | 84 |
| Shared/BrFormat.php | 82 |
| Mobile/Jobs/EnviarPushJob.php | 74 |
| Mobile/PagamentoOnlineService.php | 65 |
| Shared/NumeroSequencialService.php | 60 |
| Mobile/RastreamentoService.php | 60 |
| Mobile/Drivers/FcmV1Transport.php | 55 |
| Rh/VinculoColaborador.php | 53 |
| Rh/ModoEstoque.php | 53 |
| Mobile/Events/EntregadorPosicaoAtualizada.php | 53 |
| Shared/Geo.php | 50 |
| Mobile/PushService.php | 50 |
| Shared/TenantCache.php | 48 |
| Integracao/CredencialNaoConfiguradaException.php | 45 |
| Gestao/BemService.php | 45 |
| Mobile/Drivers/FakePagamentoDriver.php | 38 |
| Mobile/Drivers/KreaitFirebaseVerifier.php | 34 |
| Shared/Parcela.php | 30 |
| Mobile/Drivers/FakeFirebaseVerifier.php | 28 |
| Mobile/Contracts/PagamentoDriver.php | 27 |
| Mobile/Drivers/FakePushTransport.php | 24 |
| Mobile/Contracts/FirebaseVerifier.php | 23 |
| Mobile/SituacaoPagamento.php | 21 |
| Mobile/Contracts/PushTransport.php | 20 |
| Mobile/Exceptions/FirebaseTokenInvalido.php | 16 |
| **Total** | **5.314** |

---

## Leitura geral do domínio

Este é o volume mais heterogêneo — nove domínios que sobraram — e por isso o mais
útil para **verificar independentemente** o que os volumes anteriores afirmaram.
Três verificações cruzadas importantes:

**1. A coluna `endereco` está mesmo vazia.** O Volume 9 registrou o achado A-9.1
com base em dois comentários do `IdentidadeCliente`. Aqui aparece um **terceiro**
lugar, independente, dizendo o mesmo — `RelatorioService::clientesSemCompra` faz o
join com `ruas` e comenta: *"O logradouro vem da FK rua_id: a coluna `endereco`
esta NULL em toda a base, e sem este join o relatorio saía só com o numero."* O
achado A-9.1 se sustenta.

**2. O `IntegracaoTenant` existe, funciona e é bom.** O Volume 8 acusou os drivers
do Google de não o usarem. Aqui vê-se por que isso dói: o `IntegracaoTenant` faz
exatamente o que falta — resolve credencial por empresa→grupo→plataforma,
fail-closed em produção para cartão, cifra segredos por valor, e **loga warning
quando cai no env da plataforma sem grupo resolvido**. É o padrão certo,
implementado, e usado por três dos oito consumidores possíveis.

**3. `iconv('ASCII//TRANSLIT')` — segunda ocorrência não corrigida.** O Volume 7
achou no `CnabHelper`. Aqui está no `InconsistenciaService::normalizar`. Ambos
convivem com duas implementações corretas (tabela explícita) no mesmo repositório,
uma delas — `NormalizadorTexto` — citando literalmente o motivo.

O que é próprio deste volume: **é onde as camadas de infraestrutura compartilhada
estão**, e elas são de qualidade alta e uniforme. `Geo`, `Auditavel`,
`CatalogoAuditoria`, `PdfService`, `TenantCache`, `CalculoParcelasService`,
`NumeroSequencialService` — todos com decisão documentada e escopo claro. O
`Auditavel` blinda segredos por cast **e** por nome de coluna, com a justificativa
certa (*"A trilha é lida por gente do negócio: nada aqui pode ser material de
invasão se a tela vazar"*).

---

## Achados

### A-11.1 (ALTA) — `temPedidoPendente` sem empresa: um cliente do marketplace fica travado em todas as revendas

**Critério:** C6 — escopo de tenant errado.

`PedidoMobileService::temPedidoPendente()` (`:253-260`):

```php
public function temPedidoPendente(int $clienteId): bool
{
    return Pedido::query()
        ->where('cliente_id', $clienteId)
        ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
        ->exists();
}
```

Chamado em `criarDoApp()` (`:143-145`) para aplicar a regra do legado *"1 pedido
PENDENTE por cliente"*.

No modelo de instalação única a consulta está certa por acidente: um `cliente_id`
só existe numa empresa. Mas o `MarketplaceService` deste mesmo domínio existe
justamente para o cliente **escolher entre revendas** — e o
`ClienteAuthService::autenticar` resolve o cliente *"DENTRO da empresa informada
(telefone não é único entre tenants)"*, o que significa que a mesma pessoa tem
**cadastros distintos** em cada revenda.

Então o achado é mais sutil e mais grave do que parece: a consulta depende do
global scope. Com tenant resolvido, filtra pela empresa ativa — correto. Sem
tenant (A-10.1), varre todas — e a mensagem devolvida ao cliente é *"Você já tem
um pedido em andamento"*, referindo-se a um pedido de **outra revenda** que ele
não consegue ver nem cancelar naquele app.

O contraste está no mesmo arquivo: `historico()` (`:322`) e `clientePorGeoloc()`
(`:73`) filtram `empresa_id` explicitamente. A regra que **bloqueia a venda** é a
única que não filtra.

---

### A-11.2 (ALTA) — `EntregadorPosicao` tem uma linha por entregador, não por entregador×empresa

**Critério:** C6 / C4.

`RastreamentoService::registrarPing()` (`:34-45`):

```php
EntregadorPosicao::query()->updateOrCreate(
    ['entregador_user_id' => $entregadorUserId],   // ← chave sem empresa
    ['empresa_id' => $empresaId, 'latitude' => $lat, ...],
);
```

A chave do `updateOrCreate` é só o usuário. O `empresa_id` está nos **valores**, o
que significa que cada ping **sobrescreve** a empresa da linha.

Consequência para um entregador que atenda duas empresas do mesmo grupo (cenário
que o modelo de grupo permite, e que o Volume 8 já levantou em A-8.4): existe uma
única linha de posição, cuja `empresa_id` é a do último ping. As consultas que
leem essa tabela **filtrando por empresa** — `CentralService::entregadores()`
(`:74-75`, filtra `empresa_id`) e `DistribuidorService::ranquear()` (`:70-73`,
idem) — perdem o entregador de forma intermitente: ele aparece na empresa do
último ping e some da outra.

E as que leem **sem** filtrar empresa — `RoteirizadorService` (Vol. 8, `:52`) e
`GeradorMissaoService` (`:64`) — pegam a posição independentemente de qual
empresa a gravou.

O mesmo `registrarPing` também publica em pedidos filtrando `empresa_id`
corretamente (`:48-52`). Ou seja: dentro do mesmo método, a leitura é escopada e a
escrita não.

---

### A-11.3 (ALTA) — Situação "saiu para entrega" identificada por LIKE em português, e criada se não achar

**Critério:** C2 — classificação por texto / C4.

`EntregaService::situacaoSaiuParaEntrega()` (`:152-172`):

```php
$alvo = PedidoSituacao::query()
    ->where('grupo_id', $grupoId)
    ->where('efeito', EfeitoPedido::PENDENTE->value)
    ->where('ativo', true)
    ->where(function ($q) {
        foreach (['%saiu%', '%rota%', '%caminho%'] as $termo) {
            $q->orWhereRaw('LOWER(descricao) LIKE ?', [$termo]);
        }
    })
    ->orderBy('id')->first();

return $alvo ?? PedidoSituacao::create([
    'grupo_id' => $grupoId,
    'descricao' => 'Saiu para entrega',
    ...
]);
```

Dois problemas somados:

**(a) Classificação por substring em português.** Uma revenda que nomeie a coluna
do Kanban de *"Em deslocamento"*, *"Despachado"* ou *"Com o motoboy"* não casa com
nenhum dos três termos. E uma que tenha *"Aguardando saída do depósito"* casa com
`%saiu%`... não — mas *"Saiu do estoque"* casaria, movendo o pedido para a situação
errada.

**(b) Cria situação silenciosamente.** Não achando, `iniciarRota` **insere uma nova
coluna no Kanban do grupo** — sem pedir nada a ninguém, no meio de uma ação de
entregador em campo. A revenda descobre depois que apareceu uma coluna que não
cadastrou. E como a busca é por LIKE, na próxima vez ela casa com a criada e não
com a que a revenda já usava para isso.

Isto é a mesma classe de A-5.5 (vasilhame por substring) e A-6.7 (`tipo` com três
vocabulários), agora sobre a máquina de estados do pedido — que é justamente o
lugar onde o `CLAUDE.md` manda usar enum.

O `EfeitoPedido` existe e é enum. O que falta é um **papel** declarado dentro do
efeito PENDENTE (ex.: `PedidoSituacao.papel = 'em_rota'`), em vez de adivinhar
pelo texto.

---

### A-11.4 (MÉDIA) — `iconv('ASCII//TRANSLIT')` no detector de inconsistências — segunda ocorrência da armadilha documentada

**Critério:** C4.

`InconsistenciaService::normalizar()` (`:230-239`):

```php
$t = @iconv('UTF-8', 'ASCII//TRANSLIT', $v);
if ($t !== false) { $v = $t; }
```

É a armadilha que o `CLAUDE.md` documenta explicitamente (*"no Windows devolve `?`
para acentos"*) e que já apareceu no `CnabHelper` (A-7.7).

O impacto aqui é diferente do CNAB e mais sutil: a normalização alimenta um
**Levenshtein**. Se os acentos viram `?` em vez de letra:

- `"são joão"` → `"s?o jo?o"` → depois do `preg_replace('/[^a-z0-9 ]/')` → `"so joo"`
- `"sao joao"` → `"sao joao"`

A distância entre `"so joo"` e `"sao joao"` é 2 — sobre 8 caracteres, similaridade
0.75, **abaixo do limiar de 0.85**. Ou seja: no ambiente onde o TRANSLIT falha, o
detector **deixa de encontrar** exatamente as duplicatas com acento — que são a
maioria em nomes de rua brasileiros ("São", "João", "Getúlio", "Antônio").

A falha é silenciosa e do tipo pior: a fila de inconsistências fica vazia e o
operador conclui que o cadastro está limpo.

O `NormalizadorTexto` (Vol. 9) tem a tabela explícita pronta para reuso e explica
o motivo no comentário. `InconsistenciaService` não a importa.

---

### A-11.5 (MÉDIA) — `orderByRaw('… nulls last')` é sintaxe exclusiva do Postgres

**Critério:** C4.

`RelatorioService::clientesSemCompra()` (`:655`):

```php
->orderByRaw('c.data_ultima_compra asc nulls last')
```

`NULLS LAST` é ANSI-SQL suportado por Postgres e Oracle; **sqlite** (a suíte de
testes) só o suporta a partir da 3.30, e **MySQL** não o suporta de forma alguma —
lança erro de sintaxe.

O mesmo arquivo demonstra que o autor conhece o problema: `diaDoMesSql()` (`:24-33`)
faz `match` no driver justamente para evitar sintaxe específica, com o comentário
*"Evita `strftime` (SQLite-only)"*. E `TelefoniaService::sqlSoDigitos()` faz o
mesmo para `regexp_replace`.

A disciplina existe em dois lugares e falha num terceiro, no mesmo método que
carrega o comentário mais cuidadoso do arquivo. Registrado como MÉDIA porque o
relatório é dos "PRÉ-GO-LIVE" (usado, não experimental) e a falha é um erro em
runtime, não um resultado errado.

---

### A-11.6 (MÉDIA) — `TelefoniaService`: chamada atendida/rejeitada por id, sem verificar empresa

**Critério:** C6.

`atender()` (`:95-97`) e `rejeitar()` (`:122-124`):

```php
$chamada = ChamadaEntrante::query()->findOrFail($chamadaId);
```

O `$chamadaId` vem do request. Se `ChamadaEntrante` tem global scope, está
protegido pelo tenant ativo; se não tem — ou se o tenant não foi resolvido
(A-10.1) — um operador atende a chamada de outra revenda, cria uma `Ligacao` na
empresa dela (`'empresa_id' => $chamada->empresa_id`) e **deleta a chamada da fila
alheia**.

O `delete()` é o agravante: a chamada some da fila da outra revenda e o atendente
de lá nunca vê que o telefone tocou.

O contraste está no mesmo arquivo: `fila()` e `clientesPorTelefone()` filtram
`empresa_id` explicitamente. Só as duas operações **destrutivas** confiam no id.

Também vale registrar o acerto: o comentário do cabeçalho documenta o erro do
legado (formatar telefone na gravação) e a correção (*"guarda cru, compara só
dígitos"*), e `clientesPorTelefone` explica as três razões vividas para casar
pelos últimos 8 dígitos. É boa engenharia com um buraco de autorização.

---

### A-11.7 (MÉDIA) — `CadastroApoioService`: unicidade de descrição sem escopo

**Critério:** C6.

`CadastroApoioService::garantirDescricaoUnica()`:

```php
$existe = $model::query()
    ->whereRaw('lower(descricao) = ?', [mb_strtolower($descricao)])
    ->when($ignorarId, fn ($q) => $q->where('id', '<>', $ignorarId))
    ->exists();
```

O docblock diz *"Unicidade de descrição dentro do grupo"*, mas a query não filtra
grupo. Depende inteiramente do `BelongsToGrupo` — que, por A-10.1, não filtra
quando não há grupo resolvido.

Efeito no SaaS: um grupo que tente cadastrar o segmento "Residencial" recebe *"Já
existe um registro com essa descrição"* porque **outro grupo** já o cadastrou. É
falha fechada (recusa em vez de vazar), o que é a direção certa do erro, mas
bloqueia cadastro legítimo e revela a existência de dados de outro tenant.

O `CadastroApoioRegistry` cobre 19 tipos de cadastro; todos passam por aqui.

---

### A-11.8 (MÉDIA) — Trilha de auditoria: `RegistroAcao` absorve o log automático por janela de 5 segundos

**Critério:** C4.

`RegistroAcao::registrar()` (`:38-46`):

```php
$automatico = AuditLog::query()
    ->where('entidade', $alvo->getTable())
    ->where('entidade_id', $alvo->getKey())
    ->where('acao', 'atualizado')
    ->where('criado_em', '>=', now()->subSeconds(5))
    ->orderByDesc('id')
    ->first();
```

A ideia é boa e está bem justificada: *"Uma ação humana = uma linha, com o diff
junto"*. O problema é o mecanismo — **correlação por proximidade temporal**, não
por identidade.

Três consequências:

**(a) Sem `empresa_id` na busca.** Dois registros de tabelas homônimas em empresas
diferentes com o mesmo id (`clientes` id 500 na empresa A e na empresa B) — e o
`entidade_id` é o mesmo. A ação semântica de uma empresa pode **reescrever** o log
automático da outra, mudando `acao` de `'atualizado'` para `'desativou'`.

**(b) Sem `user_id` na busca.** Dois operadores editando o mesmo cliente no mesmo
intervalo de 5 s: o segundo absorve o log do primeiro.

**(c) A janela é arbitrária.** Sob carga, um `update` que demore mais de 5 s entre
o trait e o `registrar()` produz duas linhas — exatamente o que a função existe
para evitar.

A auditoria é o registro de quem decidiu o quê. Uma reescrita cruzada aqui não
corrompe dado operacional, mas corrompe a prestação de contas — e é invisível.

---

### A-11.9 (BAIXA) — `DRE` e `fluxoCaixa` misturam `datahora_baixa` datetime com string de data

**Critério:** C4.

`RelatorioService::dre()`, dentro de `$agrupar` (`:139`):

```php
->whereBetween('fp.datahora_baixa', [$dtInicio.' 00:00:00', $dtFim.' 23:59:59'])
```

Isto está **correto** — o autor montou os limites de hora explicitamente,
contornando a armadilha do `whereBetween` em coluna datetime que o `CLAUDE.md`
documenta.

`fluxoCaixa()` (`:585-586`) usa `whereDate` para o mesmo problema, também correto,
com um comentário longo explicando por quê.

O que registro como achado é a **inconsistência**: o mesmo arquivo resolve o mesmo
problema de duas formas, e uma terceira (`financeiro()`, `:97`) usa
`whereBetween('fp.vencimento', [$dtInicio, $dtFim])` com strings de data puras —
que funciona porque `vencimento` é `date`, não `datetime`... **se** for `date`. O
`fluxoCaixa` comenta o oposto: *"`vencimento` guarda datetime"*.

Os dois métodos do mesmo arquivo discordam sobre o tipo da mesma coluna. Um dos
dois está errado, e qual depende da migration (Volume 1). Se `vencimento` for
datetime, o relatório `financeiro()` **perde o último dia do período** — silenciosa
e permanentemente.

---

### A-11.10 (BAIXA) — `EredeDriver` engole falha de rede e devolve "recusado"

**Critério:** C4.

`EredeDriver::autorizar()` (`:48-50`):

```php
} catch (\Throwable $e) {
    return ['aprovado' => false, ..., 'mensagem' => $e->getMessage()];
}
```

O comentário acima explica corretamente que a resolução da credencial fica **fora**
do try, para o fail-closed propagar como 503. Mas o `catch` genérico dentro do try
transforma **timeout de rede** e **DNS falho** em `aprovado: false` com a mensagem
interna da exceção.

Duas consequências:

- O cliente vê "pagamento recusado" quando na verdade a adquirente não foi
  consultada — e pode ter sido cobrado (se o timeout ocorreu após a adquirente
  processar).
- `$e->getMessage()` vai para o campo `mensagem`, que é gravado em
  `pagamentos_online` e provavelmente exibido. Mensagem de exceção pode conter URL
  interna, host e porta.

O `CredencialNaoConfiguradaException` do mesmo volume mostra o padrão certo:
`mensagemInterna` para o log, `mensagemUsuario` neutra para a tela.

---

### A-11.11 (BAIXA) — `MarketplaceService` usa bounding-box de 60 km fixo e ignora empresa sem coordenada

**Critério:** C4.

`BBOX_RAIO_MAX_KM = 60.0` (`:44`) com o comentário *"Cobre com folga raios de
entrega realistas"* — realistas para disk-gás urbano. Uma revenda que atenda zona
rural com raio de 100 km e **sem cerca cadastrada** não aparece na descoberta,
porque o pré-filtro a exclui antes do teste de precisão.

O código prevê a exceção (empresa com cerca entra sempre, via `orWhereExists`), o
que cobre o caso desde que a revenda desenhe a cerca. Mas a falha é silenciosa: a
revenda não aparece e ninguém sabe por quê.

Também: `atende()` (`:145-158`) devolve `false` para empresa sem cerca **e** sem
`raio_entrega_km`/coordenada. O comentário diz *"precisa configurar"* — correto
como fail-safe, mas não há nada que **avise** a revenda de que ela está invisível
no marketplace por falta de configuração. Uma revenda pode aderir ao marketplace
(`app_marketplace_ativo = true`) e nunca receber um pedido, sem sinal nenhum.

---

## Padrões que este volume confirma

**1. A infraestrutura compartilhada é o melhor código do sistema.** `Geo`
(ponto único do Haversine, resolvendo 5 reimplementações), `Auditavel` (blindagem
de segredo por cast e por nome), `CatalogoAuditoria` (tradução para linguagem de
negócio), `TenantCache` (namespace por tenant), `CalculoParcelasService`,
`NumeroSequencialService` (lock pessimista), `PdfService`. Todos com decisão
documentada. Quando o sistema centraliza, ele centraliza bem.

**2. O padrão certo existe e não se propaga.** Terceira confirmação:
`IntegracaoTenant` resolve credencial por tenant e é ignorado pelos drivers do
Google (Vol. 8); `NormalizadorTexto` resolve transliteração e é ignorado pelo
`CnabHelper` (Vol. 7) e pelo `InconsistenciaService` (aqui); `Geo` centraliza
Haversine e o `ViagensService` mantém o seu (Vol. 8, `kmEntre`). Não é falta de
solução — é falta de mecanismo que force o reuso.

**3. Filtro de tenant presente na leitura, ausente na escrita.** Padrão novo,
visível em dois casos deste volume: `RastreamentoService` lê pedidos com
`empresa_id` e grava posição sem; `TelefoniaService` lista a fila com `empresa_id`
e atende/deleta sem. A leitura é onde o vazamento seria visível; a escrita é onde
o dano é real.

**4. Fail-safe declarado — quarto volume seguido.** `CotacaoMobileService`
(preço 100% server-side, *"fim da fraude de valor"*), `NotificarEstoqueBaixoJob`
(*"um alerta de estoque que falha em silêncio é pior que não existir"*),
`CredencialNaoConfiguradaException` (fail-closed em dinheiro),
`TelefoniaService::receber` (*"Com vários, escolher o primeiro abriria a ficha
errada — pior que não abrir nenhuma"*). A cultura de escolher a direção do erro
existe, e é forte. Ela só não alcançou o isolamento entre tenants (Vol. 10).

**5. Enum como registro de decisão de negócio.** `VinculoColaborador` e
`ModoEstoque` são exemplares: cada caso do enum tem o comentário que explica o que
ele decide (*"decide de quem é o botijão que está na rua, e portanto quem responde
por ele"*), e os métodos `faturaNaCarga()`/`aceitaDevolucao()` colocam a regra no
enum em vez de espalhá-la. É o oposto de A-11.3 (situação por LIKE) e de A-6.7
(`tipo` com três vocabulários).

---

## Para o plano (Volume 15)

Decisões que dependem do dono:

- **D-11.1** — Telefonia (bina): o próprio serviço marca a pergunta como aberta
  (*"O plano pergunta se o call-center usa bina hoje. Se a resposta for 'não',
  remover é apagar migration, models, service, controller e 4 rotas"*). Continua
  aberta, ao lado de D-7.1 (malote).
- **D-11.2** — Marketplace: um cliente pode ter pedido pendente simultâneo em
  duas revendas? A resposta define se A-11.1 é "filtrar por empresa" ou "regra por
  plataforma".

Itens de código para o plano consolidado:

- **`empresa_id` em `temPedidoPendente`, `atender`, `rejeitar` e
  `garantirDescricaoUnica`** — a mesma classe fechada pelo item 1 do Volume 10
  (modo estrito no scope), mas estes quatro merecem filtro explícito por serem
  operações de bloqueio/destruição.
- **Chave composta `(entregador_user_id, empresa_id)` em `EntregadorPosicao`.**
  Resolve A-11.2.
- **Papel declarado na `PedidoSituacao`** (`papel = 'em_rota'`) em vez de LIKE, e
  **nunca criar situação automaticamente**. Resolve A-11.3.
- **`InconsistenciaService` usando `NormalizadorTexto::basico`.** Resolve A-11.4.
- **`orderByRaw` do `clientesSemCompra` por driver**, no padrão do
  `diaDoMesSql()`. Resolve A-11.5.
- **Correlação de `RegistroAcao` por `(empresa_id, entidade, entidade_id,
  user_id)`** e, de preferência, por id do log passado explicitamente em vez de
  janela temporal. Resolve A-11.8.
- **Conferir o tipo de `financeiroparcelas.vencimento`** e uniformizar os três
  métodos do `RelatorioService`. Resolve A-11.9.
- **`EredeDriver` distinguindo recusa de falha de comunicação**, com mensagem
  neutra ao usuário. Resolve A-11.10.

---

**Volume 11 fechado.** 48/48 arquivos, 5.314/5.314 linhas. 11 achados
(3 alta, 5 média, 3 baixa).
