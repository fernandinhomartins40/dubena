# Regras de negócio dos apps legados — extraídas do código

Leitura linha a linha de `NfwebController.php` (1773 linhas), `MobileRepository`,
`MobileAppProcessor`, `PedidoUtil` e das 53 classes do MovelApp.

Cada regra abaixo tem arquivo e linha. **Nenhuma vem de documentação.**

---

## 1. Precificação — a regra mais importante

`MobileRepository::getPreco` (linha 596):

    private static function getPreco($produto, $order, $isAppNf)
    {
        $preco = $produto->precovendaunitario;
        if ($isAppNf) return $preco;          // ← NFWEB: aceita o preço do app
        ...
        if ($tipo != "4") {
            return static::getPrecoEspecial(...);   // tabela do cliente
        }
        return static::getPrecoConvenio(...);       // preço de convênio
    }

**Três caminhos de preço, e o primeiro é o problema:**

### 1.1 NFWEB (`isAppNf = true`) — preço livre

O servidor **aceita o valor que o app enviou**, sem consultar tabela nenhuma.
Combinado com o `TextInput` de desconto (`Pedido.js:800`), significa que o
vendedor industrial define preço **e** desconto sem qualquer teto.

É o mesmo padrão do MovelApp, onde `PedidoFragment2.java:80` faz
`txtPreco.setEnabled(pedidoId.equals("-1"))` — campo livre em pedido novo.

### 1.2 Preço especial por cliente — `getPrecoEspecial` (linha 620)

Tabela `clienteProduto`, com quatro casos em ordem:

1. `descontopara == 3` → **ignora** o preço especial, usa o de tabela;
2. `tipo == 2` → desconto **percentual**: `preco - (preco * desconto)`
   (note: `desconto` aqui é fração, não porcentagem);
3. `desconto == 0` → usa `precoEspecial->preco` (preço fixo negociado);
4. senão → desconto **em valor**: `preco - desconto`.

### 1.3 Preço de convênio — `getPrecoConvenio` (linha 643)

    if ($comissaodestino != "1") return $preco;
    return $preco - ($preco * ($comissao / 100));

Só desconta a comissão quando `comissaodestino == 1` — ou seja, quando a comissão
fica com o **cliente conveniado**, não com a rede.

---

## 2. Convênio — limite por período

`MobileAppProcessor::checkForConvenio` (linha 528). Só age quando a condição de
pagamento é `tipo == "4"`.

1. **Janela de apuração**: `getProximoVencimento($convenio->diafechamento)` define
   o fechamento; a janela é `[fechamentoAnterior, fechamento]` — um mês.
2. **Soma o consumo do período** (linha 547): quantidade de todos os itens de
   pedidos do cliente na janela, com `fechadocancelado <> 1 AND entregacancelada <> 1`
   (cancelado não conta).
3. **Produto tem de estar no convênio** (linha 566): se algum item não estiver em
   `produtoconvenio`, rejeita — *"Produto X não disponivel para convênio."* (cód. 102).
4. **Limite em dois níveis** (linha 580):
   - se `cliente->convenio` → compara com `convenio->limitecompra` (limite da empresa conveniada);
   - senão → compara com `cliente->conveniolimite` (limite individual).
5. Estourou → *"Não há limite o suficiente no convênio para realizar a compra."*

**O limite é por QUANTIDADE, não por valor.**

---

## 3. Emissão fiscal — a matriz do NFWEB

`NfwebController::savePedido` (linha 326). Quatro flags do app: `emiteNF`,
`emiteNFC`, `emiteNFCNaoId`, `emiteBoleto`.

### 3.1 Regra de NFC-e automática (linha 375)

    // Mudança de regra - mesmo que não informar que emite NF, emitir uma NFCe não identificada
    if (!$emiteNF && !$emiteNFC && $order->pagamento->appnfceauto) {
        $emiteNFC = true;
        $emiteNFCNaoId = true;
    }

