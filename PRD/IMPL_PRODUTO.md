# PRD DE IMPLEMENTAÇÃO — Produto (auditado linha-a-linha)

> Base de implementação COMPLETA p/ migrar Produto ao SPA React com PARIDADE.
> Auditado no CÓDIGO: `ProdutoController` (840), `ProdutoRequest` (108), `Produto` (359),
> view `produtos/produtos_form`, tabela `produtos` (49 colunas). Cada item tem `arquivo:linha`.
> **Pronto = todos os campos/ações/validações/sub-recursos abaixo cobertos.**

## 1. TABELA `produtos` (49 colunas — todas, com tipo/null)
**Identidade/FK (NOT NULL):** grupo_id, empresa_id, produtoclasse_id, unidademedida_id,
vasilhameretornavel (smallint), descricao, nfepermite (smallint), pGNn, pGNi, pGLP (numeric).
**Preços/decimais (numeric, null):** precovenda, precovendaminimo, customedio, custofrete,
precogasdopovo. **Peso (numeric):** pesoliquido, pesobruto. **Flags/misc:** ativo (smallint),
enviaappnf (smallint), diasgiro (smallint), observacao. **Identificação fiscal:** ean, eantrib,
ncm, nfcest, especie, marca. **NF-e/IPI:** nfealiqipi, nfebcipi, nfecodenquadramentoipi,
nfeextipi, nfedescricaofiscal, nfgrupofiscal_id, nfipi_id, nfetipoitem (SPED), nfenatrec.
**ANP/CIDE (GLP):** nfecprodanp, nfedescanp, nfeqbcprod, nfevaliqprod, nfevcide, nfecodlst,
nfecodgen. **Vínculos:** produtoretornavel_id (vasilhame), ressarcimentoproduto_id, tipo_glp.

## 2. MÉTODOS do controller (paridade — `ProdutoController`)
- `index:39` — lista produtos da empresa (`where empresa_id`). authorize('view').
- `create:47` — carrega selects: produtoclasses, vasilhame, unidademedida, nfgrupofiscal,
  nfetipoitem (SPED), nfipi, classeglp, tipoglp, ressarcimento, generos, lst, estados (cod_ibge).
- `store:95` — cria (DB::transaction); chama dadosExtras + createOrigens.
- `show:121` / `edit:127` → `showEdit`.
- `update:133` — autoriza igualdade; **REGRA: não inativa produto com saldo em estoque**
  (`:146-152` varre estoquesetor, lança exceção se quantidade≠0); recria origens.
- `destroy:222`.
- Lookups AJAX: `buscaPorSetor:371`, `buscaPorSetorNF:398`, `buscaPorSetorNFEntrada:422`,
  `buscaPorClasse:443`, `buscarPrecoAjax:451`.

## 3. REGRAS DE NEGÓCIO (dadosExtras `:167` — CRÍTICAS)
- Decimais convertidos: precovenda/precogasdopovo/customedio/precovendaminimo via
  `insertNumeroDecimalOracle`; pesoliquido/pesobruto via `conversaoPeso`. (No React/API: usar
  cast MoedaBR + normalização; NÃO perder a conversão BR.)
- **GLP — percentuais pGNi+pGNn+pGLP devem somar 100 ou 0** (`:189-192`, exceção senão).
- **NF-e (nfepermite=1)**: converte nfealiqipi/nfebcipi/nfeqbcprod/nfevaliqprod/nfevcide
  (`converterBaseCalcOracle`); se 0, nfepermite=0.
- **Origens do combustível (sub-recurso)**: lista [.. , percentual]; soma dos percentuais
  **deve dar 100%** (`:204-214`). Persistida via `createOrigens` (origens()).
- `emptyToNull` no fim; ativo/enviaappnf default 0; produtoretornavel_id null se ausente.

## 4. VALIDAÇÕES (`ProdutoRequest` — CONDICIONAIS)
- Sempre: produtoclasse_id, descricao, unidademedida_id, vasilhameretornavel (required).
- Se `nfepermite=1`: + nfgrupofiscal_id, nfedescricaofiscal. **Se a classe é GLP** (classeglp
  contém produtoclasse): + nfecprodanp, nfedescanp, nfeqbcprod, nfevaliqprod, nfevcide, origensList.
- Se `sped=1`: + nfetipoitem.
- Se `vasilhameretornavel=1`: + produtoretornavel_id.
- (mensagens custom em `messages()`.)

## 5. PÁGINA-ALVO (React, abas)
- **Lista**: paginada/escopada por empresa, busca, **ações por linha** (editar/excluir/inativar),
  marca inativo. (Não repetir o erro do Cliente: ações presentes.)
- **Ficha em abas**: Dados (descricao/classe/unidade/vasilhame/retornável/ativo/diasgiro),
  Preços (precovenda/mínimo/customédio/gasdopovo/frete — máscara BR), Fiscal/NF-e (grupo fiscal/
  IPI/CEST/NCM/EAN/descrição fiscal/tipo item SPED), GLP (tipo_glp + pGNi/pGNn/pGLP soma=100/0 +
  ANP/CIDE), **Origens do combustível** (sub-recurso, soma 100%). Campos NF-e/GLP só visíveis
  conforme nfepermite/classe (reactive).
- Permissão RBAC produto.view/create/edit/delete.

## 6. API ADMIN (a criar) — endpoints
- GET /produtos (lista), GET /produtos/{id} (com labels), POST/PUT/DELETE.
- Lookups: /lookups/produto-classes, /unidades, /nf-grupos-fiscais, /sped-tipo-itens, /ipis,
  /estados, /produtos-vasilhame, /produtos-ressarcimento, /tipo-glp.
- Sub-recurso origens: GET/PUT /produtos/{id}/origens (lista com soma 100%).
- Reusar regras do dadosExtras (decimais/GLP/origens) num Service ou no controller da API.

## 7. DoD (Definição de Pronto)
1. Todas as 49 colunas editáveis (nas abas) — exceto id/timestamps/grupo/empresa (auto).
2. Regras: GLP soma 100/0, origens soma 100%, não-inativar com estoque, conversão decimal BR.
3. Validações condicionais (NF-e/SPED/vasilhame/GLP) aplicadas na API.
4. Lista com ações por linha; criar/editar/excluir/inativar testados.
5. Testes automatizados (store/update/validações/regras GLP+origens) + suíte verde.
