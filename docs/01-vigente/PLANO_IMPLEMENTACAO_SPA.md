# PLANO DE IMPLEMENTAÇÃO — SPA React (operacional, por fases) · vigente

> **Para que serve:** guiar a execução módulo a módulo, na ordem certa, **conferindo o
> IMPL_<modulo> correspondente no momento de implementar**. É o passo-a-passo abaixo das
> fases macro do [PLANO_SPA_REACT.md](PLANO_SPA_REACT.md) (S1–S8): aqui cada módulo vira
> uma fase com entregáveis, dependências, gate e checklist de DoD copiável.
>
> **Regra de ouro (não esquecer):** PARIDADE + REORGANIZAÇÃO. Cada fase tem um **CONTRATO**
> (o IMPL_<modulo>.md). "Pronto" = 100% do DoD do contrato coberto e testado. Nunca implementar
> "enxuto". Ver [metodo de paridade](MAPA_NAVEGACAO_ALVO.md) e os princípios do índice
> [IMPL_00_INDICE.md](IMPL_00_INDICE.md).

---

## COMO USAR ESTE PLANO (ritual de cada fase)

Antes de codar qualquer módulo:
1. **Ler o IMPL_<modulo>.md inteiro** (é o contrato). Ler também a seção do módulo em
   [MAPA_NAVEGACAO_ALVO.md](MAPA_NAVEGACAO_ALVO.md) (de-para legado→novo).
2. **Reauditar o código legado** citado no IMPL (controller/request/model/view/tabela) para
   confirmar que o contrato ainda bate (o legado pode ter mudado).
3. Implementar na sequência fixa: **API admin → React → testes → suíte verde → deploy → gate**.
4. **Marcar o DoD** (checklist do IMPL) item a item. Só então a fase está "pronta".
5. Apontar o legado da entidade para `/app/<modulo>` (flag) e aposentar a tela antiga.

Sequência técnica padrão de cada módulo:
- **API**: controller em `app/ApiAdmin/Http/Controllers/`, rotas em `routes/api_admin.php`
  (grupo `auth:sanctum`), regras de negócio em Service quando houver motor, `can('<modulo>.<acao>')`.
- **React**: `frontend/src/features/<modulo>/` (api.ts + páginas/abas), reusar `components/ui`
  (DataTable com ações por linha, FormField máscara BR, AsyncSelect, Tabs, modais).
- **Testes**: PHPUnit (store/update/validações/regras/sub-recursos) — os 500 do Cliente foram
  pegos por teste, não na tela. Vitest/Playwright quando a feature justificar.

---

## ESTADO ATUAL (baseline)

| Fase macro | Estado |
|---|---|
| S1 — Fundação (Sanctum + API admin + AppShell React + login) | ✅ feito |
| S2 — Cliente (página completa, 7 abas) | ✅ feito (branch `spec-e-cliente-completo`) |
| Reorganização de docs (PRDs/planos em `docs/`) | ✅ feito (este commit) |

**Branches de código pendentes de merge:** `s2b-melhorias-pendencias`, `spec-e-cliente-completo`.
**Pendência transversal:** PR de saúde de rotas (12 nomes de rota duplicados → `route:cache` falha).
Recomenda-se resolver **antes da Fase 1** (não bloqueia codar, mas trava cache de rota no deploy).

---

## ORDEM E DEPENDÊNCIAS

```
[Cliente ✅] ─► F1 Produto ─► F2 Geográfico ─► F3 Empresa/Config ─► F4 Cadastros de apoio
                                                                          │
                              ┌───────────────────────────────────────────┘
                              ▼
                       F5 Estoque (motor+telas) ─► F6 Financeiro (motor+telas)
                              │
                              ▼
                          F7 Fiscal (SEFAZ homolog — BLOQUEANTE)
                              │
                              ▼
                          F9 Vendas/Pedidos/Caixa (ÚLTIMO, risco máximo)

F8 Satélites (RH/Frota/Vale-Gás/Relatórios/Monitoramento/Integrações) ── paralelo, após F4
F10 Limpeza & hardening (remove AdminLTE/Filament/menus; E2E; OpenAPI) ── após F9
```

**Por que esta ordem:** Produto/Geográfico/Empresa/Cadastros são as FUNDAÇÕES de dados que
Estoque/Financeiro/Fiscal/Vendas referenciam. Os motores (Estoque/Financeiro) vêm antes do
Fiscal, e Vendas é o último porque orquestra TODOS (estoque+financeiro+fiscal+vale-gás) de forma
atômica. Satélites correm em paralelo a partir de F4 (dependem só de cadastros).

