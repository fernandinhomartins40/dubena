# Banca PIE V2 — R3: Arquiteto sênior / Engenharia e dados

**Revisor:** R3 · **Commit:** `48b29f72` (main) · **Data:** 2026-10-28/29 · **Modo:** AUDITORIA (nenhum arquivo do projeto alterado)
**Ambiente das provas:** suíte sqlite `:memory:` com PHP 8.3 portátil; testes exploratórios escritos **fora do projeto**
(no scratchpad da sessão) e rodados com `vendor/bin/phpunit <arquivo>`; leitura do código vendorizado do NFePHP; VPS de
homologação só para leitura (`docker ps`, `printenv` de três variáveis não sensíveis).

Testes exploratórios (fora do repositório, descartáveis):
- `R3ProvaTest.php` (5 testes): agrupado/agrupador, desagrupar com baixa, reparcelar duas vezes, baixa em título cancelado, `PUT /pedidos/{id}`.
- `R3FiscalProvaTest.php` (1 teste): duas NF-e para o mesmo pedido e cancelamento de pedido com NF viva.
- `soap_parse.php`: a leitura de `NFePHPSefazDriver::transmitir()` aplicada a uma resposta SOAP de autorização.

Saída real (trechos de `stderr` dos testes, sem dado pessoal):

```
[R3-agrupado] saldo da conta apos baixar original AGRUPADO + agrupador: 200.00
[R3-desagrupar] agrupador cancelado=1 original status=0 parcela original baixada=0
[R3-reparcelar] titulos de reparcelamento para o mesmo original: 2 soma=200
[R3-cancelado] baixa aceita em titulo cancelado; saldo=100.00
[R3-put-a] situacao=2 (conc=2) estoque_movimentado=0 financeiros=0 historico=1
[R3-put-b] apos PUT em pedido concretizado: valor_venda=1999.00 valor do titulo=1000.00
[R3-put-b] setor original=40 setor novo=10
OK (5 tests, 11 assertions)

[R3-nf-dupla] status1=201 status2=201 notas do pedido=[{"numero":1,"situacao":"AUTORIZADA"},{"numero":2,"situacao":"AUTORIZADA"}]
[R3-cancela-com-nf] pedido situacao=2 (canc=2) estoque_movimentado=0 notas=["AUTORIZADA","AUTORIZADA"]
OK (1 test, 1 assertion)

soap_parse.php:
cStat lido = []
autorizada = false
protocolo = []
motivo = [Sem retorno]
sem envelope: cStat raiz = [104] infProt.cStat = [100]
```

---

## Achados

