# Plano — acoplar franqueado e industrial ao ecossistema

Como absorver as operações de `legado-movelapp/` e `legado-nfweb/` no Gás em
Casa. Base: [`AUDITORIA_APPS_FRANQUEADO_INDUSTRIAL.md`](AUDITORIA_APPS_FRANQUEADO_INDUSTRIAL.md).

---

## A ideia, em uma frase

**Um app, quatro perfis, uma central que autoriza** — em vez de três aplicativos
que reimplementam pedido, impressão e sincronização cada um do seu jeito.

A intuição do cliente está certa, e o código a sustenta: nenhum dos apps legados
sabe o que é vínculo empregatício. Quem decide o que cada um pode fazer é o
backend. Então a diferença entre funcionário, franqueado e industrial **não é
um app diferente — é um conjunto de permissões e uma regra de remuneração.**

---

## Por que um app só, e não três

O `app-entregador` já tem a operação de rota inteira: jornada, rota, aceitar,
recusar, ocorrência, concluir, missão, venda em campo, vale-gás. O que separa os
perfis é pouco, e é tudo **autorização**:

| Capacidade | Funcionário | Franqueado | Industrial |
|---|---|---|---|
| Receber rota e entregar | sim | sim | não |
| Lançar pedido em campo | limitado | sim | sim |
| Cadastrar cliente | não | sim | sim |
| Dar desconto | não | até o teto dele | até o teto dele |
| Emitir NF-e | não | não | **sim** |
| Ver pendência financeira do cliente | não | sim | sim |
| Imprimir no cliente | sim | sim | sim |
| Remuneração | salário | repasse/comissão | comissão |

Três apps para essa tabela é triplicar manutenção — e foi o que produziu o
estado atual, onde cada app tem sua própria impressão e seu próprio pedido.

---

## Fases

Ordenadas por dependência: cada uma entrega valor sozinha, e nenhuma exige que a
seguinte exista.

### F1 — Vínculo e perfis (fundação)

Sem isto nada mais se apoia.

- `colaboradores`: campo de **tipo de vínculo** (`funcionario`, `franqueado`,
  `industrial`) — ou tabela própria, se o franqueado tiver dados que o
  colaborador não tem (CNPJ, contrato, território).
- Papéis novos no token: `role:franqueado`, `role:industrial` — o
  `AppRole.php` já aceita, é só emitir a ability no login.
- Sub-grupos de rota em `app/v1` por papel, no padrão que já existe para
  `approle:entregador`.

**Cuidado:** o franqueado é pessoa jurídica sem vínculo CLT. Se ele for modelado
como `colaborador`, os relatórios de RH passam a contá-lo como funcionário —
verificar `Domain/Rh` antes de decidir.

### F2 — Alçada de desconto + central de vendas

O controle que nunca existiu. Independe de F1 para começar a modelagem.

- Tabela de **alçada por perfil/produto/segmento**: teto percentual ou valor.
- Verificação no `PedidoService` ao lançar pedido — **fail-closed**: sem regra
  cadastrada, não concede desconto (o `CLAUDE.md` já manda isso para dinheiro).
- Pedido acima do teto entra como **pendente de aprovação**, não como negado —
  quem aprova é a central.
- Tela de central: fila de aprovação, com quem pediu, quanto, para qual cliente
  e qual a margem resultante.
- **Trilha de auditoria**: quem aprovou, quando, com qual justificativa.

Isto sozinho já paga o projeto: hoje qualquer vendedor zera a margem sem que
ninguém veja.

### F3 — Remuneração do franqueado

- Reusar `colaborador_comissoes` — **sem mudança de schema**. O `tipo_comissao=2`
  (repasse: "valor que sobra após a empresa reter um fixo por unidade") já é
  precisamente o modelo de franquia, e `percentual_app`/`empresa_valor_app`
  permitem regra diferente para pedido de app.
- Extrato do franqueado no app: o que ele ganhou no período, por pedido.
- Fechamento integrado ao `MaloteService`, que já faz o acerto do entregador.

