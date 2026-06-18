# SPEC DE PARIDADE — Cliente (migração React, S2)

> Especificação FIEL ao legado (auditada em `ClienteController`, `ClienteRequest`,
> `resources/views/clientes/form_clientes.blade.php`, `convenio.blade.php`, `precos.blade.php`,
> tabela `clientes`). **"Pronto" = todos os itens abaixo cobertos.** Reorganizar a UX é
> permitido; PERDER campo/ação/regra NÃO é. Checklist verificável.

## 1. LISTA (index)
- [ ] Tabela paginada server-side, escopada por empresa (support vê todas). **BUG ATUAL: lista
      repete 1 cliente** — corrigir (provável paginação/`select` errado).
- [ ] Colunas: Nome, Fantasia, CPF/CNPJ, E-mail, Cidade/UF, flags (Cliente/Fornecedor), Ativo.
- [ ] Busca por nome/fantasia/cpf/cnpj/código.
- [ ] **Ações por linha**: Editar, Excluir, Ativar/Inativar (ajax.ativacliente), Contrato (PDF),
      Etiquetas convênio (PDF). Botão "Novo".

## 2. FICHA — 7 ABAS (paridade com o form legado)

### Aba 1 — Dados Gerais
Campos: `tipopessoa_id` (select; controla Física×Jurídica), `nome` (Nome/Razão Social, obrigatório),
`fantasia` (Jurídica), `segmento_id` (select), `cpf` (Física), `rg` (Física), `sexo` (F/M, Física),
`nome_app` (somente leitura), `datanascimento` (Física), `cnpj` (Jurídica), `inscricao_estadual`,
`suframa` (Jurídica), `consisa_id` (Cód. Contábil), `observacoes`, `indicador_ie`
(select: 1 Contribuinte ICMS / 2 Isento / 9 Não Contribuinte), flags: `cliente`, `fornecedor`,
`transportador`, `simples`, `ativo`, `nfemite`, `gasdopovo`. Botão "Promoções" (modal).
Regra: campos Física×Jurídica alternam por `tipopessoa_id` (reactive).

### Aba 2 — Endereço
- [ ] Endereço completo (partial `general.endereco_form_partial`): CEP, logradouro/rua, número,
      bairro, cidade, UF, complemento, ponto de referência, lat/long. Selects dependentes
      cidade→bairro→rua (+ criar bairro/cidade via popup). `endereco_app` (somente leitura).

### Aba 3 — Contatos (Telefones)
- [ ] Adicionar/editar/remover telefones: `telefonetipo_id` (select), `telefone`, `whatsapp`.
      Tabela com os telefones. (Parcialmente feito na S2b — falta editar.)

### Aba 4 — Histórico (pedidos do cliente)
- [ ] Tabela read-only: Data, Pedido, Forma Pagto, Produto, Status, Quantidade, Valor.
      Legenda concluído/cancelado.

### Aba 5 — Interações (CRM / follow-up)
- [ ] Adicionar/editar/remover interações: `contatotipo_id`, `contatosituacao_id`, `descricao`,
      `acao`, data. Tabela das interações.

### Aba 6 — Convênio (`convenio.blade.php`)
- [ ] Dados de convênio: ativo, limite de compra, dia de fechamento, dia de vencimento,
      data contrato, cliente conveniado (convenio_id), produtos do convênio. (Auditar campos
      exatos do convenio.blade ao implementar.)

### Aba 7 — Preços (`precos.blade.php`)
- [ ] Preços especiais por produto/condição de pagamento do cliente. (Auditar precos.blade.)

## 3. AÇÕES / MÉTODOS do controller legado (paridade)
- [ ] index, create, store, edit, update, show, destroy
- [ ] ativaCliente (ativar/inativar)
- [ ] contrato (gerar PDF de contrato)
- [ ] imprimirEtiquetasConvenio (PDF etiquetas)
- [ ] fechamentoConvenio
- [ ] updateCampoCliente (edição inline de campo)
- [ ] verificaEndereco (validação/geocoding de endereço)
- [ ] buscaClienteNome / buscaPorId (lookups; já há busca na lista)
- [ ] createFromPedidos / editFromPedidos (abrir cadastro a partir do pedido — relevante p/ M Pedido)

## 4. VALIDAÇÕES (do ClienteRequest legado — reaproveitar)
- nome required min:3; numero required; uf/cidade_id/bairro_id/rua_id required (regras gerais);
- Pessoa Física: rg/cpf unique por empresa (+ indicador_ie e cpf required se nfemite);
- Pessoa Jurídica: cnpj required+unique, inscricao_estadual unique, suframa max:9;
- segmento_id required se cliente; convênio: datacontrato/limite/dias required se convenioativo;
- (fix-PG do unique com id vazio já aplicado — manter via API).

## 5. CRITÉRIO DE PRONTO
1. Todas as 7 abas presentes com os campos acima.
2. Todas as ações da §3 disponíveis (na ficha/lista).
3. Validações da §4 aplicadas (API admin reusa as regras).
4. Lista sem bug, com ações por linha.
5. Testado: criar PF, criar PJ, editar, excluir, ativar/inativar, add/editar/remover telefone e
   interação, ver histórico, configurar convênio.

> Os campos de Convênio (aba 6) e Preços (aba 7) precisam de auditoria detalhada de
> `convenio.blade.php`/`precos.blade.php` antes de implementar — marcar como sub-etapa.