### R3-01 — `PUT /pedidos/{id}` contorna a máquina de estados e altera pedido já concretizado
- **Categoria:** integridade transacional / estoque / financeiro · **Severidade:** ALTA · **Estado:** CONFIRMADO (teste executado)
- **Onde:** `app/Domain/Pedido/PedidoService.php:75-94` (`atualizar`), `app/Http/Requests/PedidoRequest.php` (regras `pedidosituacao_id`, `setor_id`, `entrega_taxa` aceitas também no PUT), `app/Models/Pedido/Pedido.php:30-40` (`$fillable` traz `pedidosituacao_id`, `setor_id`, `entrega_taxa`), `app/Http/Controllers/Api/Admin/PedidoController.php:136-146`.
- **Rota:** `PUT /api/admin/pedidos/{id}` (permissão `pedido.edit`, a mesma da transição).
- **Reprodução:** (a) criar pedido PENDENTE e mandar `PUT /pedidos/{id}` com `pedidosituacao_id` = situação CONCLUIDO. (b) concluir pela rota certa (`/situacao`), depois `PUT` com `setor_id` = outro setor e `entrega_taxa` = 999, depois cancelar.
- **Esperado:** transição só pela `mudarSituacao` (baixa, financeiro, histórico); pedido concretizado sem campos de efeito editáveis.
- **Observado:** (a) pedido fica CONCLUIDO com `estoque_movimentado=0`, nenhum título e só o histórico da criação. (b) `valor_venda` vira 1999 com o título em 1000. O cancelamento devolve 10 botijões ao setor NOVO (que nunca os teve) e o setor de origem fica com 40 para sempre.
- **Por quê:** `atualizar()` faz `fill($dados)` sem `aplicarEfeito`; a única trava é contra alterar **itens** (`:79-81`), e ela lê `estoque_movimentado` do model em memória, sem lock, então também perde para um "Concluir" que chegue ao mesmo tempo.
- **Impacto:** o ledger e o saldo de estoque divergem por setor (o `estoque:conferir` acusa o efeito, não a causa), o valor do pedido diverge do título, e o pedido aparece "concluído" sem baixa nem cobrança.
- **Correção proposta:** tirar `pedidosituacao_id` do `PUT` (o `safe()` do update descarta a chave ou devolve 422 apontando para `/situacao`); com `estoque_movimentado=true`, recusar `setor_id`, `entrega_taxa`, `condicaopagamento_id` e `cliente_id`; no `atualizar`, fazer o `lockForUpdate` + `refresh` como já faz a `mudarSituacao`.
- **Teste de aceite:** os dois cenários acima. (a) devolve 422 ou deixa a situação como estava; (b) devolve 422 e o cancelamento devolve ao setor de origem.

### R3-02 — Baixa aceita parcela de título AGRUPADO, REPARCELADO ou cancelado: o mesmo dinheiro entra duas vezes
- **Categoria:** dinheiro / integridade · **Severidade:** ALTA · **Estado:** CONFIRMADO (teste executado)
- **Onde:** `app/Domain/Caixa/CaixaService.php:141-186` (`baixarParcela`: confere empresa e `baixado`, mas não `cancelado` nem `agrupamento_status`); `app/Domain/Financeiro/BaixaService.php:25-70` (porta única da baixa, a mesma lacuna; PIX `PixService.php:184` e CNAB `BoletoService.php:153` passam por ela).
- **Reprodução:** título de 100 → `agrupar([t1])` → baixar a parcela do original **e** a do agrupador no caixa.
- **Esperado:** a parcela de um título AGRUPADO (ou REPARCELADO ou cancelado) é recusada.
- **Observado:** o caixa recebe 200 por uma dívida de 100. Título cancelado também recebe a baixa (saldo 100).
- **Impacto:** recebimento duplicado e contas a receber inflado. O `financeiro:conferir` compara Σ movimentos com o saldo, e os dois movimentos são reais, então ele não acusa.
- **Correção:** no `BaixaService::baixar`, ler o título-pai junto com o lock da parcela e recusar quando `cancelado` ou quando `agrupamento_status ∈ {AGRUPADO, REPARCELADO}`. Como está na porta única, vale para caixa, PIX e CNAB. Para o canal externo (PIX/CNAB com `reentregaIdempotente`), registrar a ocorrência em vez de engolir.
- **Teste de aceite:** o cenário acima devolve 422 na segunda baixa e o saldo fica em 100; o mesmo para título cancelado.

### R3-03 — Desagrupar um agrupador já baixado cancela o título que recebeu o dinheiro e reabre a dívida
- **Categoria:** dinheiro · **Severidade:** ALTA · **Estado:** CONFIRMADO (teste executado)
- **Onde:** `app/Domain/Financeiro/FinanceiroService.php:197-213` (`desagrupar`: não verifica parcela baixada e não tem lock; compare com `cancelar()` em `:128-135`, que verifica).
- **Rota:** `POST /api/admin/financeiro/lancamentos/{id}/desagrupar`.
- **Observado:** agrupador com a parcela baixada → `desagrupar` → agrupador `cancelado=1` e original de volta a NORMAL com a parcela em aberto. O cliente que pagou volta a dever, e o recebimento fica pendurado num título cancelado.
- **Correção:** recusar quando houver parcela baixada no agrupador (a mesma regra do `cancelar`) e fazer `lockForUpdate` no agrupador e nos originais dentro da transação.
- **Teste de aceite:** agrupador com baixa → 422; nada muda.

