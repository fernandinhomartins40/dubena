# PRD FIDEDIGNO (linha-a-linha) — SPED (EFD ICMS/IPI + EFD Contribuições) · D03

> Lidos 100%: SpedfiscalController(285), SpedcontribuicaoController(205),
> SpedcreditosController(198), e o orquestrador **SpedProcessor(371)**.
> Caracterizados (arquitetura): Processors/Sped/{AbstractReg(754), Util(346),
> Fiscal/Bloco{0,1,9,C,D,E,G,H,K}, Contribuicao/Bloco{0,1,9,A,C,D,F,M}} — as classes
> de registro (Reg0000, RegC100, RegM100…) que montam cada linha do arquivo. A regra de
> cada registro vive nessas classes; o controller só declara a árvore de blocos.
> (FechamentomensalDre/BalancoRepository são do D04/gestão — não SPED.)

- **Status:** ✅ pronto (fiel — controllers + orquestrador lidos; classes de registro caracterizadas)
- **Criticidade:** 🔴🔴🔴 (obrigação acessória fiscal — arquivo entregue ao fisco)
- **Decisão:** **REFATORAR** (preservar o motor de registros — é regra fiscal correta)

---

## 1. O que cada peça FAZ (verificado)
- **SpedfiscalController (285):** gera a **EFD ICMS/IPI** (Sped Fiscal). `index/create`
  mostram o form; `validasped` (AJAX) **valida gaps na numeração de NF** do período por
  modelo (detecta NF faltante antes de gerar) e lista status de cada NF; `gerarsped`
  monta a **árvore declarativa de blocos** (0/C/D/E/G/H/K/1/9 com regs e childrens) e
  delega ao `SpedProcessor`. `getBlocoE` varia conforme `spedatividade` (comércio vs.
  indústria → IPI).
- **SpedcontribuicaoController (205):** gera a **EFD Contribuições** (PIS/COFINS). Mesma
  mecânica — árvore de blocos 0/C/D/F/M/1/9 (com `has010` por bloco) → `SpedProcessor`.
- **SpedcreditosController (198):** CRUD de **créditos de PIS/COFINS** (saldo de crédito
  por período de apuração, alimenta M100/M500). `Creditopiscofins` (tabela de códigos).
- **SpedProcessor (371) — o MOTOR:** carrega as NFs (emitidas+recebidas) do período num
  grande SQL UNION; instancia **dinamicamente** as classes de registro por reflexão
  (`new $pathOfClass($pathBloco, $data, $childrens)`); monta blocos→regs→childrens/
  múltiplos; calcula **IND_MOV** (indicador de movimento) por bloco; totaliza por
  bloco/registro (9900/9990/9999); escreve o arquivo TXT pipe-delimitado e devolve URL
  de download.

> Regra real a preservar: a estrutura de registros do SPED (layout oficial da Receita,
> blocos, IND_MOV, totalizações, validação de numeração) é obrigação acessória legal.
> Entregar errado = malha fiscal/multa. **As classes de registro são o ativo.**

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 Bug real (typo) e concorrência
- **SpedcreditosController::destroy:133 — `catch (Excpetion $e)`** (typo de `Exception`,
  sem `\`): classe inexistente → se o delete lançar exceção, **fatal "class not found"**
  em vez de tratar. **Corrigir p/ `\Exception`.**
- **SpedProcessor::generateFile:286 — `fopen($filename, 'w+')` no CWD**: grava
  `EFD_{cnpj}-{mes}.txt` no **diretório de trabalho** (raiz do app), sem path absoluto →
  **colisão entre empresas/requisições concorrentes** + arquivo fora do storage.
  **Gravar em storage com nome único (id/uuid).**

### 🔴 Segurança (SQL cru)
- **SpedProcessor::getNf:89-123 — SQL gigante interpolado** (`DB::select`): `$empresa_id`
  (sessão) e `$datainicio`/`$datafim` em `TO_DATE('$datainicio',...)`. As datas vêm de
  `mesano` parseado por Carbon (risco reduzido), mas é SQL cru — **parametrizar com
  bindings**.

### 🟠 Resíduos / dívida
- **Spedfiscal::gerarsped:79** — `// dd($e->getTrace())` comentado (inócuo, remover).
- **SpedcontribuicaoController::destroy vazio** (scaffold — rota provavelmente inexistente).
- **`$_GET["registro"]` direto** em Spedcreditos::index (via Eloquent binding — ok).

### 🟡 Dívida estrutural
- **Árvore de blocos hardcoded no controller** (centenas de linhas declarativas de
  `Reg*`) — poderia ser config/declaração externa; acopla layout SPED ao controller.
- **Instanciação por reflexão** (`new $pathOfClass`) — flexível mas frágil (erro de nome
  só falha em runtime; já lança exceção "classe não encontrada", o que é bom).
- **Geração síncrona** (gerar SPED de um mês inteiro numa request HTTP) — para volumes
  grandes deveria ser **job assíncrono** com download posterior.
- Stack: depende dos helpers de Collection (unique/listagg em PHP — já PG-safe).

### ✅ O que está BOM (NÃO regredir)
- Motor de registros (`SpedProcessor` + classes Reg*) é **bem arquitetado**: árvore de
  blocos, childrens/múltiplos, IND_MOV, totalizações automáticas (9900/9990/9999),
  validação de numeração de NF, `__get` defensivo, separação controller (declara) ×
  processor (gera) × classes (regra do registro). authorize view/create. Já PG-compatível
  (TO_DATE/UNION/`||`). Variação de bloco por perfil/atividade da empresa.

## 3. Especificação do REFATORADO (Laravel 12)
- **NÃO reescrever as classes de registro** — são o layout fiscal oficial, caro e correto.
- **Refatorar SpedProcessor**: bindings no getNf (sem SQL interpolado); gravar arquivo em
  **storage com nome único**; tornar a geração um **job/queue** (gerar mês inteiro fora do
  request) com notificação + download.
- **Mover a árvore de blocos** para arquivos de configuração declarativa (config/sped.php
  fiscal/contribuições) — controllers finos.
- **Spedcreditos → REESCREVER UI** (recurso limpo; corrigir o `Excpetion`).
- Validação de numeração (`validasped`) → serviço reutilizável.

## 4. DECISÃO
- **Decisão: REFATORAR** (motor fiscal correto; modernizar I/O + async + segurança).
- **Quick wins aplicáveis JÁ:**
  (a) **`Excpetion`→`\Exception`** no Spedcreditos::destroy (catch quebrado);
  (b) **gravar SPED em storage com nome único** (colisão/race no CWD);
  (c) parametrizar o SQL do getNf (SQL cru);
  (d) remover `// dd` comentado.
- **Pré-requisitos:** D02 (NF-e) sólido (SPED lê as NFs e impostos); baseline com SPED
  reais validados pelo PVA da Receita; alinhar com D04 (créditos/DRE) e D06 (inventário H010).
- **Esforço:** médio (motor existe e funciona) + async + config.
- **Ordem:** depois de D02; é consumidor das NFs — um dos últimos a tocar.
