# Banca R2 — Usuário adversarial / Produto e UX

**Revisor:** R2 · **Commit:** `48b29f72` (main) · **Data:** 2026-10-28/29 · **Protocolo:** PIE V2, seções 2.2 (R2), 2.5 e 5

## Método e limites (ler antes dos achados)

- **A avaliação é ESTÁTICA na maior parte.** Não tenho navegador nem aparelho: não houve clique, toque
  nem captura de tela. Toda afirmação de comportamento vem de **código lido (arquivo:linha)**, de
  **validação do backend lida**, de **simulação em Node** (aritmética e serialização) ou de **teste vitest
  que eu escrevi e rodei** contra os componentes reais da SPA.
- Os testes-sonda ficaram **fora do repositório**, no scratchpad
  (`C:\Users\fusea\AppData\Local\Temp\claude\c--FuseDev-Dubena\ab145f3a-5553-46c2-b9c9-84fd48bca578\scratchpad\probe\src\r2probe.test.tsx`
  e `...\r2baixa.test.tsx`), rodados com uma config vitest temporária que aponta para `erp-novo/frontend`.
  Nenhum arquivo do projeto foi alterado. Saída real:
  ```
  R2-PROBE-A itens enviados: [{"produto_id":10,"quantidade":2,"preco_unitario":105}]   (eram 2 itens na tela)
  R2-PROBE-B itens apos reabrir: 0                                                      (Esc descartou sem perguntar)
  R2-PROBE-C rotulo do botao: "Receber -R$ 50,00"                                        (botão habilitado)
  R2-PROBE-D chamadas POST apos duplo clique: 1                                          (proteção funciona)
  Test Files 2 passed · Tests 4 passed
  ```
- Testes existentes rodados e verdes: `NovoPedidoDialog.test.tsx`, `LancamentosTab.test.tsx`,
  `format.test.ts`, `UnsavedChanges.test.tsx`, `accessibility.test.tsx` — 5 arquivos, 15 testes.
- **VPS (somente leitura):** um `php artisan tinker` de leitura no contêiner `erpnovo-app` para ver a
  língua das mensagens: `en|en|The q field must be greater than 0.`
- Apps móveis: **só leitura de código** (o app do entregador não tem nenhum teste; não rodei os do cliente).

---

## Achados

### R2-01 — "Busque pelo nome ou telefone" não busca por telefone
- **Categoria:** jornada principal (pedido por telefone) · **Severidade:** ALTA. É a primeira ação do
  atendente de disk-gás: o cliente liga, o atendente digita o número. · **Estado:** CONFIRMADO (código lido)