---

## FASE 1 — PRODUTO · risco BAIXO/MÉDIO
**Contrato:** [IMPL_PRODUTO.md](IMPL_PRODUTO.md) · **Legado:** ProdutoController(840),
ProdutoRequest(108), Produto(359), view `produtos/produtos_form`, tabela `produtos` (49 col).

**Entregáveis:**
- API admin Produto: GET/POST/PUT/DELETE `/produtos` + `show` com labels; sub-recurso
  `/produtos/{id}/origens` (GET/PUT, soma 100%); lookups (produto-classes, unidades,
  nf-grupos-fiscais, sped-tipo-itens, ipis, estados, produtos-vasilhame, ressarcimento, tipo-glp).
- **Service de regras** (reusar `dadosExtras`): conversão decimal BR; GLP pGNi+pGNn+pGLP soma
  **100 ou 0**; origens do combustível soma **100%**; NF-e converte bases; não-inativar produto
  **com saldo em estoque** (varre estoquesetor).
- Validações condicionais do ProdutoRequest na API (NF-e→grupo fiscal+descrição; GLP→ANP/CIDE+origens;
  SPED→tipo item; vasilhame→produtoretornavel_id).
- React: Lista (paginada, escopo empresa, busca, **ações por linha** editar/excluir/inativar, marca
  inativo) + Ficha em abas (Dados / Preços máscara BR / Fiscal-NF-e / GLP / Origens), campos NF-e/GLP
  reativos (visíveis conforme nfepermite/classe).
- Reorganização: produtoclasse+unidademedida → Produtos→Configurações (abas); atualizarprecos →
  ação "Atualizar preços em massa" com preview.

**DoD (do IMPL):** 49 colunas editáveis · GLP 100/0 · origens 100% · não-inativar c/ estoque · decimal
BR · validações condicionais · lista com ações · criar/editar/excluir/inativar testados · testes
automatizados verdes.

**Gate:** Produto criado/editado/inativado no `/app` em prod; regras GLP+origens validadas por teste.

---

## FASE 2 — GEOGRÁFICO (Cidade/Bairro/Rua/Região) · risco BAIXO
**Contrato:** [IMPL_GEOGRAFICO.md](IMPL_GEOGRAFICO.md) · **Legado:** Cidade/Bairro/Rua/RegiaoController.

**Entregáveis:**
- API admin `/geo/cidades`, `/geo/bairros`, `/geo/ruas`, `/geo/regioes` (CRUD) — reusar lookups da S2b.
- Regras de **unicidade** na API: cidade (descricao+uf no grupo, ou global grupo_id null), bairro
  (descricao+cidade_id), rua (descricao+cidade_id); defaults da rua (importacaocep_id=-1, nfecompl='Rua').
- **Cidade respeita registros globais** (grupo_id null aparecem para todos).
- `destroy` com mensagem de FK amigável (não 500).
- React: 1 página **Geográfico** com abas (Cidades/Bairros/Ruas/Regiões), selects dependentes
  cidade→bairro→rua (AsyncSelect), ações por linha; drill-down (abrir Cidade → ver Bairros/Ruas).

**Decisão pendente no contrato:** RegiaoController **sem métodos públicos** — auditar model/rotas e
**decidir**: cobrir como CRUD ou marcar N/A (scaffold morto). Resolver no início da fase.

**DoD:** CRUD das 4 entidades c/ validações+unicidade · cidade global · FK amigável · ações por linha ·
testes verdes · Região decidida.

**Gate:** página Geográfico em prod; selects dependentes funcionando no form de Cliente/Produto.

---

## FASE 3 — EMPRESA / EMPRESACONFIG / GRUPOS · risco MÉDIO
**Contrato:** [IMPL_EMPRESA.md](IMPL_EMPRESA.md) · **Legado:** EmpresaController(800),
EmpresaconfigController(716), EmpresasGrupoController(156); `empresas`(95), `empresaconfigs`(106).

**Entregáveis:**
- API admin `/empresas` (CRUD), `/empresas/{id}/config` (GET/PUT — 106 colunas),
  `/grupos` (CRUD), `/empresas/{id}/ativar` (= `change`, troca empresa ativa na sessão — CRÍTICO),
  senha-mestra (verificar/alterar), teste de e-mail SMTP (`sendEmail`), lookups plano/centro de contas.
