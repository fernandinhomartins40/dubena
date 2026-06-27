# F14 — Runbook de Homologação (paridade assistida com o CTRL-WEB)

> UAT lado-a-lado: validar com usuários reais que o ERP-NOVO/SPA reproduz (ou melhora)
> o CTRL-WEB, módulo a módulo. Pré-requisito: F00–F13 concluídas. Cada roteiro tem
> passo, onde fazer (SPA), e o comparativo esperado com o legado. Data: 2026-06-25.
>
> A rastreabilidade automática (todo achado da auditoria tem rota/comando) é garantida
> por `Tests\Feature\F14RastreabilidadeTest` — guard de regressão no CI.

---

## Como conduzir
- Ambiente de homologação com **dados migrados** (`etl:run --check` verde) e gates em
  modo de homologação (SEFAZ/banco de teste). Usuário piloto de UMA empresa.
- Para cada módulo: executar no ERP-NOVO e conferir a saída com o mesmo caso no CTRL-WEB.
- Registrar OK / divergência. Divergência bloqueia o item até correção.

---

## Roteiros por módulo

### 1. Cadastros (clientes/produtos/apoio)
- [ ] Criar cliente com telefones, convênio e preços → conferir abas.
- [ ] Histórico do cliente mostra os pedidos reais (F00.4/F04).
- [ ] Hub de **Configurações** (`/configuracoes`): editar cadastros de apoio (segmentos,
      cargos, parentescos, tipos de exame) e a config global (RT/CSRT, SMTP, SAT) — F01/F04.
- [ ] Inconsistências de rua/bairro (`/cadastros/inconsistencias`) apontam duplicatas — F11.

### 2. Venda → Estoque → Financeiro (fluxo central)
- [ ] Criar pedido, concluir → estoque baixa + financeiro gerado (máquina de estados).
- [ ] Conferir saldo de estoque vs legado; conferir título/parcelas vs legado.
- [ ] Reabrir/cancelar → estorno de estoque e financeiro.

### 3. Fiscal (F03/F09)
- [ ] Emitir NFC-e a partir do pedido concluído (`/pedidos/{id}/emitir-nfce`) →
      autorizada em homologação SEFAZ (`FISCAL_DRIVER=nfephp`).
- [ ] Cancelar nota; registrar **carta de correção** (CCE); **inutilizar** faixa.
- [ ] Gerar SPED Fiscal e Contribuições → validar no PVA da Receita.
- [ ] Comparar DANFE/valores com a mesma NF no legado.

### 4. Compras / NF de Entrada (F06)
- [ ] Importar XML de NF do fornecedor → nota + itens.
- [ ] Processar → entrada de estoque + contas a pagar. Conferir saldo e CP.

### 5. Financeiro / Caixa / Cheque (F07)
- [ ] Agrupar títulos (fechamento) e desagrupar; reparcelar saldo em aberto.
- [ ] Baixa de títulos em lote no caixa; lançamento em caixa fechado (autorizado).
- [ ] Cheque recebido: depósito → compensação (credita caixa) → conferir saldo.

### 6. Cobrança (F08)
- [ ] Gerar boleto (`COBRANCA_DRIVER=caixa|itau`) → linha digitável/código de barras válidos.
- [ ] Gerar **remessa** `.rem` → banco de homologação aceita.
- [ ] Processar **retorno** → liquidação baixa a parcela.
- [ ] **Conciliação contábil** (CONSISA) bate os saldos.

### 7. Satélites (vale-gás / comodato / convênio)
- [ ] Vale-gás: venda/baixa; Comodato: empréstimo baixa estoque, devolução repõe.
- [ ] Fechamento de convênio consolida pedidos → 1 financeiro (+NF/boleto via gates).

### 8. RH / Frota (F12)
- [ ] Colaborador: família/exames/turnos/pontos/comissões.
- [ ] Veículo: abastecimento/óleo/pneu; **entrada-saída de pátio** e **documentos** (vencimento).

### 9. CRM (F12)
- [ ] Pós-venda, promoção, sorteio, meta, checklist.
- [ ] **Mala direta** (`/crm/mala-direta`): segmentar e exportar CSV.

### 10. Monitora (F12) e Mobile
- [ ] GPS: posições/cercas (`MONITORA_DRIVER=sgcasa` em homologação).
- [ ] App cliente/entregador: pedido, pagamento online (`PAGAMENTO_DRIVER=erede`), push (FCM).

### 11. Relatórios (F10)
- [ ] Central (`/relatorios`): rodar os 17 relatórios; comparar números com o legado.
- [ ] Comissões: conferir que o total bate com a matemática fina (não média).
- [ ] Export CSV/PDF de cada relatório.

### 12. Auditoria & Segurança (F11/F13)
- [ ] Trilha de auditoria (`/relatorios/auditoria`) registra create/update/delete; segredos não aparecem.
- [ ] Multi-tenant: usuário de outra empresa não vê dados (RLS + scope).
- [ ] Rate-limit responde a abuso (429).

---

## Critérios de aceite (da spec F14)
- [ ] Todos os módulos 🔴/🟡/⚠️ da auditoria fechados — **garantido por
      `F14RastreabilidadeTest`** (rotas/comandos/drivers presentes).
- [ ] Saídas (NF, boleto, SPED, DRE) conferem com o legado nos casos-piloto.
- [ ] UAT aprovado pelos usuários por módulo.
- [ ] `golive:check` e `cutover:check` verdes (ver `F16_RUNBOOK_GOLIVE.md`).

> Nota: este runbook é o roteiro OPERACIONAL de UAT. A cobertura de código (paridade
> implementada) está provada pela suíte automatizada (**341 testes**) e pelo teste de
> rastreabilidade. O que resta é execução com usuários + homologação dos gates externos
> (SEFAZ/banco/SGCasa/eRede) — fora do código.
