# Plano — Central de Vendas e acoplamento dos apps de campo

Como absorver as operações de franqueado e industrial, e criar a **Central de
Vendas** como módulo do `erp-novo`.

Base: [`AUDITORIA_APPS_FRANQUEADO_INDUSTRIAL.md`](AUDITORIA_APPS_FRANQUEADO_INDUSTRIAL.md).

---

## A arquitetura em uma frase

**O ERP fica administrativo. A Central de Vendas vira o painel operacional que
conversa com os três apps. Os apps de campo não decidem dinheiro — solicitam, e a
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
              │   CENTRAL DE VENDAS         │  ← módulo novo no erp-novo
              │  fila de solicitações       │
              │  aprovação de desconto      │
              │  criar / faturar pedido     │
              │  pós-venda · missões        │
              └──────┬──────────────────────┘
                     │ reusa
       Pedido · Fiscal · Financeiro · Logistica · Crm · Rh
                     │
              ERP administrativo (cadastros, relatórios, fiscal)
```

O ponto que sustenta o desenho: **o app já não decide preço.**
`AppMissaoController::venderGas` não aceita valor; `VendaCampoService.php:71` usa
o `preco_venda` do produto. A trava existe — falta a Central para autorizar as
exceções.

---

## Por que um app, e não três

O `app-entregador` já tem jornada, rota, aceitar/recusar, ocorrência, concluir,
missão, venda em campo e vale-gás. O que separa os perfis é **autorização**:

| Capacidade | Funcionário | Franqueado | Industrial |
|---|---|---|---|
| Receber rota e entregar | sim | sim | não |
| Vender em campo (preço de tabela) | sim | sim | sim |
| **Solicitar desconto** | não | **sim** | **sim** |
| **Fechar o próprio pedido** | não | **não — Central fecha** | a definir |
| Cadastrar cliente | não | sim | sim |
| Emitir NF-e | não | não | **sim** |
| Ver pendência financeira do cliente | não | sim | sim |
| Imprimir no cliente | sim | sim | sim |
| Remuneração | salário | repasse/comissão | comissão |

Três apps para essa tabela é triplicar manutenção — e é o que produziu o estado
atual, com impressão e pedido reimplementados em cada um.

---

## O fluxo do franqueado (o coração da Central)

Regra do cliente: **o franqueado não fatura.** Ele solicita; a Central cria,
aprova desconto e fatura.

```
franqueado no app
      │
      │ POST app/v1/entregador/solicitacoes      ← rota NOVA
      ▼
 SOLICITACAO (rascunho: cliente, itens, desconto pedido, justificativa)
      │
      │ evento → canal empresa.{id}.central       ← canal JÁ EXISTE
      ▼
 fila da Central (tempo real)
      │
      ├── atendente ajusta / aprova desconto  → PolicyEvaluator (alçada)
      │                                          ↑ motor JÁ EXISTE
      ├── acima da alçada dele → sobe para supervisor
      │
      └── aprovada → PedidoService::criar(...)   ← serviço JÁ EXISTE
                          │
                          └─ mudarSituacao(CONCLUIDO) = faturar
                                 └─ baixa estoque + gera financeiro
