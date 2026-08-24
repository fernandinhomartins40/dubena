# Apps de campo e Central de Vendas — auditoria e plano

Documento único sobre os dois aplicativos legados (`legado-nfweb/`,
`legado-movelapp/`) revelados em 2026-08-20, seu acoplamento ao ecossistema Gás
em Casa, e a **Central de Vendas** como módulo do `erp-novo`.

**Fonte: código.** Todo comportamento descrito tem arquivo e linha. Onde a
leitura do código contrariou o levantamento inicial, a correção está registrada.

> Substitui e consolida quatro documentos anteriores (análise, auditoria, regras
> de negócio, contrato de comunicação), que divergiram entre si — um deles ainda
> afirmava que o MovelApp opera offline, o que a leitura do código desmentiu.

**Sistemas lidos:** `ctrl-web/` (legado), `legado-nfweb/`, `legado-movelapp/`,
`erp-novo/`, `app-entregador/`, `app-gas-em-casa/`.

---

## Sumário

**Parte I — O que existe**
1. Os dois apps e o modelo de negócio
2. Regras de negócio extraídas do código
3. Contrato de comunicação com o `ctrl-web`
4. O que o `erp-novo` já oferece

**Parte II — O que fazer**
5. Arquitetura alvo
6. Fases (F0–F9)
7. O que NÃO fazer
8. Pendências, riscos e limites

---

# PARTE I — O QUE EXISTE

## 1. Os dois apps e o modelo de negócio

O cliente descreve **dois tipos de entregador** — funcionário CLT e franqueado
sem vínculo (comissão/repasse) — e **vendedores industriais**, que vendem para
empresa e indústria, emitem nota e dão desconto.

E uma regra decisiva: **o franqueado não fecha o pedido.** Quem cria, aprova
desconto e fatura é a central de vendas (o antigo call center).

### 1.1 Correção de premissa

O levantamento partiu de "MovelApp = app do franqueado". **O código não
sustenta**: nenhum dos dois apps conhece vínculo empregatício, e é o MovelApp —
não o NFWEB — que tem perfil de *entrega*.

Isso **não invalida** a direção de unificar num app com níveis de acesso. Ao
contrário: é o que a torna viável, porque o vínculo já é decidido no backend.

### 1.2 Identidade dos dois

| | MovelApp | NFWEB |
|---|---|---|
| Origem | SVN `android/MovelApp` | `nfweb/reactapp.git` |
| Pilha | Android Java nativo, 53 classes, v1.24 | React Native 0.61.5 |
| Papel | rota / entrega | venda consultiva |
| Última alteração | **12/11/2025** (flavio) | 17/07/2025 |
| Backend | `ctrl2/public/api/` | `NfwebController.php` (1773 linhas, 18 rotas) |
| Servidor | `gasemcasa.com.br` | `adm.gasemcasa.com.br` |
| `targetSdk` | **28** — abaixo do mínimo da Play Store | — |

O nome "NFWEB" engana: **não é sistema web de nota fiscal**. É o app do vendedor
que visita o cliente, cadastra, tira pedido e imprime.

### 1.3 Capacidades, lado a lado

| | MovelApp | NFWEB | app-entregador (novo) |
|---|---|---|---|
| Recebe pedido pronto | sim (`getPedidosPendentes`) | não | sim (`entregador/pedidos`) |
| Cria pedido em campo | sim (`exportPedidoTask`) | sim (`savePedido`) | sim (`missao/venda`) |
| **Edita preço** | **sim, livre** | **sim, livre** | **não — servidor decide** |
| Cadastra cliente | não | sim (`saveCliente`) | sim (`missao/clientes`) |
| Pendência financeira do cliente | não | sim (`getParcelasVencidasCliente`) | **não** |
| Imprime no cliente | **código morto** (§2.9) | **sim** — em uso | **não** |
| Opera sem rede | **parcial** (§2.6) | não | **não** |
| Vale-gás | sim (`getValeGas`) | não | sim (`missao/vale-gas`) |

---

## 2. Regras de negócio extraídas do código

### 2.1 Precificação — três caminhos

`MobileRepository::getPreco:596`

    $preco = $produto->precovendaunitario;
    if ($isAppNf) return $preco;              // ← NFWEB: aceita o preço do app
    if ($tipo != "4") return getPrecoEspecial(...);   // tabela do cliente
    return getPrecoConvenio(...);                     // preço de convênio

