# PRD FIDEDIGNO (linha-a-linha) — Colaboradores / RH · D08

> Lidas 100% das 3.024 linhas dos 13 controllers: Colaboradorcomissoes(562),
> Colaborador(469), Cadastrochecklist(360), Checklist(303), Estadocivil(129),
> Tipoexame(127), Turno(125), Parentesco(121), Recessotipo(113), Cargo(98),
> Colaboradorfamilia(94), Recessos(76), Tiporecessos(77), Setorcolaboradores(23).

- **Status:** ✅ pronto (fiel)
- **Criticidade:** 🟡 cadastros · **Colaboradorcomissoes 🟠** (regra que vira dinheiro)
- **Decisão:** **REESCREVER** cadastros/checklist · **REFATORAR** comissões

---

## 1. O que cada controller FAZ (verificado)
- **Colaboradorcomissoes (562):** engine de **regras de comissão** por colaborador ×
  setor × condição de pagamento × produto, com **tonelagem** (comissão por peso),
  **exceções por segmento**, **replicação** (a todos setor×colaborador) e
  **validação de sobreposição de período** (`verificarExistencia`). Inserts em massa.
- **Colaborador (469):** cadastro com 4 agregados aninhados (telefones, família,
  férias, exames) — padrão delete-all + saveMany. Validação CPF. Cria rua on-the-fly.
- **Cadastrochecklist (360):** builder de formulário dinâmico (form→tópicos→perguntas
  →respostas), com inativação e verificação de período.
- **Checklist (303):** preenchimento/consulta das pesquisas de checklist em campo +
  geração de PDF (dompdf).
- **Cadastros simples:** Cargo, Estadocivil, Parentesco, Tipoexame, Turno,
  Recessos, Recessotipo, Tiporecessos — CRUD por grupo/empresa.
- **Colaboradorfamilia (94):** 100% VAZIO (gravado pelo ColaboradorController).
- **Setorcolaboradores (23):** só `buscaAjax`.

> Regra real a preservar: a engine de comissão (sobreposição de período, tonelagem,
> exceções, replicação) define **pagamento a colaboradores** — efeito financeiro.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 QUEBRADO em produção
- **RecessotipoController::update:89 — `dd($data);`** esquecido no meio do método!
  O update **mata a requisição** (dump-and-die) e **nunca salva** + expõe os dados na
  tela. **Editar tipo de recesso está quebrado.** Remover o dd.

### 🔴 Segurança / autorização
- **RecessotipoController**: NENHUM `authorize()` em index/store/update/destroy —
  qualquer usuário autenticado cria/edita/deleta. (Turno também sem authorize em
  index/store/update/destroy; Parentesco/Turno sem authorize no index.)
- **`catch (Exception $ex)` SEM import/`\`** em Recessos:69, Tiporecessos:63 →
  catch quebrado (fatal se exceção no destroy).
- **Colaboradorcomissoes:31-36** lê `$_GET['setor_id']`/`$_GET['colaborador_id']`
  direto. `Checklist`/`Cadastrochecklist` também lêem `$_GET` direto (checarFiltro,
  create, verificarPeriodo).

### 🟠 Bugs funcionais
- **Tiporecessos::store:29 e update:46** — validação `unique` do campo **`legenda`
  aponta para a coluna `descricao`** (`unique:recessotipos,descricao,...` no lugar de
  `legenda`): valida o campo errado → pode aceitar legenda duplicada / barrar errado.
- **Colaborador::show:203** — `Cidade::where([['grupo_id', ...->id]...])` usa **id da
  empresa** onde edit:257 usa **grupo_id**: inconsistência → lista de cidades pode vir
  vazia no show.
- **Colaborador::buscaPorSetor** — retorna estruturas diferentes nos dois ramos
  (select id as colaborador_id vs get() completo): contrato inconsistente.
- **Cadastrochecklist::store:58 / editarCadastro:298** — usa `$perguntasid` que pode
  estar **indefinida** se `$topicosid==false` (notice PHP 7.4); e o fluxo segue mesmo
  com sub-criação retornando false (não aborta de fato).
- **Cadastrochecklist::verificarPeriodo:335-359** — 5 condições de sobreposição com
  linhas **idênticas duplicadas** (346==352): copy-paste; lógica de período frágil.
- **Checklist::store:107** — `throw new exception($resposta)` passando um **objeto
  Exception como mensagem string** (salvar() retorna $ex). Funciona por coerção, fluxo
  de erro confuso.

### 🟡 Dívida estrutural
- **Comissões (562):** `getMsg` (486-533) monta mensagem de erro com **SQL de 4 UNION
  ALL + cast('' as varchar(1))** — 4 selects no banco só p/ montar uma string que
  caberia em PHP. Gambiarra. `verificarExistencia` interpola `$comissao->*` em SQL cru
  (vêm de dados internos; risco menor, mas deveria ser binding).
- **God controllers (Colaborador/Cadastrochecklist):** store/update gigantes com
  delete-all + saveMany repetido por agregado; muito código duplicado store↔update.
- **`->insert()` em massa** (comissões) bypassa eventos/casts do model.
- **Cadastros pequenos sem transação** no store/update (Cargo, Estadocivil,
  Parentesco, Tipoexame, Turno, Recessotipo) — padrão do projeto.
- **Imports errados/mortos:** Recessos:11 `use App\Http\Controllers\Tiporecessos`
  (controller como model, nunca usado). Vários `use App\EmpresasGrupo/Empresa/Menu`
  sem uso.

### ✅ O que está BOM
- Comissões: validação de sobreposição de período é regra correta; transações nos
  fluxos principais; FormRequest (`ColaboradorComissaoRequest`).
- Colaborador: valida CPF; transação; autorização view/create/update/delete +
  igualdade.
- Checklist: estrutura form→tópico→pergunta→resposta coerente; PDF.

## 3. Especificação do REESCRITO (Laravel 12)
- **Cadastros** (cargo, estado civil, parentesco, tipo exame, turno, recessos,
  recessotipo) → recursos limpos com FormRequest/Policy/transação; **authorize em
  todos**; corrigir unique do `legenda`; **remover o dd()**.
- **Colaborador** → recurso + sub-recursos (telefone/família/férias/exame) com Actions;
  ficha com alertas de exame/férias por vencimento.
- **Comissão** → **Service de comissão** (regra de período/tonelagem/exceção/replicação
  num lugar testável); `getMsg` em PHP (não 4 selects); só migrar com baseline (paga
  gente, alinhar com D01/D12).
- **Checklist** → builder de formulário moderno + preenchimento mobile + PDF.
- **Limpeza:** deletar ColaboradorfamiliaController vazio; remover imports mortos.

## 4. DECISÃO
- **Cadastros + Colaborador + Checklist → REESCREVER** (baixo risco).
- **Comissões → REFATORAR** (Service + baseline; efeito financeiro).
- **Quick wins aplicáveis JÁ:**
  (a) **remover `dd($data)` do Recessotipo::update** (editar recesso quebrado);
  (b) adicionar `authorize()` em Recessotipo/Turno (sem autorização);
  (c) corrigir `unique` do `legenda` em Tiporecessos;
  (d) `\Exception` nos catches quebrados (Recessos/Tiporecessos);
  (e) corrigir `grupo_id` em Colaborador::show:203;
  (f) deletar ColaboradorfamiliaController vazio.
- **Pré-requisitos:** D11; comissão exige baseline + alinhamento D01/D12.
- **Esforço:** cadastros baixo (lote); Colaborador/Checklist médio; comissão médio-alto.
