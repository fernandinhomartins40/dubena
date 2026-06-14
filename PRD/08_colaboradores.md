# PRD — Colaboradores / RH  ·  D08

- **Status:** ✅ pronto
- **Criticidade:** 🟡 cadastro · **Colaboradorcomissoes** é 🟠 (configura regra que vira dinheiro)
- **Decisão:** **REESCREVER** cadastros + checklist · **REFATORAR** comissões (ver §8)

---

## 1. Escopo
- **Controllers:** `Colaboradorcomissoes` (562), `Colaborador` (469),
  `Cadastrochecklist` (360), `Checklist` (303), `Estadocivil` (129),
  `Tipoexame` (127), `Turno` (125), `Parentesco` (121), `Recessotipo` (113),
  `Cargo` (98), `Colaboradorfamilia` (94), `Recessos` (76), `Setorcolaboradores` (23).
- **Tabelas:** `colaboradors`, `colaboradorcomissaos`, `comissaoexcecoes`,
  `colaboradorfamilias`, `setorcolaboradores`, `cargos`, `estadocivils`,
  `parentescos`, `recessos`, `recessotipos`, `turnos`, `tipoexames`,
  `checklists`, `checklistforms`, `checklistpesquisas`, `cadastrochecklists`.

## 2. O que o módulo FAZ
- **Colaborador**: cadastro de funcionários (cargo, setor, turno, família,
  exames, documentos), vínculo a setores (`setorcolaboradores`) e a veículos (motorista).
- **Comissões** 🟠: define **regras de comissão** por colaborador × setor ×
  condição de pagamento, com flag **tonelagem** (comissão por peso vs. por venda) e
  **exceções** (`comissaoexcecoes`). É **configuração** — o cálculo que vira dinheiro
  é aplicado no fluxo de venda/relatório de comissões (D01/D12).
- **Checklist**: formulários de inspeção/pesquisa (`checklistforms`/`pesquisas`),
  preenchidos em campo; cadastro e respostas.
- **Cadastros de apoio**: cargo, estado civil, parentesco, turno, tipo de exame,
  recessos/férias.

## 3. Como FAZ hoje
- Cadastros em padrão resource.
- Comissões: filtros por setor lendo **`$_GET` direto** (controller); configuração
  com replicação por condição de pagamento.
- Checklist: estrutura forms→perguntas→respostas.

## 4. Gambiarras / dívida técnica
- [ ] **`$_GET` lido direto** em `ColaboradorcomissoesController:31-37`
      (`$_GET['setor_id']`) — deve ser `$request`/validação.
- [ ] `SelectRepository` tem o join de comissão com `tonelagem` (já corrigido o case
      `COLABORADORCOMISSAOS`→minúsculo na Fase Postgres).
- [ ] Regra de comissão (tonelagem/exceções) espalhada entre config (aqui) e
      aplicação (D01/D12) — falta um Service único de cálculo de comissão.

## 5. Riscos de tocar
- **Comissões = 🟠**: a regra define pagamento a colaboradores. Mudar a config sem
  entender a aplicação (onde vira valor) pode pagar errado. O cálculo real precisa
  de baseline (junto com D01/D12).
- Cadastros e checklist: baixo risco.

## 6. Estado de compatibilidade Postgres
- ✅ Validado na varredura. Case da tabela comissão corrigido.
- 🟡 `$_GET` no controller de comissões (entra na triagem da Frente C).

## 7. Visão REESCRITA (Laravel 12)
- Cadastros (cargo, estado civil, turno, exame, parentesco, recessos, família,
  checklist) → recursos limpos + UI moderna. Baixo risco.
- **Colaborador**: ficha completa (dados, setores, veículos, exames, documentos
  com vencimento/alerta).
- **Comissão**: extrair **Service de cálculo de comissão** (config + aplicação num
  só lugar testável); só migrar com baseline do relatório de comissões (D12).
- Checklist: formulário dinâmico moderno (builder de perguntas + respostas mobile).

## 8. DECISÃO e justificativa
- **Cadastros + Colaborador + Checklist → REESCREVER** (baixo risco, ganho de UX).
- **Comissões → REFATORAR** (tirar `$_GET`, criar Service de comissão), reescrever
  só após mapear a aplicação no D01/D12 e ter baseline (paga gente).
- **Pré-requisitos:** D11; para comissão, alinhar com D01 (vendas) e D12 (relatório
  de comissões) + baseline.
- **Esforço:** cadastros baixo; comissão médio (precisa do cálculo ponta-a-ponta).
- **Ordem:** cadastros junto da leva de apoio (pós-D11); comissão junto/depois de D01.