- **Onde:** `erp-novo/frontend/src/features/pedidos/NovoPedidoDialog.tsx:165` (placeholder "Busque pelo nome ou
  telefone…", endpoint `/lookups/clientes-fornecedores`) → `erp-novo/app/Http/Controllers/Api/Admin/LookupController.php:39`
  (`['clientes','nome','empresa']`) e `:201` (`LOWER(nome) LIKE ?`). A lista de clientes também não busca
  telefone: `ClienteController.php:41-47` (nome, fantasia, cpf, cnpj).
- **Reprodução (estática):** Novo pedido → campo Cliente → digitar `42999990000`.
- **Esperado:** aparece o cliente dono do telefone. **Observado:** o filtro só olha `nome` → "Nenhum resultado."
  (`async-select.tsx:87`). Também não ignora acento (`LOWER ... LIKE`: "joao" não acha "João") e corta em 50.
- **Impacto:** o atendente tem de perguntar o nome e torcer para estar escrito igual; pode criar um cliente
  duplicado. A bina (`PainelChamadas`) só ajuda quando a telefonia está integrada.
- **Correção:** no lookup de clientes, buscar também em `clientetelefones` pelos dígitos (o sufixo de 8 dígitos já
  existe em `ClienteAuthService::somenteDigitos`) e usar `unaccent`/`ilike`. Ou trocar o texto do placeholder.
- **Aceite:** um teste de feature com cliente de telefone `(42) 99999-0000`, onde `GET /lookups/clientes-fornecedores?q=999990000`
  devolve esse cliente.

### R2-02 — App do cliente manda TODO pedido para a tela de PIX, com um aviso de cancelamento que não é verdade
- **Categoria:** fluxo de pagamento / mensagem enganosa · **Severidade:** ALTA · **Estado:** CONFIRMADO (código lido)
- **Onde:** `app-gas-em-casa/src/app/(auth)/carrinho.tsx:91-101`: o `onSuccess` chama `OrderService.GerarPix`
  **sempre**, qualquer que seja a forma de pagamento escolhida. O servidor não confere a condição:
  `AppPedidoController.php:102-110` → `PixService::criarCobrancaPedido` (`PixService.php:64-90`).
- **Reprodução:** escolher "Dinheiro" (ou cartão na entrega) e tocar em Finalizar, numa revenda com PIX configurado.
- **Esperado:** ir para o acompanhamento. **Observado:** o app cria uma cobrança PIX e abre "Aguardando pagamento
  PIX" (`pix.tsx`). Quando a empresa não tem credencial, o `GerarPix` falha e o app cai no acompanhamento: o defeito
  aparece justamente nas revendas que configuraram PIX.
- **Mensagem falsa:** `pix.tsx:114` diz "Você tem até 5 minutos... Caso o prazo seja excedido, o pedido será
  cancelado". Mas `PixService::expirarVencidas` (`PixService.php:211-218`) só marca a **cobrança** como EXPIRADA;
  o pedido continua aberto. Depois da expiração, a tela fica fazendo polling de `pago` para sempre e não oferece
  gerar um PIX novo nem trocar a forma de pagamento.
- **Impacto:** quem ia pagar em dinheiro acha que precisa pagar PIX (e pode pagar duas vezes); quem perde o prazo
  acha que o pedido foi cancelado e pede de novo.
- **Correção:** só gerar PIX quando a condição for PIX (ter um flag/tipo na `CondicaoPagamento`) e recusar no
  servidor com 422; fazer o texto da tela bater com a regra real.
- **Aceite:** um pedido com condição "Dinheiro" recebe 422 em `POST /app/v1/pedidos/{id}/pix`, e o app navega
  para `/track`.

### R2-03 — Fila offline do entregador troca a chave de idempotência e o reenvio duplica a ação
- **Categoria:** duplicidade em rede instável · **Severidade:** ALTA · **Estado:** CONFIRMADO (código lido; o app não tem testes)
- **Onde:** `app-entregador/src/helpers/envioResiliente.ts:41` gera `chave` e a envia na 1ª tentativa (`:48`).
  Na falha de rede, `enfileirar(endpoint, method, data)` (`:60`) **não recebe a chave**, e `filaOffline.ts:85-86`
  cria outra com `id: novoId()`. O reenvio usa esse id novo (`envioResiliente.ts:76`). O comentário de
  `envioResiliente.ts:18-21` diz o contrário ("preservada entre tentativas").
- **Reprodução:** sinal fraco → "Solicitar venda" / "Novo cliente" / ocorrência → o servidor grava, mas a
  resposta passa dos 20 s (`mobile-shared/src/http.ts:44`) → axios devolve status 0 → o app enfileira → quando a
  rede volta, a fila reenvia com uma chave que o middleware `Idempotente` nunca viu.
- **Esperado:** o replay devolve a 1ª resposta. **Observado:** a operação roda duas vezes. Rotas afetadas:
  `POST entregador/solicitacoes` (`venda.service.ts:65`), `entregador/clientes` (`:116`) e `pedidos/{id}/ocorrencia`.
- **Impacto:** solicitação de venda em dobro (e pedido em dobro, se for aprovada), cliente duplicado.
- **Correção:** `enfileirar(endpoint, method, data, chave)` reaproveitando a chave da 1ª tentativa.
- **Aceite:** um teste unitário com `request` falhando por timeout, que verifica que o item enfileirado tem
  `id === ` a chave enviada.

### R2-04 — A fila do entregador apaga as pendências em 401 e não é limpa no logout
- **Categoria:** perda de dado / atribuição errada · **Severidade:** ALTA · **Estado:** CONFIRMADO (o descarte);
  FORTE INDÍCIO (o cenário de aparelho compartilhado)
- **Onde:** `filaOffline.ts:163-167` descarta qualquer 4xx (exceto 408/429), e **401 entra nessa regra**. A fila
  envia por `Http.client` (`envioResiliente.ts:70-77`), que só tem interceptor de *request*; o tratamento de 401
  (`onUnauthorized`) mora em `normalizeError` do wrapper `request` (`mobile-shared/src/http.ts:65`), que esse
  caminho não usa. `limpar()` (`filaOffline.ts:105`) não tem nenhum chamador, e a sincronização roda sempre que
  há token (`app/(app)/_layout.tsx:21`).
- **Reprodução:** ações enfileiradas → o token vence (Sanctum `expiration` 1440 min, `config/sanctum.php:61`) ou é
  revogado → a sincronização recebe 401 em cada item → todos são apagados e contados como "falharam". Outro caso:
  o entregador A sai com itens pendentes, o entregador B entra no mesmo aparelho e a fila de A sai com o token de B.
- **Esperado:** um 401 para a fila e leva ao login, sem descartar nada; o logout limpa a fila ou a amarra ao usuário.
- **Impacto:** mudanças de status, ocorrências, vendas e clientes registrados sem sinal somem sem aviso, ou são
  gravados em nome de outro entregador.
- **Correção:** tratar 401/419 como "parar e manter" e chamar o `logout`; guardar `user_id` em cada item; chamar
  `limpar()` (ou filtrar pelo usuário) no logout.

### R2-05 — Abertura e fechamento de caixa gravados 3 horas no futuro
- **Categoria:** data/fuso · **Severidade:** MÉDIA (o fechamento cai no dia seguinte a partir das 21h) · **Estado:** CONFIRMADO
- **Onde:** `erp-novo/frontend/src/features/financeiro/tabs/CaixaTab.tsx:15` faz
  `new Date().toISOString().slice(0,19).replace('T',' ')` e manda isso como `datahoraabertura`/`datahorafechamento`.
  O servidor aceita o valor (`CaixaController.php:129,142` só valida `nullable|date`) e grava como hora local
  (`CaixaService.php:126-128`; `APP_TIMEZONE=America/Sao_Paulo`).
- **Evidência (Node, TZ America/Sao_Paulo):** 28/10 22:30 local → `CaixaTab agora: 2026-10-29 01:30:00`.
- **Impacto:** o caixa fechado às 22h fica registrado como fechado no dia seguinte, e o relatório de caixa do dia
  sai errado. O botão "Fechar" fecha com um clique só, sem confirmação (`CaixaTab.tsx:29`).
- **Correção:** não mandar a hora (o servidor já usa `now()`) ou mandar ISO com offset; pôr um `ConfirmDialog` no fechar.

### R2-06 — "Hoje" calculado em UTC: depois das 21h, os padrões de data viram amanhã
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código lido + simulação Node)
- **Onde:** `MaloteTab.tsx:10` (constante de módulo: além do UTC, fica parada se a aba passa a noite aberta);
  `comodatos/DevolucaoDialog.tsx:32` (data da devolução; o servidor aceita data futura, `ComodatoController.php:131`);
  `comodatos/AjustarComodatoDialog.tsx:57,66`; `rh/tabs/PontoTab.tsx:10`; `gestao/McmmPage.tsx:22`;
  `financeiro/tabs/FinanceiroExtraTabs.tsx:172,212`; `fiscal/tabs/SpedTab.tsx:9`; `pagamentos/GasDoPovoProgramaTab.tsx:17`.
