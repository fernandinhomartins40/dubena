# Agente D — App do entregador e sessão móvel (Rodada 3)

**Data:** 2026-10-31 · **Commit de partida:** `9fcfde5a` · **Área:** `app-entregador/**`, `mobile-shared/**`
(sessão dos dois apps), `Api/Mobile/AppEntregadorController`, `Domain/Mobile/EntregaService`.
Nada foi commitado (commit é do coordenador). Evidências brutas em
`%TEMP%\claude\c--FuseDev-Dubena\80f4869b-…\scratchpad\D3\`.

---

## 1. Ambiente

| Item | Valor |
|---|---|
| Banco | `pie_r3_d` (clonado de `pie_r3_base`), Postgres 15 local `:55432`, runtime `erp_app` |
| API | `php -d max_execution_time=0 -S 127.0.0.1:8214 …/server.php` (cwd `erp-novo/public`), env do `e2e/README.md` com `SAAS_ENFORCE_LICENCA=true` e envelope de tenant |
| Apps | `npx tsc --noEmit` e `npx vitest run` em `app-entregador` e `app-gas-em-casa` |
| PHP | `php artisan test --filter=…` (sqlite) |
| Proibido e não usado | emulador, adb, Gradle, build de APK |

Ajustes **só no banco `pie_r3_d`** para a jornada HTTP rodar (ver R3-D-01): `colaboradores` id 31
ligado ao `users.id=4` (`entregador@pie.local`, vínculo `funcionario`); pedidos 495, 496 e 497
atribuídos ao entregador 4.

## 2. Matriz perfil × jornada (Entregador)

| Jornada | Estado | Elo que falta (§2 do protocolo) |
|---|---|---|
| Login → token | **PARCIAL** | Funciona por HTTP só depois de criar à mão o colaborador (R3-D-01); tela não executada (sem aparelho) |
| Renovação de sessão (RC7) | **IMPLEMENTADA, VERIFICADA em vitest** + HTTP do `token/refresh` (ver §4.1) | Aparelho real (relógio adiantado/background longo) |
| Iniciar jornada / pedidos / iniciar rota | **PARCIAL** (HTTP real, ver §4.6) | elos 1–2 (tela) sem aparelho |
| Concluir entrega com foto+assinatura, com e sem sinal | **IMPLEMENTADA, VERIFICADA** (vitest do app + PHPUnit com payload literal + HTTP real) | aparelho: câmera, canvas, FormData nativo lendo `file://` |
| Ocorrência | **PARCIAL** — entra na fila multipart (vitest); HTTP real ver §4.6 | aparelho |
| Rastreamento em 2º plano / fim de turno | **IMPLEMENTADA NÃO VERIFICADA** em aparelho; regra pura testada | task headless real; encerramento automático no servidor é decisão (§5) |
| Leitura offline e "sem conexão" | **IMPLEMENTADA, VERIFICADA em vitest** (lógica) | tela em aparelho |
| Acerto / pagamento na entrega | **BLOQUEADA por decisão** (P2-C-03, P2-C-13) | — |

Toda jornada do app segue **PARCIAL até haver aparelho/Maestro** (protocolo §3.4).

## 3. Achados

### R3-D-01 — Entregador criado pelo painel não entra no app sem um registro de colaborador
- **Onde:** `AppAuthController::login` → `vinculoDe()` (`AppAuthController.php:261-273`) exige `colaboradores.user_id` ativo; o banco-base tem `entregador@pie.local` com papel, sem colaborador.
- **Reprodução:** `POST /api/app/v1/login {email: entregador@pie.local, password, plataforma}` na API 8214.
- **Esperado × observado:** 200 com token × **403** `"Este acesso e exclusivo para colaboradores. Use o aplicativo do cliente."` (saída em `scratchpad\D3\jornada-saida.txt`, 1ª execução).
- **Severidade:** MÉDIA (onboarding: a revenda dá o papel "Entregador" ao usuário e ele não entra; a mensagem manda usar o app do cliente). **Estado:** CONFIRMADO; fora da minha área (AppAuthController é do E; vínculo usuário×colaborador é de C/F). Ver "Para outros donos".

