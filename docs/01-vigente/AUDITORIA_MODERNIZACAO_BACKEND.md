# Auditoria — Modernização do backend legado (coexistência Strangler)

> Pergunta do cliente: *dá para modernizar a lógica legada (Redirect/Session/helpers Oracle →
> Service/JSON) de forma paralela e coexistente, como fizemos no frontend, sem quebrar e sem
> risco? Qual a recomendação e qual o risco em toda a aplicação?*

Resposta curta: **sim, é viável e já está acontecendo** — a API admin (F1–F9) é exatamente essa
camada nova coexistindo com o legado. O risco **não é uniforme**: é baixo na maior parte e
concentrado num núcleo pequeno (os 7 motores financeiros/estoque/fiscal). A estratégia segura é
modernizar **por camada**, preservando os motores e cercando cada porte com teste de caracterização.

---

## 1. Tamanho real do legado (medido)

| Métrica | Valor |
|---|---:|
| Controllers legados | **160** |
| Linhas de controller | **55.307** |
| Models | 203 |
| **Motores (`app/Processors/`)** | **7** |
| Controllers que usam `Session::get('empresa_padrao')` | 126 |
| Controllers que retornam `View(...)` | 150 |
| Controllers que retornam `Redirect` | 51 |
| Controllers que invocam um Processor | **25** |
| Arquivos que usam helpers Oracle (`*PercentualOracle`, `*Oracle`) | 102 |

Leitura: o legado é **grande, mas raso**. 150 dos 160 controllers são CRUD/relatório que
devolvem HTML. A complexidade perigosa está concentrada nos **7 motores** e nos **25 controllers**
que os chamam — ~16% da superfície.

## 2. O que já existe de modernização coexistindo (medido)

| Métrica | Valor |
|---|---:|
| Controllers da API admin (`app/ApiAdmin/`) | 23 |
| Rotas `/api/admin` | 192 |
| Linhas da API admin | 4.487 |
| Baseline characterization tests | 3 (Estoque, Financeiro, Pedido) |

A fundação de coexistência **já está pronta e em produção**:
- Grupo de middleware `api_admin` (Sanctum stateful + RBAC `podeRecurso`) — `RouteServiceProvider`.
- Padrão `contexto()`: a API stateless popula `Session('empresa_padrao')` + `Auth::login($user)`
  antes de chamar um motor que depende deles (ver `ApiAdmin/CaixaController::contexto`). É a ponte
  que deixa o motor legado rodar **intacto** sob uma requisição moderna.
- SPA React em `/app` consome essa API; AdminLTE legado continua respondendo nas mesmas rotas.

Ou seja: a pergunta "dá para fazer paralelo como no frontend?" **já tem resposta afirmativa
demonstrada** — é o que sustenta F1–F9.

## 3. As três camadas e o risco de cada uma

O código legado mistura três camadas. Modernizar não é uma decisão única:

### Camada A — Apresentação (Redirect, View, `$request->only('percentual'.$i)`)
**Risco BAIXO.** É casca. Trocar `Redirect::route()->withErrors()` por `response()->json()` e um
payload estruturado (`parcelas:[{dias,percentual}]`) não toca regra de negócio. Reversível: desligar
a flag do módulo volta para a tela antiga.

### Camada B — Regra de negócio de CRUD (tipos de condição, parcelamento, validações)
**Risco MÉDIO, controlável.** Lógica entendível e isolável. O perigo real são dois detalhes
herdados do Oracle:
- `insertPercentualOracle`/`requestPercentualOracle`: `"12,50 %" ↔ 12.5`. Mudança sutil de
  parse/arredondamento grava valor errado.
- Strings mágicas e tipos como string (`tipo === '0'`, `'0,00 %'`).

Mitigação: **teste de caracterização primeiro** — roda legado e Service novo com os mesmos inputs e
compara o que cai no banco. Já é o método aplicado em Estoque/Financeiro/Pedido.

