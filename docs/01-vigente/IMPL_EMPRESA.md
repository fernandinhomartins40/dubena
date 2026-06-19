# PRD DE IMPLEMENTAÇÃO — Empresa / EmpresaConfig / Grupos · auditado

> Auditado no CÓDIGO: EmpresaController (800), EmpresaconfigController (716),
> EmpresasGrupoController (156); tabelas `empresas` (95 col), `empresaconfigs` (106 col),
> `empresas_grupos`. **A "tela densa com dezenas de flags" que o cliente citou.**

## 1. EMPRESA (`empresas`, 95 colunas)
**NOT NULL:** grupo_id, razao_social, cidade_id, cep, bairro_id, uf.
**Blocos de colunas (viram ABAS):** Identificação (razao_social, nome_informal/fantasia, cnpj,
ie, im, regime), Endereço (cidade/bairro/rua/cep/uf/complemento), Contato (telefones/email/site),
Fiscal/NF-e (certificado, série, ambiente, csc/idcsc NFC-e, spedemite, regime tributário),
Logo (bytea — BlobWriter), Bancário, e diversos parâmetros.
**Métodos:** index, create, store, show, edit, update, destroy, **change(id)** (TROCA a empresa
ativa na sessão — crítico, multi-empresa), form, carregaempresa(id), getMatrizGrupo(grupo_id).
**Request:** EmpresaRequest (auditar regras ao implementar).

## 2. EMPRESACONFIG (`empresaconfigs`, 106 colunas) — config operacional/contábil/fiscal
**Grupos (viram ABAS da página de Configurações):**
- **Pedido/Entrega:** tempoentrega, tempourgente, validacordenadasentrega, validagasbolso,
  validaatraso, pedidovalidacartao(+dias), pedidocontrolatempoligacoes, pedidostatuspadrao,
  pedidoemitenfce, maximoparcelas, operacaodisk, quant_padrao, pedidooperacao_id, transportadorpadrao_id.
- **Estoque:** permiteestoquenegativo, setorprincipal_id, qnddiasinativocompra.
- **Impressão:** impressaotipo/modelo/porta/automatica/qtdviaspedido.
- **E-mail SMTP:** emailremetente, emailnomeremente, emailusuario, emailsenha, emailservidorsmtp,
  emailportasmtp, emailassunto, emailcorpo, emailrequerautenticacao, emailrequerconexaotls, emailkeygoogle.
- **Mapeamento Contábil (plano_id/centro_id por finalidade):** cartão (pc/cccartao), receita/despesa
  desconto e juros (pc/cc receitadesconto, receitajuro, despesasdesconto, despesasjuro), vale-gás
  (pc/ccvalegas), frete (pc/ccfrete + frete gp + frete convênio), convênio (pc/ccconvenio),
  planoconta_id/centrocusto_id principais, nfoperacoes_id, nfcecliente_id, nfcecliente.
- **Percentuais:** percentualencargos, percentualprovisaodevedores, percentualremuneracaocapital,
  percentualdistribuicaoresul.
- **Frete/Presença:** fretemodalidade, presencacomprador(+appnf), keygooglemaps.
- **Diversos:** diastrabalhadosemana, contadevolucaocheque, contachecktroco, telacontrolakm, androidutiliza/enviatodos.
**Métodos:** index, store, update, **senhaMestre/changePassword/verificaSenhaMestre** (senha mestra
de operações sensíveis), controleKm, getPresencaFrete(which), sendEmail (teste de SMTP).
**Request:** EmpresaConfigRequest. ARMADILHA: `index` referencia ~40 variáveis no compact() — todas
precisam estar definidas (default null) p/ empresa sem config (corrigido na F0, manter).

## 3. EMPRESAS_GRUPO (`empresas_grupos`) — CRUD de grupos (matriz/filiais).

## 4. REORGANIZAÇÃO / UX (ver MAPA_NAVEGACAO_ALVO.md)
**De-para:**
| Tela legada | Destino no app novo |
|---|---|
| (cadastro de empresa) | Página **Empresas** (lista + ficha em abas) |
| empresas_grupo.index | Empresas → aba/seção **Grupos** |
| empresaconfig.index | Empresas → ficha da empresa → aba **Configurações** (sub-abas por tema) |
| empresaconfig.senhamestre | Configurações → **Senha Mestra** |
| Configurações Gerais / Androids / App | Administração → Configurações/Integrações |
**Agrupamento:** as 106 flags de config viram **sub-abas temáticas** (Pedido/Estoque/Impressão/
E-mail/Contábil/Frete/Percentuais) em vez de um formulário gigante único. **Visão nova:** trocar de
empresa ativa (change) vira seletor no header; ver matriz/filiais do grupo juntas.
**Crítico (não perder):** `change` (empresa ativa na sessão) e a Senha Mestra são funções essenciais.

## 5. API ADMIN (a criar)
- /empresas (CRUD), /empresas/{id}/config (GET/PUT — todas as 106), /grupos (CRUD).
- /empresas/{id}/ativar (troca empresa ativa — equivalente ao change). senha-mestra (verificar/alterar).
- teste de e-mail SMTP (sendEmail). Lookups de plano/centro de contas p/ o mapeamento contábil.

## 6. DoD
1. Todas as 95 colunas de empresa e 106 de config editáveis (nas abas temáticas).
2. change (empresa ativa) + senha mestra + teste SMTP funcionando.
3. Logo (bytea) upload; mapeamento contábil completo.
4. Lista com ações; testado; testes automatizados + suíte verde.
