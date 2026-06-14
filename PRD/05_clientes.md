# PRD — Clientes / CRM  ·  D05

- **Status:** ✅ pronto
- **Criticidade:** 🟠 (cadastro central do negócio; convênio/comodato têm efeito financeiro)
- **Decisão:** **REESCREVER** (com cuidado — quebrar em sub-recursos)

---

## 1. Escopo
- **Controllers:** `Cliente` (1318), `Maladireta` (274), `Posvendacadastro` (263),
  `Posvenda` (208), `Clientecontatotipo` (130), `Clientecontatosituacao` (129),
  `Tipopessoa` (127), `Segmento` (126), `Clientecontato` (87), `Clienteproduto` (15).
- **Tabelas:** `clientes`, `clientetelefones`, `clienteenderecos`, `clientecontatos`,
  `clienteprodutos`, `clienteconvenios` (+dependente/produtoconvenio/limite),
  `comodatos` (vínculo), `tipopessoas`, `segmentos`, `posvendas`/`posvendapesquisas`,
  `maladiretas`.

## 2. O que o módulo FAZ
- **Cadastro de cliente** (PF/PJ): dados, múltiplos telefones/endereços, segmento,
  setor, condição de pagamento, produtos/preços específicos do cliente.
- **Convênio** 🟠: cliente conveniado com **limite de crédito** (`conveniolimite`),
  dependentes, produtos do convênio, fechamento (vale-gás corporativo). Tem efeito
  financeiro (limite, fechamento → cobrança).
- **Comodato**: vínculo de equipamento em comodato ao cliente.
- **Contratos / etiquetas**: geração de contrato e etiquetas de convênio (PDF).
- **CRM**: contatos (tipo/situação), pós-venda (pesquisas de satisfação), mala direta.

## 3. Como FAZ hoje
- Usa **`ClienteRequest`** (FormRequest — boa prática) para validação.
- `store`/`update` **gigantes** (`update` ~600 linhas, 377→985): cadastro + convênio
  + comodato + produtos + endereços + telefones num método só.
- 0 `$_GET`/whereRaw interpolado no controller (relativamente limpo para o tamanho).
- Buscas de cliente (autocomplete) em SearchController (D-search).

## 4. Gambiarras / dívida técnica
- [ ] **God methods**: `store`/`update` concentram dezenas de responsabilidades
      (cliente, convênio, dependentes, comodato, produtos, endereços). Difícil testar.
- [ ] Lógica de convênio/limite embutida no controller (deveria ser Service).
- [ ] `updateCampoCliente` (update genérico de 1 campo) — padrão arriscado (mass
      assignment de campo arbitrário; verificar whitelist).
- [ ] Geração de contrato/etiqueta (PDF) acoplada ao controller.

## 5. Riscos de tocar
- **Médio-alto**: cliente é referenciado por Pedido, NF-e, Financeiro, Convênio,
  Comodato. O **limite de convênio** afeta o que o cliente pode comprar fiado →
  efeito financeiro. Reescrever exige preservar essas regras.
- `clientes` é das tabelas mais referenciadas (muitas FKs).

## 6. Estado de compatibilidade Postgres
- ✅ index/create/edit validados (200) na varredura. ClienteRequest funciona.
- Sem whereRaw de risco no controller (buscas estão no SearchController — Frente C).

## 7. Visão REESCRITA (Laravel 12)
- Quebrar o God controller em **recursos/sub-recursos**: Cliente, ClienteEndereco,
  ClienteTelefone, ClienteContato, ClienteProduto, Convênio, Comodato — cada um com
  FormRequest/Resource/Policy.
- **Service de Convênio** (limite, dependentes, fechamento) testável e isolado.
- `store`/`update` viram orquestração de Actions pequenas (uma por agregado).
- UI moderna: ficha 360º do cliente (abas: dados, endereços, convênio, comodato,
  histórico de pedidos, pós-venda).
- Preservar contratos de busca usados pelo app (SearchController) e por Pedido.

## 8. DECISÃO e justificativa
- **Decisão: REESCREVER** — mas faseado (não num big-bang): primeiro o cadastro base
  + endereços/telefones/contatos; depois convênio/comodato (com baseline financeiro).
- **Por quê:** é cadastro central com alta dívida (God methods) e alto valor de UX
  (ficha 360º). Risco médio gerenciável quebrando em agregados.
- **Pré-requisitos:** D11; mapear o uso de cliente por Pedido/NF-e/Financeiro antes
  de mexer no schema; baseline para a parte de convênio (efeito financeiro).
- **Esforço:** médio-alto (é grande). Cadastros de apoio do D05 (tipopessoa,
  segmento, contato tipo/situação) = baixo, vão na leva de apoio.
- **Ordem:** cadastros de apoio cedo; Cliente core depois dos cadastros base e do
  D06 (produtos); convênio junto/depois do financeiro (D04).