Se a **condição de pagamento** tem `appnfceauto`, sai NFC-e não identificada
mesmo que o vendedor não peça nota. Fiscalmente relevante.

### 3.2 A condição de pagamento governa a situação (linha 382)

    $this->throwIf($order->pagamento->pedidosituacaoappnf_id == null,
        "Status de pedido não vinculado a esta condição de pagamento: ...");
    $order->pedidosituacao_id = $order->pagamento->pedidosituacaoappnf_id;

**A situação inicial do pedido vem da condição de pagamento**, não do app. É a
condição que decide se o pedido nasce pendente, em entrega ou concluído.

### 3.3 NFC-e × NF-e (linhas 447 e 502)

| | NFC-e | NF-e |
|---|---|---|
| Identificação | `nftipo` 0 (não id.) ou 1 | sempre 1 |
| CPF/CNPJ | vazio se não identificada | `cliente->cpf` |
| `indicador_ie` | 9 se não identificada | do cliente |
| Transportador | `null` | `empresaconfig->transportadorappnf_id` |
| Placa do frete | — | do **veículo do colaborador** no setor |
| Operação | padrão | `data->operacao->id` (escolhida no app) |

A placa vem de uma busca pelo veículo cujo `colaborador_id` bate com o vendedor
(linha 504) — se ele não tiver veículo vinculado, vai vazio.

### 3.4 Transmissão e falha

Emite → `transmitirnf` (linha 490). Se a emissão falha, **o pedido permanece
criado** e retorna `responseError` com a mensagem — *"Pedido N gerado, ocorreu um
erro ao gerar a NFCe: ..."*. Pedido sem nota é estado possível no legado.

### 3.5 Financeiro só com nota (linha 578)

    if ($numnf != "") {
        if (PedidoUtil::allwedToCreateFinanceiro($order->condicaopagamento, $order->pedidosituacao)) {
            $fin->documento = $numnf;   // amarra o financeiro ao número da nota
        }
    }

`allwedToCreateFinanceiro` (`PedidoUtil.php:59`):

    return static::condicaoGeraFinanceiro($condicao) && !static::isFechadoCancelado($situacao);

**Duas condições**: a condição de pagamento tem de gerar financeiro *e* a situação
não pode ser fechado/cancelado.

### 3.6 Boleto (linha 585)

Só depois da nota. Monta as parcelas do financeiro, zera juros e multa
(`juros = 0`, `multa = 0`), gera pelo `BoletoProcessor` na conta
`empresaconfig->contaappnf_id`, e salva o PDF por empresa/pedido.

---

## 4. Autenticação — o buraco de segurança

`savePedido` (linha 329):

    $logged = $auth->loginFromApi([
        'email'    => env('DEFAULT_USER_SYSTEM'),
        'password' => env('DEFAULT_PASSWORD_SYSTEM')
    ]);

**O pedido é criado por um usuário de sistema fixo**, não pelo vendedor logado. O
`colaborador_id` vem do *corpo da requisição* (`Input::all()["colaborador_id"]`),
sem verificação de que o token pertence àquele colaborador.

Quem alcançar a API pode lançar pedido **em nome de qualquer vendedor**. No ERP
novo isso não se reproduz: o `AppMissaoController` usa `$request->user()->id`.

---

## 5. MovelApp — regras de rota

### 5.1 Situação governa a entrega — 9 flags

`Situacao.java` e `tbl_situacoes` (`DataBaseHandler.java:69`):

`entrega_finalizada`, `entrega_pendente`, `entrega_cancelada`,
`entrega_transferida`, `em_entrega`, `vale_gas`, `mensagem_enviada`,
`mensagem_lida`, `cartao`.

É a mesma matriz que o ERP novo condensou no enum `EfeitoPedido`
(PENDENTE/CONCLUIDO/CANCELADO) — o legado tem 9 dimensões onde o novo tem 3.
**Ao migrar, cada flag precisa de destino explícito**; `entrega_transferida` e
`em_entrega`, por exemplo, não têm equivalente direto.