- **Reprodução:** abrir Financeiro → Malote às 21h30. **Esperado:** o turno de hoje. **Observado:** o filtro vem com a
  data de amanhã e a conferência sai vazia, bem no horário em que o malote é fechado.
- **Correção:** criar um helper `hojeLocal()` em `@/lib/format` (`getFullYear/getMonth/getDate`) e acrescentar um
  guardião que procure `toISOString().slice(0, 10)` em `features/`.

### R2-07 — Valor com "%", "R$" ou milhar vira `null` sem aviso: a contraproposta de desconto é ignorada e o teto de alçada some
- **Categoria:** valor errado / formatação brasileira · **Severidade:** ALTA · **Estado:** CONFIRMADO (código + simulação Node)
- **Onde:** `central-vendas/CentralVendasPage.tsx:147`: `Number(desconto.replace(',', '.'))`, e `NaN` vira `null` no
  JSON. O servidor trata `null` como "aprova o pedido inteiro": `CentralVendasService.php:115`
  (`$descontoAprovado ?? desconto_solicitado`). Em `central-vendas/AlcadasPage.tsx:134` o `valor_max` inválido também
  vira `null`, que significa "sem teto".
- **Evidência (Node):** `"10,5"→10.5`, `"1.250,50"→null`, `"R$ 5"→null`, `"5%"→null`, `"abc"→null`.
- **Reprodução:** a solicitação pede 10% e o gerente digita `5%` como contraproposta (o próprio docstring do serviço
  descreve "pediu 10%, sai com 5%") → Aprovar. **Esperado:** 5%, ou um erro de formato. **Observado:** sai aprovado
  o desconto pedido inteiro, sem aviso. No teto, `1.000,00` grava "sem teto".
