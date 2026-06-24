# Plano de Reescrita do Backend — Gás em Casa / ctrl-web (Greenfield)

> Documento operacional, faseado, 100% derivado da leitura do código-fonte real
> (ver `INVENTARIO_BACKEND_COMPLETO.md`). Nenhuma fase depende de documentação legada.

---

## 0. Decisões fechadas (base do plano)

| Decisão | Escolha |
|---|---|
| **Stack** | Laravel 12 / PHP 8.3 / PostgreSQL (mantida) |
| **Schema / ORM** | Migrations + Eloquent (schema novo declarado em migrations, versionado, rollback) |
| **Alvo** | Greenfield — backend novo, banco novo, projeto Laravel limpo |
| **Schema do banco** | Redesenhado limpo (tipos corretos, sem string formatada, FKs/índices bem-feitos) |
| **Motores** | Reescritos limpos — EXCETO fiscal (NF-e/SPED) que é **portado** (legislado), e pagamentos que reusam libs PHP como **gates** |
| **Migração de dados** | ETL: lê banco de produção (dump) → grava no schema novo via Eloquent, validando invariantes |
| **Sequência** | Por módulo de negócio inteiro |
| **Testes** | Baseline obrigatório no núcleo financeiro; testes de migração (invariantes) no cutover |
| **Coexistência** | Mundo A (legado em produção, intocado) e Mundo B (app nova). Tocam-se só no cutover. NÃO é Strangler no mesmo banco. |

---

## 1. Princípios inegociáveis

1. **A app nova não toca o banco de produção** até o cutover. Desenvolvimento em banco novo.
2. **O dado no banco novo é número de verdade** (`decimal`/`integer`), nunca string formatada. A tradução string-BR↔número (os `*Oracle`) morre: vai para o **ETL** (uma vez) e para **Resources/Casts** (exibição).
3. **Contrato JSON uniforme** em toda a API: número cru, objeto nomeado (nada de array posicional `$x[0..n]`), sem `View`/`Redirect`/strings de protocolo (`"OK|"`).
4. **Regra de negócio em Services**, nunca em controller. Controllers só validam (Form Request), chamam o Service e devolvem Resource.
5. **Saldo auditável**: caixa/financeiro/estoque guardam saldo, mas ele tem de ser **derivável dos movimentos** (não só incremental). É o que torna a migração verificável.
6. **Fiscal não se reinventa**: porta-se a lógica validada (NFePHP + cálculo de imposto). Reescrever do zero reintroduz risco fiscal/SEFAZ.
7. **Toda regra financeira/fiscal portada entra com teste** comparando resultado (baseline / caso de ouro) antes do merge.
8. **Tenant explícito por request** (token → empresa/grupo), substituindo `Session('empresa_padrao')`.
9. **Uma fase = uma branch.** Você revisa e mergeia antes da próxima.
10. **Integrações externas são gates** (PIX Itaú, boleto CNAB, cartão Rede, SEFAZ, GPS SGCasa, OFX, geocoding): não testáveis em CI; exigem credencial/homologação; portar a lib + isolar em service + documentar.

---

## 2. Arquitetura-alvo (resumo)