### R3-04 — Reparcelar não confere estado nem trava: a dívida duplica
- **Categoria:** dinheiro / idempotência · **Severidade:** ALTA · **Estado:** CONFIRMADO (teste executado)
- **Onde:** `app/Domain/Financeiro/FinanceiroService.php:219-241`. O saldo em aberto é calculado **fora** da transação, não há lock, e nada recusa título já REPARCELADO, cancelado ou AGRUPADO. As parcelas abertas do original continuam baixáveis (ver R3-02).
- **Rota:** `POST /api/admin/financeiro/lancamentos/{id}/reparcelar`.
- **Observado:** dois reparcelamentos do mesmo título de 100 geram dois títulos novos que somam 200. O duplo clique basta; não precisa de corrida.
- **Correção:** dentro da transação, `lockForUpdate` no original; recusar se `agrupamento_status !== NORMAL` ou se estiver cancelado; recalcular o saldo depois do lock; marcar as parcelas abertas do original como substituídas (ou cancelar o resto) para que não sejam mais baixáveis.
- **Teste de aceite:** o segundo reparcelamento devolve 422; a parcela do original devolve 422 na baixa.

### R3-05 — Duas NF-e autorizadas para o mesmo pedido: o backend não impede, só a SPA esconde o botão
- **Categoria:** fiscal / idempotência / concorrência · **Severidade:** ALTA · **Estado:** CONFIRMADO (teste executado com `FakeSefazDriver`)
- **Onde:** `app/Domain/Fiscal/FiscalService.php:42-71` (`montarDoPedido` não procura nota viva do pedido), `:191-225` (`emitir` lê `situacao` do model **fora** da transação, sem lock na nota), `app/Http/Controllers/Api/Admin/NotaFiscalController.php:89-114`. A única guarda é o `tem_nf` da listagem (`PedidoController.php:53`, `Pedido::notasVivas`), que só a SPA consulta.
- **Reprodução:** `POST /api/admin/notas/emitir` duas vezes com o mesmo `pedido_id`.
- **Observado:** `201`/`201`, com notas 1 e 2 AUTORIZADAS para o mesmo pedido. O mesmo vale para dois `POST /fiscal/nfe/{id}/transmitir` simultâneos do mesmo rascunho: os dois passam pelo `situacao === RASCUNHO`, numeram duas vezes e transmitem duas vezes, e o segundo `update` sobrescreve número e chave do primeiro. Nesse caso a nota autorizada some do banco.
- **Impacto:** imposto destacado em dobro, cancelamento obrigatório dentro do prazo da SEFAZ, e uma nota autorizada pode ficar sem registro local.
- **Correção:** em `emitir`, `NotaFiscal::lockForUpdate()` + `refresh` e reconferir a situação **dentro** da transação; em `montarDoPedido`, travar o pedido e recusar se já houver nota viva (do mesmo modelo); garantia no banco com índice único parcial `notas_fiscais(pedido_id, modelo) WHERE situacao NOT IN ('CANCELADA','REJEITADA')`.
- **Teste de aceite:** a segunda chamada devolve 422 e o pedido fica com uma nota só.

