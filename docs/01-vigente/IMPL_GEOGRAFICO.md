# PRD DE IMPLEMENTAÇÃO — Geográfico (Cidade / Bairro / Rua / Região) · auditado

> Auditado no CÓDIGO: CidadeController, BairroController, RuaController, RegiaoController
> + tabelas. Já há piloto Filament (descartado); refazer em React com paridade.

## CIDADE (`cidades`: id, grupo_id, descricao, uf, cod_ibge; +cidade global grupo_id null)
- Métodos: index, store, update (via insertUpdate), destroy, dropdown(uf), buscaPorNomeEEstado.
- **Validação:** descricao required, uf required, cod_ibge required|numeric.
- **Regra de unicidade:** não pode repetir descricao+uf no mesmo grupo (ou global grupo_id null)
  (`getCidadesIguais`). store define grupo_id = empresa_padrao.grupo_id.
- destroy trata FK (isForeignKeyViolation → mensagem amigável, não 500).
- Cidades "globais" (grupo_id null) aparecem para todos — preservar no escopo da API.

## BAIRRO (`bairros`: id, grupo_id, cidade_id, descricao)
- Métodos: index, store, update, destroy.
- **Validação:** descricao required, cidade_id required.
- **Unicidade:** descricao+cidade_id no grupo (`getBairrosIguais`).
- `checkIfIgnored` no update (inconsistência ignorada — auditar se relevante).

## RUA (`ruas`: id, grupo_id, empresa_id, cidade_id, bairro_id, descricao, cep, importacaocep_id,
   nfecompl, ativo)
- Métodos: index, store, update, dropdown(id), destroy, createRuaFromOther.
- **Validação:** descricao required, cidade_id required.
- **Defaults no create:** importacaocep_id=-1, empresa_id=empresa_padrao.id, nfecompl='Rua',
  grupo_id=grupo. **Unicidade:** descricao+cidade_id (`getRuasIguais`).

## REGIÃO (`regiaos`: id, grupo_id, descricao, ativo) — **DECIDIDO (reauditado 2026-06-19):**
   é CRUD REAL. RegiaoController tem index/store/update (só create/show/edit são scaffold vazio,
   não há destroy no legado). Validação: descricao required; escopo grupo_id; ativo default 0.
   **Implementar como CRUD** (com destroy FK-amigável no app novo). Entra como aba da página Geográfico.

## PÁGINA-ALVO (React) — grupo "Cadastros"
- Cada entidade: lista paginada/escopada (incluir registros globais grupo_id null p/ Cidade)
  + ações por linha (editar/excluir) + form (campos acima). Selects dependentes
  cidade→bairro→rua (reusar AsyncSelect + lookups já criados na S2b).
- destroy com mensagem de FK amigável (não deixar 500).

## API ADMIN (a criar)
- /geo/cidades, /geo/bairros, /geo/ruas, /geo/regioes — CRUD; reusar lookups existentes.
- Aplicar as REGRAS DE UNICIDADE (cidade nome+uf, bairro nome+cidade, rua nome+cidade) na API.

## REORGANIZAÇÃO / UX (ver MAPA_NAVEGACAO_ALVO.md)
**De-para:** cidade.index / bairro.index / rua.index / regiao → **1 página Geográfico** com abas
(Cidades / Bairros / Ruas / Regiões). **Agrupamento:** 4 telas → 1 página com abas e selects
dependentes. **Visão nova:** ao abrir uma Cidade, ver seus Bairros e Ruas na própria página
(drill-down), em vez de 3 telas isoladas. Nada eliminado.

## DoD
1. CRUD das 4 entidades com campos/validações/unicidade acima.
2. Cidade respeita registros globais (grupo_id null).
3. destroy com erro amigável de FK.
4. Lista com ações por linha; testado; testes automatizados + suíte verde.
5. Região: auditar e cobrir (ou marcar como N/A se for scaffold morto — decidir).