- **Correção:** um `parseDecimalBR` central que recuse o que não for número (toast e sem envio); no servidor, exigir
  presença explícita para "aprovar como pedido" e não confiar em `null`.

### R2-08 — Baixa com desconto maior que a parcela gera recebimento NEGATIVO
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (teste R2-PROBE-C + código)
- **Onde:** `financeiro/tabs/BaixaDialog.tsx:26,70-72` (não há teto para o desconto); `CaixaController.php:155-160`
  (`desconto` só tem `gte:0`); `CaixaService.php:166-168` (`liquido = valor + juros + multa - desconto`; se for
  negativo, o "recebimento" **tira** dinheiro do caixa).
- **Observado (teste):** parcela de R$ 100, desconto de 150 → botão "Receber -R$ 50,00" habilitado → POST enviado
  com `desconto: 150`.
- **Correção:** `desconto <= valor + juros + multa` no servidor (422) e no formulário.

### R2-09 — Novo pedido: item com quantidade 0, vazia ou inválida some sem aviso
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (teste R2-PROBE-A)
- **Onde:** `NovoPedidoDialog.tsx:115` (`validos = itens.filter(... Number(quantidade) > 0)`) e `:141` (só os `validos`
  vão para o POST). A validação (`:117-124`) só exige "ao menos um item".
- **Observado:** dois itens na tela, um com Qtd "0" → POST com 1 item e toast "Pedido #… criado". A linha com 0
  continua visível até o fim.
- **Correção:** listar como erro a linha com produto e quantidade inválida (ou produto vazio) em vez de filtrar em silêncio.

### R2-10 — Novo pedido: Esc, clique fora ou "X" jogam fora o pedido montado sem perguntar
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (teste R2-PROBE-B)
- **Onde:** `NovoPedidoDialog.tsx:157` usa o `Dialog` cru; `fechar(false)` (`:72-79`) zera tudo. As outras telas usam
  `FormDialog`, que confirma o descarte (`components/ui/form-dialog.tsx:36-40`). Trocar o cliente também apaga os
  itens já digitados (`:169`).
- **Impacto:** durante a ligação, um Esc ou um clique fora apaga cliente, itens e preços.
- **Correção:** guarda de "sujo" igual à do `FormDialog` (cliente ou itens preenchidos → "Descartar pedido?").

### R2-11 — Mensagens de validação e de sessão chegam em inglês (ou como chave crua)
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- **Evidência:** na homologação, `en|en|The q field must be greater than 0.`. No `.env` local, `APP_LOCALE` e
  `APP_FALLBACK_LOCALE` estão como `pt_BR`, mas não existe diretório `lang/` → o tinker local devolveu
  `validation.gt.numeric`. Nenhum FormRequest define `messages()`.
- **Onde aparece:** toda tela que mostra `e.response.data.message` (ex.: `NovoPedidoDialog.tsx:150`,
  `BaixaDialog.tsx:44`) e `AsyncState` (`components/ui/async-state.tsx:30-32`). Sessão vencida aparece como
  "Unauthenticated."; rede fora, como "Network Error".
- **Correção:** instalar as traduções pt_BR (ex.: `laravel-lang`), definir `APP_FALLBACK_LOCALE=pt_BR` na imagem e
  traduzir no cliente os erros de transporte do axios.