### R3-06 — O driver real da SEFAZ nunca reconhece autorização, cancelamento, inutilização nem CC-e
- **Categoria:** integração fiscal · **Severidade:** CRÍTICA (bloqueia ligar `FISCAL_DRIVER=nfephp`) · **Estado:** FORTE INDÍCIO (análise do código vendorizado + reprodução da leitura; sem SEFAZ real)
- **Onde:** `app/Domain/Fiscal/Drivers/NFePHPSefazDriver.php:45-54` (transmitir), `:67` (cancelar), `:85` (inutilizar), `:103` (CC-e).
- **Por quê:** `Tools::sendRequest()` (`vendor/nfephp-org/sped-nfe/src/Common/Tools.php:526-539`) devolve `Strings::normalize($response)`, e `SoapCurl::send()` devolve o corpo HTTP inteiro (`vendor/nfephp-org/sped-common/src/Soap/SoapCurl.php:124,154`), que é o **envelope SOAP**. O driver faz `simplexml_load_string($resp)->cStat`, e na raiz `soap:Envelope` esse nó não existe. Mesmo sem o envelope, o `cStat` da raiz do `retEnviNFe` síncrono é o do **lote** (104); a autorização (100) fica em `protNFe/infProt/cStat`.
- **Evidência:** a saída do `soap_parse.php` acima: `cStat lido = []`, `autorizada = false`; e sem envelope `cStat raiz = [104]`, `infProt.cStat = [100]`.
- **Cenário de falha:** a SEFAZ autoriza a nota e o ERP grava REJEITADA ("Sem retorno"). O operador emite de novo, com número novo, e sai uma segunda nota autorizada (ver R3-05). O cancelamento aceito pela SEFAZ nunca vira CANCELADA no ERP. Também não se guarda o XML autorizado (`nfeProc`): `NotaFiscal::$fillable` não tem coluna de XML.
- **Contexto:** a homologação roda com o driver fake. `FISCAL_DRIVER` não está definido no contêiner (`printenv` vazio), e o padrão em `config/services.php:83` é `'fake'`. O próprio arquivo diz que o driver "não é exercido pela suíte" (`:21`).
- **Correção:** ler com `NFePHP\NFe\Common\Standardize::toStd()` (que acha o `ret*` dentro do envelope); na autorização síncrona, conferir lote ∈ {103, 104} e depois `protNFe.infProt.cStat ∈ {100, 150}`; nos eventos, `retEvento.infEvento.cStat`; guardar o `nfeProc` (`Complements::toAuthorize`). Em erro de transporte, consultar pela chave (`sefazConsultaChave`) antes de marcar REJEITADA.
- **Teste de aceite:** teste unitário do driver alimentado com respostas SOAP gravadas (autorizada 100, rejeitada 2xx, lote 104 com 100 dentro, cancelamento 135).

### R3-07 — Cancelar pedido com NF-e autorizada devolve o estoque e estorna o financeiro, e a nota continua AUTORIZADA
- **Categoria:** integridade entre domínios · **Severidade:** ALTA · **Estado:** CONFIRMADO (teste executado)
- **Onde:** `app/Domain/Pedido/PedidoService.php:145-164` (`aplicarEfeito` não consulta `notasVivas`); vale para todas as portas da `mudarSituacao` (admin, entregador, app do cliente, ponte MovelApp).
- **Observado:** o pedido vira CANCELADO com `estoque_movimentado=0`, e as notas continuam `["AUTORIZADA","AUTORIZADA"]`.
- **Impacto:** o documento fiscal declara uma venda que o estoque e o financeiro dizem não ter acontecido. O SPED e a apuração saem com uma saída sem lastro.
- **Correção:** recusar o efeito `cancela()` enquanto houver nota viva (exigir cancelar ou inutilizar a NF antes) ou orquestrar o cancelamento fiscal na mesma operação.
- **Teste de aceite:** pedido com nota AUTORIZADA → cancelamento devolve 422.

