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

## REGIÃO (`RegiaoController` SEM métodos públicos no controller — auditar model/rotas;
   provável CRUD simples via outro caminho ou scaffold. Confirmar antes de implementar.)

## PÁGINA-ALVO (React) — grupo "Cadastros"
- Cada entidade: lista paginada/escopada (incluir registros globais grupo_id null p/ Cidade)
  + ações por linha (editar/excluir) + form (campos acima). Selects dependentes
  cidade→bairro→rua (reusar AsyncSelect + lookups já criados na S2b).
- destroy com mensagem de FK amigável (não deixar 500).

## API ADMIN (a criar)
- /geo/cidades, /geo/bairros, /geo/ruas, /geo/regioes — CRUD; reusar lookups existentes.
- Aplicar as REGRAS DE UNICIDADE (cidade nome+uf, bairro nome+cidade, rua nome+cidade) na API.

## DoD
1. CRUD das 4 entidades com campos/validações/unicidade acima.
2. Cidade respeita registros globais (grupo_id null).
3. destroy com erro amigável de FK.
4. Lista com ações por linha; testado; testes automatizados + suíte verde.
5. Região: auditar e cobrir (ou marcar como N/A se for scaffold morto — decidir).