### R2-12 — Sessão vencida na SPA: sem interceptor de 401, sem redirecionamento imediato, rascunho perdido
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código lido)
- **Onde:** `erp-novo/frontend/src/lib/api.ts:81-96` tem só interceptor de request. O `/me` só revalida no foco ou
  depois de 60 s (`lib/auth.tsx:63-86`; o padrão global é `refetchOnWindowFocus: false`, `main.tsx:24`). O token vale
  24 h (`config/sanctum.php:61`).
- **Reprodução:** um operador que entrou ontem às 8h trabalha hoje às 8h com a aba aberta. **Observado:** cada ação
  mostra "Unauthenticated."; ele só cai no login quando a janela perde e recupera o foco, e aí o formulário aberto
  (ex.: Novo pedido) é desmontado e o conteúdo se perde.
- **Correção:** um interceptor de 401 que invalide `['me']` e abra um modal de re-login sem desmontar a tela (ou que
  guarde o rascunho em `sessionStorage`).

### R2-13 — App do cliente: pedido sem idempotência, timeout de 20 s com "Sem conexão" e carrinho que não esvazia
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (carrinho cheio; ausência de chave); FORTE INDÍCIO (duplicata em rede lenta)
- **Onde:** `mobile-shared/src/http.ts:44` (timeout de 20 s) e `:70` (status 0 vira "Sem conexão. Verifique sua
  internet.", mesmo quando o servidor recebeu); `AppPedidoController.php:37-74` não tem chave de idempotência e o
  middleware `idempotente` só cobre `entregador` (`routes/api.php:878`); `PedidoMobileService` não deduplica.
  O carrinho só é limpo quando o pedido termina ou é pago (`pix.tsx:32`, `track.tsx:112`), nunca ao criar
  (`carrinho.tsx:93-101`).
- **Reprodução:** 3G lento → Finalizar → toast "Sem conexão" → o cliente tenta de novo → dois pedidos. Também:
  pedido criado → voltar da tela de acompanhamento → a Home ainda mostra o carrinho cheio com total, pronto para
  outro "Finalizar".
- **Correção:** `Idempotency-Key` gerada quando o carrinho é aberto, mais o middleware `idempotente` em
  `POST app/v1/pedidos`; `clearCart()` logo depois de criar.

### R2-14 — Carrinho mostra Total R$ 0,00 quando a cotação falhou ou não chegou, e deixa finalizar
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código lido)
- **Onde:** `carrinho.tsx:124-126` (`total = cotacao?.total ?? 0`) e `:256-264` (o botão só depende de `isPending`);
  a cotação é feita pela Home (`(tabs)/home/index.tsx:86-95`).
- **Impacto:** o cliente confirma vendo R$ 0,00 e é cobrado pelo preço real.
- **Correção:** desabilitar Finalizar sem cotação válida e mostrar "Calculando…" ou "Não foi possível calcular — tentar de novo".

### R2-15 — Perfil do app: opções que não são salvas e risco de apagar nascimento e CPF
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO (opções descartadas); FORTE INDÍCIO (apagamento)
- **Onde:** `app-gas-em-casa/src/app/(auth)/perfil-dados.tsx:56-58` envia só `nome, cpf, datanascimento`. As
  caixas "Possuo Convênio", "Programa Gás do Povo" e o campo "Sexo" (`components/templates/UserForm.tsx:186-257`)
  são exibidas e jogadas fora, e o servidor nem as aceita (`AppPerfilController.php:46-53`).
- **Apagamento:** o `UserForm` copia `user.*` para `useState` uma vez só (`UserForm.tsx:28-31`). Se `["perfil"]`
  ainda não estiver no cache quando a tela abre (ex.: o GET da Home falhou offline), o formulário fica vazio e
  "Salvar" manda `datanascimento: null`. O CPF só aparece quando gás-do-povo está marcado; nos outros casos o
  estado vazio é enviado como `cpf: ""`. Nos dois casos, o dado gravado é apagado. A hidratação normal vem do cache
  da Home (`HomeHeader.tsx:21-25`), por isso não classifico como confirmado.
