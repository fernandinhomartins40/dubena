# PRD FIDEDIGNO (linha-a-linha) — Cadastros base / Geográfico · D10

> Escrito após LER 100% das linhas dos 16 controllers do domínio (3.642 linhas):
> Empresa(803), Empresaconfig(715), Documento(295), Setor(281), Sorteio(179),
> Rua(171), Empresabens(166), Bairro(164), EmpresasGrupo(162), Cidade(136),
> Documentotipo(127), Motivonaovenda(124), Banco(93), Regiao(83),
> Documentogestao(83), Configuracoesgerais(61).

- **Status:** ✅ pronto (fiel)
- **Criticidade:** 🟡 cadastros · **Empresa/Empresaconfig 🔴** (config fiscal/financeira)
- **Decisão:** **REESCREVER** CRUDs de apoio · **REFATORAR** Empresa/Empresaconfig

---

## 1. O que cada controller FAZ (verificado)
- **EmpresaController (803):** CRUD da empresa/filial — **fortemente fiscal**: dezenas
  de selects de NF-e/NFC-e/SAT/SPED (`getSelects` 584-741), certificado digital PFX
  (`saveCertNf` 464-478, senha via `customCrypt`), contingência por UF
  (`validateContingency` 541-582), controle de número de NF p/ evitar duplicidade
  (`checarNfNumero` 754-801), lat/long por endereço, logo. `change` (91-122) troca
  empresa ativa recarregando sessão (menu/permissões/config). NÃO é "só formulário".
- **EmpresaconfigController (715):** parâmetros operacionais/financeiros — planos de
  conta/centros de custo default (cartão, frete, juros, desconto, vale-gás, convênio),
  PIX (client_id/secret/chavepix criptografados, webhook), senha mestre
  (Hash::make/check + log de tentativas `logSenha`), replicação **matriz→filial**
  (`matriz` 569-595 / `filial` 597-616).
- **Geográfico (Bairro 164 / Cidade 136 / Rua 171):** CRUD + dropdowns encadeados
  uf→cidade→bairro→rua; filtro por grupo (`grupo_id IS NULL` = global). Criam
  registro on-the-fly (`createRuaFromOther`).
- **Setor (281):** setor de entrega; vincula colaboradores (sincroniza `Android` do
  app); regra: não inativa setor com estoque (262-268); cria rua nova se id "N".
- **Documento (295) / Documentotipo (127) / Documentogestao (83):** documentos do
  colaborador com versões (upload/download Storage) + dashboard de vencimento.
- **Empresabens (166):** bens com **depreciação** (dias/meses/anos, %).
- **Sorteio (179):** sorteia pedido apto no período (`random_int`).
- **Banco/Regiao/Motivonaovenda/EmpresasGrupo/Configuracoesgerais:** CRUD simples.

---

## 2. BUGS E DÍVIDA — VERIFICADOS LINHA-A-LINHA

### 🔴 Compatibilidade Postgres (QUEBRADO em runtime)
- **`updateLob()` — método do driver Oracle (yajra/oci8, REMOVIDO na Fase 4) —
  usado em 8 lugares**: EmpresaController:152,378; EmpresasGrupoController:64,121
  (+ os 4 espelhos no Monitora). NÃO há macro registrada. No Postgres
  `DB::table()->updateLob(...)` → **BadMethodCallException**. ⇒ **Gravar o logo
  (logoimg) da empresa e do grupo está QUEBRADO no Postgres.** A varredura de 98
  módulos não pegou porque só exercitou o `index` (logo é gravado no store/update).
  **Corrigir: substituir updateLob por update normal de coluna bytea.**
- **`strpos($errorMsg, "ORA-02292")`** (Bairro:145, Cidade:130, Rua:83): detecta
  violação de FK pelo código de erro **Oracle**. No Postgres o código é `23503`
  (SQLSTATE) → a detecção falha e retorna a **mensagem de erro crua** em vez da
  amigável "registro em uso". Bug de UX/compat em delete de bairro/cidade/rua.

### 🔴 Segurança (SQLi)
- **whereRaw com input do usuário interpolado** nos 3 geográficos:
  - `Bairro:25,28,31,40,107` — `$uf_atual` vem de `$_GET['uf_filtro']` (linha 23) e
    `$descricao` de `Input::get` (38) → **SQLi** (linha 25 e LIKE 40). `$grupo_id`
    vem da sessão (risco menor).
  - `Rua:26,29,32,41,154` — idêntico (`$uf_atual`/`$uf_empresa`/`$descricao`).
  - `Cidade:25,85` — `$descricao` LIKE interpolado.
  - Há sanitização parcial (`str_encode_to_query`, `rawTranslateSpecialChars`) mas
    não substitui binding. **Corrigir: parametrizar (Frente C).**

