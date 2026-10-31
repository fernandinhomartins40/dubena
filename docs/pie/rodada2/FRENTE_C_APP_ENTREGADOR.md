# Frente C — App do entregador (`app-entregador`)

**Rodada 2 da auditoria PIE V2** · **Data:** 2026-10-30 · **Commit:** `133f588d` (main)
**Revisor:** Frente C (passagem própria; mesmo modelo das demais frentes, sem alegar independência real)
**Escopo:** o app como ferramenta de trabalho de campo, e o backend que ele chama
(`routes/api.php:878-937`, `Api/Mobile/AppEntregadorController`, `AppMissaoController`,
`AppSolicitacaoController`, `Domain/Mobile/EntregaService`, `Domain/Logistica/RoteirizadorService`,
`Middleware/Idempotente`, `Domain/Caixa/MaloteService`).

Nada do projeto foi alterado. Sondas e saídas ficam no scratchpad da sessão
(`...\scratchpad\c\sonda\` — vitest; `...\scratchpad\c\sonda-php\` — PHPUnit), citadas abaixo com a saída real.

---

## 0. Resumo executivo

O app cobre o caminho feliz — login, jornada com checklist, lista, rota, comprovação com
foto/assinatura, ocorrência, missão de campo e solicitação à Central — e o typecheck passa.
Como **ferramenta de campo que mexe com dinheiro**, porém, ele não está pronto:

1. **Dinheiro:** a entrega é concluída sem registrar *como* o cliente pagou, quanto entregou
   ou o troco (P2-C-03); o entregador pode "recusar" um pedido **já concluído** e ele some do
   próprio malote (P2-C-01, provado); a verificação de Vale Gás aprova vale **não pago,
   expirado e vencido**, e nunca o consome (P2-C-02, provado).
2. **Operação:** depois de "Iniciar rota", **todas** as entregas vão para a aba *Histórico* e
   somem do Início, porque o app classifica a situação pelo texto e "Saiu para entrega" contém
   "entreg" (P2-C-04, provado).
3. **Offline:** a correção do PIE-16 está **comprovada no seu escopo** (chave preservada, 401
   preserva, dono separa — 10 sondas verdes). Mas a etapa que mais importa — **concluir a
   entrega** — não entra na fila nem é idempotente (P2-C-05, provado), e a fila descarta em
   silêncio o que o servidor recusa e trava para sempre com um item envenenado, sem tela para
   o entregador ver (P2-C-06, provado).

**Parecer: REPROVADO** como ferramenta de campo para produção (detalhe em §9). PIE-16 e PIE-18:
**correções confirmadas**, com lacunas novas registradas como achados próprios.

---

## 1. Dia do entregador: passo × tela × endpoint × offline

Legenda offline: **F** = entra na fila offline com Idempotency-Key · **R** = exige rede, falha
com toast · **M** = leitura só em memória (perde no reinício) · **—** = não existe.

| # | Passo do dia | Tela (`src/app/...`) | Endpoint (`app/v1/...`) | Sem rede | Observação |
|---|---|---|---|---|---|
| 1 | Login (e-mail/senha, 2FA 423) | `login.tsx` | `POST login` | R | Token de 24 h sem renovação (P2-C-09) |
| 2 | Início do turno: veículo, km, checklist | `(app)/iniciar-jornada.tsx` | `GET entregador/veiculos`, `POST entregador/jornada/iniciar` | R | Checklist de 5 itens fixo no código (P2-C-19) |
| 3 | Divulgação + permissão de GPS em 2º plano | `helpers/backgroundLocation.ts:39-89` | `POST entregador/posicao` (15 s / 25 m) | descarta | Ping sem jornada → 422 (sonda 4) |
| 4 | Carga do caminhão / estoque embarcado | — (só "Comigo agora" do franqueado em `ganhos.tsx`) | `GET entregador/estoque` | M | Entregador CLT não tem conferência de carga nem vasilhame (P2-C-13) |
| 5 | Receber entrega nova | polling 30 s em `inicio.tsx`/`entregas.tsx` | `GET entregador/pedidos` | M | Sem push, sem tempo real (P2-C-10) |
| 6 | Ver a rota / ordem das paradas | `(tabs)/rota.tsx` | `GET entregador/rota` | M | Vizinho-mais-próximo recalculado a cada consulta (P2-C-12) |
| 7 | Iniciar rota (avisa clientes) | `(tabs)/rota.tsx:96` | `POST entregador/rota/iniciar` | R | Sem confirmação; depois disso a lista some (P2-C-04) |
| 8 | Navegar | `navegacao.tsx` (interna) / `pedido/[id]/index.tsx:75` (Maps externo) | `GET entregador/rota` a cada 20 s | M | Sem curva-a-curva; sem Waze (P2-C-12) |
| 9 | Aceitar / recusar corrida | `pedido/[id]/index.tsx:128,139` | `POST pedidos/{id}/aceitar` / `recusar` | F | Sem confirmação; vale em pedido concluído (P2-C-01) |
| 10 | Ver o que entregar (itens, troca de vasilhame, forma de pagamento, troco, telefone, observação) | `pedido/[id]/index.tsx` | `GET entregador/pedidos` | M | **Nenhum desses campos vem da API** (sonda 5; P2-C-03) |
| 11 | Receber dinheiro / PIX / cartão, troco, parcial | — | — | — | **Não existe** (P2-C-03) |
| 12 | Vale Gás do cliente | `vale-gas.tsx` (digitação; sem leitor) | `POST entregador/vale-gas/verificar` | R | Aprova não pago/expirado/vencido; não consome (P2-C-02) |
| 13 | Comprovar e concluir (foto e/ou assinatura) | `pedido/[id]/concluir.tsx` | `POST pedidos/{id}/concluir` (multipart) | **R** | Fora da fila, sem chave; reenvio → 422 (P2-C-05) |
| 14 | NFC-e / NF-e em campo, cupom térmico | — | `fiscal/emitir`, `pedidos/{id}/cupom` existem | — | API pronta, **sem tela** (P2-C-15) |
| 15 | Ocorrência (ausente, endereço errado, avaria) | `pedido/[id]/ocorrencia.tsx` | `POST pedidos/{id}/ocorrencia` (multipart) | R | Tipo "recusou" do enum não aparece; sem chave |
| 16 | Venda avulsa na rua (missão) | `missao-venda.tsx` | `POST missao/venda` (cria pedido **concluído**) | R | Sem condição de pagamento; reenvio duplica venda (P2-C-05) |
| 17 | Venda pela Central (franqueado/industrial) | `clientes.tsx` → `solicitar-venda.tsx` | `POST entregador/solicitacoes` | F | 4xx no reenvio some em silêncio (P2-C-06) |
| 18 | Cadastro de cliente na porta | `cliente-novo.tsx`, `missao-venda.tsx` | `POST entregador/clientes` (F) / `missao/clientes` (R) | F/R | Erro 4xx aparece em inglês (P2-C-08) |
| 19 | Retorno / encerrar jornada | `inicio.tsx:45`, `perfil.tsx:185` | `POST entregador/jornada/encerrar` | R | `km_final` sempre `null`; não confere pendências nem fila |
| 20 | Acerto / fechamento com o caixa | — no app; `MaloteService` no painel | `GET malotes/conferencia` (admin) | — | Entregador não vê o próprio acerto (P2-C-13) |
| 21 | Ganhos (franqueado) / minhas vendas | `ganhos.tsx`, `relatorio-vendas.tsx` | `GET extrato`, `GET relatorio-vendas` | M | Único lugar que mostra "ações sem sinal" |
| 22 | Sair | `perfil.tsx:191-218` | `POST logout` | R | Avisa e limpa a fila (PIE-16 OK) |

Telas que **não existem** e o entregador precisa: forma de pagamento/valor recebido/troco na
conclusão; carga embarcada e vasilhames; "meu acerto do dia"; pendências de sincronização com
ação (reenviar/descartar); emissão fiscal e impressão (perfil industrial); leitor de código de barras do vale.

---

## 2. Verificação das correções da Rodada 1

### PIE-16 (fila offline) — correção **COMPROVADA no escopo original**; lacunas novas em P2-C-05/06/08

Sondas vitest em `scratchpad\c\sonda\fila.test.ts`, importando os módulos **reais**
`src/helpers/filaOffline.ts` e `src/helpers/envioResiliente.ts`, com storage, store e HTTP
substituídos por dublês. Saída real (`saida-vitest.txt`), **10/10 verdes**:

```
[A] chave 1a tentativa: a4d0deed-acea-4429-b5b8-e3069bf792d8 | id na fila: a4d0deed-acea-4429-b5b8-e3069bf792d8 | dono: 1
[A] reenvio chave: a4d0deed-acea-4429-b5b8-e3069bf792d8 | resultado: { enviados: 1, falharam: 0, restantes: 0 }
[B] apos 401: { enviados: 0, falharam: 1, restantes: 1 } [ 'k1' ]
[C] requisicoes enviadas com B logado: 0
[C2] item sem dono enviado com user 2: 1
[D] resultado: { enviados: 0, falharam: 1, restantes: 0 } | restam na fila: 0 | algum registro do erro guardado? [ [ 'fila-offline-v1', '[]' ] ]
[E] sync1: { enviados: 0, falharam: 1, restantes: 0 } sync2: { enviados: 1, falharam: 0, restantes: 0 } | restam: 0
[F] apos 12 janelas: [ { end: 'x/envenenado', tentativas: 12, erro: 'Não sincronizou após 8 tentativas. Request failed with status code 500' },
                       { end: 'x/legitimo', tentativas: 0, erro: undefined } ]
[F] o legitimo chegou ao servidor? false
[G] ordem vista pelo servidor: [ 'status', 'aceitar' ]
[H] e.message que vai ao Toast: "Request failed with status code 422"
[I] apos reinicio: [ 'kI1', 'kI2' ]
 Test Files  1 passed (1)   Tests  10 passed (10)
```

| Item do PIE-16 | Resultado |
|---|---|
| R2-03: reenvio com a mesma chave | **Provado** (A) |
| R2-04: 401 não apaga | **Provado** (B) |
| R2-04: item de outro entregador não sai com o token errado | **Provado** (C). Resíduo: item gravado antes da correção (sem `dono`) sai com o token de quem estiver logado (C2) — BAIXA, só afeta filas antigas |
| R2-04: logout limpa a fila | Confirmado por leitura: `perfil.tsx:191-218` avisa e chama `limparFila()` |
| App morto com fila cheia | **Provado** que a fila sobrevive (I): é regravada no MMKV a cada operação e o `useSincronizacao` do `(app)/_layout.tsx:23` reenvia no boot |

Fora do escopo do PIE-16, mas descobertos na mesma sonda: D, E, F, G (→ P2-C-06) e H (→ P2-C-08).
A mensagem H foi reconfirmada com o **axios real do app (1.18.1)** contra um servidor HTTP de
verdade (`mensagem.test.ts`): `e.message = "Request failed with status code 422"`, enquanto
`e.response.data.message = "Já existe cliente com este telefone."`.

### PIE-18 (typecheck) — **CONFIRMADO**

`cd app-entregador && npx tsc --noEmit` → **EXIT=0** (saída vazia). O app continua **fora do CI**:
`.github/workflows/` só tem `erp-novo.yml`, e ele não cita `app-entregador`. E continua **sem
nenhum teste** e sem runner (`package.json` não tem `test`).

---

## 3. Achados

### P2-C-01 — "Recusar" num pedido já concluído desvincula o entregador e tira o pedido do malote dele
- **Categoria:** dinheiro / máquina de estados · **Severidade:** ALTA — quem tem o dinheiro na mão consegue apagar a cobrança do próprio acerto, com um toque e sem confirmação · **Estado:** CONFIRMADO (sonda PHP)
- **Onde:** `erp-novo/app/Domain/Mobile/EntregaService.php:46-61` (recusar não confere a situação; grava `entregador_user_id = null`); `:36-43` (aceitar idem). App: `pedido/[id]/index.tsx:128,139` mostra "Aceitar" e "Recusar" em **todo** pedido, inclusive os da aba Histórico, e `recusar` (`:61`) não pede confirmação.
- **Reprodução:** concluir um pedido em dinheiro → abrir o pedido no Histórico → "Recusar corrida".
- **Esperado × observado:** recusa só de pedido pendente (409/422 nos demais) × HTTP 201, pedido continua CONCLUÍDO e sem entregador.
- **Evidência** (`scratchpad\c\sonda-php\SondaFrenteCTest.php`, PHPUnit fora do projeto):
  ```
  [SONDA-1] recusar pedido CONCLUIDO -> HTTP 201 | entregador_user_id=NULL | situacao efeito=CONCLUIDO |
            malote do entregador: antes 1 pedido(s) R$ 100.00 a baixar; depois 0 pedido(s) R$ 0.00
  [SONDA-2] aceitar pedido CONCLUIDO -> HTTP 200 | datahora_acao 2026-10-30 16:09:58 -> 2026-11-01 16:09:58
  ```
  `MaloteService::conferir` filtra por `entregador_user_id` (`MaloteService.php:70`), por isso o pedido some da conferência do entregador.
- **Impacto:** o conferente do malote não vê o pedido; o título continua aberto e cai na cobrança do cliente que já pagou. Também acontece por toque acidental, porque o botão vermelho fica sempre visível.
- **Correção:** `recusar` e `aceitar` só com efeito PENDENTE, sob `lockForUpdate` (mesmo padrão de `mudarSituacao` com `$origensPermitidas`); no app, mostrar as ações conforme a situação e confirmar a recusa com motivo.
- **Aceite:** teste de feature: recusar/aceitar pedido CONCLUÍDO ou CANCELADO → 422, `entregador_user_id` e `datahora_acao` inalterados.
- **Esforço:** P

### P2-C-02 — A verificação de Vale Gás aprova vale não pago, expirado e vencido, e nunca o consome
- **Categoria:** proteção de receita · **Severidade:** ALTA — o vale é dinheiro em papel e o app é o que o entregador consulta para aceitá-lo · **Estado:** CONFIRMADO (sonda PHP)
- **Onde:** `AppSolicitacaoController.php:261-290` só recusa situação contendo "cancel" ou "utilizad"; ignora `EMITIDO` (aguardando pagamento) e `EXPIRADO` (`SituacaoValeGas`), não compara `validade` com hoje e não marca o vale como utilizado. O app (`vale-gas.tsx`) só exibe o resultado e não liga o vale ao pedido nem à conclusão.
- **Evidência:**
  ```
  [SONDA-6] vale EMITIDO (nao pago)        -> 1a verificacao HTTP 200, 2a verificacao HTTP 200
  [SONDA-6] vale EXPIRADO                  -> 1a verificacao HTTP 200, 2a verificacao HTTP 200
  [SONDA-6] vale PAGO com validade vencida -> 1a verificacao HTTP 200, 2a verificacao HTTP 200
  [SONDA-6] vale PAGO valido (2x seguidas) -> 1a verificacao HTTP 200, 2a verificacao HTTP 200
  ```
- **Impacto:** o mesmo vale é aceito em duas portas, por dois entregadores, e um vale nunca pago passa como bom.
- **Correção:** aceitar só `PAGO` dentro da validade; resgatar (`utilizado_em`, `pedido_id`) **na conclusão do pedido**, numa transação com lock, e não numa consulta solta; no app, o vale vira forma de pagamento da tela de conclusão (P2-C-03).
- **Aceite:** EMITIDO/EXPIRADO/vencido → 422 com mensagem; o 2º resgate do mesmo código → 422; um teste de concorrência com dois resgates simultâneos.
- **Esforço:** M

### P2-C-03 — A entrega é concluída sem pagamento: o app não registra forma, valor recebido, troco, parcial nem vasilhame, e não mostra o que entregar
- **Categoria:** dinheiro em campo / completude · **Severidade:** ALTA · **Estado:** CONFIRMADO
- **Onde:** `GET entregador/pedidos` (`AppEntregadorController.php:168-190`) devolve só `id, valor_venda, situacao, cliente, endereco, lat, lng`; `PedidoEntrega` (`types.ts:10-18`) idem. `concluir.tsx` envia só `recebido_por`, coordenadas, foto e assinatura. A venda de missão (`missao-venda.tsx:80`) não manda `condicaopagamento_id`, que a API aceita (`AppMissaoController.php:164`), e o pedido nasce "Sem condição".
- **Evidência:** `[SONDA-5] campos de cada pedido no app: id,valor_venda,situacao,cliente,endereco,lat,lng`.
- **Esperado × observado:** na porta, o entregador precisa saber os itens, a troca do vasilhame, a forma de pagamento combinada, se leva troco e o telefone para ligar. O app mostra nome, endereço e valor.
- **Impacto:** o título nasce pela condição do pedido (`PedidoService.php:217-244`). Se o cliente paga em PIX em vez de dinheiro, ou paga só parte, o malote espera o valor errado do entregador e a diferença aparece no fim do turno, sem rastro de onde veio. O preço **não** é calculado no app (correto: o servidor decide), mas o valor recebido também não é registrado em lugar nenhum.
- **Correção:** a lista passa a trazer itens, condição de pagamento, troco, observação, telefone e `efeito`/`papel` da situação. A conclusão ganha "como pagou" (dinheiro/PIX/cartão/vale/fiado conforme as condições do tenant), "valor recebido", "troco" e "vasilhame recolhido". O backend grava e, se a forma mudou, ajusta a condição antes de gerar o título.
- **Aceite:** concluir com forma diferente da combinada → título gerado na forma informada; malote por entregador soma por forma **efetiva**.
- **Esforço:** G

### P2-C-04 — Depois de "Iniciar rota", todas as entregas vão para "Histórico" e somem do Início
- **Categoria:** funcionamento / UX de campo · **Severidade:** ALTA — o entregador abre a aba Entregas e não acha o que tem a fazer · **Estado:** CONFIRMADO (sonda)
- **Onde:** `entregas.tsx:117-127` e `inicio.tsx:57` classificam pela **descrição** da situação; `"entreg"` conta como finalizado. `EntregaService::iniciarRota` move tudo para a situação de papel EM_ROTA, chamada "Saiu para entrega" no `HomologSeeder.php:204`.
- **Evidência** (função copiada literalmente do app, `scratchpad\c\sonda-abas.txt`):
  ```
  Saiu para entrega      -> aba: historico | aparece em Proximas (Inicio)? false
  Aguardando entrega     -> aba: historico | aparece em Proximas (Inicio)? false
  Em trânsito            -> aba: pendentes | ...
  ```
- **Impacto:** é exatamente o momento em que o entregador está na rua. Para outra revenda, com outros nomes, a classificação muda de novo. O backend já resolveu esse problema com `efeito`/`papel` (`PapelSituacao`, F3-04A), mas o app não recebe esses campos.
- **Correção:** expor `efeito` e `papel` na lista e classificar por eles; o texto só serve para exibição.
- **Aceite:** teste unitário da classificação com descrições arbitrárias ("Saiu para entrega", "En camino") produzindo a mesma aba para o mesmo papel.
- **Esforço:** P

### P2-C-05 — Concluir a entrega (e a venda de missão) exige rede e não é idempotente: o reenvio dá erro ou duplica
- **Categoria:** offline / consistência · **Severidade:** ALTA · **Estado:** CONFIRMADO (conclusão, sonda PHP); FORTE INDÍCIO (duplicação da venda de missão, por leitura)
- **Onde:** `entrega.service.ts:181-186` deixa `Concluir` e `Ocorrencia` fora da fila "por serem multipart"; `mobile-shared/src/http.ts:126-134` (`PrepareForm`) não envia `Idempotency-Key`, embora a rota tenha o middleware `idempotente` (`routes/api.php:881`). Timeout de 20 s (`http.ts:44`) para foto de câmera com `quality: 0.6` (`camera.ts:246`). `missao.service.ts:395-416` (VenderGas) também é multipart sem chave, e cada chamada cria **um pedido novo concluído** com baixa de estoque.
- **Reprodução:** 3G fraco → "Concluir entrega" → o upload passa de 20 s → toast de erro → tocar de novo.
- **Evidência:**
  ```
  [SONDA-3] 2o concluir -> HTTP 422 msg="O pedido mudou de situação (Concluido) e esta ação não vale mais." |
            arquivos no disco=2 | comprovacoes no banco=1
  ```
- **Impacto:** (a) sem sinal, a entrega simplesmente não conclui, que é o oposto do que F7 prometia para a rota; (b) com sinal ruim, a primeira tentativa grava e a segunda mostra um erro que faz o entregador achar que não registrou; (c) arquivo órfão no disco a cada reenvio; (d) na venda de missão, o reenvio faz **duas vendas concluídas e baixa o estoque duas vezes** (FORTE INDÍCIO: não há guarda de estado porque o pedido é novo).
- **Correção:** chave de idempotência em todo POST do entregador, incluindo multipart (`PrepareForm(endpoint, form, {chave})`). Fila de multipart com o arquivo copiado para `documentDirectory` e referenciado por caminho, sem base64 no MMKV. Timeout de upload maior que o de JSON. No servidor, `concluir` repetido pelo mesmo entregador devolve a comprovação existente (200), não 422.
- **Aceite:** o mesmo `Idempotency-Key` em dois `POST concluir` → um único 201 repetido e um arquivo; o mesmo em `missao/venda` → um único pedido; sonda vitest da fila com item multipart persistido e reenviado após reinício.
- **Esforço:** M

### P2-C-06 — A fila descarta em silêncio o que o servidor recusa, perde item em 409 "em processamento" e trava com um item envenenado, sem tela de pendências
- **Categoria:** offline / perda de dado · **Severidade:** ALTA — a tela diz "Guardado. Vai assim que a rede voltar", e o que não foi não é mostrado a ninguém · **Estado:** CONFIRMADO (sondas D, E, F, G)
- **Onde:** `filaOffline.ts:187-191` (4xx → `descartar` sem registro); `:171-175` (todo 409 é tratado como sucesso, mas `Idempotente.php:70-74,94-97` devolve 409 também para "ainda em processamento" e solta a chave se a 1ª execução falhar, `:110-113`); `:193-207` (5xx mantém e faz `break`: o item na cabeça bloqueia o resto para sempre, mesmo depois de `MAX_TENTATIVAS`); `envioResiliente.ts:42-58` envia ação nova direto, à frente das pendentes. O comentário de `filaOffline.ts:200-201` cita uma "tela de pendências" que **não existe**: `pendentes()` não é chamada por nenhuma tela, e só `ganhos.tsx` (Perfil → Ganhos, área do franqueado) mostra a **contagem**.
- **Evidência:** sondas D (422 → fila vazia, nada guardado), E (duas sincronizações concorrentes — `useSincronizacao` é montado no `_layout` **e** em `ganhos.tsx`, com travas `useRef` separadas; a 1ª termina 500, a 2ª recebe 409 → item perdido sem nunca ter sido gravado), F (12 janelas, o item legítimo nunca chega ao servidor), G (servidor recebe `status` antes de `aceitar`).
- **Cenário real (D):** o entregador solicita uma venda sem sinal; a Central desativa o cliente ou o desconto passa da alçada; no reenvio vem 422; a solicitação some e ninguém é avisado.
- **Correção:** 4xx vira estado "recusado pelo servidor" com a mensagem, numa tela de pendências (reenviar/descartar/ver) acessível pelo Início, com um selo na tab. Distinguir os dois 409 (o middleware pode devolver um código ou cabeçalho próprio para "em processamento", que o app trata como "tentar depois"). Item com `MAX_TENTATIVAS` sai da cabeça da fila. Trava única de sincronização no módulo, não por hook. `enviarOuEnfileirar` esvazia a fila antes de enviar ação nova, ou enfileira atrás dela.
- **Aceite:** as sondas D–G invertidas passam a falhar com o código atual e passam com a correção (adotar vitest no app, como já existe no `app-gas-em-casa`).
- **Esforço:** M

### P2-C-07 — Rastreamento pode continuar fora do turno; com o app morto, a tarefa em 2º plano envia sem token
- **Categoria:** LGPD / bateria · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (sem encerramento automático, ping recusado sem jornada); FORTE INDÍCIO (envio sem token no modo headless); HIPÓTESE (consumo)
- **Onde:** `emServico` é persistido (`appStore.ts:24-47`, sem `partialize`) e só é recalculado quando `useJornada` roda — e ele só é montado em `inicio.tsx` e `perfil.tsx`. Não há rotina que encerre jornada esquecida: `routes/console.php` só agenda missões (linha 55). Com o app removido da memória, a task de `backgroundLocation.ts:17-33` roda sem a árvore React; `useAppStore` usa `skipHydration` e a hidratação está em `_layout.tsx:34`, então o `getToken` devolve `""`.
- **Evidência:** `[SONDA-4] posicao sem jornada -> HTTP 422 msg="Inicie a jornada para operar."` — o servidor só recusa **depois** que a jornada é encerrada. A política (`resources/views/publico/privacidade.blade.php:30`) promete que o rastreamento "termina quando a jornada é encerrada ou o entregador sai do aplicativo".
- **Impacto:** se o entregador esquece de encerrar, o servidor aceita a posição dele em casa a noite toda (jornada ativa), o que viola a minimização da LGPD e a própria política. Se a Central encerra a jornada, o GPS do aparelho segue ligado até alguém abrir o Início. No modo headless, um ping a cada 15 s que só gera 401 gasta bateria.
- **Correção:** encerramento automático configurável por tenant (horário-limite e/ou N minutos sem movimento e sem entrega ativa); a task consulta `jornada` a cada N pings e chama `pararBackground()` em 422/401; hidratar a store dentro da task, ou guardar o token num lugar legível sem a árvore React.
- **Aceite:** comando agendado que encerra jornada vencida, com teste; com o app morto (teste em aparelho físico), um 422 desliga a task.
- **Esforço:** M

### P2-C-08 — Erro de validação aparece ao entregador em inglês genérico
- **Categoria:** UX · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (sonda com axios real 1.18.1)
- **Onde:** `envioResiliente.ts:45,57` usa `Http.client.request` (axios cru) e relança o `AxiosError`; as telas mostram `e.message` (`cliente-novo.tsx` "422 do DomainException: telefone duplicado", `solicitar-venda.tsx`, `pedido/[id]/index.tsx:55,69`). O núcleo `@shared/http` normaliza a mensagem, mas esse caminho não passa por ele.
- **Evidência:** `[H-real] versao axios: 1.18.1 | e.message = "Request failed with status code 422" | e.response.data.message = "Já existe cliente com este telefone."`
- **Correção:** normalizar o erro em `enviarOuEnfileirar` (`{status, message: data.message, errors}`), como faz `normalizeError` em `mobile-shared/src/http.ts:63-78`.
- **Aceite:** sonda H passa a ver a mensagem do servidor.
- **Esforço:** P

### P2-C-09 — Token de 24 h sem renovação: o entregador é deslogado no meio da rua
- **Categoria:** continuidade operacional · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (config + código)
- **Onde:** `config/sanctum.php:61` e `.env.production.example:142` (`SANCTUM_EXPIRATION=1440`); `auth.service.ts:157` define `Refresh`, que **nenhuma tela chama**. No 401, `helpers/http.ts:12-15` faz `logout()`: `emServico=false` e o GPS para, enquanto a jornada continua ativa no servidor.
- **Impacto:** 24 h depois do último login, o entregador cai para a tela de login durante o turno e precisa digitar e-mail, senha e talvez 2FA na calçada; a Central perde o rastreamento até ele entrar de novo.
- **Correção:** chamar `token/refresh` no boot e ao voltar ao 1º plano quando faltar menos de X h; no 401 com jornada ativa, levar ao login sem desligar o estado local.
- **Aceite:** teste com relógio adiantado: o app renova antes de expirar.
- **Esforço:** P

### P2-C-10 — Entrega nova só aparece com o app aberto: sem push e sem tempo real
- **Categoria:** completude · **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- **Onde:** não há `expo-notifications` no `package.json`; `RegisterDevice` (`auth.service.ts:160`) nunca é chamado e o login não manda `push_token`; `connectRealtime` (`helpers/realtime.ts:26`) nunca é chamado (`laravel-echo` e `pusher-js` são dependências mortas). `app.json` declara `aps-environment: production` sem uso.
- **Impacto:** com o app no bolso (justamente o caso do GPS em 2º plano), o entregador não fica sabendo de pedido novo ou cancelado até abrir o app; o polling é de 30 s com a tela aberta.
- **Correção:** push via FCM do lado do servidor (a infraestrutura de devices já existe) para atribuição e cancelamento; Reverb quando configurado.
- **Esforço:** M

### P2-C-11 — Sem leitura offline depois de reiniciar, e as listas mostram "vazio" em vez de "sem conexão"
- **Categoria:** offline · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código)
- **Onde:** React Query só em memória (`_layout.tsx:18-20`, sem `persistQueryClient`); `entregas.tsx:50-54` mostra "Nenhuma entrega nesta aba." quando a consulta falha; `pedido/[id]/index.tsx:87-92` mostra "Entrega não encontrada na lista.". O plano F7 previa "cache: produtos, preços, clientes da rota" (`APPS_DE_CAMPO...md:579`), e isso não foi feito.
- **Impacto:** se o app reinicia num lugar sem sinal, o entregador perde endereço, cliente e rota. Ficou abaixo até do MovelApp legado, que é offline para **leitura** da rota (`APPS_DE_CAMPO...md:195`).
- **Correção:** persistir as consultas `entregador/*` no MMKV (persister do React Query) e diferenciar erro de vazio em todas as listas.
- **Esforço:** P

### P2-C-12 — Rota: a ordem é recalculada a cada consulta, ignora a ordem de chegada e o agendamento, e a "navegação" não dá instruções
- **Categoria:** produto / mapa · **Severidade:** MÉDIA · **Estado:** FORTE INDÍCIO (leitura; testes do roteirizador verdes)
- **Onde:** `RoteirizadorService.php:41-101` começa da **última posição** (`EntregadorPosicao`) e ordena por vizinho-mais-próximo (`:116-144`) a cada `GET rota`, chamado a cada 20 s em `navegacao.tsx` e 30 s em `rota.tsx`; não considera horário do pedido, agendamento nem prazo prometido.
- **Commit `133f588d`** (revisado com `git show`): a correção do `key` no `MapView` resolve o "mapa preso no Brasil" em `rota.tsx:105` e `pedido/[id]/index.tsx:100`. Mas a mensagem diz "enquadra as paradas" e o código **centraliza na 1ª parada com delta fixo de 0,04°** (`rota.tsx:55`, uns 4,4 km): paradas mais distantes ficam fora do quadro (não há `fitToCoordinates`). A reta estimada tracejada (`#9CA3AF`) tem contraste de 2,27:1 sobre mapa claro. `comGeo` e `paradas` são arrays novos a cada render, então o `useMemo` dos trechos nunca memoriza (decodifica as polylines a cada 3 s na navegação). `moeda.ts` está correto (milhar e negativo).
- **Impacto:** a "próxima parada" pode trocar no meio do trajeto e o ETA do cliente oscila; o cliente que pediu primeiro pode ficar por último. A navegação interna mostra o traçado mas não fala "vire à direita"; só o detalhe do pedido abre o Google Maps/Apple Maps (`index.tsx:75-85`), sem Waze, e a tela de navegação não tem esse atalho.
- **Correção:** congelar a sequência ao "Iniciar rota" (recalcular só ao entrar ou sair pedido); peso por idade e prazo do pedido; `fitToCoordinates` com todas as paradas e a posição; botão "abrir no Waze/Maps" no banner da navegação.
- **Aceite:** teste de domínio: com a mesma lista de pedidos, duas consultas com posições diferentes mantêm a ordem depois de iniciada a rota.
- **Esforço:** M

### P2-C-13 — Não há carga embarcada, vasilhame nem "meu acerto" para o entregador; encerrar a jornada não confere nada
- **Categoria:** completude / dinheiro · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (app); FORTE INDÍCIO (backend: `Setor` não tem vínculo com veículo/entregador — grep sem ocorrência em model e migrations)
- **Onde:** "Comigo agora" (`ganhos.tsx`) só aparece com `modo_estoque` de franqueado; `Encerrar(null)` em `inicio.tsx:41` e `perfil.tsx:27` manda `km_final: null` sem perguntar e sem conferir entregas pendentes, fila pendente nem dinheiro em mãos. Malote só no painel. De passagem: `MaloteService::conferir` só exclui cancelados (`:76`), então um pedido ainda **não entregue** entra no total e aparece como `ja_baixado` (`:113`) por não ter título — FORTE INDÍCIO, a confirmar pela frente do ERP.
- **Impacto:** a divergência de botijões e de dinheiro só aparece no caixa, sem trilha; o km rodado da jornada nunca é medido.
- **Correção:** tela "Fechar o dia": entregas pendentes, ações sem sinal, dinheiro esperado por forma (a partir do P2-C-03), vasilhames, km final; encerrar só depois de ver o resumo.
- **Esforço:** G

### P2-C-14 — Ações irreversíveis sem confirmação e sem levar o estado em conta
- **Categoria:** UX de campo · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código)
- **Onde:** "Recusar corrida" (`index.tsx:139`) sem confirmação e sem motivo (a API aceita `motivo`); "Iniciar rota" (`rota.tsx:96`), que dispara push a **todos** os clientes, sem confirmação e sempre visível; "Aceitar" é no-op (`EntregaService.php:36-43`) e o botão continua igual depois do toast; "Concluir"/"Ocorrência" aparecem em pedido concluído. Com `enfileirado: true`, `aceitar`/`recusar` mostram o mesmo toast de sucesso, sem dizer "guardado".
- **Correção:** ações conforme o papel da situação; confirmação com motivo na recusa; toast distinto para enfileirado.
- **Esforço:** P

### P2-C-15 — NF-e em campo (F6 "completa") e cupom térmico (F8) não têm tela no app
- **Categoria:** completude · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (grep: nenhuma ocorrência de `fiscal` em `app-entregador/src`; `CupomPedido` em `venda.service.ts:518` sem uso; `Cancelar` solicitação sem uso)
- **Impacto:** o perfil "industrial" — que o próprio login de teste anuncia como "emissão de NF-e em campo" (`login.tsx:123`) — não emite nada pelo app, e o documento de status declara F6 completa (`APPS_DE_CAMPO...md:433`). O vendedor também não consegue cancelar uma solicitação que mandou errada.
- **Correção:** tela de emissão sob `approle:industrial` e cancelamento de solicitação pendente; corrigir o status no documento.
- **Esforço:** M

### P2-C-16 — Assinatura: o desenho só vale depois de "Salvar assinatura" dentro do quadro
- **Categoria:** UX · **Severidade:** BAIXA · **Estado:** CONFIRMADO (o desenho sem salvar é ignorado); FORTE INDÍCIO (rolagem do `ScrollView` ao assinar no Android — não testado em aparelho)
- **Onde:** `concluir.tsx:101-110` — `onOK` só dispara no botão do componente; `concluir` não chama `readSignature()`; o canvas fica dentro de `ScrollView` (`:83`) sem desligar a rolagem durante o traço.
- **Impacto:** o cliente assina, o entregador toca "Concluir entrega" e recebe "Anexe foto ou colha a assinatura."
- **Correção:** `readSignature()` ao concluir; `scrollEnabled` desligado em `onBegin` e religado em `onEnd`.
- **Esforço:** P

### P2-C-17 — Acessibilidade e leitura sob sol
- **Categoria:** usabilidade · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (medido)
- **Evidência:** 0 ocorrências de `accessibility*` em `src/`; os botões só de ícone (fechar e recentrar em `navegacao.tsx`) ficam sem rótulo no leitor de tela. Contraste calculado (WCAG): **branco sobre laranja `#FF6200` = 3,00:1** (botão primário 16 px bold, header, abas ativas: o AA pede 4,5:1); laranja sobre branco ("Ver todas", nº do pedido) 3,00:1; `muted` sobre fundo `#F5F5F5` 4,43:1. Pontos positivos: botões de 52 px, campos de 50 px, tema claro fixo (bom sob sol), retorno tátil do `TouchableOpacity`.
- **Impacto:** leitura difícil sob sol forte, justamente nos botões de ação.
- **Correção:** escurecer o laranja de ação (≈ `#C2410C` dá mais de 4,5:1 com branco) ou usar texto grafite; rótulos de acessibilidade nos botões de ícone; testar com fonte em 130%.
- **Esforço:** P

### P2-C-18 — Qualidade e cadeia de suprimentos
- **Categoria:** testes / dependências · **Severidade:** MÉDIA · **Estado:** CONFIRMADO
- **Evidência:** `tsc --noEmit` EXIT 0; **nenhum teste**, nenhum runner, fora do CI. `npm audit --omit=dev`: **37 vulnerabilidades (1 crítica, 18 altas, 12 moderadas, 6 baixas)**. Quase todas vêm de ferramentas de build que entram em `dependencies` via `expo` (`shell-quote` crítica, `tar`, `undici`, `node-forge`, `@xmldom/xmldom`...). A única **direta** é `axios` (alta: prototype pollution no adapter `fetch`; o RN usa XHR, então o impacto prático é baixo). Duplicação: `helpers/polyline.ts` é idêntico ao do `app-gas-em-casa`, que tem teste; `storage.ts` e `realtime.ts` são quase cópias.
- **Correção:** vitest no app (as sondas desta frente servem de semente), job no CI com `tsc` e testes; mover `polyline`/`storage` para `mobile-shared`; `npm audit fix` sem `--force` e revisão do axios.
- **Esforço:** M

### P2-C-19 — White-label e distribuição: o app é da Dubena, não do produto
- **Categoria:** produto SaaS · **Severidade:** MÉDIA · **Estado:** CONFIRMADO (código); HIPÓTESE (estratégia de distribuição)
- **Onde:** `app.json`: nome "Gás em Casa Entregador", pacote `br.com.dubena.gasemcasa.entregador`; `COLORS` "Paleta Supergasbras" (`constants/app.ts:216`); logo e textos "Gás em Casa" (`login.tsx:197`); checklist do veículo com 5 itens fixos no código (`iniciar-jornada.tsx:12`), embora o ERP já tenha checklist configurável (`Models/Crm/Checklist*`). A regra do repositório diz que convenção de uma revenda não é regra do produto.
- **Leitura de produto:** o tenant sai do token (`app.config.ts:21-23`), então **um** app neutro de entregador serviria todas as revendas sem build por cliente; a marca é que amarra o app à Dubena. O app é de uso interno (conta criada pela empresa) e pede localização em 2º plano. A publicação pública na Play (`eas.json` `track: production`, runbook `PUBLICACAO_LOJAS.md:76-81`) exige declaração e vídeo, e cada revenda nova veria "Gás em Casa". Distribuir como **app privado pelo Managed Google Play** (ou um app neutro da plataforma) evita listagem pública de uma ferramenta de funcionário — HIPÓTESE a validar com as revendas e com a política vigente da Play.
- **Versionamento:** `eas.json` usa `appVersionSource: remote` + `autoIncrement`, mas o runbook (`:143`) diz que o `versionCode` vem do `app.json` (1) e o build é local por Gradle — duas fontes de verdade. O perfil `preview` tem `APP_DEBUG=true`: um APK de homologação entregue a um entregador real mostra os atalhos de teste se `LOGIN_TESTE_*` estiver definido.
- **Correção:** marca, cores e checklist vindos da configuração do tenant (`GET entregador/config`); decidir app neutro × app por revenda; uma fonte só de versão.
- **Esforço:** G

### P2-C-20 — Chave do MMKV maior que o limite e gerada com `Math.random`
- **Categoria:** segurança local · **Severidade:** BAIXA (o sandbox do SO protege o arquivo) · **Estado:** CONFIRMADO (documentação da lib) / HIPÓTESE (efeito exato: truncar ou lançar)
- **Onde:** `storage.ts:70-76` gera 64 caracteres hex com `Math.random`; `react-native-mmkv@3.3.3` (`src/Types.ts:55`): "Encryption keys can have a maximum length of 16 bytes". Se a lib lançar, `initSecureStorage` (`:100-104`) cai em silêncio para MMKV **sem** cifra. O mesmo padrão existe no app do cliente.
- **Correção:** 16 bytes de `expo-crypto.getRandomBytes`; registrar o fallback.
- **Esforço:** P

---

## 4. GPS, rastreamento e bateria (item 3 da missão)

| Aspecto | O que o código faz | Avaliação |
|---|---|---|
| Coleta | `startLocationUpdatesAsync`, `Accuracy.Balanced`, a cada 15 s **ou** 25 m, com foreground service ("Em serviço") — `backgroundLocation.ts:58-68` | Adequado para acompanhar em tempo real |
| Envio | Um `POST posicao` por atualização, só a última posição do lote (`:20`), sem fila (`entrega.service.ts:181-186`, decisão registrada) | Coerente: a posição velha não vale nada. Custo de rádio alto (≈240 POST/h parado em trânsito) — consumo **não medido** (HIPÓTESE) |
| Tela bloqueada | Android: o FGS de localização segue em Doze; iOS: `UIBackgroundModes: location` + indicador | Correto por leitura; não testado em aparelho (proibido) |
| Fallback sem "sempre" | Loop em 1º plano a cada 15 s (`useRastreamento.ts:164-172`) | Correto |
| Navegação interna | `watchPositionAsync` `BestForNavigation`, 3 s / 10 m, com a tela aberta | Soma ao FGS: duas fontes de GPS ao mesmo tempo |
| Missão | `useTrilhaMissao`: ponto a cada 30 s, envio em lote de 5, buffer **só em memória** (perde ao matar o app) | Aceitável; documentado |
| Divulgação em destaque (Play) | `Alert` próprio antes do pedido do SO (`:73-89`), texto fiel à política | **Conforme** |
| Manifest | `ACCESS_BACKGROUND_LOCATION`, `isAndroidBackgroundLocationEnabled`, `blockedPermissions` para microfone e armazenamento, `targetSdk 36` | Conforme; a declaração e o vídeo estão no runbook |
| LGPD | Servidor recusa ping sem jornada (sonda 4); snapshot único por entregador (upsert) = boa minimização | Lacuna: jornada esquecida mantém o rastreamento (P2-C-07) |

---

## 5. Dinheiro em campo (item 4)

- **Preço:** decidido no servidor em todos os caminhos (`venda.service.ts:445`, `VendaCampoService.php:76`) — **correto**; o app só mostra o total de tabela como referência.
- **Registro do recebimento:** inexistente (P2-C-03). Troco, pagamento parcial, troca de forma de pagamento e vale: não há onde lançar.
- **Batimento com o caixa:** o `MaloteService` confere por `entregador_user_id` e por condição do **pedido**; a recusa pós-conclusão tira o pedido do malote (P2-C-01); pedido não entregue entra no total como "já baixado" (FORTE INDÍCIO, P2-C-13).
- **Testes PHP existentes executados** (`php artisan test --filter "EntregaP7Test|JornadaEntregadorTest|MaloteTest|IdempotenciaTest|IdempotenciaSobConcorrenciaTest|IniciarRotaTest|RastreamentoEntregadorTest|MissaoFluxoTest|RoteirizadorServiceTest|JornadaServiceTest|PapelSituacaoEmRotaTest"`): **76 passed (182 assertions), 28,31 s**. Nenhum deles cobre recusa ou aceite fora de PENDENTE, a verificação de vale por situação ou validade, nem o reenvio de `concluir` — por isso os achados acima passam verdes na suíte.

---

## 6. Usabilidade de campo (item 6) — síntese

Pontos fortes: alvos de toque grandes (52 px), fluxo curto até "Concluir", toasts claros no
caminho feliz, divulgação de localização bem escrita, valores em R$ com milhar (commit `133f588d`).
Problemas: ações perigosas sem confirmação (P2-C-14); lista some depois de iniciar a rota
(P2-C-04); mensagens em inglês (P2-C-08); assinatura que não "pega" (P2-C-16); contraste 3:1 nos
botões laranja (P2-C-17); não há modo escuro (aceitável sob sol; ruim à noite — HIPÓTESE); nenhum
rótulo de acessibilidade; o "Encerrar" do Início é um link de texto de 13 px (tem confirmação).
Uso com uma mão: a ação principal fica embaixo nas telas de fluxo, mas no detalhe do pedido há
quatro botões empilhados com "Recusar" (vermelho) por último — fácil de tocar por engano.

---

## 7. Oportunidades de produto (ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR → CRIAR)

| Tipo | Oportunidade | Base |
|---|---|---|
| ELIMINAR | Botão "Aceitar corrida" — no-op no servidor (`EntregaService.php:36-43`); a atribuição já é o aceite | **Fundamentada** |
| ELIMINAR | Dependências mortas `laravel-echo`/`pusher-js` (nunca conectadas) ou ligá-las de vez | **Fundamentada** |
| JUNTAR | Uma tela "Fechar entrega": itens + vasilhame + forma de pagamento + vale + troco + comprovação (hoje vale, comprovação e pagamento estão separados ou não existem) | **Fundamentada** (P2-C-02/03) |
| JUNTAR | Recusa e ocorrência num só fluxo com motivo (hoje a recusa não pede motivo e a ocorrência não desvincula) | **Fundamentada** |
| AUTOMATIZAR | Encerramento de jornada por inatividade ou horário configurável do tenant | **Fundamentada** (P2-C-07) |
| AUTOMATIZAR | Renovação silenciosa do token | **Fundamentada** (P2-C-09) |
| AUTOMATIZAR | Consumir o vale e ajustar o título na conclusão, sem o conferente refazer no malote | **Fundamentada** |
| ANTECIPAR | "Chegando em ~5 min" ao cliente por geocerca da próxima parada (posição e rota já existem) | Hipótese a validar com revendas |
| ANTECIPAR | Antes de encerrar: "3 entregas abertas, 2 ações sem sinal, R$ 340 em dinheiro esperado" | **Fundamentada** (dados existem) |
| ANTECIPAR | Pré-carregar rota, clientes e produtos para leitura offline no início da jornada | **Fundamentada** (plano F7 previa) |
| CRIAR | "Meu acerto do dia" para o entregador CLT (o malote visto pelo entregador) | Fundamentada no código; valor a validar com revendas |
| CRIAR | Leitor de código de barras do vale pela câmera (o texto do app já promete "leia o código") | **Fundamentada** (`vale-gas.tsx`) |
| CRIAR | Carga embarcada / vasilhames por veículo, com conferência na saída e no retorno | Hipótese a validar (depende de como cada revenda controla o caminhão) |
| CRIAR | App de entregador neutro da plataforma, com marca e checklist por tenant | Hipótese de produto SaaS (P2-C-19) |

---

## 8. Priorização sugerida

1. **Antes de qualquer entregador real:** P2-C-01 (P), P2-C-02 (M), P2-C-04 (P), P2-C-08 (P), P2-C-14 (P).
2. **Antes de depender do app para o dinheiro:** P2-C-03 (G), P2-C-05 (M), P2-C-06 (M), P2-C-13 (G).
3. **Operação estável:** P2-C-07, P2-C-09, P2-C-10, P2-C-11, P2-C-12.
4. **Produto e qualidade:** P2-C-15, P2-C-17, P2-C-18, P2-C-19, P2-C-16, P2-C-20.

---

## 9. Fechamento

**Inspecionado:** os 24 arquivos de tela/layout de `src/app` (`missao-visita.tsx`, `solicitacoes.tsx` e `relatorio-vendas.tsx` só por amostra e pelo diff) e todos os helpers, hooks, services, store e tipos de
`app-entregador/src`; `app.json`, `app.config.ts`, `eas.json`, `package.json`;
`mobile-shared/src/http.ts`; rotas `app/v1/entregador/*`; `AppEntregadorController`,
`EntregaService`, `PedidoService::mudarSituacao/aplicarEfeito`, `FinanceiroService::gerarDoPedido`,
`MaloteService`, `RoteirizadorService`, `Idempotente`, `verificarValeGas`,
`config/sanctum.php`, política de privacidade; `git show 133f588d`; runbook das lojas;
`APPS_DE_CAMPO_E_CENTRAL_DE_VENDAS.md`.

**Executado (saída real):**
- `npx tsc --noEmit` (app-entregador) → EXIT 0.
- `npm audit --omit=dev` → 37 vulnerabilidades (1 crítica, 18 altas).
- 11 sondas vitest (`scratchpad\c\sonda\fila.test.ts`, `mensagem.test.ts`) sobre os módulos reais da fila → 11/11 verdes, provando os itens do PIE-16 e as lacunas D–I.
- Sonda da classificação por abas (`scratchpad\c\sonda-abas.txt`).
- 6 sondas PHPUnit (`scratchpad\c\sonda-php\SondaFrenteCTest.php`, fora do projeto) → 6/6, 15 asserções.
- 11 arquivos de teste PHP existentes → 76 passed.
- Contraste WCAG das cores do tema (cálculo).
- `git status` final idêntico ao inicial: nenhum arquivo do projeto alterado além deste relatório.

**Não testado:** nada em aparelho ou emulador (proibido): consumo de bateria, comportamento real
do FGS com a tela bloqueada, task headless sem token, rolagem do canvas de assinatura, câmera,
tempos de upload em 3G, renderização do mapa. Nada na VPS nem no banco de homologação. Os testes
PHP rodaram em sqlite (sem RLS). A duplicação da venda de missão no reenvio está por leitura, não executada.

**Limitações:** a sonda E (409 concorrente) modela a ordem dos eventos com um servidor falso; a
ordem real depende da latência. A função de abas foi **copiada** do app para a sonda (não dá para
importar `.tsx` com React Native sem o preset do Metro); a cópia é literal e está registrada no
arquivo da sonda.

**Parecer da Frente C: REPROVADO** como ferramenta de campo para produção. O caminho feliz
funciona e as correções da Rodada 1 (PIE-16, PIE-18) estão confirmadas. Mas três defeitos atingem
dinheiro diretamente (P2-C-01, P2-C-02, P2-C-03), um faz as entregas sumirem da tela no momento de
uso (P2-C-04), e a promessa de operar sem sinal não vale para a ação central do dia — concluir a
entrega (P2-C-05) —, enquanto a fila perde em silêncio o que o servidor recusa (P2-C-06). Os cinco
itens da prioridade 1 são de esforço P/M e destravam um piloto controlado.