**Depende de decisão do cliente** sobre como ele remunera hoje (pendência 2 da
auditoria).

### F4 — Emissão de NF-e em campo (industrial)

- Expor `notas/emitir` e `notas/{id}/danfe` ao `app/v1` sob `role:industrial`.
  O `Domain/Fiscal` já faz tudo — XML, DANFE, SPED, certificado.
- **Fail-closed**: sem certificado da empresa, não emite (regra já vigente).
- Ponto de atenção: emitir NF-e da rua exige rede. Definir o comportamento
  quando não houver — pedido fica pendente de emissão, ou bloqueia a venda?

### F5 — Operação offline

A parte mais delicada, porque toca consistência.

- Fila local de ações no `app-entregador` (o MovelApp resolveu com SQLite de 8
  tabelas; hoje o app novo não tem nem AsyncStorage).
- Cache do necessário para operar sem rede: produtos, preços, clientes da rota,
  situações, motivos de atraso.
- Sincronização com **idempotência** — id de operação gerado no dispositivo, para
  o mesmo pedido não entrar duas vezes quando a rede voltar.
- Resolução de conflito: preço mudou no servidor enquanto o app estava offline —
  vale o preço do momento da venda ou o atual? **Decisão de negócio.**

**Não subestimar:** é aqui que a maioria dos projetos de campo quebra. Vender
offline com preço defasado e desconto sem alçada é combinação cara.

### F6 — Impressão térmica

Depende inteiramente da **pendência 1** (parque de impressoras).

- Se ESC/POS genérico atende: módulo Bluetooth no app novo, portando a lógica de
  layout de `ESCP.java`/`NotaFiscalImpressao.java` do MovelApp.
- Se as "Leopardo Pro Max" ficam: é preciso o `NfePrinterLib.jar` do fabricante e
  um módulo nativo — o Expo exige *development build*, não roda em Expo Go.
- Terceira via: trocar o parque por impressoras ESC/POS padrão. Pode sair mais
  barato que manter integração proprietária, dependendo da quantidade.

### F7 — Desligar os apps legados

Só depois que F1–F6 estiverem em produção **e** conferidos contra o comportamento
antigo. Enquanto isso, os dois legados continuam rodando — eles atendem o cliente
hoje, e `targetSdk 28` não impede uso, só publicação em loja.

---

## O que NÃO fazer

- **Não portar o app do industrial como app separado.** Repetiria o erro atual.
- **Não modelar franqueado como cliente.** Ele vende em nome da rede; se virar
  cliente, a NF-e e a comissão saem erradas.
- **Não deixar desconto livre "por enquanto".** É o problema que motivou tudo.
- **Não copiar o `NfePrinterLib.jar` para o repositório** antes de confirmar
  licença de redistribuição com o fabricante.
- **Não reaproveitar o `movelapp.jks`.** Está versionado no SVN, ou seja,
  comprometido. App novo, keystore novo, fora do repositório.

---

## Ordem sugerida

```
F1 (vínculo/perfis) ──┬── F2 (alçada + central)   ← maior valor isolado
                      ├── F3 (remuneração)         ← precisa de decisão do cliente
                      └── F4 (NF-e industrial)     ← mais barata, Fiscal já pronto

F5 (offline) e F6 (impressão) em paralelo, independentes das demais.
F6 está bloqueada pela pendência das impressoras.
F7 fecha, e só depois de conferência.
```

Se for preciso escolher um ponto de partida: **F2**. É a que resolve o problema
que hoje custa dinheiro sem que ninguém perceba, e não depende de nenhuma
resposta externa para começar.

---

## Antes de executar

Este plano se apoia em leitura de estrutura, rotas e pontos de decisão — **não**
na regra de negócio linha a linha dos dois apps legados (~5 mil linhas). Antes de
cortar qualquer app, é preciso conferir contra o legado: tabela de preço por
segmento, condições de pagamento, e o cálculo de comissão que o cliente usa hoje.

As quatro pendências com o cliente estão na auditoria. Duas delas — impressoras e
modelo de remuneração — bloqueiam fases inteiras.