### 5.2 Exigências condicionais na baixa (`PedidoStatusActivity`)

    if (pedeMotivoAtraso.equals("true")) { ... }   // linha 86
    if (pedeCartao.equals("true"))       { ... }   // linha 122
    params.put("pedidomotivoatraso_id", pedeMotivoAtraso ? codMotivoAtraso : "-1");
    params.put("cartao_autorizacao",    pedeCartao ? cartaoAutorizacao : "");

A situação de destino decide se o entregador **tem de informar motivo de atraso**
ou **número de autorização do cartão**. Sem isso, a baixa não passa.

### 5.3 Escrita local só após confirmação do servidor (linha 264)

    if (status.equals("OK")) {
        if (dbHandler.atualizaStatusPedido(pedido)) { ... }
    }

O SQLite local **só é atualizado depois que o servidor confirma**. Ou seja: o
MovelApp opera offline para *leitura* (consultar a rota carregada), mas a baixa
de pedido **exige rede**. Isso corrige a leitura anterior de que ele seria
totalmente offline.

### 5.4 Impressão só de nota autorizada

`NotaFiscalImpressaoActivity.java:120`:

    // 0=não encontrou NF na base; 1=encontrou, mas situação <> 100;
    // 2=achou NF autorizada; 8=erro conexão impressora; 9=erro na impressão

Só imprime com `nfsituacao_id == 100` (Autorizado o uso da NF-e). Se não
autorizada: *"Aguarde 1 minuto e tente novamente."*

**A impressão depende de rede** — consulta a nota antes de imprimir.

---

## 6. Vínculo de veículo — um por colaborador

`NfwebController::changeVeiculo` (linha 303):

    $veiculos = Veiculo::where('colaborador_id', $colaborador->id);
    $veiculos->update(array('colaborador_id' => null));   // limpa o anterior
    $veiculo->colaborador_id = $colaborador->id;

Trocar de veículo **desvincula todos os outros**. Regra de exclusividade que
importa para a placa da NF-e (3.3).

---

## 7. Parcelas vencidas — informativo, não bloqueia

`getParcelasVencidasCliente` (linha 284) só consulta: `baixado = false` e
`datavencimento < hoje`, ordenado por vencimento.

**Não há bloqueio de venda para inadimplente** em nenhum dos dois apps. O
vendedor vê a pendência e decide. Se a Central de Vendas quiser bloquear, é
regra nova.

---

## 8. Implicações para o acoplamento

| Regra do legado | Situação no ERP novo |
|---|---|
| Preço livre no NFWEB (`isAppNf`) | **não existe** — `venderGas` não aceita preço. Manter assim e adicionar alçada |
| Preço especial por cliente (4 casos) | **conferir** se `preco_venda` cobre os 4 |
| Convênio com limite por quantidade | existe `convenios` — **conferir** a janela de apuração |
| Situação vinda da condição de pagamento | **conferir**: o novo usa `pedidosituacao_id` explícito |
| NFC-e automática por `appnfceauto` | **não localizado** no ERP novo |
| Financeiro só com nota + condição | `EfeitoPedido` decide por efeito — **modelo diferente** |
| 9 flags de situação | condensadas em 3 efeitos — **mapear cada uma** |
| Motivo de atraso / autorização de cartão | existe `justificarAtraso`; **cartão não localizado** |
| Um veículo por colaborador | **conferir** |
| Pedido por usuário de sistema | corrigido no novo (`$request->user()`) |

---

## 9. O que ainda não foi lido

- `NotaFiscalImpressao.java` (2000+ linhas) — layout ESC/POS, coluna a coluna.
- `BoletoImpressao.java` — layout do boleto e código de barras.
- Cálculo de imposto do legado (`nfimposto`, CST/CFOP por operação).
- `PedidoController::store` do ctrl-web — o que acontece depois do `createOrder`.

Os três primeiros são layout de impressão: importam para a fase de impressão
térmica, não para o desenho da Central.