- **Correção:** remover (ou implementar de verdade) as opções; renderizar o `UserForm` só com o `perfil` carregado
  (`key={perfil?.id}`); não enviar campo que o usuário não mexeu.
- *Commit 48b29f72 (data de nascimento):* a correção está certa (`dataNascimento.ts` + `date_format:Y-m-d`), e o
  `validateBirthDate` compartilhado recusa data incompleta. Não achei o mesmo padrão DD/MM em outros campos de data
  dos apps. Na SPA o nascimento usa `type="date"` com ISO (`ClienteFormPage.tsx:159`, `ClienteResource.php:26`): OK.

### R2-16 — Login do app recusa nomes reais e aceita símbolos
- **Severidade:** BAIXA→MÉDIA (bloqueia a entrada de clientes reais; nome alemão é comum na região de Guarapuava) · **Estado:** CONFIRMADO (Node)
- **Onde:** `app-gas-em-casa/src/app/login.tsx:47`: `/^[A-zÀ-ú\s]+$/`.
- **Evidência:** "Ana Müller" → false, "Maria D'Ávila" → false, "Ana-Maria Souza" → false; "Ivo [Admin]" → true,
  "Zé_Ninguém Silva" → true (`A-z` inclui `[ \ ] ^ _` e a crase).
- **Correção:** `/^[\p{L}][\p{L}'’\- ]+$/u`.

### R2-17 — CPF/CNPJ no cadastro admin: sem máscara, sem normalização, sem dígito verificador
- **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- **Onde:** `clientes/ClienteFormPage.tsx:175,177` (texto livre); `ClienteRequest.php:38-40` (`string|max:20`);
  `app/Rules/` só tem `ExisteNoTenant`; a busca faz `ilike` no texto cru (`ClienteController.php:45`).
- **Impacto:** a base mistura `123.456.789-09` com `12345678909`, a busca por CPF formatado não acha o cadastro só
  com dígitos, e um CPF inválido só aparece na rejeição da NF-e.
- **Correção:** `prepareForValidation` guardando só dígitos, regra de DV e máscara no campo.

### R2-18 — `input type="number"` em dinheiro: vírgula e roda do mouse (HIPÓTESE)
- **Severidade:** BAIXA · **Estado:** HIPÓTESE (o comportamento muda com navegador e locale; o jsdom não reproduz)
- **Onde:** `NovoPedidoDialog.tsx:212,215`, `BaixaDialog.tsx:70-72` e mais 87 inputs `type="number"` nas features.
- **Risco:** se o navegador considerar "12,50" inválido, `value` vira `''`. No pedido, isso faz o servidor usar o
  preço de venda (`:145`); na baixa, vira juros 0. Em ambos, sem aviso. A roda do mouse sobre um campo focado muda a quantidade.
- **Teste de aceite:** manual no Chrome e no Firefox em pt-BR e en-US, digitando `12,50`.

### R2-19 — Total do Novo pedido ignora item sem preço (BAIXA)
- **Onde:** `NovoPedidoDialog.tsx:116` (`Number('') || 0`) e `:144-145` (vazio → o servidor usa o preço de venda).
  Se a consulta de preço falhar (`:83-87` engole o erro), o total mostrado fica menor que o cobrado. **Estado:** CONFIRMADO (código).

### R2-20 — Concluir entrega sem idempotência (HIPÓTESE, BAIXA)
- `app/(app)/pedido/[id]/concluir.tsx:52-79` protege com `salvando` (o `Botao` fica inativo, `components/ui.tsx:37,47`),
  mas o multipart vai sem chave e `EntregaService.php:96-110` cria uma `PedidoComprovacao` a cada chamada. Dois
  toques no mesmo frame podem gravar duas comprovações; o estoque está protegido por `estoque_movimentado`.

---

## O que foi verificado e está bem (NÃO REPRODUZIDO / positivo)

- **Duplo clique em "Criar pedido":** 1 POST só (R2-PROBE-D). O `Button` desabilita com `loading`
  (`components/ui/button.tsx:40`).
