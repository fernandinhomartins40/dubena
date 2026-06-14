# PRD — Fiscal: SPED  ·  D03

- **Status:** ✅ pronto
- **Criticidade:** 🔴🔴 (obrigação acessória à Receita — erro = malha/multa)
- **Decisão:** **MANTER** o motor SPED · **REFATORAR** pontual · **REESCREVER** só UI

---

## 1. Escopo
- **Controllers:** `Spedfiscal` (285), `Spedcontribuicao` (205), `Spedcreditos` (198).
- **MOTOR** (`app/Processors/Sped/`, **119 arquivos**):
  - `SpedProcessor.php`, `AbstractReg.php`, `Util.php`.
  - `Fiscal/` — blocos do SPED Fiscal (EFD ICMS/IPI): Bloco0, C, D, E, H, 1, 9...
  - `Contribuicao/` — blocos do SPED Contribuições (PIS/COFINS): Bloco0, A, C, D, M, 1...
  - Cada **registro** do layout (C100, C170, M100, 1500...) é uma classe `RegXXXX`.

## 2. O que o módulo FAZ
- Gera os **arquivos SPED** (texto posicional layout Receita) a partir dos dados
  fiscais do período: notas, itens, impostos, apuração ICMS/IPI/PIS/COFINS, créditos.
- É **obrigação acessória mensal** — entregue à Receita; erro gera malha fiscal.
- Apuração de créditos (PIS/COFINS, ICMS) com regras específicas.

## 3. Como FAZ hoje
- Motor **muito bem estruturado** (119 classes, uma por registro do layout) —
  espelha o leiaute oficial do SPED. **Engenharia fiscal madura, não gambiarra.**
- Controllers finos (só disparam a geração e entregam o arquivo).
- Usa funções de data/agregação (já traduzidas p/ Postgres na Fase 3 — incl.
  `unique()`/listagg que eram Oracle).

## 4. Gambiarras / dívida técnica
- [ ] Os `RegXXXX` tinham `LISTAGG`/`unique()` Oracle (traduzidos p/ STRING_AGG /
      string_agg + LIMIT). **Validado por sintaxe; valor depende de baseline.**
- [ ] `AbstractReg`/`Util` concentram helpers — ok.
- **O motor em si não é dívida** — é a implementação fiel do leiaute SPED.

## 5. Riscos de tocar
- **MÁXIMO.** Arquivo SPED errado = inconsistência com a Receita (cruza com as
  NF-e emitidas/recebidas). Cada registro tem layout e regra de preenchimento
  oficiais. Reescrever do zero = reimplementar 119 registros do leiaute → inviável
  sem ganho real.

## 6. Estado de compatibilidade Postgres
- ✅ Geração roda (sintaxe traduzida). 🟡 **Conteúdo do arquivo por VALOR não
  validado** — precisa baseline: gerar SPED do mesmo período no Oracle e no Postgres
  e comparar arquivo a arquivo (Frente D/E/F).

## 7. Visão REESCRITA/REFATORADA (Laravel 12)
- **Motor SPED → MANTER** (refatorar só o que for incompatível/feio; cobrir com
  teste golden-master de arquivo gerado). NÃO reescrever os 119 registros.
- **Controllers/UI → REESCREVER UI** (tela de geração/validação/download moderna).
- Considerar validador oficial (PVA) no pipeline de teste.

## 8. DECISÃO e justificativa
- **Motor SPED → MANTER + refatorar pontual.** **Controllers/UI → REESCREVER UI.**
- **Por quê:** é a implementação fiel de um leiaute oficial complexo (119 registros),
  cruzada com a Receita. O valor está em estar correto, não em estar bonito.
  Reescrever = risco fiscal sem retorno.
- **Pré-requisitos (BLOQUEANTES):** baseline SPED (arquivo Oracle×Postgres idêntico
  por período) — Frentes D/E/F; valida de quebra as traduções de listagg/unique.
- **Esforço:** UI = baixo-médio; **validação por baseline = o trabalho real.**
- **Ordem:** **ÚLTIMO** (junto/depois de D02 NF-e — compartilham os dados fiscais).