**(a) NFWEB — preço livre.** O servidor **aceita o valor que o app enviou**, sem
consultar tabela. Combinado com o campo de desconto (`Pedido.js:800`), o vendedor
industrial define preço **e** desconto sem teto.

O MovelApp faz o mesmo na interface: `PedidoFragment2.java:80` —
`txtPreco.setEnabled(pedidoId.equals("-1"))` deixa o preço livre em pedido novo.

Busca por alçada (`desconto_max`, `limite_desconto`, `alcada`) em `ctrl-web/` e
`erp-novo/`: **nada nos dois**. Hoje qualquer vendedor ou franqueado zera a
margem sem que ninguém aprove. **Não é regra a portar — é controle que nunca
existiu.**

**(b) Preço especial por cliente** (`getPrecoEspecial:620`), 4 casos em ordem:

1. `descontopara == 3` → ignora o especial, usa o de tabela;
2. `tipo == 2` → percentual: `preco - (preco * desconto)` (fração, não %);
3. `desconto == 0` → `precoEspecial->preco` (preço fixo negociado);
4. senão → valor: `preco - desconto`.

**(c) Preço de convênio** (`getPrecoConvenio:643`): só desconta a comissão quando
`comissaodestino == 1` — quando ela fica com o cliente conveniado, não com a rede.

### 2.2 Convênio — limite por quantidade

`MobileAppProcessor::checkForConvenio:528`, só quando a condição de pagamento é
`tipo == "4"`:

1. **Janela**: `getProximoVencimento($convenio->diafechamento)`; apura um mês.
2. **Soma o consumo** (linha 547) ignorando `fechadocancelado` e `entregacancelada`.
3. **Produto tem de estar no convênio** (linha 566), senão rejeita (código 102).
4. **Limite em dois níveis** (linha 580): `convenio->limitecompra` (empresa
   conveniada) ou `cliente->conveniolimite` (individual).

**O limite é por QUANTIDADE, não por valor.**

### 2.3 A condição de pagamento governa o fiscal

**Situação inicial do pedido** (`savePedido:382`):

    $this->throwIf($order->pagamento->pedidosituacaoappnf_id == null, ...);
    $order->pedidosituacao_id = $order->pagamento->pedidosituacaoappnf_id;

**NFC-e automática** (linha 375):

    if (!$emiteNF && !$emiteNFC && $order->pagamento->appnfceauto) {
        $emiteNFC = true; $emiteNFCNaoId = true;
    }

Se a condição tem `appnfceauto`, **sai NFC-e não identificada mesmo sem o
vendedor pedir nota**. Fiscalmente relevante.

**NFC-e × NF-e** (linhas 447 e 502):

| | NFC-e | NF-e |
|---|---|---|
| CPF/CNPJ | vazio se não identificada | `cliente->cpf` |
| `indicador_ie` | 9 se não identificada | do cliente |
| Transportador | `null` | `empresaconfig->transportadorappnf_id` |
| Placa | — | do veículo do colaborador no setor |

**Falha na emissão não desfaz o pedido** (linha 490): retorna erro, mas o pedido
fica criado. Pedido sem nota é estado possível.

**Financeiro só com nota** (linha 578) e sob dupla condição
(`PedidoUtil::allwedToCreateFinanceiro:59`): a condição de pagamento gera
financeiro **e** a situação não é fechada/cancelada.

**Boleto** (linha 585): só após a nota; zera juros e multa; conta
`empresaconfig->contaappnf_id`.

### 2.4 As 9 flags de situação do MovelApp

`Situacao.java` / `tbl_situacoes` (`DataBaseHandler.java:69`):
`entrega_finalizada`, `entrega_pendente`, `entrega_cancelada`,
`entrega_transferida`, `em_entrega`, `vale_gas`, `mensagem_enviada`,
`mensagem_lida`, `cartao`.

O ERP novo condensou isso em **3 efeitos** (`EfeitoPedido`:
PENDENTE/CONCLUIDO/CANCELADO). **Cada flag precisa de destino explícito** —
`entrega_transferida` e `em_entrega` não têm equivalente direto.

### 2.5 Exigências condicionais na baixa

`PedidoStatusActivity:86,122` — a situação de destino decide se o entregador
**tem de informar motivo de atraso** ou **autorização de cartão**. Sem isso, a
baixa não passa.