### R3-D-02 — Reenvio de `concluir` sem chave: 422 e arquivo órfão; recusa deixava foto no disco (P2-C-05, lado servidor)
- **Onde:** `EntregaService::concluir` gravava os arquivos **antes** de conferir a situação; a exceção desfazia a linha, não o arquivo.
- **Prova:** `PieEntregadorConclusaoReenvioTest` (payload literal do app), sem a correção: reenvio sem chave → **422**; pedido cancelado → **2 arquivos no disco sem comprovação**; pedido concluído por outro caminho → 2 arquivos órfãos. **CORRIGIDO** (§4.2).
- **Achado lateral:** com a mesma `Idempotency-Key` o servidor **já** devolvia a primeira resposta em multipart (o hash do middleware ignora o conteúdo do arquivo) — o defeito era o app não mandar a chave. O teste correspondente passa com e sem a correção (é guarda).

### R3-D-03 — A rotação do token revoga o antigo na hora: resposta perdida = sessão perdida
- **Onde:** `AppAuthController::refresh` (`:388-406`) apaga o token atual logo após emitir o novo.
- **Cenário:** o `token/refresh` chega ao servidor e a resposta se perde (3G). O app fica com o token revogado; a próxima chamada dá 401, a renovação com o token velho dá 401 e o app desloga. O núcleo novo minimiza (uma renovação a cada 6 h, nunca em paralelo), mas não elimina.
- **Severidade:** BAIXA/MÉDIA · **Estado:** **CORRIGIDO** (§4.7): o token antigo vale por mais 120 s.

### Itens da Rodada 2 tratados aqui
P2-C-09 / P2-B-03 (RC7), P2-C-05, P2-C-07, P2-C-10, P2-C-11, P2-C-14 (parcial), P2-C-16, P2-C-17, P2-C-20 — ver §4.
Conferidos e **não refeitos** (commit `dd5eeb11`): P2-C-01, 02, 04, 06, 08 — os testes deles (`PieEntregadorCampoTest`, `situacao.test.ts`, `filaOffline.test.ts`) seguem verdes.

## 4. Correções

### 4.1 RC7 — renovação de token no núcleo `mobile-shared` (cobre os dois apps)
- **Arquivos:** `mobile-shared/src/http.ts` (opção `renovacao` no `createHttp`; `renovarSessao`, `marcarTokenNovo`; `PrepareForm(endpoint, form, {chave, timeoutMs})`; `UPLOAD_TIMEOUT_MS = 90 s`); `app-entregador/src/helpers/http.ts` (liga a renovação); `app/login.tsx` (`marcarTokenNovo()` após o login).
- **Comportamento:** (a) antes de qualquer requisição protegida, troca o token se ele tem idade desconhecida (boot) ou mais de 6 h; (b) em 401, UMA renovação e UMA repetição; se outra requisição já trocou o token, só repete com o vigente; (c) a própria renovação é marcada e nunca dispara outra (sem laço); (d) uma renovação por vez (dez requisições simultâneas → 1 refresh); (e) sem rede, segue com o token atual e espera 60 s; (f) se o usuário saiu durante o refresh, o token novo não ressuscita a sessão; (g) **sem `renovacao` o comportamento é idêntico ao antigo** — o app do consumidor não muda até ligar.
- **Fila offline:** usa o mesmo `client`, então o reenvio renova em vez de esbarrar no 401; sessão vencida de vez mantém o item na fila (testado).
- **Teste:** `app-entregador/src/helpers/sessao.test.ts` (11) — **sem a correção: 9 falham, 2 passam** (as duas guardas: rota pública e comportamento sem `renovacao`). Com: 11/11.
- **HTTP real:** ver §4.6 (passos 12–14).

### 4.2 P2-C-05 — concluir e ocorrência pela fila, multipart, com `Idempotency-Key`
- **App:** `helpers/arquivosFila.ts` (novo: copia foto/assinatura do cache para `documentDirectory/fila-offline/<chave>/`, apaga quando o item sai); `helpers/filaOffline.ts` (item com `formulario {campos, arquivos por caminho}`; descartar/dispensar/limpar apagam os anexos; `novoId` usa o uuid nativo do expo-modules-core quando existe); `helpers/envioResiliente.ts` (`enviarFormularioOuEnfileirar`, `montarFormulario`, `acaoPendenteDoPedido`; timeout de upload 90 s); `services/entrega.service.ts` (`Concluir`/`Ocorrencia` pela fila); telas `concluir.tsx`, `ocorrencia.tsx` ("Sem sinal: entrega guardada"), `pedido/[id]/index.tsx` (esconde "Concluir" quando a conclusão já está guardada).
- **Servidor:** `EntregaService::concluir` — confere a situação sob `lockForUpdate` antes de gravar arquivo; se ESTE entregador já concluiu, devolve a comprovação existente; arquivos gravados são apagados se a transação falhar. `AppEntregadorController::concluir` — reenvio responde 200 e não repete o push ao cliente.
- **Testes:**
  - `filaMultipart.test.ts` (6, vitest, módulos reais da fila + núcleo http): com sinal leva chave e anexos copiados; timeout → fila com a MESMA chave e os MESMOS campos, sobrevive a "reinício" (JSON do storage, sem base64) e reenvia; 422 real mostra a frase do servidor, não enfileira e apaga os anexos; ocorrência sem sinal enfileira; fila × sessão renovada. **Sem a correção: 6/6 falham.**
  - `erp-novo/tests/Feature/PieEntregadorConclusaoReenvioTest.php` (4, payload literal do app). Com: 4/4 (15 asserções). **Sem a correção:**
    ```
    ✓ reenvio com a mesma chave devolve a primeira resposta sem novo arquivo   (guarda: o middleware já cobria)
    ⨯ reenvio sem chave pelo mesmo entregador …  Expected response status code [200] but received 422.
    ⨯ pedido cancelado recusa e nao deixa arquivo orfao   Failed asserting that 2 is identical to 0.
    ⨯ outro entregador nao herda a comprovacao alheia     Failed asserting that 2 is identical to 0.
    ```