### R3-08 — O pagamento online (PIX/cartão) do pedido não se liga ao título do pedido nem ao cancelamento, e permite pagar duas vezes
- **Categoria:** dinheiro / idempotência · **Severidade:** ALTA · **Estado:** CONFIRMADO (análise estática de caminho único) quanto à falta de ligação e de guarda; FORTE INDÍCIO quanto aos efeitos a jusante (cobrança de vencido, malote).
- **Onde:**
  - `app/Domain/Cobranca/PixService.php:183-191`: o webhook só baixa quando há `financeiroparcela_id`. A cobrança do pedido (`criarCobrancaPedido`, `:64-90`) grava só `pedido_id`, e `PixConfirmado` só faz broadcast; nenhum listener (`grep PixConfirmado` acha só o evento e o dispatch).
  - `app/Domain/Mobile/PagamentoOnlineService.php:23-65`: o cartão grava `PagamentoOnline` e não baixa nada. Não recusa pedido que já tem pagamento AUTORIZADO ou INDETERMINADO, nem pedido cancelado. O próprio comentário em `:41-47` descreve a cobrança dupla, mas a guarda não existe.
  - `app/Http/Controllers/Api/Mobile/AppPedidoController.php:77-120`: `pagar` e `gerarPix` não olham a situação do pedido. `criarCobrancaPedido` reaproveita só cobrança **ATIVA**, então depois de CONCLUIDA gera outra.
  - `routes/api.php:864-872`: as rotas do app do cliente (`POST pedidos`, `pagar`, `pix`) **não** estão no middleware `idempotente`. Só o grupo do entregador está (`:878`).
  - `app/Domain/Mobile/PedidoMobileService.php:292-312`: o cancelamento pelo app não estorna o cartão nem trata o PIX pago.
  - `app/Domain/Financeiro/FinanceiroService.php:80-116`: na concretização, `gerarDoPedido` sempre cria título em aberto.
- **Cenário:** o cliente paga por PIX, o entregador conclui e nasce um título em aberto do mesmo valor. O `financeiro:notificar-vencidos` passa a cobrar esse cliente, e o malote/caixa espera do entregador um dinheiro que já entrou no banco. Retry de rede no `pagar` cobra o cartão duas vezes. Cliente cancela depois de pagar: o dinheiro fica retido sem registro de estorno.
- **Correção:** ao concretizar, baixar o título com a cobrança CONCLUIDA/AUTORIZADA do pedido (ou, no webhook, baixar o título do pedido se já existir); recusar `pagar`/`gerarPix` para pedido cancelado ou já pago; pôr as escritas do app do cliente sob `idempotente`; no cancelamento, estornar o cartão ou sinalizar o PIX para devolução.
- **Teste de aceite:** PIX do pedido CONCLUIDA, depois concluir o pedido: o título nasce baixado. Segundo `pagar` → 409/422. Cancelar pedido pago → estorno chamado no driver fake.

### R3-09 — Chamada externa dentro de `DB::transaction`: a autorização remota pode ficar sem registro local
- **Categoria:** transações / integrações · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (estático); o efeito exige falha de banco depois da resposta remota
- **Onde:** `app/Domain/Fiscal/FiscalService.php:197-224` (a `sefaz->transmitir` roda com o lock da linha de `sequencias` aberto); `app/Domain/Mobile/PagamentoOnlineService.php:28-64` (`driver->autorizar` dentro da transação que criou o `PagamentoOnline`); `app/Domain/Cobranca/BoletoService.php:30-46` (`driver->gerar`).
- **Cenário:** o gateway autoriza o cartão e o `update` seguinte falha (conexão, timeout de statement, worker morto). O rollback apaga até a linha PENDENTE, e fica uma cobrança autorizada sem rastro. Na NF, o rollback devolve o número à sequência depois de a SEFAZ autorizar, e a próxima nota recebe o mesmo número (rejeição por duplicidade, e a nota autorizada fica sem registro). Mesmo sem falha, o lock da sequência fica preso durante todo o round-trip da SEFAZ e serializa a emissão de NFC-e no balcão.
- **Correção:** separar em fases: (1) transação curta que reserva o número e grava EMITINDO/PENDENTE; (2) chamada externa fora da transação; (3) transação curta que grava o desfecho. Desfecho indeterminado fica num estado próprio para conciliar (como já existe INDETERMINADO no cartão).
- **Teste de aceite:** driver fake que autoriza e depois lança, com `update` forçado a falhar: o registro sobrevive com estado não terminal e o número não é reutilizado.