- React: página **Empresas** (lista + ficha em abas: Identificação/Endereço/Contato/Fiscal-NF-e/
  Logo/Bancário) · aba **Configurações** com **sub-abas temáticas** (Pedido-Entrega/Estoque/Impressão/
  E-mail/Contábil/Frete/Percentuais) — fim do formulário gigante · aba **Grupos** (matriz/filiais) ·
  **Senha Mestra**. **Trocar empresa ativa = seletor no header**.
- Logo (bytea) upload; mapeamento contábil completo (plano_id/centro_id por finalidade).
- ARMADILHA (manter fix F0): `index` da config faz compact() de ~40 vars → todas com default null
  para empresa sem config.

**DoD:** 95 col empresa + 106 col config editáveis nas abas · change + senha mestra + teste SMTP ·
logo upload · mapeamento contábil · lista com ações · testes verdes.

**Gate:** trocar empresa ativa pelo header em prod; salvar config completa sem perder campo.

---

## FASE 4 — CADASTROS DE APOIO (consolidado) · risco BAIXO
**Contrato:** [IMPL_CADASTROS_APOIO.md](IMPL_CADASTROS_APOIO.md).

**Entregáveis:**
- API admin CRUD genérico por cadastro (`/config/<cadastro>`) com escopo grupo/empresa + unicidade
  (descricao) + `destroy` FK amigável. Reusar lookups da S2b (adicionar create/update/delete).
- React: cada "Configurações" (de Clientes/Produtos/Financeiro/RH/Frota) é **1 página com abas**, cada
  aba um CRUD simples (DataTable + form modal). Cadastros de 1ª classe (Banco, Setor) podem ter página
  própria no grupo Cadastros.
- **De-para 100%** da tabela do IMPL (Segmento/Tipopessoa/Telefonetipo/ClienteContatoTipo/Situacao/
  Promocao → Clientes→Config; Produtoclasse/Unidademedida → Produtos→Config; Banco/Layoutbanco/
  Condicaopagamento/Contamovimentotipo → Financeiro→Config; Cargo/Estadocivil/Parentesco/Tipoexame →
  RH→Config; Tipocombustivel/Veiculotipo/Tipodocumento → Frota→Config; etc.).

**Atenção (não tratar como apoio trivial):** **Pedidosituacao** (máquina de estados) e
**Pedidooperacao** (movimenta estoque/financeiro) são CONFIG CRÍTICA do Pedido → ficam para o IMPL_VENDAS.

**DoD:** todo cadastro de apoio tem destino (de-para 100% conferido contra a árvore de menus) · CRUD
c/ validação+unicidade+escopo+FK amigável · nenhuma função perdida · testes dos principais verdes.

**Gate:** páginas de Configurações dos módulos já migrados (Cliente/Produto) preenchidas; nenhum item
de menu de apoio do legado sem destino.

---

## FASE 5 — ESTOQUE (motor + telas) · risco MÉDIO
**Contrato:** [IMPL_ESTOQUE.md](IMPL_ESTOQUE.md) · **Motor:** EstoqueProcessor (caracterizado F1).

**Entregáveis:**
- **Baseline de teste do motor ANTES de mexer**: movimentar/fechar/abrir/efetivar/custo médio
  (golden F1 verde). Não regredir o fix F0 (efetivarEstoquefisico gravava id no empresa_id).
- Extrair EstoqueProcessor como Domain Service testável.
- API admin: `/estoque/saldos`, `/estoque/transferencias` (CRUD), `/estoque/requisicoes`,
  `/estoque/inventarios`, `/estoque/fisico` (+efetivar), `/estoque/fechamento` (fechar/abrir) — todas
  com transação atômica; respeita `permiteestoquenegativo`.
- React: 1 página **Estoque** com abas (Saldos/Requisição/Transferência/Acerto/Inventário/Físico/
  Fechamento), ações por linha, **preview de saldo (antes/depois)** por movimento, timeline do produto.
  Relatórios de estoque → Central de Relatórios (F8).

**DoD:** motor preservado c/ baseline verde · todas as telas na página Estoque c/ abas + ações + preview ·
permiteestoquenegativo respeitado · não-inativar produto com saldo (liga com F1) · testes (motor+endpoints) verdes.

**Gate:** transferência/inventário/físico executados no `/app` em prod sem regressão de saldo.

---

## FASE 6 — FINANCEIRO / TESOURARIA · risco MÉDIO/ALTO
**Contrato:** [IMPL_FINANCEIRO.md](IMPL_FINANCEIRO.md) · **Motor:** Processors financeiro/caixa/
cheque/boleto (parte caracterizada F1). Domínio com MAIS telas dispersas (12+).