- **Pendente:** venda de missão (`missao.service.ts::VenderGas`) ainda sem chave estável por tentativa — o reenvio pode duplicar a venda (P2-C-05, parte missão).

### 4.3 P2-C-07 — rastreamento fora do turno e task em 2º plano sem token
- `helpers/rastreamentoRegra.ts` (novo, puro): `deveRastrear` (token **e** em serviço), `deveEncerrarRastreamento` (401/403/422 param; rede e 5xx não).
- `helpers/backgroundLocation.ts`: a task hidrata storage+store quando roda sem a árvore React; sem token ou fora de serviço desliga o GPS; 422 "Inicie a jornada"/401/403 desligam e zeram `emServico`.
- `hooks/useRastreamento.ts`: o loop de 1º plano aplica a mesma regra.
- `hooks/useJornada.ts` + `(app)/_layout.tsx`: `useVigiaJornada` no layout autenticado — a jornada encerrada pela Central desliga o GPS em qualquer tela (antes só Início/Perfil liam a jornada).
- **Teste:** `campoOffline.test.ts` (regra pura). Task headless real: NÃO verificada (exige aparelho).
- **Decisão pendente:** encerramento automático de jornada esquecida no servidor (§5).

### 4.4 P2-C-10 / P2-C-11 — "sem conexão" em vez de "vazio" e leitura offline após reiniciar
- `helpers/cacheLocal.ts` (novo): última resposta boa de `pedidos` e `rota` no MMKV cifrado, por entregador, volta como `initialData`; `estadoDaLista` e `avisoOffline`; `limparCaches()` no logout explícito (`perfil.tsx`).
- `components/ui.tsx`: `AvisoOffline` ("Sem conexão — mostrando o que foi carregado às 14:32.") e `FalhaAoCarregar` (com "Tentar de novo").
- Telas: `entregas.tsx`, `inicio.tsx`, `rota.tsx`, `pedido/[id]/index.tsx` (não diz mais "Entrega não encontrada na lista" quando é falta de sinal).
- **Teste:** `campoOffline.test.ts` (5 casos de cache/estado). Sem persister do React Query (seria dependência nova).

### 4.5 P2-C-14, P2-C-16, P2-C-17, P2-C-20
- **P2-C-14:** "Iniciar rota" pede confirmação dizendo quantos clientes serão avisados; aceitar/recusar/concluir/ocorrência dizem "guardado" quando entram na fila. *Não feito:* motivo na recusa (a confirmação já existia).
- **P2-C-16:** cada traço da assinatura é lido ao terminar (`onEnd → readSignature`); "Concluir" pede a leitura se o último traço não chegou; rolagem travada durante o traço; "Limpar" zera a assinatura. Não verificável sem aparelho (WebView).
- **P2-C-17:** `COLORS.primaryStrong #C2410C` para botões primários, abas ativas e textos em laranja (branco: **5,18:1**; sobre `#F5F5F5`: **4,75:1**; antes 3,00 e 2,75); `muted` `#5B6270` (5,62:1, antes 4,43); `Botao` com `accessibilityRole/Label/State/Hint`; `Campo` com rótulo; botões só de ícone da navegação rotulados; abas `tab` com `selected` e 44 px; chips de ocorrência `radio`; card do pedido com rótulo completo. O cabeçalho continua no laranja da marca (decisão de marca, §5).
- **P2-C-20:** `store/chaveMmkv.ts` (novo) — 16 caracteres (limite do MMKV) / 96 bits do uuid **nativo** (`globalThis.expo.uuidv4`, SecureRandom/UUID do SO, já no binário — sem dependência nem build); sem gerador seguro, recusa; `storage.ts` registra `storageSemCifra` no fallback. Instalações antigas mantêm a chave existente (trocar apagaria a fila). Teste em `campoOffline.test.ts` (3).