### R3-10 — Guarda de cancelamento fora do lock e máquina de estados sem matriz de transição
- **Categoria:** concorrência / máquina de estados · **Severidade:** MÉDIA · **Estado:** FORTE INDÍCIO (corrida não reproduzível em sqlite; caminho lido)
- **Onde:** `app/Domain/Mobile/PedidoMobileService.php:292-311` (confere CONCLUIDO/CANCELADO no model carregado e só depois chama `mudarSituacao`); `app/Domain/Pedido/PedidoService.php:99-123` (trava e faz `refresh`, mas aceita **qualquer** origem → destino); `app/Http/Controllers/Api/Mobile/AppEntregadorController.php:195-217` e `app/Domain/Mobile/EntregaService.php:90-113` (o entregador conclui ou move pedido CANCELADO sem objeção).
- **Cenário:** o entregador conclui (baixa e título) enquanto o cliente toca "cancelar" com a tela antiga. A guarda do cliente leu PENDENTE e a transição CONCLUIDO→CANCELADO passa: o estoque volta e o título é estornado com o gás entregue. No sentido inverso, o entregador "conclui" pedido que o cliente cancelou.
- **Correção:** matriz de transição permitida por efeito (ex.: CANCELADO é terminal fora do admin; CONCLUIDO→CANCELADO só pelo admin), validada **dentro** da `mudarSituacao` depois do lock, com o efeito anterior lido do banco.
- **Teste de aceite:** pedido CONCLUIDO → `cancelar` do app → 422; pedido CANCELADO → `concluir` do entregador → 422.

### R3-11 — Webhook PIX que chega depois da expiração local é rejeitado sem deixar rastro
- **Categoria:** integração / conciliação · **Severidade:** MÉDIA · **Estado:** FORTE INDÍCIO
- **Onde:** `app/Domain/Cobranca/PixService.php:164-166` (EXPIRADA → `ValidationException`), `:211-218` (cron a cada minuto pelo relógio local); `app/Http/Controllers/PixWebhookController.php:72-77` (devolve 422 sem log nem persistência; a transação faz rollback).
- **Cenário:** o PSP aceita o pagamento perto do fim do prazo e a primeira entrega do webhook falha (indisponibilidade, deploy). Os retries chegam depois que o `pix:expirar` marcou EXPIRADA e recebem 422 para sempre. O dinheiro está na conta e o ERP não registra o `e2eid`.
- **Correção:** um pagamento confirmado pelo PSP (assinatura válida) é fato. Registrar sempre (estado PAGA_APOS_EXPIRACAO ou fila de conciliação, com alerta) e não recusar com 422. Recusar só o que é fraude (valor divergente), e mesmo assim registrar.
- **Teste de aceite:** cobrança EXPIRADA + webhook válido → linha registrada e alerta, não 422 silencioso.

### R3-12 — Numerações auxiliares sem lock: remessa CNAB e sequência de CC-e
- **Categoria:** sequências · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (estático)
- **Onde:** `app/Domain/Cobranca/BoletoService.php:75-82` (`max('numero_remessa') + 1`; o lock é só nos boletos selecionados, e duas remessas com conjuntos distintos não se bloqueiam); `database/migrations/0008_01_01_000000_create_cobranca_tables.php:51` (`numero_remessa` sem unique). `app/Domain/Fiscal/FiscalService.php:292` (`max('sequencia') + 1` da CC-e, sem lock na nota).
- **Cenário:** duas remessas geradas juntas recebem o mesmo número e o mesmo caminho `remessas/empresa_X/CB{banco}_{n}.rem`. O `Storage::put` da segunda **sobrescreve** o arquivo da primeira, e a remessa 1 aponta para o conteúdo da 2. O banco recusa sequencial repetido.
- **Correção:** usar o `NumeroSequencialService` (já existe, com o formato `boleto:empresa:...` reconhecido em `empresaDaChave`) e índice único `(empresa_id, banco_codigo, numero_remessa)`; CC-e com lock na nota.
- **Teste de aceite:** índice presente; duas gerações seguidas produzem números distintos e arquivos distintos.