```
erp-novo/                      (projeto Laravel 12 limpo)
├── app/
│   ├── Domain/                 (a regra de negócio — Services puros, sem HTTP)
│   │   ├── Tenant/             TenantContext (substitui Session('empresa_padrao'))
│   │   ├── Cliente/            ClienteService + sub-serviços
│   │   ├── Produto/            ProdutoService
│   │   ├── Estoque/            EstoqueService (saldo derivável de movimentos)
│   │   ├── Financeiro/         FinanceiroService (a/pagar-receber, agrupamento)
│   │   ├── Caixa/              CaixaService (abrir/fechar/baixar/transferir/estornar)
│   │   ├── Cheque/             ChequeService
│   │   ├── Pedido/             PedidoService (orquestra Estoque+Financeiro+Fiscal)
│   │   ├── Fiscal/             FiscalService (PORTADO — NF-e/NFC-e/CF-e/SPED + cálculo imposto)
│   │   ├── Cobranca/           BoletoService, PixService (gates)
│   │   ├── Pagamento/          PagamentoOnlineService (Rede — gate)
│   │   └── Convenio/ ValeGas/ Comodato/ ...
│   ├── Http/
│   │   ├── Controllers/Api/    (finos: valida → Service → Resource)
│   │   ├── Requests/           (Form Requests — validação)
│   │   ├── Resources/          (serialização JSON — número cru, nomeado)
│   │   └── Middleware/         (auth token, tenant, RBAC)
│   ├── Models/                 (Eloquent — schema novo, casts numéricos)
│   ├── Policies/               (RBAC — substitui menuusers/podeRecurso)
│   └── Jobs/                   (fila: emissão fiscal, PIX, vídeo, e-mails)
├── database/migrations/        (SCHEMA NOVO declarado aqui)
├── etl/                        (ferramenta de migração: legado → novo)
│   ├── Readers/                (lê o dump/banco legado)
│   ├── Transformers/           (string-BR→número, array posicional→DTO, datas)
│   ├── Loaders/                (grava no schema novo via Eloquent)
│   └── Invariants/             (valida: Σ movimentos = saldo, contagens, totais)
└── tests/
    ├── Feature/                (API: request/response)
    ├── Domain/                 (regra: baseline financeiro/fiscal)
    └── Migration/              (invariantes do ETL contra dump real)
```

**Frontend:** o SPA React/Vite já existente é reaproveitado; passa a consumir a API nova (JSON limpo). Onde faltar tela, completa-se por fase.

---

## 3. Os 3 blocos de trabalho (andam juntos, por módulo)

- **Bloco 1 — Backend novo** (schema + Service + API + Resource + testes).
- **Bloco 2 — ETL** (reader + transformer + loader + invariantes) — escrito **junto** com cada módulo, nunca deixado pro fim.
- **Bloco 3 — Cutover** (freeze produção → dump → ETL completo → validação → vira).

Regra de ouro do ETL: **toda fase que migra dados entrega os 3 checks** contra um dump real:
1. **Contagem** (nº de registros origem = destino, menos descartes explícitos).
2. **Invariante de valor** (somatórios batem: Σ saldos, Σ títulos, Σ estoque; e a invariante `Σ movimentos = saldo`).
3. **Integridade** (zero FK órfã, zero campo obrigatório nulo).

---

## 4. O que REESCREVER vs PORTAR vs GATE (do inventário)

| Componente | Estratégia | Por quê |
|---|---|---|
| Controllers (CRUD, validação, formatação) | **Reescrever** | Casca; é onde está o débito (string, Redirect, array posicional) |
| caixaProcessor / financeiroProcessor / EstoqueProcessor / ChequeProcessor | **Reescrever limpo (com baseline)** | Greenfield; saldo precisa virar auditável. Risco máximo → baseline obrigatório |
| Cálculo de imposto (NfeImpostoProcessor, IcmsBase, tributação) | **Portar** | Legislado; reescrever reintroduz risco fiscal |
| NF-e/NFC-e/CF-e XML + SEFAZ (NFePHP, SefazEvento, MakeXml, TagMaker) | **Portar + isolar (gate)** | Validado pela SEFAZ; depende de certificado/homologação |
| SPED Fiscal/Contribuições (Sped/) | **Portar** | Leiaute Receita; gate fiscal |
| Boleto CNAB (BoletoProcessor + lib eduardokum) | **Portar (gate)** | Integração bancária Caixa/Itaú; homologação |
| PIX (PixService — Itaú) | **Portar (gate)** | API Itaú; credencial/webhook |
| Cartão online (MobileAppProcessor — eRede/Rede) | **Portar (gate)** | API Rede; PV/token |
| OFX (ImportextratoController — beccha/ofxparser) | **Reescrever (usa lib)** | Conciliação; lib mantém |
| GPS / Monitora (SGCasa) | **Portar como módulo isolado** | Bounded context próprio, schema próprio |
| Helpers `*Oracle` | **Eliminar** | Só formatação BR; vira cast/ETL |
| `calculoParcelas`, `agrupamento_status`, custo médio | **Reescrever preservando a regra** | Regra central; baseline |
| menuusers (menu-no-banco) | **Substituir** | Por RBAC (Policies) + nav declarativa (direção da SPA) |