### 4.6 Jornada HTTP real (API 8214, banco `pie_r3_d`, ordem do app)
`scratchpad\D3\jornada.mjs` → `jornada-saida.txt` (payloads de `auth.service.ts`, `jornada.service.ts`
e da fila multipart; foto/assinatura PNG reais; `Idempotency-Key` uuid):
```
1 login 200 (user 4, papel entregador)              8  concluir (1a)                201 {comprovacao_id:1}
2 jornada atual 200 {data:null}                     9  concluir reenvio MESMA chave 201 {comprovacao_id:1}
3 veiculos 200 {data:[]}                            10 concluir reenvio SEM chave   200 {comprovacao_id:1}
4 iniciar jornada 201 {status:"ativa"}              11 ocorrencia (multipart+chave) 201 {tipo:"ausente"}
5 pedidos 200 qtd=3 (situacao_efeito/papel vêm)     12 token/refresh                200 (token novo)
6 iniciar rota 200 {iniciados:3}                    13 token antigo após refresh    401
7 após rota: 495/496/497 "Em Rota"/EM_ROTA, PENDENTE 14 token novo                  200
```
**Persistência relida pelo owner:** `pedido_comprovacoes` = **1 linha** (pedido 495, entregador 4,
`recebido_por=Ana`, foto e assinatura, `tenant_account_id=1`); pedido 495 "Concluído", 496/497 "Em Rota";
`requisicoes_idempotentes` com `concluir` 201 e `ocorrencia` 201 concluídas; disco
`storage/app/private/entregas/1/495` = **2 arquivos** (foto + assinatura) depois de 3 envios — nenhum
órfão. Os 3 arquivos gerados pela jornada foram apagados ao final.
Achado colateral: `veiculos` vem vazio no banco-base (a jornada inicia sem veículo).

### 4.7 R3-D-03 — sobrevida do token antigo na rotação (feito a pedido do coordenador)
- **Arquivo:** `AppAuthController::refresh`. O token substituído não é mais apagado: recebe `expires_at = now()+120 s` (e nunca tem um prazo mais curto estendido). Assim, quando a resposta do refresh se perde, o app ainda renova com o token antigo dentro desse prazo. O núcleo espera 60 s depois de uma renovação que falhou, o que cabe nos 120 s. Divide o arquivo com outra mudança do C/E (mensagem `sem_colaborador`, R3-G-01). Ela não foi tocada.
- **Teste:** `AppAuthHardeningTest`. O teste antigo, que exigia o token anterior apagado, foi trocado por `…_o_anterior_vence_em_minutos`. Também entrou `test_resposta_do_refresh_perdida_o_app_renova_de_novo_com_o_token_antigo` (2º refresh com o token antigo → 200; o papel `role:entregador` é preservado; 3 min depois o token antigo dá 401). Resultado com a correção: 6/6. **Sem a correção** (só o trecho R3-D-03 desligado, o resto do arquivo intacto): **2 falham**, um deles com `Expected response status code [200] but received 401`.

### 4.8 R3-G-04 — `numero` aceitava 20 caracteres, mas a coluna tem 10
- **Arquivos:** `AppSolicitacaoController::cadastrarCliente` e `AppMissaoController::cadastrarCliente` passam de `max:20` para `max:10`, o mesmo contrato de `clientes.numero varchar(10)` e de `ClienteRequest`. Não houve migration. No app, `cliente-novo.tsx` ganhou `maxLength={10}`. Agora o 422 vai para "precisa de atenção" na fila e não retenta para sempre.
- **Teste:** `tests/Feature/PieClienteEmCampoNumeroTest.php` (4), com o payload literal de `cliente-novo.tsx` e de `missao.service.ts`. Inclui um guardião que compara o `max:` de `numero` nos dois controllers com o tamanho na migration; ele tem piso de 2 regras varridas. Resultado com a correção: 4/4. **Sem a correção: 3 falham**. A guarda do caso comum (`1234-B fds`) continua passando.
- **Para o E:** `AppPerfilController.php:97` (`PUT perfil/endereco`) tem o mesmo `max:20`.