### R3-13 — `cancelar` título sem lock compete com a baixa
- **Categoria:** concorrência / dinheiro · **Severidade:** BAIXA · **Estado:** FORTE INDÍCIO
- **Onde:** `app/Domain/Financeiro/FinanceiroService.php:128-135` (o `exists()` da baixa não trava; `BaixaService::baixar` trava só a parcela).
- **Cenário:** cancelamento e baixa simultâneos produzem título cancelado com parcela baixada. Combinado com R3-02, a baixa nem precisa da corrida.
- **Correção:** `lockForUpdate` nas parcelas do título dentro de uma transação no `cancelar` (e R3-02 resolve o outro lado).

### R3-14 — Job de geocodificação despachado dentro da transação, com fila redis `after_commit=false`
- **Categoria:** jobs / consistência eventual · **Severidade:** MÉDIA · **Estado:** FORTE INDÍCIO
- **Onde:** `app/Domain/Cliente/ClienteService.php:37` e `:61` (dispatch dentro de `DB::transaction`); `config/queue.php:73` (`after_commit => false` na conexão redis); homologação com `QUEUE_CONNECTION=redis` (verificado por `printenv` nos contêineres `erpnovo-queue`/`erpnovo-scheduler`); o job sai em silêncio quando não acha o cliente (`app/.../GeocodificarClienteJob.php:70-75`).
- **Cenário:** o worker pega o job antes do commit, não enxerga o cliente recém-criado e termina com sucesso sem geocodificar. A distribuição por proximidade (L3) cai no fallback para esse cliente sem ninguém saber.
- **Correção:** `->afterCommit()` no dispatch (ou `ShouldQueueAfterCommit` no job). O `PedidoService::criar` já faz isso certo (dispara depois do commit, `:63-69`).
- **Teste de aceite:** `Queue::fake()` + verificar `afterCommit` no job despachado.

### R3-15 — Middleware `Idempotente`: chave órfã bloqueia o reenvio para sempre e a tabela nunca é purgada
- **Categoria:** idempotência · **Severidade:** BAIXA · **Estado:** CONFIRMADO (estático)
- **Onde:** `app/Http/Middleware/Idempotente.php`: a reserva é gravada antes de executar e só é concluída ou apagada se o processo voltar. A busca não filtra `expira_em`, e não há `Prunable` nem comando que limpe `requisicoes_idempotentes` (grep sem resultados fora do middleware e do model).
- **Cenário:** o worker morre (timeout, OOM) entre a reserva e o fim. Todo reenvio do app do entregador com a mesma chave recebe 409 "em processamento", indefinidamente, e não se sabe se a ação foi feita.
- **Correção:** tratar reserva não concluída com mais de N minutos como abandonada (reexecutar ou responder com o estado real); filtrar e purgar por `expira_em`.

### R3-16 — `tenant_staging_artifacts` sem RLS
- **Categoria:** migrations / multi-tenant · **Severidade:** BAIXA · **Estado:** CONFIRMADO (sem RLS); exposição é HIPÓTESE (hoje só comandos de console usam a tabela)
- **Onde:** `database/migrations/2026_08_29_000500_create_tenant_staging_artifacts.php` (tabela com `tenant_account_id` e `payload` JSON, sem `ENABLE ROW LEVEL SECURITY`/policy; pelas `DEFAULT PRIVILEGES` descritas no CLAUDE.md, `erp_app` nasce com escrita). Nenhuma outra migration cita a tabela.
- **Correção:** RLS por `tenant_account_id` (ou `REVOKE` de `erp_app` se só o owner deve tocá-la).

---

