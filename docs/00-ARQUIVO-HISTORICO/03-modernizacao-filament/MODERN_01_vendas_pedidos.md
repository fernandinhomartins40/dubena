# MODERNIZAÇÃO (auditoria de código) — Vendas / Pedidos · D01

>
> **Paradigma-alvo:** ver [`MODERN_00_VISAO_UX.md`](MODERN_00_VISAO_UX.md) — página
> completa por entidade (abas/RelationManagers), navegação DECLARATIVA (sem menu-no-banco),
> permissão por recurso/ação (roles). O layout/fluxo legado é DESCARTADO; só as REGRAS de
> negócio são preservadas.
> **Método:** auditoria do CÓDIGO REAL (não da doc), comparando o PRD fiel
> [`01_vendas_pedidos.md`](01_vendas_pedidos.md) (estado original) com o código de
> hoje (pós F0–F4A). Cada afirmação tem `arquivo:linha` verificado.
> **Objetivo:** registrar o que mudou (antes×agora) e desenhar a modernização de
> UX/UI/fluxos sem quebrar.

---

## 1. ANTES × AGORA (bugs do PRD fiel — verificado no código atual)

| Item (PRD fiel) | Estado original | HOJE (auditado) | Ref. atual |
|---|---|---|---|
| Atualizarprecos UPDATE Oracle | 🔴 quebrado no PG | ✅ **corrigido** (UPDATE…FROM) | `AtualizarprecosController.php:178` |
| Vendaativa `rownum<=2` (4×) | 🔴 quebra no PG | ✅ **corrigido** (`limit 2`) | `VendaativaController.php:113,238,325,414` |
| Caixa `->wwhere('ativo')` | 🔴 fatal | ✅ **corrigido** (`->where('ativo', true)`) | `CaixaController.php:277` |
| Caixa recibo CR colunas snake | 🔴 coluna inexistente | ✅ **corrigido** | `CaixaController.php:836` (comentário) |
| Atualizarprecos `$this->error` | 🟠 undefined property | ❌ **ABERTO** (define `errors`, lê `error`) | `AtualizarprecosController.php:35` |
| Atualizarprecos::store sem authorize | 🟠 sem authorize | ❌ **ABERTO** (store sem `$this->authorize`) | `AtualizarprecosController.php:61` |

> **Conclusão:** a F0 fechou os bugs que QUEBRAVAM em produção (Postgres/typo). Restam
> resíduos menores (typo `error`, authorize ausente) e toda a DÍVIDA ESTRUTURAL/UX intacta.

---

## 2. DÍVIDA DE UX/UI E FLUXO (o que faz o sistema "parecer antigo")

- **God controllers**: `PedidoController` (1661 linhas) e `CaixaController` (1053)
  concentram orquestração transacional + montagem de view + regras. Fluxo de venda é
  uma sequência de telas cheias (AdminLTE) com `Form::select`/HTML no backend
  (ex.: `Pedido::buscaPorId` monta `\Form::select`).
- **Tela de venda não é uma jornada**: cadastro de pedido é um formulário longo único,
  sem wizard/etapas, sem feedback em tempo real (estoque, preço, vale-gás calculados no
  submit). UX de "formulário gigante" típico de 2010.
- **Máquina de estados invisível ao usuário**: as transições (pendente→entrega→concluído/
  cancelado) existem na lógica mas não há UI clara de status/kanban; o operador não vê o
  ciclo do pedido como fluxo.
- **Caixa/tesouraria**: baixa de título com rateio é um form ddenso; sem visão de extrato
  moderno nem confirmação visual do efeito (saldo antes/depois).
- **Sem responsividade real / mobile-first** nas telas internas (o app mobile é separado, D13).

---

## 3. REGRAS DE NEGÓCIO A PRESERVAR (NÃO regredir — ativo mais caro)

- Orquestração ATÔMICA na transição de estado do pedido: estoque (SAÍDA ao concluir,
  ENTRADA ao estornar/cancelar, estorno+reinsere ao trocar setor), financeiro (insere/
  exclui financeiro+parcelas+rateios), vale-gás, NF-e/NFC-e, pagamento online.
- Estorno CONDICIONADO: bloqueia se parcela baixada / cheque / boleto / pagamento online.
- Config crítica: `pedidooperacao` (movimentaestoque/movimentafinanceiro) e
  `pedidosituacao` (flags da máquina de estados). Contratos do app mobile (D13).

---

## 4. BLUEPRINT DE MODERNIZAÇÃO (alvo Filament 3 + Domain Services)

- **Camada de domínio**: extrair de `PedidoController` Actions por transição
  (`CriarPedido`, `ConfirmarPedido`, `CancelarPedido`, `TrocarSetor`) com `DB::transaction`
  e **eventos de domínio** (`PedidoConfirmado` → estoque/financeiro/NF/mapa). Controller/UI
  ficam finos. Cobrir com testes (baseline obrigatório).
- **UX da venda**: jornada em etapas (cliente → itens com preço/estoque em tempo real →
  pagamento/condição → confirmação) usando Filament (wizard) ou Livewire; feedback imediato.
- **Pedidos como painel/Kanban**: lista filtrável + visão de status (pendente/entrega/
  concluído) com ações de transição explícitas e seguras (Policy por ação).
- **Caixa**: extrato moderno + baixa com preview do efeito no saldo; manter o Service de
  tesouraria testável.
- **Atualizarprecos**: reescrever como ação com preview do impacto (quantos produtos,
  variação) + bindings (já sem SQLi de UPDATE Oracle, mas ainda interpola filtros — ver §5).

---

## 5. PENDÊNCIAS RESIDUAIS (arquivo:linha — auditado hoje)

- `AtualizarprecosController.php:35` — `implode(', ', $this->error)` → deveria `$this->errors`.
- `AtualizarprecosController.php:61` — `store()` sem `$this->authorize` (index/create/show têm).
- `AtualizarprecosController.php` — filtros (`segmento_id`/`tipopessoa_id`/`setor_id`/`variacao`)
  ainda interpolados no UPDATE…FROM — **verificar bindings** (era SQLi; confirmar se F0
  parametrizou ou só traduziu a sintaxe).
- `VendaativaController.php` — confirmar se `$_GET`/`whereRaw` interpolado de filtros
  (cidade/bairro/rua/datas) foi parametrizado (rownum já foi).
- God controllers Pedido/Caixa — alvo de refatoração em Service (não é "bug", é dívida).

> **Decisão herdada (PRD fiel):** REFATORAR Pedido/Caixa (um dos últimos, exige baseline +
> estoque/financeiro/NF sólidos) · REESCREVER Atualizarprecos/Vendaativa/Promotor/
> Vendasmensais/Promocao/cadastros. Esta auditoria CONFIRMA a ordem e atualiza o estado.