- **Kanban:** mover um card para uma situação com efeito pede confirmação explícita (`KanbanView.tsx:230-238`).
- **Baixa em dobro:** `lockForUpdate` + "Parcela já baixada." (`CaixaService.php:151,161-163`).
- **Estoque:** `Idempotency-Key` estável entre tentativas, aceita pelo controller (`estoque/api.ts:117,129`;
  `EstoqueController.php:214`).
- **`FormDialog`:** confirma o descarte e bloqueia o fechamento durante o salvamento.
- **Formulários de página:** `useResourceForm` registra `beforeunload` e bloqueio de rota (`lib/useResourceForm.ts:64-71`, `UnsavedChanges.tsx`).
- **Acessibilidade básica:** `Field` liga `label`/`aria-describedby` e o erro sai com `role="alert"` (`components/ui/field.tsx`);
  botões só com ícone têm `aria-label` (ex.: `NovoPedidoDialog.tsx:219`). Tabela com `overflow-x-auto` e diálogo com
  `max-h` + rolagem: são responsivos no que o código mostra.
- **Data civil:** `format.ts:36-44` já corrige a data civil que voltava um dia.

---

## Oportunidades ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR → CRIAR

**Fundamentadas (com evidência acima):**
1. **ELIMINAR:** as caixas e o "Sexo" do perfil do app que não são salvos (R2-15). Isso também atende à minimização
   da LGPD: o dado coletado não tem uso.
2. **ELIMINAR:** o envio da hora pelo cliente na abertura e no fechamento de caixa; o servidor já sabe a hora (R2-05).
3. **JUNTAR:** uma busca única de cliente (nome sem acento, telefone por dígitos, CPF/CNPJ normalizado) usada pelo
   lookup do pedido, pela lista de clientes e pela bina (R2-01, R2-17).
4. **AUTOMATIZAR:** helpers centrais `parseDecimalBR` e `hojeLocal`, com um guardião que proíba
   `toISOString().slice(0,10)` e `Number(x.replace(',', '.'))` em `features/` (R2-06, R2-07).
5. **AUTOMATIZAR:** normalizar CPF, CNPJ e telefone no servidor (`prepareForValidation`) (R2-17).
6. **AUTOMATIZAR:** gerar PIX só para condição do tipo PIX, decidido pelo servidor (R2-02).
7. **ANTECIPAR:** um modal de re-login que preserve a tela, mais aviso antes do token de 24 h vencer (R2-12).
8. **CRIAR:** `Idempotency-Key` em `POST /pedidos` (admin) e `POST app/v1/pedidos`, reaproveitando o middleware
   `Idempotente` que já existe (R2-13).
9. **CRIAR:** traduções pt_BR do Laravel (R2-11).

**Hipóteses a validar com a operação:**
- O "Novo pedido" poderia abrir já com o cliente da chamada em curso (bina) e o "Repetir último pedido" aplicado:
  pedido em 2 passos. Depende de a telefonia estar ligada (`useFilaChamadas` faz polling de 5 s).
- O malote poderia ser fechado por entregador direto da lista de entregas do dia. Fica condicionado à decisão do
  dono, citada em `MaloteTab.tsx`.

---

## O que foi inspecionado, testado e NÃO testado

- **Inspecionado:** pedidos (Novo pedido, Kanban, api), financeiro (Baixa, Caixa, Malote, api), central-vendas
  (Decisão, Alçadas, Estoque franqueado), clientes (formulário, lista, request, controller), comodatos (devolução,
  ajuste), estoque (api, idempotência), lib (`api`, `auth`, `format`, `UnsavedChanges`, `useResourceForm`),
  componentes de UI (`Button`, `Input`, `Field`, `FormDialog`, `Dialog`, `AsyncState`); app do cliente (login,
  cadastro, perfil, carrinho, pix, track, http compartilhado, validadores); app do entregador (fila offline, envio
  resiliente, sincronização, concluir, http).
- **Testado:** 4 testes-sonda vitest (A–D) e 5 arquivos de teste existentes (15 testes), todos verdes; simulações
  Node (UTC, `parseFloat`, regex de nome); leitura de locale no contêiner de homologação.
- **NÃO testado:** nenhum fluxo em navegador ou aparelho real; comportamento do `type="number"` por navegador
  (R2-18); contraste de cores (não dá para verificar sem renderizar); leitor de tela; tamanhos de tela reais;
  testes de backend (não rodei a suíte; a baseline está em execução por outro processo); app do cliente com o
  Firebase real.