### 🟠 Bug lógico
- **EmpresaconfigController::update:419** — `if (!isset($replicado) && !$replicado)`:
  condição **invertida** vs. o `store:365` (`if (isset($replicado) && !$replicado)`).
  No update, se `$replicado` não setado, dispara o throw; e quando setado false, NÃO
  dispara. Lógica de erro da replicação matriz/filial provavelmente furada.
- **EmpresasGrupo/Banco/Documentotipo/Motivonaovenda `store`/`update` SEM transação**
  (create/update soltos) — diferente do padrão DB::transaction do resto do domínio.

### 🟡 Dívida estrutural
- **Empresa/Empresaconfig = God forms**: `index` do Empresaconfig monta ~70 variáveis
  no compact (260-336); `getSelects` com switch de 15 casos. Muito formulário.
- **Lógica de apresentação no controller**: `Cidade::dropdown`/`Bairro::dropdown`
  montam `\Form::select` (HTML) e retornam string.
- **`$_GET` lido direto** (Bairro/Rua/Cidade index: filtros + montagem de URL).
- **`create`/`show`/`edit` vazios** em vários (Documentotipo, Motivonaovenda,
  EmpresasGrupo, Regiao) — formulários via modal no front (acoplamento JS/back).
- **`menuspermissoesAll()`** (Empresaconfig:189-190): confirma uso de Centrocusto::/
  Planoconta:: (não o Menu morto do D11).

### ✅ O que está BOM
- Maioria dos CRUDs com DB::transaction + rollback + autorização (`view/create/
  update/delete` + `igualdade` por dono).
- Empresa: `checarNfNumero` evita duplicidade de número de NF (regra fiscal real,
  preservar). `validateContingency` (mapa UF→SVC) é regra fiscal válida.
- PIX no Empresaconfig: client_id/secret/chavepix com `encrypt()` (Crypt). Senha
  mestre com Hash + log de auditoria.
- Documento: versionamento de arquivo com Storage, transações.
- Sorteio: limpo, sem SQL cru de risco.

## 3. Especificação do REESCRITO (Laravel 12) — baseada no código real
- **CRUDs de apoio** (Banco, Regiao, Cidade, Documento/tipo/gestao, Motivonaovenda,
  Sorteio, Empresabens, Setor, EmpresasGrupo) → recursos limpos (FormRequest/Resource/
  Policy), UI moderna, **com transação** em todos. Vitrine do padrão novo.
- **Geográfico** → serviço de endereço com **bindings** (sem whereRaw/`$_GET`);
  dropdowns via API JSON (sem `Form::select` no back); detecção de FK por SQLSTATE
  `23503` (não ORA-02292).
- **Logo (logoimg)** → coluna `bytea`, gravada por update Eloquent normal (eliminar
  `updateLob`). **Quick win de compat aplicável já.**
- **Empresa** → reescrever UI (abas: cadastro / fiscal NF-e/NFC-e / SAT / SPED /
  certificado); manter schema (muitas FKs) e as regras `checarNfNumero`/contingência.
- **Empresaconfig** → REFATORAR: separar config em grupos; corrigir o bug do `update:419`;
  Service de replicação matriz→filial testável. Reescrever UI depois. É 🔴 (lido por
  NF-e/financeiro) → só mexer no modelo com baseline.

## 4. DECISÃO
- **CRUDs de apoio + geográfico + Setor + Documento + Empresabens + Sorteio →
  REESCREVER** (baixo risco, alto ganho UX).
- **Empresa → REESCREVER UI / manter schema e regras fiscais.**
- **Empresaconfig → REFATORAR** (fiscal/financeiro; baseline).
- **Quick wins de compat/segurança aplicáveis JÁ (não dependem da reescrita):**
  (a) eliminar `updateLob` (logo quebrado no PG) — 8 lugares;
  (b) trocar `ORA-02292` por SQLSTATE 23503 (delete geográfico) — 3 lugares;
  (c) parametrizar whereRaw dos geográficos (SQLi) — Frente C;
  (d) corrigir bug lógico Empresaconfig:419.
- **Pré-requisitos:** D11 (navegação nova); baseline p/ Empresaconfig.
- **Esforço:** apoio = baixo (lote); Empresa/Empresaconfig = médio.