### Camada C — Motores (`caixaProcessor`, `financeiroProcessor`, `EstoqueProcessor`, NF-e…)
**Risco ALTO se reescritos / BAIXO se preservados.** Ex.: `CaixaController@baixar` tem ~270 linhas,
transações aninhadas, mexe em saldo de conta, gera movimento/recibo/estado de cheque. Reescrever =
reescrever o coração financeiro; qualquer divergência corrompe saldo em produção. **Não reescrever.**
A modernização aqui é a **interface** (fachada JSON que invoca o motor via `contexto()`), não o motor.

## 4. Matriz de decisão por tipo de funcionalidade

| Tipo | Quantos (aprox.) | Estratégia | Risco |
|---|---:|---|---|
| Cadastros simples (descricao+ativo) | ~40 | Genérico `CadastroApoioController` (já existe) | muito baixo |
| Cadastros ricos (Condição pgto, Conta, Produto…) | ~20 | Service novo + JSON **+ baseline test** | médio→baixo |
| Relatórios/PDF | ~30 | Manter legado por ora (gera PDF; baixo valor em reescrever) | n/a |
| Visões/listagens (Contas a Receber/Pagar) | ~15 | Query nova só-leitura | baixo |
| **Operações de motor** (caixa, cheque, estoque, pedido, NF-e) | **25** | **Fachada JSON → motor intacto** | baixo (preservado) |
| Integrações externas (CNAB, OFX, PIX, GPS, cartão) | ~10 | **Gates** — exigem credencial/homologação; portar só a UI/disparo | fora de escopo de teste |

## 5. Risco para a aplicação inteira

- **Risco de regressão funcional:** baixo **se** todo porte de regra/motor vier com baseline test e
  o legado permanecer ligado como fallback (Strangler). Alto se reescrevermos motor sem rede.
- **Risco de produção:** mínimo enquanto a rota legada continua respondendo — a flag por módulo
  (`uiModernaAtiva`) permite rollback instantâneo, já implementado.
- **Risco de divergência de dados:** o ponto mais sensível é a Camada B (helpers Oracle de
  formatação). Endereçado por teste de caracterização byte-a-byte.
- **Risco de prazo:** o legado é grande (55k linhas). Modernizar 100% é um esforço longo. Mas **não
  é necessário**: o objetivo é a SPA cobrir o fluxo de uso; relatórios e telas raras podem
  permanecer legados indefinidamente sob o Strangler sem prejuízo.

## 6. Recomendação

1. **Continuar exatamente o modelo Strangler já em curso** — é a resposta à pergunta "paralelo como
   no frontend": sim, e já é assim. A API admin é a camada nova; o AdminLTE é o fallback.
2. **Modernizar por camada, nunca o motor:**
   - Casca + interface → modernizar para JSON/Service (risco baixo).
   - Regra de CRUD → Service novo **com baseline test** (risco médio controlado).
   - Motores → **fachada que invoca o motor intacto** (`contexto()`), nunca reescrever.
3. **Toda mudança de regra/motor entra com teste de caracterização** comparando legado × novo no
   banco, antes do merge. Já temos 3; é o padrão a manter.
4. **Manter o legado ligado** (flag por módulo) até a SPA cobrir o fluxo — rollback em 1 clique.
5. **Não perseguir 100%:** relatórios/PDF e telas raras podem ficar legados. Priorizar por uso.
6. **Integrações externas continuam gates:** portar a UI e o disparo, mas a execução (CNAB, OFX,
   PIX, GPS) depende de credencial/homologação e não é testável em CI — documentar como tal.

### Veredito
Modernizar o backend de forma coexistente **é seguro e recomendado**, desde que (a) os 7 motores
sejam preservados e apenas "fachadeados", (b) toda regra portada tenha baseline test, e (c) o legado
permaneça como fallback até a SPA cobrir o fluxo. O risco perigoso está confinado a ~16% da
superfície (motores + seus chamadores) e é neutralizado pela combinação **fachada + baseline +
fallback** que o projeto já pratica.
