# PRD — Fiscal: NF-e / NFC-e  ·  D02

- **Status:** ✅ pronto
- **Criticidade:** 🔴🔴 (o MAIOR risco do sistema — erro = nota rejeitada/multa/Receita)
- **Decisão:** **MANTER + REFATORAR pontual** o motor fiscal · **REESCREVER** só a UI (ver §8)

---

## 1. Escopo
- **Controllers:** `Nfemitida` (2213 — maior do sistema), `Nfweb` (1763),
  `CupomFiscal` (1359), `Impostonf` (891), `Nfrecebida` (714), `IBPT` (254),
  `NfOperacao` (243), + tabelas de tributos (`Nficms`/`Nfpis`/`Nfcofins`/`Nfipi`/
  `Nfcst`/`Nfclasstrib`/`Nfgrupofiscal`/`Nfsituacao`/`Ocorrenciasremessas`/`ConfigNfcePedido`).
- **MOTOR FISCAL** (`app/Processors/Nfe/`, 18 arquivos):
  - `NfProcessor.php` — orquestra a emissão.
  - `Tributacao/` — `CalculoImposto`, `NfeImpostoProcessor`, `ImpostoDB`, e Bases
    por tributo: `IcmsBase`, `PisBase`, `CofinsBase`, `IpiBase`, **`IbsCbsBase`**
    (Reforma Tributária IBS/CBS!), `DetBase`, `ValidationBase`.
  - `Tools/` — `MakeXml`, `TagMaker`, `Tools`, `SefazEvento` (comunicação SEFAZ).
  - `XmlTags/` — geração das tags do XML (TagIcms, TagProd, TagIbsCbs).

## 2. O que o módulo FAZ
- **Emite NF-e/NFC-e**: monta o XML, **calcula impostos** (ICMS/ST, PIS, COFINS,
  IPI, FCP, e IBS/CBS da reforma), assina, transmite à SEFAZ, trata retorno/eventos
  (cancelamento, carta de correção, inutilização).
- **Tributação**: por produto × operação × grupo fiscal × UF (origem/destino) —
  matriz fiscal complexa (CST/CSOSN, alíquotas, reduções, ST).
- **Cupom fiscal** (NFC-e/SAT): venda no PDV.
- **NF recebida**: entrada de notas de fornecedores (XML import).
- **IBPT**: tabela de carga tributária aproximada (Lei da Transparência).

## 3. Como FAZ hoje
- Motor fiscal **bem estruturado** (raro no legado): separação por tributo em
  classes Base + Calculo + XmlTags. **Não é gambiarra — é engenharia fiscal densa.**
- Controllers de NF gigantes (Nfemitida 2213) concentram orquestração + UI + ações
  (transmitir, cancelar, CC-e, exportar) — ESSA parte é dívida.
- Já tem suporte a **IBS/CBS** (reforma tributária) — código atualizado.

## 4. Gambiarras / dívida técnica
- [ ] Controllers de NF gigantes (orquestração + UI + ações num lugar) → dívida de
      ORGANIZAÇÃO, não de cálculo.
- [ ] `whereRaw` em alguns pontos (já triados; Reportnfemitidas etc.).
- [ ] Senha do certificado: já migrada de base64 → Crypt (Fase 1).
- **O motor de cálculo (Tributacao/) NÃO é gambiarra** — é o ativo mais valioso e
  perigoso de tocar.

## 5. Riscos de tocar
- **MÁXIMO ABSOLUTO.** Erro no cálculo = nota rejeitada pela SEFAZ, imposto errado,
  multa, problema com a Receita. Cada `CASE`/alíquota tem razão fiscal (UF, CST, ST,
  reduções). Reescrever o motor do zero = recriar anos de regra fiscal de memória →
  **inaceitável sem baseline massivo.**

## 6. Estado de compatibilidade Postgres
- ✅ index validados (200). 🟡 cálculo fiscal por VALOR **NÃO validado** (precisa
  baseline Oracle×Postgres com dados reais — Frente D). É a maior incógnita.

## 7. Visão REESCRITA/REFATORADA (Laravel 12)
- **MOTOR FISCAL (Tributacao/Tools): MANTER** e só refatorar pontualmente (extrair
  do controller o que é orquestração; cobrir com testes golden-master). NÃO reescrever.
- **Controllers de NF: REFATORAR** — separar orquestração (Service de emissão) das
  ações (transmitir/cancelar/CC-e como Actions) e da UI.
- **UI: REESCREVER** — telas modernas de emissão/consulta/eventos por cima dos
  Services. Aqui o ganho de UX é grande e o risco é baixo (não toca o cálculo).
- Considerar lib mantida (sped-nfe/nfephp) se já não for a base — avaliar.

## 8. DECISÃO e justificativa
- **Motor fiscal (Processors/Nfe) → MANTER + refatorar pontual** (extrair do
  controller, blindar com testes). **NUNCA reescrever do zero.**
- **Controllers/UI de NF → REFATORAR a orquestração + REESCREVER a UI.**
- **Por quê:** o cálculo fiscal é o conhecimento mais caro e perigoso do sistema,
  está bem estruturado e já cobre a reforma (IBS/CBS). O que incomoda (controllers
  gigantes, UI velha) é tratável sem tocar o cálculo.
- **Pré-requisitos (BLOQUEANTES):** baseline fiscal massivo (Frente D) — emitir as
  MESMAS notas no Oracle e no Postgres e comparar XML/imposto centavo a centavo;
  objetos ocultos do Oracle (Frente E) que possam afetar tributo.
- **Esforço:** UI = médio; refatoração de orquestração = médio; **validação por
  baseline = o grosso do trabalho.**
- **Ordem:** **ÚLTIMO** a migrar. UI nova pode vir antes (lê o legado), mas o motor
  só se mexe com baseline verde.