---

## 5. FASES — plano operacional

> Cada fase: **objetivo → entregas backend → entregas ETL → testes → critério de pronto (DoD)**.
> Numeração N0..N12. Uma branch por fase; merge antes da próxima.

---

### N0 — Fundação

**Objetivo:** projeto Laravel 12 limpo de pé, com as peças transversais das quais TODO o resto depende.

**Entregas backend:**
- Projeto Laravel 12 novo (sem arrastar os 624 migrations / 203 models / 160 controllers legados).
- **TenantContext**: resolução de empresa/grupo por token no request (substitui `Session('empresa_padrao')`). Middleware que injeta o tenant; escopo automático de queries por `empresa_id`/`grupo_id`.
- **Auth** (Sanctum para SPA + token para apps) e **RBAC** unificado via Policies/roles (decide o modelo único: substituir menuusers + spatie por um só).
- **Helpers de domínio essenciais reescritos limpos**: `calculoParcelas` (serviço), conversão de moeda/data (casts), `getProximoVencimento`, geração de número sequencial com lock (numeração fiscal — ver N9).
- Estrutura `app/Domain`, `Resources`, `Requests`, `Policies`, `etl/`, CI (PHPUnit + lint).
- Esqueleto do ETL: conexão de leitura ao dump legado + runner + framework de invariantes.

**Entregas ETL:** conexão read-only ao dump; 1 migração ponta-a-ponta de exemplo (ex.: estados/cidades) provando o pipeline reader→transformer→loader→invariante.

**Testes:** smoke (app sobe, auth funciona, tenant resolve); 1 invariante de exemplo passando.

**DoD:** login + tenant + RBAC funcionando; ETL roda uma tabela de exemplo com os 3 checks verdes; CI verde.

---

### N1 — Cadastros base (tenant + apoio)

**Objetivo:** Empresa/Grupo/Usuário + os ~40 cadastros de apoio (descricao+ativo).

**Entregas backend:**
- **Empresa** (a entidade-tenant) + **EmpresaConfig** (config operacional) + **change()** equivalente (trocar tenant ativo via token/contexto). Certificado digital A1 em storage seguro/secret; senha re-encriptada.
- **Grupo (EmpresasGrupo)**, **Usuário** + RBAC, **Regiao**.
- Cadastros de apoio genéricos (padrão `CadastroApoioController` já existente na SPA): Banco, Cidade, Bairro, Rua, Segmento, Tipopessoa, Telefonetipo, Estadocivil, Parentesco, Tipoexame, Cargo, Unidademedida, Produtoclasse, Contamovimentotipo, Documentotipo, Pedidosituacao, Pedidooperacao, Condicaopagamento (com parcelas), Contatipo, etc.
- Selects fiscais (CST/CFOP/modalidade) como enum/tabela (sair do hardcoded).

**Entregas ETL:** migra empresas/grupos/usuários/regiões + todos os cadastros de apoio. Geocoding/cep preservados. **Certificados** movidos + senhas re-encriptadas.

**Testes:** API + contagens batem; tenant ativo troca corretamente; RBAC nega/permite.

**DoD:** dá pra logar, escolher empresa, e ver/editar todos os cadastros de apoio na SPA.

---

### N2 — Clientes

**Objetivo:** cliente (também fornecedor/transportador) com todas as sub-relações.

**Entregas backend:**
- **ClienteService** + sub-serviços: telefones, contatos/interações, convênio + dependentes (parentesco), produtos com preço/desconto, produtos de convênio, promoções, condições de pagamento.
- Payload **JSON aninhado** (`telefones:[{...}]`) substituindo arrays posicionais.
- Geocoding assíncrono (Job); auditoria via observers/lib (não `keepRevisions` manual).
- Anti-duplicidade de endereço (query parametrizada, sem SQLi).

**Entregas ETL:** migra clientes + todas as sub-tabelas; `*Oracle` de data/percentual/valor convertidos para número/data; arrays posicionais → linhas normalizadas.

**Testes:** API + nº de clientes bate, zero órfão; convênio/desconto preservados.