## Inspecionado sem achado (pontos fortes, verificados)
- `NumeroSequencialService` (`app/Domain/Shared/NumeroSequencialService.php`): `insertOrIgnore` + `lockForUpdate` + guarda contra linha invisível sob RLS. Correto para NF-e/NFC-e (o problema da NF está no R3-05, R3-06 e R3-09, não na sequência).
- `EstoqueService::movimentar`: lock do saldo, unique `(setor_id, produto_id)` (`0004_..._create_estoque_tables.php:41`), chave de idempotência com índice parcial, recusa de período fechado.
- `CaixaService::estornar`: lock, recusa de estorno duplo e índice único (`2026_10_09_000100`). `movimentar` trava a conta.
- `ComodatoService`: `travar()` + reconferência dentro da transação em `devolver`/`estornar`.
- `PedidoService::mudarSituacao`: lock + `refresh` contra o "Concluir" duplo (F4-01).
- Agendamentos (`routes/console.php`): todos com `withoutOverlapping`; os que leem ou gravam dados de tenant passam por `AutomacaoPorEmpresa` (lista pelo owner, trabalho no envelope), sem a armadilha "console lê zero sob RLS".
- Varredura de migrations depois da descoberta de RLS: as tabelas novas com `empresa_id` têm RLS na própria migration ou foram cobertas pelas varreduras `2026_06_26_000300` e `2026_07_03_000300`. As globais sem RLS são declaradas como tal (caches de rota/vias/malha, catálogos IBGE/CNEFE, `migracoes`, plano de controle da conversão). A exceção é o R3-16.
- Migrations a partir de `2026_09`: nenhum `up()` destrutivo.
- Índices em tabelas grandes: `pedidos(empresa_id, datahora)`, `(empresa_id, pedidosituacao_id)`, `financeiroparcelas(empresa_id, baixado, vencimento)` e `(empresa_id, datahora_baixa)` existem.
- Models com `empresa_id` sem `BelongsToTenant`: só identidade e plataforma (`User`, `Role`, `Empresa`, `EmpresaConfig`, `AuditLog`, `LoginLog`, `Saas/*`), todos cobertos por RLS no banco. Nada anômalo.
- Contrato de API: `ApiContratoDriftTest` rodado: **OK (2 testes, 4 asserções)**.
- `NfEntradaService`: unique em `chave` + lock no processamento.

## Não testado / limitações
- Nenhuma corrida real foi executada (o sqlite não tem `FOR UPDATE`): R3-10, R3-11, R3-13 e R3-14 são análise de caminho. O gate Postgres local não foi usado.
- O R3-06 não foi exercido contra a SEFAZ (sem certificado A1). A prova é a leitura do vendor + reprodução do parse sobre o formato padrão da resposta.
- Não auditei fundo os drivers de PSP/cartão reais (Efí/Rede), a ETL nem o legado (fora do escopo).
- Respostas fora de `{data: ...}`: amostragem só; 192 ocorrências de `response()->json([` sem `'data'` na mesma linha, a maioria multilinha. O drift do manifesto passou. Não classifiquei.
- Hubs (`Empresa`, 961 arestas): não achei risco concreto que justifique um achado. Os riscos reais desta área estão nas regras de transição (R3-01, R3-10) e na falta de ligação entre domínios (R3-07, R3-08), não no tamanho das classes.

## Parecer da área (Engenharia e dados)
**REPROVADO.** Há perda ou duplicação de dinheiro confirmada por teste (R3-02, R3-03, R3-04), corrupção de estoque e financeiro pela API administrativa (R3-01), documento fiscal duplicado e desligado do pedido (R3-05, R3-07) e um driver fiscal real que, ligado, grava cada autorização como rejeição (R3-06). As fundações (sequência fiscal, ledger de estoque, caixa, comodato, scheduler sob RLS) estão bem construídas. Os defeitos estão nas **bordas entre estados e entre domínios**: transições sem guarda, operações de título sem verificar o estado do título, e pagamento online sem ligação com o financeiro.