**Entregáveis:**
- **Baseline de teste do motor ANTES de mexer**: setParcelasRequest/gravar/baixar/rateio/caixa.
- 🔴 **Corrigir SQLi** `FinanceiroController::getLancamentosFinanceiros:355-365` (parametrizar bindings)
  + `case 4;` malformado (:361). **Prioridade de segurança** — fazer no início da fase.
- API admin: `/financeiro/lancamentos` (filtros PARAMETRIZADOS), `/caixa` (abrir/fechar/baixar),
  `/cheques`, `/boletos`, `/pix`, `/conciliacao`, `/plano-contas` (árvore), `/centro-custos`,
  `/fechamento-mensal`. Processors como Domain Services.
- React (consolidação 12+ telas): **Lançamentos** (CR+CP+receita+despesa, filtros tipados) · **Caixa/
  Tesouraria** (abrir/fechar/baixar com **preview de saldo** + rateio visual) · **Cheques** (abas) ·
  **Boletos/PIX** (abas) · **Conciliação** (+import extrato/cartão) · **Config Financeiro** (árvore
  plano/centro+layout banco+tipos movimento) · **Fechamento Mensal** (DRE/Balanço).

**DoD:** motor preservado baseline verde · SQLi fechado + case corrigido · 12+ telas consolidadas
(de-para 100%) · Caixa com preview · Lançamentos com filtros tipados · testes verdes.

**Gate:** lançar/baixar título e fechar caixa no `/app` em prod; filtro de lançamentos sem SQLi (teste).

---

## FASE 7 — FISCAL (NF-e / NFC-e / Malha / SPED) · risco ALTO · 🔴 SEFAZ BLOQUEANTE
**Contrato:** [IMPL_FISCAL.md](IMPL_FISCAL.md) · **Núcleo:** NfemitidaController (maduro), Nfrecebida,
NfwebController, malha fiscal (~8 telas), SPED.

**Entregáveis:**
- 🔴 **VALIDAR EMISSÃO EM HOMOLOGAÇÃO SEFAZ** (Carbon 3 + PHP 8.3 podem afetar datas/decimais) — oráculo
  é SEFAZ aceitar/rejeitar; SPED validar no PVA. **Depende de certificado/ambiente — gate externo.**
- Refatorar motor fiscal em Service testável; preservar emissão/transmissão/cancelamento/CCe/importação.
  Aplicar fix unique-PG onde houver concatenação de id em FormRequests fiscais.
- API admin: `/fiscal/nfe` (emitir/transmitir/cancelar/cce/consultar), `/fiscal/nf-recebida` (importar),
  `/fiscal/malha/*` (CRUD tributários), `/fiscal/sped` (gerar).
- React: **NF-e/NFC-e** (lista com status rascunho/transmitida/autorizada/cancelada + ações) · **NF
  Recebida** (importar XML) · **Malha Fiscal** (1 página, abas por imposto — fim das ~8 telas) ·
  **SPED** (gerar com preview de blocos/contagem) · **NFC-e/SAT**.

**DoD:** NF-e/NFC-e emissão/transmissão/cancelamento/CCe/importação preservados · malha consolidada
(todos CSTs/grupo/operação/IBPT) · SPED gerável com preview · **emissão validada em homologação SEFAZ** ·
testes verdes, sem regressão fiscal.

**Gate (duplo):** técnico (telas/testes) **+ SEFAZ homologação aceita** antes de ligar em produção real.

---

## FASE 8 — SATÉLITES (paralelo, a partir de F4) · risco BAIXO/MÉDIO
**Contrato:** [IMPL_SATELITES.md](IMPL_SATELITES.md). Cada sub-módulo: **auditar SPEC fina antes de codar**.

**Sub-fases (independentes entre si):**
- **8a RH/Colaboradores** — ficha com abas (Dados/Cargo/Comissões/Família/Exames/Recessos);
  reescrever scaffolds vazios (Colaboradorfamilia) como RelationManager; Config (Cargos/Tipo exame/recesso).
- **8b Frota/Veículos** — ficha por veículo (Abastecimentos/Trocas óleo/Pneus/Entrada-Saída/Documentos),
  timeline de manutenção; Config (Tipo veículo/combustível). Reescrever Veiculodocumento (scaffold).