**DoD:** CRUD de cliente completo na SPA, com convênio/produtos/contatos.

---

### N3 — Produtos / Estoque

**Objetivo:** produto (classificação fiscal/GLP) + **EstoqueService reescrito**.

**Entregas backend:**
- **ProdutoService**: NCM/CEST/IPI/gênero TIPI/LST/origem combustível/tipo GLP/PGLP. Listas TIPI/LST como tabela (sair do hardcoded ~330 linhas).
- **EstoqueService** (reescrito): movimentação ENTRADA/SAIDA por setor, **custo médio** ponderado, fechamento/abertura/reabertura, estoque físico (inventário), requisição, transferência, comodato, acerto.
- **Saldo de estoque derivável de histórico** (`Σ estoquesetorhistorico = estoquesetor.quantidade`), mantido + auditável.

**Entregas ETL:** migra produtos + saldo de estoque + histórico + fechamentos.

**Testes (baseline núcleo):** invariante `Σ histórico = saldo` por setor×produto; custo médio confere; saldo dos fechamentos confere.

**DoD:** produto + estoque na SPA; movimentação e fechamento funcionando; invariantes verdes contra dump.

---

### N4 — Pedidos / Vendas

**Objetivo:** núcleo de vendas — **PedidoService** orquestrando estoque + financeiro + (fiscal/pagamento nas fases seguintes).

**Entregas backend:**
- **PedidoService**: criar/editar/mudar status; **máquina de estados de situação explícita** (substitui a matriz implícita status×condição×setor que decide INSERE/EXCLUI financeiro e SAIDA/ENTRADA estoque).
- Itens como DTO nomeado; valores crus.
- Integração com EstoqueService (movimentação) e FinanceiroService (gerar/estornar — N5).
- Vale-gás, gás-do-povo, venda ativa, comanda/impressão.

**Entregas ETL:** migra pedidos + itens + histórico de situação.

**Testes (baseline):** totais de pedido conferem; transição de status produz a mesma movimentação que o legado (baseline PedidoBaselineTest).

**DoD:** pedido completo na SPA; estoque movimenta correto; pronto para plugar financeiro/fiscal.

---

### N5 — Financeiro (a pagar / a receber)

**Objetivo:** **FinanceiroService** — o serviço único que TODOS os geradores passam a usar (elimina a duplicação inline documentada).

**Entregas backend:**
- **FinanceiroService**: criar financeiro + parcelas + rateios; **agrupamento/reparcelamento** (`agrupamento_status` 0/1/2/3 como enum); cancelar/desagrupar; alterar; baixa (via CaixaService — N6).
- Centralizar aqui os geradores hoje duplicados: Pedido, NF emitida/recebida, CF-e, Convênio, Vale-gás, OFX → todos chamam o FinanceiroService.
- Consulta de contas a pagar/receber via query parametrizada/repository (sem SQLi).
- Importação de cartão (conciliação de adquirente).

**Entregas ETL:** migra financeiros + parcelas + rateios; `agrupamento_status` preservado.

**Testes (baseline obrigatório):** soma de títulos bate; nenhuma parcela órfã; agrupamento/reparcelamento reproduz o legado.

**DoD:** contas a pagar/receber na SPA; agrupamento funciona; invariantes verdes.

---

### N6 — Caixa / Conta / Cheque (o coração do saldo)

**Objetivo:** **CaixaService** — o componente de **risco máximo**. Reescrito com saldo auditável.

**Entregas backend:**
- **CaixaService**: abrir/fechar caixa (saldo inicial/final por fechamento); **baixar títulos** (redistribuição de desconto/juros/multa entre parcelas — regra sensível a centavo); transferir entre contas; estornar; taxa de cartão (gera contas-a-pagar).
- **Saldo derivável de movimentos** (`Σ contamovimentos = conta.saldoatual`); propagação a fechamentos posteriores explícita e testada.
- **ContaService** (conta/boleto-config/permissões por usuário/talões/extrato OFX-config).
- **ChequeService** (recebido/emitido): máquina de estados explícita; encontro de contas; troco/adiantamento/devolução; integra CaixaService.
- Permissões de caixa por usuário×conta (operar/transferir/estornar/lançar-fechado) como Policies.

