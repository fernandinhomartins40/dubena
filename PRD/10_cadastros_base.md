# PRD — Cadastros base / Geográfico  ·  D10

- **Status:** ✅ pronto
- **Criticidade:** 🟡 (cadastro/apoio) — exceto **Empresaconfig** que é 🔴 (guarda config fiscal usada por NF-e/financeiro)
- **Decisão:** **REESCREVER** os CRUDs simples · **REFATORAR** Empresa/Empresaconfig (ver §8)

---

## 1. Escopo
- **Controllers:** `Empresa` (803), `Empresaconfig` (714), `Documento` (295),
  `Setor` (281), `Sorteio` (179), `Rua` (171), `Empresabens` (166), `Bairro` (164),
  `EmpresasGrupo` (162), `Cidade` (136), `Documentotipo` (127), `Motivonaovenda` (124),
  `Banco` (93), `Regiao` (83), `Documentogestao` (83), `Configuracoesgerais` (61).
- **Tabelas (public):** `empresas`, `empresas_grupos`, `empresaconfigs`, `empresabens`,
  `configuracoesgerais`, `bairros`, `cidades`, `ruas`, `estados`, `regiaos`, `setors`,
  `bancos`, `documentos`, `documentotipos`, `motivonaovendas`, `sorteios`.
- **Rotas:** resources homônimos (`empresa`, `bairro`, `cidade`, `banco`...) + buscas
  AJAX (dropdowns geográficos encadeados uf→cidade→bairro→rua).

## 2. O que o módulo FAZ
- **Empresa / Grupo de empresas**: cadastro das filiais/revendas; base do
  multi-empresa (todo o resto referencia `empresa_id`/`grupo_id`). Define matriz,
  dados fiscais (CNPJ, IE, endereço), regime.
- **Empresaconfig** 🔴: a "caixa de parâmetros" da operação — planos de conta/centros
  de custo default (frete, juros, desconto, vale-gás), config de NF-e/NFC-e,
  tempos de entrega, chaves (Maps), senha mestre, logo. **Usada por Pedido,
  Financeiro, NF-e, Relatórios.**
- **Geográfico**: estados→cidades→bairros→ruas, com dropdowns encadeados; filtro
  por grupo (cidade pode ser global `grupo_id IS NULL` ou do grupo).
- **Setor**: setorização de entrega (liga a veículos/colaboradores/metas).
- **Cadastros de apoio**: banco, documento/tipo, motivo de não-venda, sorteio,
  bens da empresa — CRUD simples.

## 3. Como FAZ hoje
- CRUDs em padrão Laravel resource (controller fino, Eloquent) — relativamente
  limpos nos cadastros pequenos (Banco/Regiao/Cidade: 0 SQL cru).
- Geográfico usa `whereRaw` nas buscas de dropdown (filtro por grupo + LIKE).
- `Empresa`/`Empresaconfig` grandes por concentrarem MUITOS campos de config (não
  por lógica complexa) — muito formulário, pouca regra.

## 4. Gambiarras / dívida técnica
- [ ] **SQLi potencial** `BairroController:40` `whereRaw("... LIKE '%$descricao%'")`
      — `$descricao` interpolado; se vier de input, é injeção. Idem `:107`
      (`= '$descricao'`), `:25/:28` (grupo_id/uf interpolados — menor risco, vêm de sessão).
- [ ] Buscas geográficas com `whereRaw` poderiam ser query builder com bindings.
- [ ] `Empresaconfig` mistura ~50 campos heterogêneos numa tabela só (config fiscal +
      operacional + chaves + visual) — candidata a separação/normalização.
- [ ] Validação de formulário inline nos controllers (não FormRequest dedicado em todos).

## 5. Riscos de tocar
- **Empresaconfig é 🔴**: alterar a forma como os defaults (PC/CC de frete, juros,
  NF-e) são lidos afeta Pedido/Financeiro/NF-e. Reescrever exige cuidado e baseline.
- Geográfico é acoplado a Cliente/Pedido/Entrega (endereço). Mudança no modelo
  geográfico ramifica.
- Os CRUDs de apoio (banco, documento...) são isolados → baixo risco.

## 6. Estado de compatibilidade Postgres
- ✅ CRUDs funcionam (validados na varredura de 98 módulos, 200).
- 🟡 `BairroController` tem whereRaw interpolado a parametrizar (entra na Frente C).
- Empresaconfig já teve fix de null-guard (caso "empresa sem config").

## 7. Visão REESCRITA (Laravel 12)
- **CRUDs de apoio** (banco, regiao, cidade, documento/tipo, motivo, sorteio,
  estadocivil-like): reescrever como recursos limpos (FormRequest + Resource +
  Policy + UI moderna em tabela/formulário padronizado). Baixo risco, ganho de UX
  alto e rápido — bons primeiros módulos do "novo".
- **Geográfico**: serviço de endereço com bindings (sem whereRaw); dropdowns via
  API JSON; possível integração CEP.
- **Empresa**: reescrever o cadastro com UX moderna, mas **mantendo o schema**
  (muitas FKs dependem dele).
- **Empresaconfig**: REFATORAR primeiro (separar grupos de config; expor via
  serviço `EmpresaConfig::getForSession`), reescrever a UI depois. Não reescrever
  o modelo de dados sem baseline fiscal (frete/juros/NF-e dependem dele).

## 8. DECISÃO e justificativa
- **CRUDs de apoio → REESCREVER** (Banco, Regiao, Cidade, Documento, Documentotipo,
  Documentogestao, Motivonaovenda, Sorteio, Empresabens, Setor): baixo risco, alto
  ganho de UX, ótimos para estrear o padrão novo após o D11.
- **Geográfico (Bairro/Cidade/Rua) → REFATORAR** primeiro (corrigir SQLi/whereRaw),
  reescrever UI depois.
- **Empresa → REESCREVER UI / manter schema.**
- **Empresaconfig → REFATORAR** (é 🔴, lido por NF-e/financeiro; mexer no modelo só
  com baseline fiscal — Frente D).
- **Pré-requisitos:** SQLi do Bairro (Frente C) antes de qualquer migração; D11
  (acesso/menu novo) antes, pois os CRUDs novos usarão a navegação nova.
- **Esforço:** CRUDs de apoio = baixo (dias, em lote); Empresa/Empresaconfig = médio.
- **Ordem:** depois do D11; servem de "vitrine" do padrão novo com risco mínimo.