- **8c Vale-Gás/Convênio** — Vale-Gás (venda→consulta→baixar→cancelar com status); Convênio (gestão+
  fechamento+dashboards via query service parametrizado).
- **8d Central de Relatórios** — 26 Report*Controllers em área única por categoria (filtros+preview+
  export PDF/XLSX). Mover SQL pesado p/ query services parametrizados (fechar whereRaw/$_GET).
- **8e Monitoramento GPS** (App\Monitora — schema/guard próprios) — mapa+status; jobs preservados;
  alinhar permissões ao RBAC novo; verificar hardcode empresa 2 em SearchController:65.
- **8f Integrações/Notificações** — FCM/Pix/eRede em Services com chaves via config(); Notificações
  (envio/histórico); Administração→Integrações/Configurações; descartar obsoleto.

**DoD (por sub-fase):** SPEC fina auditada · página com abas (de-para 100%) · scaffolds vazios reescritos ·
relatórios na Central + SQL parametrizado · testes verdes.

---

## FASE 9 — VENDAS / PEDIDOS / CAIXA · risco MÁXIMO · ÚLTIMO
**Contrato:** [IMPL_VENDAS.md](IMPL_VENDAS.md) · **Legado:** PedidoController(1661), CaixaController(1056),
PedidoRequest. **Só iniciar com F1–F7 prontos e testados.**

**Entregáveis:**
- **Baseline de teste OBRIGATÓRIO antes de mexer**: máquina de estados + orquestração atômica
  (estoque+financeiro+NF+vale-gás na transição; estorno condicionado a parcela baixada/cheque/boleto/
  pagamento online). Contratos do **app mobile** (D13) preservados.
- Refatorar em **Domain/Actions** por transição (CriarPedido/ConfirmarPedido/CancelarPedido/TrocarSetor),
  cada uma `DB::transaction` + eventos de domínio; controllers/UI finos.
- **Atualizarprecos** seguro (bindings/authorize no store/$this->errors).
- Cadastros críticos do pedido (Pedidosituacao/Pedidooperacao) — CRUD aqui (vindos da F4).
- React: **Pedidos** como painel/Kanban por status + ficha com jornada (cliente→itens c/ preço/estoque
  em tempo real→pagamento→confirmação); **Caixa/Tesouraria** (em Financeiro) com preview; venda guiada
  por etapas. Vendaativa/Promotor/Promocao/Vendasmensaisgestao reorganizados conforme de-para.

**DoD:** máquina de estados + orquestração atômica preservada e **coberta por testes** · Pedidos Kanban +
ficha jornada · Caixa preview · Atualizarprecos seguro · contratos app mobile intactos · suíte verde ·
emissão fiscal validada (depende de F7).

**Gate:** ciclo completo do pedido (criar→confirmar→entregar/cancelar) no `/app` em prod, com estoque+
financeiro+NF coerentes e app mobile funcionando.

---

## FASE 10 — LIMPEZA & HARDENING · após F9
- Remover AdminLTE + Filament + tabelas `menus`/`menuusers` (navegação agora é declarativa no SPA).
- Consolidar RBAC; auditoria de segurança final; **OpenAPI** da API admin.
- E2E (Playwright) cobrindo as jornadas críticas (cliente/produto/pedido/caixa/NF-e).
- Resolver pendências transversais remanescentes (nomes de rota, flags de segurança em staging).

---

## CHECKLIST GENÉRICO DE FASE (copiar por módulo)

- [ ] Reli o IMPL_<modulo>.md (contrato) e a seção do MAPA_NAVEGACAO_ALVO.
- [ ] Reauditei o legado citado (controller/request/model/view/tabela) — contrato confere.
- [ ] API admin criada (rotas em `api_admin.php`, `auth:sanctum`, `can('<modulo>.<acao>')`).
- [ ] Regras/motor em Service; baseline de teste do motor verde ANTES de refatorar (quando houver).
- [ ] Validações condicionais/unicidade reaproveitadas do legado.
- [ ] React: lista paginada SEM bug + **ações por linha**; ficha/abas cobrindo TODOS os campos.
- [ ] Reorganização aplicada (de-para 100%); nenhuma função/campo perdido.
- [ ] Testes automatizados (store/update/validações/regras/sub-recursos) — **suíte verde**.
- [ ] Deploy (CI build Vite + `npm ci`) + verificado na VPS; cache/owner OK (chown www-data).
- [ ] Gate da fase atingido; legado da entidade apontado para `/app/<modulo>` (flag).
- [ ] DoD específico do IMPL marcado 100%.