### 4.9 Regressão executada
| Lote | Resultado |
|---|---|
| `app-entregador`: `npx tsc --noEmit` (compila também `mobile-shared` pelo alias `@shared`) | **exit 0** |
| `app-entregador`: `npx vitest run` | **42/42** em 5 arquivos (situacao 4, campoOffline 10, sessao 11, filaMultipart 6, filaOffline 11) |
| `mobile-shared` | Não tem `tsconfig` nem runner próprio: o código dele é compilado e testado pelo `app-entregador` (`sessao.test.ts` importa `@shared/http` real) |
| `app-gas-em-casa`: `tsc` / `vitest` (consumidor do núcleo) | vitest **40/40**. O `tsc` só falha em `src/services/order.service.test.ts`, arquivo novo do E (top-level `await`) |
| PHP `--filter="PieEntregador\|PieClienteEmCampo\|AppAuthHardening\|EntregaP7\|JornadaEntregador\|Idempotencia\|RastreamentoEntregador\|IniciarRota\|MissaoFluxo"` | **60 passed** (185 asserções), 108 s |
| PHP lote anterior `--filter="JornadaEntregador\|EntregaP7\|PieEntregadorCampo\|PieEntregadorConclusaoReenvio\|Rastreamento\|Idempotencia\|AppLogin\|Mobile"` | **102 passed, 4 skipped** (os do Postgres/RLS), 965 s |

## 5. Para outros donos e decisões pendentes

### Para o Agente E (app do consumidor) — sessão nova
- Ligar no `app-gas-em-casa/src/helpers/http.ts`: `renovacao: { endpoint: "app/v1/token/refresh", onToken: (t) => useAppStore.getState().setToken(t) }` e chamar `Http.marcarTokenNovo()` depois de gravar o token do login/verificação por SMS. Exportar `renovarSessao`/`marcarTokenNovo` do retorno do `createHttp`. Sem isso, nada muda no consumidor (compatível por desenho).
- O `refresh` devolve `{token, user}` sem `data` (o núcleo aceita os dois formatos).
- R3-D-03: **feito pelo D** (§4.7), a pedido do coordenador.
- R3-D-01 (login exige colaborador) é do `AppAuthController` — avaliar mensagem própria ("seu usuário não tem cadastro de colaborador ativo; peça ao administrador") em vez de "use o aplicativo do cliente".
- `app-gas-em-casa`: `npx tsc --noEmit` falha só em `src/services/order.service.test.ts` (arquivo novo, não versionado, top-level `await`) — é do E.

### Para C/F (acessos / RH)
- R3-D-01: dar papel "Entregador" a um usuário não basta para o app; precisa de `colaboradores.user_id` ativo. Falta a costura no painel (criar/ligar o colaborador ao dar o papel, ou checklist). O banco-base de teste também não tem esse vínculo.

### Decisões do dono (não implementadas)
1. **P2-C-03 — pagamento na entrega.** Proposta: a conclusão ganha "como pagou" (condições do tenant marcadas "aceita na entrega"), valor recebido e troco; se a forma muda, o servidor troca a condição antes de gerar o título; o Vale Gás é resgatado na mesma transação (fecha P2-C-02). Configurável por tenant, desligado por padrão.
2. **P2-C-13 — carga, vasilhame e "meu acerto".** Proposta: tela "Fechar o dia" (entregas abertas, ações sem sinal, dinheiro esperado por forma — depende de 1 —, km final) antes de encerrar; carga/vasilhame por veículo só depois de ouvir 2–3 revendas.
3. **P2-C-07 — jornada esquecida.** Proposta: encerramento automático por tenant (horário-limite e/ou N min sem movimento e sem entrega ativa), comando agendado; padrão desligado. Recomendo ligar com 12 h.
4. **Marca × contraste:** o cabeçalho segue `#FF6200` (branco 3,00:1; o título bold de 17 px fica abaixo do limite de texto grande). Recomendo `primaryStrong` também no cabeçalho, ou texto grafite.

## 6. Não testado e por quê
- Nada em aparelho/emulador (proibido): câmera, canvas de assinatura, FormData nativo lendo `file://` do `documentDirectory`, task headless, FGS, consumo de bateria, leitor de tela real.
- Venda de missão com chave estável (não implementado).
- A suíte PHP rodou em sqlite; a correção do `EntregaService` não toca tenant/policy (nenhum teste Postgres novo é necessário para ela). **Nenhum teste Postgres novo** para o portão.
- Push de cancelamento/atribuição (P2-C-10, parte push) e P2-C-12 (rota), P2-C-15 (NF-e em campo), P2-C-19 (white-label): fora do escopo pedido.