- **Limitações:** a severidade considera a operação de disk-gás descrita no `CLAUDE.md`. Os cenários "rede lenta"
  e "token vencido" foram derivados do código, não observados.

## Parecer da área (Produto/UX)

**REPROVADO.** Há falhas confirmadas que, pelo critério 2.4 do protocolo, causam **perda de dado** (R2-04),
**valor errado** (R2-07, R2-08, R2-05) e **falha na jornada essencial** (R2-01: o atendente não encontra o cliente
pelo telefone; R2-02: cliente de dinheiro mandado para o PIX). Nenhuma é de correção cara. R2-01, R2-02, R2-03,
R2-04 e R2-07 devem entrar antes da liberação. O restante pode ir como ressalva com prazo.

## Resumo

| ID | Sev. | Estado | Onde |
|---|---|---|---|
| R2-01 Busca "nome ou telefone" só busca nome | ALTA | CONF. | `LookupController.php:201`; `NovoPedidoDialog.tsx:165` |
| R2-02 Todo pedido do app vai ao PIX; aviso de cancelamento falso | ALTA | CONF. | `carrinho.tsx:91-101`; `PixService.php:64-90,211-218`; `pix.tsx:114` |
| R2-03 Fila do entregador troca a chave de idempotência | ALTA | CONF. | `envioResiliente.ts:41,60`; `filaOffline.ts:85-86` |
| R2-04 Fila descarta em 401 e não limpa no logout | ALTA | CONF./F.I. | `filaOffline.ts:163-167,105` |
| R2-05 Caixa abre/fecha com hora UTC (+3h) | MÉDIA | CONF. | `CaixaTab.tsx:15` |
| R2-06 "Hoje" em UTC após 21h (malote, comodato, ponto…) | MÉDIA | CONF. | `MaloteTab.tsx:10`; `DevolucaoDialog.tsx:32` |
| R2-07 "5%"/"1.000,00" vira null → desconto pedido aprovado / sem teto | ALTA | CONF. | `CentralVendasPage.tsx:147`; `AlcadasPage.tsx:134` |
| R2-08 Desconto > parcela → recebimento negativo | MÉDIA | CONF. (teste) | `CaixaService.php:166-168`; `BaixaDialog.tsx:26` |
| R2-09 Item com qtd 0 some do pedido | MÉDIA | CONF. (teste) | `NovoPedidoDialog.tsx:115,141` |
| R2-10 Esc descarta o pedido sem confirmar | MÉDIA | CONF. (teste) | `NovoPedidoDialog.tsx:72-79,157` |
| R2-11 Mensagens em inglês / chave crua | MÉDIA | CONF. | homolog `en`; sem `lang/` |
| R2-12 SPA sem interceptor de 401 | MÉDIA | CONF. | `lib/api.ts:81-96`; `lib/auth.tsx:63-86` |
| R2-13 Pedido do app sem idempotência; carrinho não limpa | MÉDIA | CONF./F.I. | `http.ts:44,70`; `carrinho.tsx:93-101` |
| R2-14 Total R$ 0,00 sem cotação e finalizar liberado | MÉDIA | CONF. | `carrinho.tsx:124-126,259-264` |
| R2-15 Perfil descarta opções; risco de apagar nascimento/CPF | MÉDIA | CONF./F.I. | `perfil-dados.tsx:56-58`; `UserForm.tsx:28-31` |
| R2-16 Regex de nome recusa "Müller", aceita "[" | BAIXA-MÉDIA | CONF. | `login.tsx:47` |
| R2-17 CPF/CNPJ sem normalização nem DV | MÉDIA | CONF. | `ClienteRequest.php:38-40` |
| R2-18 `type=number` com vírgula | BAIXA | HIPÓTESE | `NovoPedidoDialog.tsx:215` |
| R2-19 Total ignora item sem preço | BAIXA | CONF. | `NovoPedidoDialog.tsx:116` |
| R2-20 Concluir entrega sem idempotência | BAIXA | HIPÓTESE | `EntregaService.php:96-110` |