### 2.6 O MovelApp NÃO é totalmente offline

Correção de afirmação anterior. `PedidoStatusActivity:264`:

    if (status.equals("OK")) {
        if (dbHandler.atualizaStatusPedido(pedido)) { ... }
    }

O SQLite local **só é atualizado depois que o servidor confirma**. E a impressão
consulta a nota antes (`NotaFiscalImpressaoActivity:120` — só imprime
`nfsituacao_id == 100`, autorizada pela SEFAZ).

**É offline para leitura da rota carregada; a baixa e a impressão exigem rede.**

### 2.9 Correção: só UM dos apps imprime

Este documento afirmava que "os DOIS imprimem no cliente". **Errado**, e o
cliente corrigiu. Verificando de novo:

- **MovelApp — código morto.** As classes existem (`Bluetooth.java`,
  `ESCP.java`, `NotaFiscalImpressao.java`) e as permissões `BLUETOOTH` estão no
  manifesto, mas as três Activities de impressão
  (`NotaFiscalImpressaoActivity`, `BoletoImpressaoActivity`,
  `ReportImpressaoActivity`) **não estão declaradas no AndroidManifest**. Sem
  declaração, o Android não as abre: são código que sobrou.
- **NFWEB — em uso.** `PedidoConsulta.js:108,132,147` chama de fato
  `PrintDanfe.printDanfe(...)`, `PrintBoleto.printBoleto(...)` e
  `PrintDuplicata.printDuplicata(...)`, e os módulos estão registrados via
  `CustomPrintPackage.java`. O commit de 17/07/2025 ("Nova impressora leopardo
  Pro Max") mexeu exatamente nesses arquivos.

**O erro de método:** eu vi as classes e concluí "o app imprime", sem verificar
se aquele código era alcançável. Existir no repositório não é o mesmo que estar
ligado — e o manifesto é onde isso se confirma no Android.

**Consequência prática:** só os vendedores do industrial (NFWEB) têm impressora
em campo, e o cliente confirmou que alguns ainda usam. O parque continua sendo
pendência, mas de escopo menor do que este documento sugeria.


### 2.7 Outras regras

- **Um veículo por colaborador** (`changeVeiculo:303`): vincular um desvincula
  todos os outros. Importa para a placa da NF-e.
- **Parcelas vencidas são informativas** (`getParcelasVencidasCliente:284`):
  **não há bloqueio de venda para inadimplente** em nenhum dos dois apps. Se a
  Central quiser bloquear, é regra nova.

### 2.8 Buraco de autenticação

`savePedido:329`:

    $logged = $auth->loginFromApi([
        'email'    => env('DEFAULT_USER_SYSTEM'),
        'password' => env('DEFAULT_PASSWORD_SYSTEM')
    ]);

O pedido é criado por um **usuário de sistema fixo**, e o `colaborador_id` vem do
corpo da requisição sem verificação. Quem alcança a API lança pedido **em nome de
qualquer vendedor**. O ERP novo não reproduz isso (usa `$request->user()->id`).

---

## 3. Contrato de comunicação com o `ctrl-web`

Os apps legados falam **só** com o `ctrl-web`. Quatro divergências em relação ao
`erp-novo`:

### 3.1 Envelope — HTTP 200 mesmo em erro

`ctrl-web/app/Helpers/customHelper.php:1648-1685`:

| Helper | Corpo | HTTP | Significado |
|---|---|---|---|
| `responseSuccess` | `{data, msg, status:"OK"}` | 200 | sucesso |
| `responseError` | `{msg, status:"NOK"}` | **200** | erro técnico |
| `responseReject` | `{msg, status:"OPS"}` | **200** | **recusa de regra de negócio** |

O cliente confirma (`legado-nfweb/src/helper/Http.js:164`): `OK` e `OPS` são
resposta válida; só `NOK` rejeita.

O `erp-novo` faz o oposto: `{data}` em sucesso, HTTP **4xx** com `{message}` em
erro.

**O `OPS` é o achado que mais pesa.** É o canal pelo qual o legado devolve recusa
de negócio — *"Não há limite suficiente no convênio"*. **Não existe equivalente
no ERP novo.** Distinguir erro técnico de recusa de negócio é requisito de
contrato, não detalhe de formato.

### 3.2 Autenticação

| | ctrl-web | erp-novo |
|---|---|---|
| Driver | **Passport (OAuth2)** (`config/auth.php:45`) | **Sanctum** |
| Credencial | `app_key` global → `access_token` | e-mail/senha → token pessoal |
| Papel | implícito | **ability no token** (`role:entregador`) |
| Transporte | Bearer **e** token no corpo | apenas header |

O `app_key` (`NfwebController::getToken:85`) autentica **o aplicativo**, não o
usuário. O próprio código registra que a chave anterior vazou no repositório.

### 3.3 Transporte

`Utils.java:196-205` — **tudo POST**, `x-www-form-urlencoded`, inclusive leituras
(`getPedidosPendentes`). O ERP novo usa JSON e verbos REST.

### 3.4 Tenant — o app informa vs. o servidor deriva

**Legado** (`ApiController.php:34,71,796`):

    $empresa = Empresa::find($data['revenda_id']);

Sem verificar se o token pertence àquela empresa — **IDOR de tenant**.

**ERP novo** (`ResolveTenant.php:31`): deriva de `$user->empresa_id`, com RLS
como segunda barreira. O `app-entregador` documenta
(`src/services/auth.service.ts:8`): *"o app nunca envia empresa_id"*.

### 3.5 A rota do entregador vem do APARELHO

`ApiController::getPedidosPendentes:323`:

    $android = Android::where('androidid', $data['androidid'])->where('ativo', true)->first();
    $condicoes[] = ['empresa_id', $android->empresa_id];
    $condicoes[] = ['entregasetor_id', $android->setor_id];
    if (!$config->androidenviatodos) {
        $condicoes[] = ['colaborador_id', $android->colaborador_id];
    }

O dispositivo é registrado (`setAndroidRegistration:31`) com empresa, colaborador
e **setor**. Três regras: só situações com `entregapendente`; filtro por empresa +
setor **do aparelho**; e o flag por empresa `androidenviatodos` decide se o
entregador vê o setor inteiro ou só os pedidos dele. Acima de tudo,
`androidutiliza` habilita ou não o app.

**No ERP novo** a rota sai de usuário + jornada. O modelo "um aparelho = um setor"
**desaparece** — e isso precisa de decisão (§8.1).

### 3.6 Quadro de risco

| Aspecto | ctrl-web | erp-novo | Risco |
|---|---|---|---|
| Envelope sucesso | `{data,msg,status}` | `{data}` | **alto** |
| Envelope erro | HTTP 200 + `NOK` | HTTP 4xx | **alto** |
| Recusa de negócio | `status:"OPS"` | **não existe** | **alto** |
| Tenant | `revenda_id` do app | derivado do token | **alto** se multi-revenda |
| Rota do entregador | por `androidid` → setor | usuário + jornada | **alto** |
| Situação | 9 flags | 3 efeitos | **alto** |
| Auth | Passport + app_key | Sanctum + token | médio |
| Transporte | form-urlencoded | JSON REST | médio |
| `androidenviatodos` | flag por empresa | não existe | médio |

---

## 4. O que o `erp-novo` já oferece

### 4.1 A "central" existente é de logística

`Domain/Logistica/CentralService.php` (226 linhas): `filaDistribuicao`,
`atribuir`, `redistribuir`, `bloquearEntregador`, `priorizar`, `reagendar`.

**Não cria pedido, não fatura, não aprova desconto, não faz pós-venda.** A
Central de Vendas é módulo novo — irmão, não extensão.

Falso amigo: `cargaPorEntregador` é **número de pedidos**, não botijão no veículo.

### 4.2 Autorização — a fundação está pronta

| Peça | Onde |
|---|---|
| RBAC por chave `modulo.acao` | `Concerns/AutorizaPorPermissao.php:28` |
| **ABAC: limite / ownership / horário** | `Domain/Acesso/PolicyEvaluator.php:163-165` |
| `avaliarLimite` genérico (campo × `valor_max`) | `PolicyEvaluator.php:171` |
| Papel por token (Sanctum ability) | `Middleware/AppRole.php:16` |

`AutorizaPorPermissao.php:38` cita o caso de uso literal: *"aprovar pedido até um
limite"*. **O motor de alçada existe**; falta política cadastrada e ponto de
chamada.

### 4.3 Fail-closed em preço — já do lado certo

- `AppMissaoController::venderGas:155` aceita **apenas** `produto_id` e
  `quantidade`;
- `VendaCampoService.php:71`: *"SEM preco_unitario: o PedidoService usa o
  preco_venda do produto"*.

**Hoje o franqueado não daria desconto pelo app novo nem se quisesse.** O caminho
é acrescentar a exceção controlada, **não remover a trava**.

### 4.4 Tempo real e fila

Cinco eventos de broadcast (`PedidoEntrouNaFila`, `PedidoAtribuido`,
`PedidoStatusAtualizado`, `EntregadorPosicaoAtualizada`, `PixConfirmado`) e o
canal `empresa.{empresaId}.central` (`routes/channels.php`) com autorização por
tenant.

`PedidoService::criar:38` já dispara `PedidoEntrouNaFila` + `AtribuirPedidoJob` —
**é o encaixe natural da solicitação do franqueado**.

### 4.5 Comissão serve para franquia sem mudar schema

`colaborador_comissoes` (`2026_06_22_000200_create_rh_tables.php:67`):
`tipo_comissao` 1=percentual / **2=repasse** (*"valor que fica para a empresa"* —
**é o modelo de franquia**), com `percentual_app`/`empresa_valor_app`, regra por
produto/setor/condição, vigência e `comissao_excecoes`.

### 4.6 Faturamento não existe como ação

Não há `faturar()` no `PedidoController`. Faturar é `mudarSituacao` para efeito
CONCLUIDO, e a máquina de estados baixa estoque e gera financeiro — preservando a
idempotência (`estoque_movimentado`).

### 4.7 Outras peças

Pós-venda (`CrmController::posVendaIndex/Salvar`), acerto financeiro
(`MaloteService`), NF-e completa (`Domain/Fiscal/`), missões (`MissaoController`),
ocorrência e conclusão (`AppEntregadorController`).

### 4.8 O que a implementação corrigiu desta auditoria

Escrever o código desmentiu duas afirmações feitas antes:

- **O `OPS` TEM equivalente.** Estava escrito aqui que "não existe equivalente no
  ERP novo". `bootstrap/app.php:74` já mapeia `DomainException` → 422 com o
  comentário *"o domínio dizendo 'isto não pode'"* — exatamente a semântica de
  recusa de negócio. A ponte F0 só precisou **traduzir** 422 → `OPS`, não criar o
  conceito.

- **Existem DOIS dialetos no legado, não um.** `customHelper::responseSuccess`
  devolve a carga em `data` (o NFWEB lê `data`, Http.js:164), mas o
  `ApiController` — que atende o MovelApp — devolve em **`dados`**
  (`ApiController::getVeiculos:169`, e `CadastroImportActivity:207` lê
  `getJSONArray("dados")`). A ponte recebe a chave por parâmetro.

E revelou uma regra que ninguém aplicava:

- **`preco_venda_minimo`** existe no Produto, vem migrado do legado e é exposto na
  API, mas nenhuma regra o verificava. Virou o piso da alçada (F2) — vale
  inclusive para desconto aprovado pela Central, porque é limite do PRODUTO, não
  da pessoa.


---

# PARTE II — O QUE FAZER

> **Estado da implementação (2026-08-21).**
>
> | Fase | Situação |
> |---|---|
> | F0 ponte · F1 vínculo · F2 alçada · F3 Central · F4 solicitação · F6 NF-e | **completas** |
> | F5 remuneração e estoque | **completa** — extrato, remuneração mista (`tipo_comissao=3`) e mercadoria em poder do franqueado (consignação × compra) |
> | F7 offline | **backend + fila no app**; ocorrência/conclusão (multipart) seguem exigindo rede |
> | F8 impressão | **conteúdo do cupom entregue** (`CupomTextoService`); falta só a camada Bluetooth **no NFWEB** — o MovelApp não imprime (§2.9) |
> | F9 desligar legados | não implementável — exige conferência em produção |
>
> 49 testes novos. Correções que a implementação impôs à Parte I estão em §4.8.


## 5. Arquitetura alvo

**O ERP fica administrativo. A Central de Vendas vira o painel operacional que
conversa com os apps. Os apps de campo não decidem dinheiro — solicitam, e a
Central autoriza.**

```
   app-gas-em-casa          app-entregador              (legados a desligar)
   (cliente pede)      (funcionário / franqueado /       MovelApp + NFWEB
         │              industrial — mesmo app,
         │               papéis diferentes)
         │                       │
         └───────────┬───────────┘
                     │  app/v1  (Sanctum + role:*)
              ┌──────▼──────────────────────┐
              │   CENTRAL DE VENDAS         │  ← módulo novo
              │  fila de solicitações       │
              │  aprovação de desconto      │
              │  criar / faturar pedido     │
              │  pós-venda · missões        │
              └──────┬──────────────────────┘
                     │ reusa
       Pedido · Fiscal · Financeiro · Logistica · Crm · Rh
```

### 5.1 Um app, quatro perfis

| Capacidade                        | Funcionário | Franqueado              | Industrial |
|-----------------------------------|-------------|-------------------------|------------|
| Receber rota e entregar           | sim         | sim                     | não        |
| Vender em campo (preço de tabela) | sim         | sim                     | sim        |
| **Solicitar desconto**            | não         | **sim**                 | **sim**    |
| **Fechar o próprio pedido**       | não         | **não — Central fecha** | a definir  |
| Cadastrar cliente                 | não         | sim                     | sim        |
| Emitir NF-e                       | não         | não                     | **sim**    |
| Ver pendência do cliente          | não         | sim                     | sim        |
| Imprimir no cliente               | sim         | sim                     | sim        |
| Remuneração                       | salário     | repasse/comissão        | comissão   |

Três apps para essa tabela é triplicar manutenção — foi o que produziu o estado
atual, com impressão e pedido reimplementados em cada um.

### 5.2 O fluxo do franqueado

```
franqueado no app
      │ POST app/v1/entregador/solicitacoes          ← rota NOVA
      ▼
 SOLICITACAO (cliente, itens, desconto pedido, justificativa)
      │ evento → canal empresa.{id}.central          ← canal JÁ EXISTE
      ▼
 fila da Central (tempo real)
      ├── atendente aprova desconto → PolicyEvaluator (alçada)  ← motor JÁ EXISTE
      ├── acima da alçada → sobe para supervisor
      └── aprovada → PedidoService::criar             ← serviço JÁ EXISTE
                        └─ mudarSituacao(CONCLUIDO) = faturar
```

1. **Solicitação não é pedido.** Criar `Pedido` só na aprovação evita poluir
   estoque e financeiro com rascunho.
2. **Faturar não é método novo** (§4.6).
3. **A fila já tem precedente** (§4.4).

---

## 6. Fases

### F0 — Ponte de compatibilidade

**Vem antes de tudo**: hoje os apps legados só falam com o `ctrl-web`. Sem a
ponte, nenhuma fase chega ao campo.

Grupo de rotas no ERP novo falando o dialeto antigo (§3): envelope
`{data,msg,status}`, HTTP 200 em erro, `OPS` para recusa de negócio,
form-urlencoded, aceita `revenda_id`.

- **Vantagem:** apps legados apontam para o ERP novo **sem republicar em loja** —
  o que importa porque `targetSdk 28` impede publicar o MovelApp hoje.
- **Regra inegociável:** aceita `revenda_id` mas **valida contra o token**, em vez
  de confiar (§3.4). Compatibilidade de formato, não de vulnerabilidade.
- Definir aqui o equivalente ao `OPS` para toda a API.

### F1 — Vínculo e perfis

- Tipo de vínculo em `colaboradores` (`funcionario`/`franqueado`/`industrial`), ou
  tabela própria se o franqueado tiver dados que colaborador não tem (CNPJ,
  contrato, território).
- `role:franqueado` e `role:industrial` no token — `AppRole.php:16` já aceita;
  basta emitir a ability no `AppAuthController` (hoje fixo em `role:entregador`,
  linha 119).

**Cuidado:** franqueado é PJ sem vínculo CLT. Se virar `colaborador`, os
relatórios de RH passam a contá-lo como funcionário — conferir `Domain/Rh` antes.

### F2 — Alçada de desconto

- Política por perfil/produto/segmento usando `PolicyEvaluator` (`limite`, com
  `campo` e `valor_max`).
- Ponto de chamada: `PedidoService::recalcularTotais:185` hoje soma o desconto dos
  itens **sem verificar nada**.
- **Fail-closed**: sem política, desconto zero.
- **Convive com três bases de preço** (§2.1): tabela, preço especial do cliente e
  convênio. O desconto do vendedor incide **depois** — a política precisa saber
  sobre qual base aplica o teto, ou um cliente com preço especial teria desconto
  em cascata.
- Trilha: quem pediu, quem aprovou, quando, justificativa, margem resultante.

### F3 — Central de Vendas (módulo)

- `Domain/Venda/CentralVendasService.php` — irmão do `CentralService`.
- Rotas `central-vendas/*` com permissões novas (`venda.aprovar`, `venda.faturar`,
  `venda.solicitacao.view`).
- Feature `frontend/src/features/central-vendas/`, padrão de `features/central/`.
- Absorver em vez de duplicar: pós-venda, missões, distribuição (§4.7).

### F4 — Solicitação pelo app

**Bloqueada pela pendência §8.1** (app ou WhatsApp?).

Recomendação: **fazer o painel primeiro** — serve aos dois casos (o atendente
digita o que chegou por WhatsApp), e a tela no app vira incremento.

### F5 — Remuneração do franqueado

Reusar `colaborador_comissoes` **sem mudança de schema** (§4.5). Extrato no app;
fechamento junto ao `MaloteService`. **Depende da pendência §8.3.**

### F6 — NF-e em campo (industrial)

Expor `notas/emitir` e `notas/{id}/danfe` ao `app/v1` sob `role:industrial`.
Fail-closed sem certificado. Definir comportamento sem rede.

### F7 — Operação offline

- Fila local no `app-entregador` (hoje não há AsyncStorage).
- **Atenção:** o MovelApp *não* é totalmente offline (§2.6) — copiar o legado é
  menos ambicioso do que parecia; fazer melhor exige decidir a fila de escrita,
  que o legado nunca teve.
- Cache: produtos, preços, clientes da rota, situações, motivos.
- **Idempotência**: id de operação gerado no dispositivo.
- Conflito de preço offline: vale o do momento da venda ou o atual? **Decisão de
  negócio.**

Vender offline com preço defasado e sem alçada é combinação cara — por isso F2
vem antes.

### F8 — Impressão térmica

**Bloqueada pela pendência §8.2.**

- ESC/POS genérico: portar o layout de `ESCP.java`/`NotaFiscalImpressao.java`.
- Manter as "Leopardo Pro Max": exige `NfePrinterLib.jar` e módulo nativo — Expo
  precisa de *development build*.
- Trocar o parque pode sair mais barato que manter integração proprietária.

### F9 — Desligar os legados

Só após F0–F8 em produção **e** conferidos. Enquanto isso os legados seguem
rodando: atendem o cliente hoje, e `targetSdk 28` impede publicação, não uso.

### Ordem

```
F0 (ponte)   ← sem isto nada chega ao campo
  └─► F1 (vínculo/perfis)
        ├─► F2 (alçada) ──► F3 (Central) ──► F4 (solicitação no app) ← §8.1
        ├─► F5 (remuneração)   ← §8.3
        └─► F6 (NF-e industrial)   ← mais barata: Fiscal pronto

F7 (offline) e F8 (impressão) em paralelo — F8 travada por §8.2.
F9 fecha, só após conferência.
```

**Ponto de partida: F0 se a meta é desligar o `ctrl-web`; F2 se a meta é parar a
perda de margem.** São independentes — F0 é transição, F2 é controle de negócio.

---

## 7. O que NÃO fazer

- **Não estender o `CentralService` de logística.** Vendas é outro domínio.
- **Não criar `Pedido` para rascunho de solicitação.** Poluiria estoque e financeiro.
- **Não inventar `faturar()`.** Usar `mudarSituacao` preserva a máquina de estados.
- **Não permitir preço livre no app** para alcançar paridade com o legado. A trava
  atual está certa (§4.3).
- **Não replicar o IDOR de tenant na ponte** (§3.4).
- **Não republicar os apps legados** para falar o dialeto novo — F0 resolve, e o
  MovelApp nem publica hoje.
- **Não modelar franqueado como cliente.** Ele vende em nome da rede.
- **Não portar o app do industrial como app separado.**
- **Não copiar o `NfePrinterLib.jar`** sem confirmar licença de redistribuição.
- **Não reaproveitar o `movelapp.jks`** — versionado no SVN, comprometido.

---

## 8. Pendências, riscos e limites

### 8.1 Decisões do cliente

> **Respondidas em 2026-08-21** (as quatro primeiras). O que segue aberto está
> marcado como PENDENTE.

| Pergunta | Resposta | Efeito no código |
|---|---|---|
| Parque de impressoras | Só o NFWEB imprime; **alguns vendedores ainda usam** | camada Bluetooth segue **PENDENTE** — falta saber os modelos |
| Como o franqueado é remunerado | **Misto** — repasse + percentual, somados | `tipo_comissao = 3` no `ComissaoService` |
| Consignação ou compra | **Os dois**, fixo por franqueado | `colaboradores.modo_estoque` + `CargaFranqueadoService` |
| Teto de desconto e liberação | **Teto por perfil + liberação pela Central** | já entregue: `alcada_descontos` (F2) + aprovação (F3) |

**Ainda pendentes:**



1. **O franqueado solicita pelo app ou por WhatsApp?** (o cliente não recordava) —
   bloqueia F4.
2. **Quantas impressoras e quais modelos?** — bloqueia F8.
3. **Como o franqueado é remunerado** (percentual, repasse, misto)? — bloqueia F5.
4. **Consignação ou compra?** Muda estoque e fiscal.
5. **Teto de desconto por perfil** e quem aprova acima disso.
6. **Multi-revenda no mesmo aparelho?** Se alguma instalação troca de
   `revenda_id`, derivar tenant do token não basta (§3.4).
7. **Aparelho fixo por setor?** A operação depende de `androidid → setor_id`
   (celular no veículo trocando de motorista), ou cada um usa o próprio? (§3.5)
8. **`androidenviatodos`** vira permissão por perfil ou continua config de empresa?

### 8.2 Lacunas técnicas, por dificuldade

1. **Impressão térmica** — inexistente no ecossistema novo.
2. **Operação offline** — o `app-entregador` não tem AsyncStorage nem fila; o
   "offline-first" de `src/helpers/realtime.ts:15` é só queda para polling.
3. **Alçada de desconto** — motor existe, falta política e ponto de chamada.
4. **Fluxo solicitação → aprovação** — não existe. Coração da Central.
5. **Vínculo franqueado** — busca por "franqueado" no `erp-novo`: zero ocorrências.
6. ~~**Estoque em poder do franqueado**~~ — **resolvido**: `colaboradores.modo_estoque`
   (consignação | compra) + `CargaFranqueadoService`, que reusa
   `EstoqueService::transferir` dando um setor próprio a cada franqueado.
7. **Pendência financeira do cliente no app** — sem equivalente.
8. **DANFE/boleto no app** — existem, só em rota admin. O item mais barato.

### 8.3 Regras a conferir antes de desligar cada app

| Regra do legado | Situação no ERP novo |
|---|---|
| Preço livre no NFWEB (`isAppNf`) | **não existe** — manter assim, adicionar alçada |
| Preço especial por cliente (4 casos) | **conferir** se `preco_venda` cobre |
| Convênio por quantidade | existe `convenios` — **conferir** a janela |
| Situação vinda da condição de pagamento | modelo diferente — **conferir** |
| NFC-e automática (`appnfceauto`) | **não localizado** |
| Financeiro por condição + situação | `EfeitoPedido` decide por efeito — **diferente** |
| 9 flags de situação | 3 efeitos — **mapear cada uma** |
| Motivo de atraso / autorização de cartão | `justificarAtraso` existe; **cartão não localizado** |
| Um veículo por colaborador | **conferir** |
| Pedido por usuário de sistema | corrigido no novo |

### 8.4 Riscos herdados (segurança)

| O quê | Onde |
|---|---|
| **Keystore de produção versionado** | `movelapp.jks` no SVN (não copiado) |
| **IDOR de tenant** | `ApiController:34` — `revenda_id` sem validação |
| **Pedido por usuário de sistema** | `savePedido:329` (§2.8) |
| Chave Google API no APK | `legado-nfweb/src/helper/Constants.js:22` |
| Chaves Firebase | `google-services.json` (fora do commit) |
| WebSocket sem TLS + `app_key` na query | `ws://adm.gasemcasa.com.br:8092` |
| `targetSdk 28` | `legado-movelapp/app/build.gradle` |

### 8.5 O que ainda não foi lido

- `PedidoController::store` do `ctrl-web` — o que acontece **depois** do
  `createOrder` (validações, triggers, financeiro).
- Layouts de impressão: `NotaFiscalImpressao.java` (2000+ linhas),
  `BoletoImpressao.java`.
- Cálculo de imposto do legado (CST/CFOP por operação).

Os dois últimos importam para F8; o primeiro, para conferir paridade na criação
de pedido.