**Entregas ETL:** migra contas (saldoatual!), contafechamentos (saldoinicial/final), contamovimentos, transferências, estornos, cheques.

**Testes (baseline obrigatório — o caso #1):** byte-a-byte — saldo de cada conta + saldoinicial/final de cada fechamento + cada movimento, legado × novo. **Invariante de cutover:** `Σ movimentos por conta = saldoatual`.

**DoD:** caixa/conta/cheque na SPA; baixa/estorno/transferência corretos; **invariante de saldo verde em dump real** (sem isso, não avança).

---

### N7 — Boleto / CNAB / PIX (cobrança)

**Objetivo:** cobrança registrada (gates bancários).

**Entregas backend:**
- **BoletoService** (porta BoletoProcessor + lib eduardokum): gerar boleto PDF, remessa CNAB (.rem), processar retorno, ocorrências (protesto/baixa/abatimento). Bancos Caixa(104)/Itaú(341) como drivers.
- **PixService** (porta — Itaú): cobrança imediata, QR/copia-e-cola, **webhook seguro** (valida valor/payload/binding — a correção S3), conciliação. Job ProcessPixPedido.
- Layout CNAB via lib (não parse posicional manual); ocorrências como tabela.

**Entregas ETL:** migra boletos emitidos + histórico + remessas.

**Testes:** baseline de geração de boleto (mesmos campos); **gate de homologação por banco** (não em CI).

**DoD:** boleto/remessa/retorno + PIX funcionando em homologação; conciliação OFX (N5) integrada.

---

### N8 — Convênio / Vale-gás / Comodato / Pós-venda

**Objetivo:** módulos satélite que dependem de Financeiro/Estoque/Fiscal já prontos.

**Entregas backend:**
- **ConvenioFechamentoService** (agrupa pedidos do mês → 1 financeiro consolidado via FinanceiroService; emite NF/boleto).
- **ValeGasService** (cupom pré-pago: máquina de estados por enum, financeiro, etiquetas por template).
- **ComodatoService** (vasilhame; movimenta estoque).
- Pós-venda, promoção, sorteio, mala-direta (e-mail em massa via Job).

**Entregas ETL:** migra convênios/fechamentos, vale-gás, comodatos.

**Testes:** baseline financeiro nos que geram financeiro; contagens.

**DoD:** módulos satélite na SPA.

---

### N9 — Fiscal (NF-e / NFC-e / CF-e / SPED) — PORTADO

**Objetivo:** emissão fiscal — **portar, não reinventar**.

**Entregas backend:**
- **FiscalService** que encapsula o cálculo de imposto (porta NfeImpostoProcessor/IcmsBase/CalculoImposto — ICMS/ST/PIS/COFINS/IPI/FCP/deson/diferimento/IBS-CBS) e a montagem de XML (NFePHP/MakeXml/TagMaker).
- **SEFAZ isolado** (SefazEvento via NFePHP): transmitir/consultar/cancelar/CCe/inutilizar/DANFE/e-mail. Certificado A1 do tenant (N1).
- **Numeração fiscal com lock** (a regra crítica de `trataNumNF`/checarNfNumero — anti-duplicidade).
- **SAT/CF-e** (porta — agente WebSocket, regional SP; avaliar se em escopo pelo uso real).
- **SPED Fiscal/Contribuições** (porta SpedProcessor + Reg* por leiaute Receita).
- NF de entrada (recebida) + import de XML (Standardize).
- Financeiro/estoque da NF via Financeiro/EstoqueService (não inline).
- Cadastro de configuração fiscal (Imposto por operação×grupo×estado/PF-PJ).

**Entregas ETL:** migra NF emitidas/recebidas + itens + impostos + config fiscal + numeração atual da empresa.

**Testes (baseline fiscal):** mesma entrada → mesmo XML/imposto que o legado (casos de ouro). **Gate SEFAZ** (homologação, não CI).

**DoD:** emissão/cancelamento de NF-e/NFC-e em homologação batendo com o legado; numeração íntegra.

---

### N10 — Mobile (app cliente + entregador) / API externa

**Objetivo:** as APIs de app (contrato com apps em campo).

**Entregas backend:**
- **API mobile versionada** (porta app/Api — Passport/OAuth): cliente, pedido, produto, endereço, cupom, notificação, vídeo. Resources já dão JSON limpo.
- **PedidoMobileService** (porta MobileAppProcessor): matching de cliente/endereço/setor por geolocalização; convênio; **PagamentoOnlineService (Rede)** + **PixService**; grava transação (gateway isolado, ex-`sgcm_api`).
- API do app de entregadores (porta ApiController/Nfweb): registro de device, sync de cadastros, status com geoloc, vale-gás. **Auth real do colaborador por token** (eliminar usuário-mestre via env).
- Push (FCM) via service; emissão de NF do app assíncrona (Job, não sleep).

**Entregas ETL:** migra dados do app (devices, oauth clients, pedidos do app, transações online).

**Testes:** contrato de API (request/response) por endpoint; gate de pagamento/push.

**DoD:** apps funcionando contra a API nova em homologação; sem usuário-mestre; sem vazamento de credenciais.

---

### N11 — Monitora (GPS) — módulo isolado

**Objetivo:** rastreamento de frota como bounded context próprio.

**Entregas backend:**
- **Módulo Monitora** (porta app/Monitora): Device, Position, Ultimaposicao, Cerca/geofencing, Rota, Veiculo; schema próprio (`monitora`); guard próprio.
- Sync com SGCasa (commands SyncPosicoesSGCasa/UpdateClientsLocation; evento NotifySGC); job agendado `report:positions`.

**Entregas ETL:** migra schema monitora separado.

**Testes:** sync GPS em homologação (gate externo).

**DoD:** rastreamento operando; pode virar serviço independente no futuro.

---

### N12 — Relatórios, jobs agendados e limpeza final

**Objetivo:** fechar a superfície e preparar o cutover.

**Entregas backend:**
- **Relatórios** como Query Services parametrizados (os ~39 relatórios + DRE/Balanço): agregação no SQL, export por lib, sem SQLi/`TO_CHAR` Oracle. Priorizar por uso; relatórios raros podem vir por último.
- **Cron jobs** recriados: notify:alertas (07:00), vendadiaria:send (07:15), notify:delete (06:00), ibpt:update (05:00), remembermail:send (1min), documentosvencidosmail:send (07:30), notify:inconsistencies, report:positions (1min).
- **Jobs de fila**: ProcessAppVideo, ProcessPixPedido (+ emissão fiscal assíncrona).
- Eliminar resíduos Oracle (connection morta, AlterSequencesSeeder/`user_sequences`).

**Testes:** suíte completa; smoke dos relatórios; jobs disparam.

**DoD:** sistema funcional ponta-a-ponta no banco novo, com relatórios e agendamentos.

---

### CUTOVER (a virada)

**Objetivo:** colocar a app nova em produção com os dados reais.

**Passos:**
1. **Freeze** de escrita no legado (janela combinada).
2. **Dump** do banco de produção.
3. Rodar **ETL completo** (todos os módulos N1..N11) no banco novo.
4. **Validar invariantes** (o portão final):
   - Contagens por entidade (clientes, pedidos, NF, financeiros, etc).
   - **Saldo:** `Σ contamovimentos = conta.saldoatual`; saldoinicial/final de cada fechamento.
   - **Estoque:** `Σ estoquesetorhistorico = estoquesetor.quantidade`; saldos de fechamento.
   - **Financeiro:** Σ títulos a pagar/receber; zero parcela órfã; agrupamento consistente.
   - Integridade (FK órfã = 0; obrigatórios nulos = 0).
   - Certificados fiscais + numeração de NF migrados.
5. Apontar SPA/apps para a app nova; manter o legado em standby por N dias (rollback possível).
6. Acompanhamento pós-cutover (saldos, emissão fiscal, pagamentos).

**DoD do projeto:** app nova em produção; invariantes 100% verdes no dump real; legado desligável.

---

## 6. Ordem de risco e dependências

```
N0 Fundação ─┬─> N1 Cadastros ─┬─> N2 Clientes
             │                  ├─> N3 Produtos/Estoque ──┐
             │                  └─> (apoio)               │
             └─> Tenant/Auth/RBAC/calculoParcelas (base de tudo)
N3 ─> N4 Pedidos ─> N5 Financeiro ─> N6 Caixa/Cheque (saldo) ─> N7 Boleto/PIX
N5+N9 ─> N8 Convênio/ValeGás/Comodato
N6+N5 ─> N9 Fiscal (numeração, financeiro/estoque da NF)
N4+N5+N9 ─> N10 Mobile/API
(isolado) ─> N11 Monitora GPS
todos ─> N12 Relatórios/Jobs ─> CUTOVER
```

**Caminho crítico (maior risco, faça com mais cuidado/baseline):** N3 Estoque → N5 Financeiro → **N6 Caixa/Cheque** → N9 Fiscal. São os que carregam saldo/dinheiro/obrigação legal.

---

## 7. Riscos e mitigações (do código)

| Risco | Mitigação |
|---|---|
| Saldo incremental diverge na migração | Saldo derivável de movimentos + invariante `Σ mov = saldo` no ETL e em teste |
| Reescrever motor financeiro corrompe valor | Baseline byte-a-byte (legado×novo) antes do merge; N6 é o caso #1 |
| Reescrever fiscal reintroduz erro legal | **Portar** (não reinventar) + casos de ouro de imposto/XML |
| Perder regra escondida (anos de motores) | Ler o motor legado como especificação ao reescrever cada Service (inventário já feito) |
| Integração quebra (banco/SEFAZ/Rede/PIX) | Gates de homologação; libs PHP preservadas; isolar em service |
| `agrupamento_status` mal migrado | Enum explícito + teste de agrupamento/reparcelamento |
| SQLi / vazamento de credenciais (legado) | Queries parametrizadas no novo; nunca expor password/secret; auth por token |
| Numeração fiscal duplicada | Lock na numeração (regra trataNumNF preservada) |
| Certificado/credenciais na migração | Mover certificados + re-encriptar senhas no ETL; secret manager |
| Prazo (greenfield é grande) | Fases por módulo, valor incremental; relatórios raros podem ficar por último |

---

## 8. Tabela-resumo das fases

| Fase | Módulo | Reescreve/Porta | Baseline? | Gate? |
|---|---|---|---|---|
| N0 | Fundação (tenant/auth/RBAC/ETL) | Reescreve | — | — |
| N1 | Cadastros base + Empresa/Config | Reescreve | — | cert. fiscal |
| N2 | Clientes | Reescreve | — | geocoding |
| N3 | Produtos / Estoque | Reescreve | **Sim (estoque)** | — |
| N4 | Pedidos / Vendas | Reescreve | **Sim** | — |
| N5 | Financeiro a/pagar-receber | Reescreve | **Sim** | — |
| N6 | Caixa / Conta / Cheque | Reescreve | **Sim (#1)** | — |
| N7 | Boleto / CNAB / PIX | Porta | Sim (geração) | **Sim** |
| N8 | Convênio / Vale-gás / Comodato | Reescreve | Sim (financeiro) | — |
| N9 | Fiscal NF-e/NFC-e/CF-e/SPED | **Porta** | Sim (fiscal) | **Sim (SEFAZ)** |
| N10 | Mobile / API externa | Porta | contrato | **Sim (Rede/PIX/push)** |
| N11 | Monitora (GPS) | Porta (isolado) | — | **Sim (SGCasa)** |
| N12 | Relatórios / Jobs / limpeza | Reescreve | — | — |
| — | **CUTOVER** | — | invariantes | — |

---

## 9. Próximos passos imediatos

1. **N0** — criar o projeto Laravel 12 limpo + TenantContext + Auth/RBAC + esqueleto do ETL.
2. Definir o **modelo RBAC único** (decisão a tomar no início do N0).
3. Conseguir um **dump real (anonimizado) de produção** até o N1, para os testes de invariante do ETL desde cedo.
4. Decidir nome/local do projeto novo (pasta no workspace × repositório separado).

> Quando autorizar, começo pelo **N0**.