```

Três observações de implementação, todas verificadas no código:

1. **Solicitação não é pedido.** Criar `Pedido` só na aprovação evita poluir
   estoque e financeiro com rascunho. Entidade própria (`pedido_solicitacoes`),
   com o pedido nascendo no momento do aceite.
2. **Faturar não é método novo.** Não existe `faturar()` no `PedidoController`; o
   faturamento é `mudarSituacao` para situação com efeito CONCLUIDO. Usar a
   máquina de estados preserva a idempotência (`estoque_movimentado`).
3. **A fila já tem precedente.** `PedidoService::criar:38` dispara
   `PedidoEntrouNaFila` + `AtribuirPedidoJob`. A fila de solicitações segue o
   mesmo padrão de evento.

---

## Fases

Cada uma entrega valor sozinha e nenhuma exige que a seguinte exista.

### F1 — Vínculo e perfis (fundação)

- Tipo de vínculo em `colaboradores` (`funcionario` | `franqueado` | `industrial`),
  ou tabela própria se o franqueado tiver dados que colaborador não tem (CNPJ,
  contrato, território).
- Papéis novos no token: `role:franqueado`, `role:industrial` — o `AppRole.php:16`
  já aceita, basta emitir a ability no `AppAuthController` (hoje fixo em
  `role:entregador`, linha 119).
- Sub-grupos de rota em `app/v1` por papel, no padrão já usado.

**Cuidado:** franqueado é PJ sem vínculo CLT. Se virar `colaborador`, os
relatórios de RH passam a contá-lo como funcionário — conferir `Domain/Rh` antes.

### F2 — Alçada de desconto

O motor já existe; falta ligar.

- Cadastro de política de alçada por perfil/produto/segmento, usando as condições
  de `PolicyEvaluator` (`limite`, com `campo` e `valor_max`).
- Ponto de chamada: `PedidoService::recalcularTotais:185` hoje soma o desconto dos
  itens **sem verificar nada**. A verificação entra aqui (ou em `criar`, antes do
  commit).
- **Fail-closed** — sem política cadastrada, desconto zero. É o que o `CLAUDE.md`
  manda para dinheiro.
- Trilha: quem pediu, quem aprovou, quando, com qual justificativa e qual margem
  resultante.

### F3 — Central de Vendas (módulo)

- `Domain/Venda/CentralVendasService.php` — irmão do `CentralService` de
  logística, não extensão dele.
- Rotas `central-vendas/*` sob permissões novas (`venda.aprovar`,
  `venda.faturar`, `venda.solicitacao.view`).
- Feature `frontend/src/features/central-vendas/`, no padrão de `features/central/`.
- Painel: fila de solicitações em tempo real (canal `empresa.{id}.central` já
  existe), aprovação de desconto, criar/faturar pedido, acompanhamento.
- Absorver o que já existe em vez de duplicar: pós-venda (`CrmController`),
  missões (`MissaoController`), distribuição (`CentralService`).

### F4 — Solicitação pelo app

**Bloqueada pela pendência 1** (o cliente não lembra se hoje é app ou WhatsApp).

- Se app: `POST entregador/solicitacoes` + tela no `app-entregador`.
- Se WhatsApp: só o painel da Central; o atendente digita.
- Recomendação: fazer o painel primeiro. Ele serve aos dois casos, e a tela do app
  vira incremento.

### F5 — Remuneração do franqueado

- Reusar `colaborador_comissoes` — **sem mudança de schema**. `tipo_comissao=2`
  (repasse) é o modelo de franquia; `percentual_app` dá regra por canal.
- Extrato no app: o que ganhou no período, por pedido.
- Fechamento junto ao `MaloteService`, que já faz o acerto do entregador.

**Depende da pendência 3** (como o cliente remunera hoje).

### F6 — NF-e em campo (industrial)

- Expor `notas/emitir` e `notas/{id}/danfe` ao `app/v1` sob `role:industrial`.
  O `Domain/Fiscal` já faz XML, DANFE, SPED e certificado.
- **Fail-closed**: sem certificado da empresa, não emite (regra vigente).
- Definir o comportamento sem rede: pendente de emissão, ou bloqueia a venda?

### F7 — Operação offline

A mais delicada, porque toca consistência.

- Fila local no `app-entregador` (hoje não há AsyncStorage; o MovelApp resolveu
  com SQLite de 8 tabelas).
- Cache do necessário: produtos, preços, clientes da rota, situações, motivos.
- **Idempotência**: id de operação gerado no dispositivo, para o mesmo pedido não
  entrar duas vezes quando a rede voltar.
- Conflito de preço: mudou no servidor enquanto o app estava offline — vale o do
  momento da venda ou o atual? **Decisão de negócio.**

**Não subestimar:** é onde projetos de campo quebram. Vender offline com preço
defasado e desconto sem alçada é combinação cara — por isso F2 vem antes.

### F8 — Impressão térmica

**Bloqueada pela pendência 2** (parque de impressoras).

- ESC/POS genérico: portar a lógica de layout de `ESCP.java` /
  `NotaFiscalImpressao.java` do MovelApp para um módulo Bluetooth.
- Manter as "Leopardo Pro Max": exige `NfePrinterLib.jar` e módulo nativo — Expo
  precisa de *development build*, não roda em Expo Go.
- Trocar o parque por ESC/POS padrão pode sair mais barato que manter integração
  proprietária, dependendo da quantidade.

### F9 — Desligar os legados

Só depois de F1–F8 em produção **e** conferidos contra o comportamento antigo.
Enquanto isso os dois legados seguem rodando: atendem o cliente hoje, e
`targetSdk 28` impede publicação em loja, não uso.

---

## Ordem sugerida

```
F1 (vínculo/perfis)
  └─► F2 (alçada)  ──► F3 (Central de Vendas)  ──► F4 (solicitação no app)
                                                      ↑ pendência 1
  └─► F5 (remuneração)   ← pendência 3
  └─► F6 (NF-e industrial)   ← mais barata: Fiscal já pronto

F7 (offline) e F8 (impressão) em paralelo — F8 travada pela pendência 2.
F9 fecha, só após conferência.
```

**Ponto de partida: F2.** Resolve o problema que hoje custa dinheiro sem ninguém
ver, não depende de resposta externa, e é pré-requisito honesto de F7 — vender
offline sem alçada seria propagar o problema.

---

## O que NÃO fazer

- **Não estender o `CentralService` de logística.** Ele distribui entrega; vendas
  é outro domínio. Irmão, não herdeiro.
- **Não criar `Pedido` para rascunho de solicitação.** Poluiria estoque e
  financeiro. Entidade própria até a aprovação.
- **Não inventar `faturar()`.** Usar `mudarSituacao` preserva a máquina de estados
  e a idempotência que já existem.
- **Não permitir preço livre no app** para alcançar paridade com o legado. A trava
  atual está certa; o que falta é a exceção controlada.
- **Não modelar franqueado como cliente.** Ele vende em nome da rede — NF-e e
  comissão sairiam erradas.
- **Não portar o app do industrial como app separado.** Repetiria o erro atual.
- **Não copiar o `NfePrinterLib.jar`** sem confirmar licença de redistribuição.
- **Não reaproveitar o `movelapp.jks`** — está versionado no SVN, ou seja,
  comprometido. Keystore novo, fora do repositório.

---

## Antes de executar

Este plano se apoia em leitura de estrutura, rotas, contratos e pontos de decisão
— **não** na regra de negócio linha a linha dos legados (~5 mil linhas). Antes de
desligar qualquer app, conferir: tabela de preço por segmento, condições de
pagamento e o cálculo de comissão em uso.

As cinco pendências estão na auditoria. Três bloqueiam fases inteiras: solicitação
(F4), remuneração (F5) e impressoras (F8).
